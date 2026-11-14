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
    id: 'game-1', title: 'Test', externalId: null, extra: null, ratingCategoryId: null, ratingIncludeScores: true, ratingPackage: null, ratingCreatedAt: null, ratingUploadedAt: null, status: 'draft', statusLabel: 'Открытая', statusActionLabel: 'Открыть',
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

describe('mobile and tablet adaptive layout', () => {
  it('switches between round, team, and table modes on mobile', async () => {
    const modeButtons = Array.from(container.querySelectorAll('button')).filter(b =>
      ['По раундам', 'По командам', 'Таблица'].includes(b.textContent?.trim() ?? '')
    )
    expect(modeButtons).toHaveLength(3)

    // Default is round mode
    expect(container.textContent).toContain('Раунд 1')

    // Switch to team mode
    const teamBtn = modeButtons.find(b => b.textContent?.trim() === 'По командам')!
    teamBtn.click()
    await nextTick()
    expect(container.textContent).toContain('Alpha')
    expect(container.textContent).toContain('Р1')

    // Switch to table mode
    const tableBtn = modeButtons.find(b => b.textContent?.trim() === 'Таблица')!
    tableBtn.click()
    await nextTick()
    const stickyHeader = container.querySelector('th.sticky')
    expect(stickyHeader).not.toBeNull()
  })

  it('navigates between rounds in mobile round mode and enters scores', async () => {
    // Setup game with multiple rounds
    const store = useQuizStore()
    if (store.currentGame) {
      store.currentGame.rounds = [
        { id: 'round-1', title: 'Start', maxScore: 6 },
        { id: 'round-2', title: 'Music', maxScore: 6 }
      ]
      store.currentGame.teams = [
        { id: 'team-1', name: 'Alpha', scores: { 'round-1': 4, 'round-2': 5 } }
      ]
    }
    await nextTick()

    // Mode is 'round'
    expect(container.textContent).toContain('Раунд 1')

    // Switch to round 2 via next button
    const nextBtn = Array.from(container.querySelectorAll('button')).find(b =>
      b.textContent?.includes('Р2 →')
    )
    expect(nextBtn).toBeDefined()
    nextBtn?.click()
    await nextTick()
    expect(container.textContent).toContain('Раунд 2')

    // Input in round 2 should show team's score
    const roundInputs = Array.from(container.querySelectorAll('input')).filter(
      i => i.getAttribute('aria-label')?.includes('Music')
    )
    expect(roundInputs.length).toBeGreaterThan(0)
    const mobileInput = roundInputs[0]
    expect(mobileInput.value).toBe('5')

    mobileInput.focus()
    await nextTick()
    await enterScore(mobileInput, '6')
    await vi.advanceTimersByTimeAsync(350)
    expect(store.currentGame?.teams[0]?.scores['round-2']).toBe(6)
  })
})

describe('ranking changes', () => {
  it.each(['desktop', 'По раундам', 'По командам', 'Таблица'])(
    'preserves the edited input and reorders teams after blur in %s',
    async (mode) => {
      const store = useQuizStore()
      store.currentGame!.teams.push({ id: 'team-2', name: 'Beta', scores: { 'round-1': 5 } })
      await nextTick()
      if (mode !== 'desktop') {
        const button = Array.from(container.querySelectorAll('button')).find(
          button => button.textContent?.trim() === mode
        )!
        button.click()
        await nextTick()
      }
      const inputs = Array.from(container.querySelectorAll<HTMLInputElement>('input')).filter(
        input => input.getAttribute('aria-label')?.includes('Alpha')
      )
      const input = mode === 'desktop' ? inputs[0]! : inputs[1]!
      const group = input.closest('tbody') ?? (mode === 'По командам'
        ? input.closest('.rounded-xl')!.parentElement!
        : input.parentElement!.parentElement!.parentElement!)
      input.focus()
      await nextTick()
      await enterScore(input, '8')
      expect(group.textContent!.indexOf('Beta')).toBeLessThan(group.textContent!.indexOf('Alpha'))
      expect(document.activeElement).toBe(input)
      input.blur()
      await nextTick()
      await nextTick()
      expect(group.textContent!.indexOf('Alpha')).toBeLessThan(group.textContent!.indexOf('Beta'))
      expect(group.contains(input)).toBe(true)
      expect(input.value).toBe('8')
    }
  )
})

async function enterScore(input: HTMLInputElement, value: string): Promise<void> {
  input.value = value
  input.dispatchEvent(new Event('input', { bubbles: true }))
  await nextTick()
}
