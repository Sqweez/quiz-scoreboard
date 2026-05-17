import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useQuizStore } from '../../app/stores/quiz'
import type { Game } from '#shared/quiz'

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
})

function createStoreWithDelayedScoreSave(): {
  fetchMock: ReturnType<typeof vi.fn>
  store: ReturnType<typeof useQuizStore>
} {
  const store = useQuizStore()

  store.currentGame = createGame()
  const fetchMock = vi.fn((_url: string, _options: { body: unknown }) =>
    new Promise<Game>((resolve) => {
      setTimeout(() => {
        resolve({
          ...createGame(),
          updatedAt: new Date('2026-05-17T00:00:00.000Z').toISOString()
        })
      }, API_DELAY_MS)
    })
  )

  vi.stubGlobal('$fetch', fetchMock)

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
