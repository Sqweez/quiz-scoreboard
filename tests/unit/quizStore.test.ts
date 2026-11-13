import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useQuizStore } from '../../resources/js/stores/quiz'
import { http } from '../../resources/js/utils/http'

vi.mock('../../resources/js/utils/http', () => ({ http: vi.fn(), getRequestMessage: (error: Error) => error.message }))

import type { Game } from '~/lib/quiz'

const SCORE_SAVE_DEBOUNCE_MS = 350
const API_DELAY_MS = 2000

describe('useQuizStore score autosave', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.useRealTimers()
  })

  it('keeps score changes pending while a slow autosave request is in flight', async () => {
    const { fetchMock, store } = createStoreWithDelayedScoreSave()

    await store.updateTeamScore('team-1', 'round-1', '1.5')

    expect(store.currentGame?.teams[0]?.scores['round-1']).toBe(1.5)
    expect(store.scoreSyncTone).toBe('saving')
    expect(store.hasPendingScoreChanges).toBe(true)
    expect(fetchMock).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(SCORE_SAVE_DEBOUNCE_MS)

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(store.scoreSyncTone).toBe('saving')
    expect(store.hasPendingScoreChanges).toBe(true)

    await vi.advanceTimersByTimeAsync(API_DELAY_MS - 1)

    expect(store.scoreSyncTone).toBe('saving')
    expect(store.hasPendingScoreChanges).toBe(true)

    await vi.advanceTimersByTimeAsync(1)

    expect(store.hasPendingScoreChanges).toBe(false)
    expect(store.scoreSyncTone).toBe('saved')
  })

  it('waits for a slow in-flight save before flushing a newer queued score', async () => {
    const { fetchMock, store } = createStoreWithDelayedScoreSave()

    await store.updateTeamScore('team-1', 'round-1', 5)
    await vi.advanceTimersByTimeAsync(SCORE_SAVE_DEBOUNCE_MS)

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(getFirstSavedScore(fetchMock)).toBe(5)

    await store.updateTeamScore('team-1', 'round-1', 7)
    const flushPromise = store.flushPendingScoreChanges(true)

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(store.hasPendingScoreChanges).toBe(true)

    await vi.advanceTimersByTimeAsync(API_DELAY_MS)

    expect(fetchMock).toHaveBeenCalledTimes(2)
    expect(getLastSavedScore(fetchMock)).toBe(7)

    await vi.advanceTimersByTimeAsync(API_DELAY_MS)

    await expect(flushPromise).resolves.toBe(true)
    expect(store.hasPendingScoreChanges).toBe(false)
    expect(store.currentGame?.teams[0]?.scores['round-1']).toBe(7)
    expect(store.scoreSyncTone).toBe('saved')
  })

  it('saves the latest queued score before switching to another game', async () => {
    const { fetchMock, store } = createStoreWithDelayedScoreSave()
    const nextGame = { ...createGame(), id: 'game-2', title: 'Next game' }

    await store.updateTeamScore('team-1', 'round-1', 5)
    await vi.advanceTimersByTimeAsync(SCORE_SAVE_DEBOUNCE_MS)
    await store.updateTeamScore('team-1', 'round-1', 7)
    const loadPromise = store.loadGame(nextGame.id)

    expect(store.currentGame?.id).toBe('game-1')
    expect(fetchMock).toHaveBeenCalledTimes(1)
    await vi.advanceTimersByTimeAsync(API_DELAY_MS)
    expect(getLastSavedScore(fetchMock)).toBe(7)
    expect(fetchMock).toHaveBeenCalledTimes(2)
    expect(store.currentGame?.id).toBe('game-1')

    fetchMock.mockResolvedValueOnce(nextGame)
    await vi.advanceTimersByTimeAsync(API_DELAY_MS)
    await loadPromise

    expect(fetchMock.mock.calls[2]).toEqual(['/api/games/game-2'])
    expect(store.currentGame?.id).toBe('game-2')
    expect(store.hasPendingScoreChanges).toBe(false)
  })

  it('loads only the latest requested game when rapid switches wait for the same save', async () => {
    const { fetchMock, store } = createStoreWithDelayedScoreSave()
    const nextGame = { ...createGame(), id: 'game-3' }
    await store.updateTeamScore('team-1', 'round-1', 5)
    await vi.advanceTimersByTimeAsync(SCORE_SAVE_DEBOUNCE_MS)
    await store.updateTeamScore('team-1', 'round-1', 7)
    const firstLoad = store.loadGame('game-2')
    const latestLoad = store.loadGame('game-3')

    await vi.advanceTimersByTimeAsync(API_DELAY_MS)
    fetchMock.mockResolvedValueOnce(nextGame)
    await vi.advanceTimersByTimeAsync(API_DELAY_MS)
    await Promise.all([firstLoad, latestLoad])

    expect(fetchMock.mock.calls.map(call => call[0])).toEqual([
      '/api/games/game-1/scores/bulk',
      '/api/games/game-1/scores/bulk',
      '/api/games/game-3'
    ])
    expect(store.currentGame?.id).toBe('game-3')
  })

  it('keeps the current game and pending score when a save blocks switching games', async () => {
    const { fetchMock, store } = createStoreWithDelayedScoreSave()
    fetchMock.mockRejectedValue(new Error('Saving failed.'))
    await store.updateTeamScore('team-1', 'round-1', 7)

    await store.loadGame('game-2')

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/games/game-1/scores/bulk')
    expect(store.currentGame?.id).toBe('game-1')
    expect(store.currentGame?.teams[0]?.scores['round-1']).toBe(7)
    expect(store.hasPendingScoreChanges).toBe(true)
    expect(store.scoreSyncTone).toBe('error')
    expect(store.error).toBe('Saving failed.')
  })

})

describe('useQuizStore game loading', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('applies a game fetched by id when no game is currently selected', async () => {
    const store = useQuizStore()
    const game = createGame()
    const fetchMock = vi.fn().mockResolvedValue(game)

    vi.mocked(http).mockImplementation(fetchMock)

    await store.loadGame(game.id)

    expect(fetchMock).toHaveBeenCalledWith(`/api/games/${game.id}`)
    expect(store.currentGame?.id).toBe(game.id)
    expect(store.currentGame?.title).toBe(game.title)
  })

  it('does not show the previous game while another game is loading', async () => {
    const store = useQuizStore()
    store.currentGame = createGame()
    const nextGame = { ...createGame(), id: 'game-2', title: 'Next game' }
    let resolveNext: (game: Game) => void = () => {}
    vi.mocked(http).mockImplementation(() => new Promise<Game>((resolve) => { resolveNext = resolve }))

    const loadPromise = store.loadGame(nextGame.id)

    expect(store.currentGame).toBeNull()
    expect(store.isLoading).toBe(true)

    resolveNext(nextGame)
    await loadPromise

    expect(store.currentGame?.id).toBe('game-2')
  })
})

function createStoreWithDelayedScoreSave(): {
  fetchMock: ReturnType<typeof vi.fn>
  store: ReturnType<typeof useQuizStore>
} {
  const store = useQuizStore()

  store.currentGame = createGame()
  const fetchMock = vi.fn((_url: string, _options?: { body?: unknown }) =>
    new Promise<Game>((resolve) => {
      setTimeout(() => {
        resolve({
          ...createGame(),
          updatedAt: new Date('2026-05-17T00:00:00.000Z').toISOString()
        })
      }, API_DELAY_MS)
    })
  )

  vi.mocked(http).mockImplementation(fetchMock)

  return { fetchMock, store }
}

function createGame(): Game {
  return {
    id: 'game-1',
    title: 'Квиз',
    status: 'draft',
    statusLabel: 'Черновик',
    statusActionLabel: 'Завершить',
    rounds: [
      {
        id: 'round-1',
        title: 'Раунд 1'
      }
    ],
    teams: [
      {
        id: 'team-1',
        name: 'Команда 1',
        scores: {
          'round-1': 0
        }
      }
    ],
    createdAt: new Date('2026-05-17T00:00:00.000Z').toISOString(),
    updatedAt: new Date('2026-05-17T00:00:00.000Z').toISOString()
  }
}

function getFirstSavedScore(fetchMock: ReturnType<typeof vi.fn>): number {
  return getSavedScore(fetchMock, 0)
}

function getLastSavedScore(fetchMock: ReturnType<typeof vi.fn>): number {
  return getSavedScore(fetchMock, fetchMock.mock.calls.length - 1)
}

function getSavedScore(fetchMock: ReturnType<typeof vi.fn>, callIndex: number): number {
  const options = fetchMock.mock.calls[callIndex]?.[1] as { body?: { updates?: Array<{ score: number }> } } | undefined

  return options?.body?.updates?.[0]?.score ?? Number.NaN
}
