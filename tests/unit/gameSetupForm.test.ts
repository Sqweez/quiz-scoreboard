import { describe, expect, it, vi } from 'vitest'
import { useGameSetupForm } from '../../app/composables/useGameSetupForm'

describe('useGameSetupForm', () => {
  it('creates a game from valid draft values and navigates to the game page', async () => {
    const createGame = vi.fn().mockResolvedValue(undefined)
    const navigateToGame = vi.fn()
    const form = useGameSetupForm({ createGame, navigateToGame })

    form.title.value = '  Final quiz  '
    form.teamNames.value[0].name = ' Team A '
    form.teamNames.value[1].name = 'Team B'
    form.rounds.value[0].title = ' Round 1 '
    form.rounds.value[0].maxScore = '10'
    form.rounds.value[0].questionsCount = '5'
    form.rounds.value[1].title = 'Round 2'

    await form.createGame()

    expect(createGame).toHaveBeenCalledWith({
      title: '  Final quiz  ',
      teamNames: ['Team A', 'Team B'],
      rounds: [
        { title: 'Round 1', maxScore: 10, questionsCount: 5 },
        { title: 'Round 2', maxScore: null, questionsCount: null }
      ]
    })
    expect(navigateToGame).toHaveBeenCalledOnce()
    expect(form.error.value).toBe('')
  })

  it('keeps one team and one round when removing draft rows', () => {
    const form = useGameSetupForm({ createGame: vi.fn(), navigateToGame: vi.fn() })

    form.removeTeam(form.teamNames.value[0].id)
    form.removeTeam(form.teamNames.value[0].id)
    form.removeRound(form.rounds.value[0].id)
    form.removeRound(form.rounds.value[0].id)

    expect(form.teamNames.value).toHaveLength(1)
    expect(form.rounds.value).toHaveLength(1)
  })

  it('reports validation errors before creating a game', () => {
    const createGame = vi.fn()
    const navigateToGame = vi.fn()
    const form = useGameSetupForm({ createGame, navigateToGame })

    form.title.value = ' '
    form.createGame()

    expect(form.error.value).toBe('Введите название игры.')
    expect(createGame).not.toHaveBeenCalled()
    expect(navigateToGame).not.toHaveBeenCalled()
  })
})
