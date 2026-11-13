// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, nextTick, type App } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import ScoreTable from '../../resources/js/components/ScoreTable.vue'
import { useQuizStore } from '../../resources/js/stores/quiz'
import { http } from '../../resources/js/utils/http'
import type { Game } from '~/lib/quiz'

vi.mock('../../resources/js/utils/http', () => ({
  http: vi.fn(), getRequestMessage: (error: Error) => error.message
}))

let app: App
let container: HTMLDivElement

beforeEach(() => {
  vi.useFakeTimers()
  const pinia = createPinia()
  setActivePinia(pinia)
  const game: Game = {
    id: 'game-1', title: 'Test', externalId: null, extra: null, ratingCategoryId: null, ratingIncludeScores: true, ratingPackage: null, ratingCreatedAt: null, status: 'draft', statusLabel: 'Открытая', statusActionLabel: 'Открыть',
    rounds: [{ id: 'round-1', title: 'Round', maxScore: 10 }],
    teams: [{ id: 'team-1', name: 'Alpha', scores: { 'round-1': 0 } }],
    createdAt: '2026-11-14T00:00:00.000Z', updatedAt: '2026-11-14T00:00:00.000Z'
  }
  useQuizStore().currentGame = game
  vi.mocked(http).mockResolvedValue(game)
  container = document.createElement('div')
  document.body.append(container)
  app = createApp(ScoreTable).use(pinia)
  app.mount(container)
})

afterEach(async () => {
  app.unmount()
  container.remove()
  await vi.runAllTimersAsync()
  vi.clearAllMocks()
  vi.useRealTimers()
})

describe('decimal score entry', () => {
  it.each(['.', ','])('preserves an unfinished %s separator through autosave and normalizes on blur', async (separator) => {
    const input = container.querySelector('input') as HTMLInputElement
    expect(input.type).toBe('text')
    expect(input.inputMode).toBe('decimal')
    input.focus()
    await nextTick()
    await enterScore(input, `1${separator}`)
    await vi.advanceTimersByTimeAsync(350)
    expect(input.value).toBe(`1${separator}`)
    await enterScore(input, `1${separator}50`)
    await vi.advanceTimersByTimeAsync(350)
    expect(input.value).toBe(`1${separator}50`)
    expect(useQuizStore().currentGame?.teams[0]?.scores['round-1']).toBe(1.5)
    expect(http).toHaveBeenLastCalledWith('/api/games/game-1/scores/bulk', {
      method: 'PATCH', body: { updates: [{ teamId: 'team-1', roundId: 'round-1', score: 1.5 }] }
    })
    input.blur()
    await nextTick()
    expect(input.value).toBe('1.5')
  })

  it('keeps limits and zero normalization without interrupting the draft', async () => {
    const input = container.querySelector('input') as HTMLInputElement
    input.focus()
    await nextTick()
    await enterScore(input, '12,50')
    expect(input.value).toBe('12,50')
    expect(useQuizStore().currentGame?.teams[0]?.scores['round-1']).toBe(10)
    input.blur()
    await nextTick()
    expect(input.value).toBe('10')
    input.focus()
    await nextTick()
    await enterScore(input, '')
    expect(input.value).toBe('')
    input.blur()
    await nextTick()
    expect(input.value).toBe('0')
  })
})

async function enterScore(input: HTMLInputElement, value: string): Promise<void> {
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
  await nextTick()
}
