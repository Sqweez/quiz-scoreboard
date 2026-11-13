import { describe, expect, it, vi } from 'vitest'
import { useGameSetupForm } from '../../resources/js/composables/useGameSetupForm'

describe('useGameSetupForm', () => {
  it('starts with an empty title and empty team rows', () => {
    const form = useGameSetupForm({ createGame: vi.fn(), navigateToGame: vi.fn() })

    expect(form.title.value).toBe('')
    expect(form.teams.value.map((team) => team.name)).toEqual(['', ''])
  })

  it('creates a game with filled teams and generated round titles', async () => {
    const createGame = vi.fn().mockResolvedValue({ id: 'game-1' })
    const navigateToGame = vi.fn()
    const form = useGameSetupForm({ createGame, navigateToGame })

    form.title.value = '  Final quiz  '
    form.teams.value[0].name = ' Team A '
    form.setRoundsCount(3)

    await form.createGame()

    expect(createGame).toHaveBeenCalledWith({
      title: 'Final quiz',
      teamNames: ['Team A'],
      rounds: [{ title: 'Раунд 1' }, { title: 'Раунд 2' }, { title: 'Раунд 3' }]
    })
    expect(navigateToGame).toHaveBeenCalledWith({ id: 'game-1' })
  })

  it('blocks submit without a title or teams', async () => {
    const createGame = vi.fn()
    const form = useGameSetupForm({ createGame, navigateToGame: vi.fn() })

    await form.createGame()

    expect(form.titleError.value).toBe('Введите название игры.')
    expect(form.teamsError.value).toBe('Добавьте хотя бы одну команду.')
    expect(createGame).not.toHaveBeenCalled()
  })

  it('blocks submit with duplicate team names ignoring case', async () => {
    const createGame = vi.fn()
    const form = useGameSetupForm({ createGame, navigateToGame: vi.fn() })

    form.title.value = 'Quiz'
    form.teams.value[0].name = 'Сова'
    form.teams.value[1].name = ' сова '
    await form.createGame()

    expect(form.duplicateTeamIds.value.has(form.teams.value[1].id)).toBe(true)
    expect(createGame).not.toHaveBeenCalled()
  })

  it('splits a pasted list into separate teams', () => {
    const form = useGameSetupForm({ createGame: vi.fn(), navigateToGame: vi.fn() })

    form.pasteTeams(form.teams.value[0].id, 'A\n\nB\r\nC\n')

    expect(form.teams.value.map((team) => team.name)).toEqual(['A', 'B', 'C', ''])
  })

  it('clamps the rounds count and keeps at least one team row', () => {
    const form = useGameSetupForm({ createGame: vi.fn(), navigateToGame: vi.fn() })

    form.setRoundsCount(0)
    expect(form.roundsCount.value).toBe(1)
    form.setRoundsCount(100)
    expect(form.roundsCount.value).toBe(30)

    form.removeTeam(form.teams.value[0].id)
    form.removeTeam(form.teams.value[0].id)
    expect(form.teams.value).toHaveLength(1)
  })

  it('replaces teams on a repeated import, keeps rounds and sends the external id', async () => {
    const createGame = vi.fn().mockResolvedValue({ id: 'game-1' })
    const form = useGameSetupForm({ createGame, navigateToGame: vi.fn() })
    const imported = { externalId: '01a19b42-0cc8-709f-8197-62881b0dbcdc', title: 'Квиз', teamNames: ['Совы', 'Ёжики'] }

    form.setRoundsCount(4)
    form.applyImport(imported)
    form.applyImport(imported)
    await form.createGame()

    expect(form.teams.value.map((team) => team.name)).toEqual(['Совы', 'Ёжики'])
    expect(createGame).toHaveBeenCalledWith(expect.objectContaining({
      title: 'Квиз',
      externalId: imported.externalId,
      teamNames: ['Совы', 'Ёжики']
    }))
    expect(createGame.mock.calls[0][0].rounds).toHaveLength(4)
  })

  it('keeps existing teams when the import has no confirmed teams', () => {
    const form = useGameSetupForm({ createGame: vi.fn(), navigateToGame: vi.fn() })

    form.teams.value[0].name = 'Manual'
    form.applyImport({ externalId: 'id', title: 'Квиз', teamNames: [] })

    expect(form.title.value).toBe('Квиз')
    expect(form.teams.value.map((team) => team.name)).toEqual(['Manual', ''])
  })
})
