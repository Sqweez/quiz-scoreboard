import { ref } from 'vue'
import type { Game } from '#shared/quiz'

export type DraftTeam = {
  id: string
  name: string
}

export type DraftRound = {
  id: string
  title: string
  maxScore: string
  questionsCount: string
}

type GameSetupRound = {
  title: string
  maxScore: number | null
  questionsCount: number | null
}

type CreateGamePayload = {
  title: string
  teamNames: string[]
  rounds: GameSetupRound[]
}

type UseGameSetupFormOptions = {
  createGame: (payload: CreateGamePayload) => Promise<Game>
  navigateToGame: (game: Game) => Promise<unknown> | unknown
}

export function useGameSetupForm(options: UseGameSetupFormOptions) {
  const title = ref('Квиз')
  const teamNames = ref<DraftTeam[]>([
    { id: createDraftId(), name: 'Команда 1' },
    { id: createDraftId(), name: 'Команда 2' }
  ])
  const rounds = ref<DraftRound[]>([
    { id: createDraftId(), title: 'Раунд 1', maxScore: '', questionsCount: '' },
    { id: createDraftId(), title: 'Раунд 2', maxScore: '', questionsCount: '' }
  ])
  const error = ref('')

  function addTeam(): void {
    teamNames.value.push({ id: createDraftId(), name: `Команда ${teamNames.value.length + 1}` })
  }

  function removeTeam(id: string): void {
    if (teamNames.value.length > 1) {
      teamNames.value = teamNames.value.filter((team) => team.id !== id)
    }
  }

  function addRound(): void {
    rounds.value.push({
      id: createDraftId(),
      title: `Раунд ${rounds.value.length + 1}`,
      maxScore: '',
      questionsCount: ''
    })
  }

  function removeRound(id: string): void {
    if (rounds.value.length > 1) {
      rounds.value = rounds.value.filter((round) => round.id !== id)
    }
  }

  async function createGame(): Promise<void> {
    const teamValues = teamNames.value.map((team) => team.name.trim()).filter(Boolean)
    const roundValues = rounds.value
      .map((round) => ({
        title: round.title.trim(),
        maxScore: parseOptionalNumber(round.maxScore),
        questionsCount: parseOptionalNumber(round.questionsCount)
      }))
      .filter((round) => round.title)

    if (!title.value.trim()) {
      error.value = 'Введите название игры.'
      return
    }

    if (teamValues.length < 1 || teamValues.length !== teamNames.value.length) {
      error.value = 'Добавьте минимум одну команду и заполните все названия команд.'
      return
    }

    if (roundValues.length < 1 || roundValues.length !== rounds.value.length) {
      error.value = 'Добавьте минимум один раунд и заполните все названия раундов.'
      return
    }

    try {
      const game = await options.createGame({
        title: title.value,
        teamNames: teamValues,
        rounds: roundValues
      })
      await options.navigateToGame(game)
      error.value = ''
    } catch {
      error.value = 'Не удалось создать игру. Проверьте подключение и авторизацию.'
    }
  }

  return {
    title,
    teamNames,
    rounds,
    error,
    addTeam,
    removeTeam,
    addRound,
    removeRound,
    createGame
  }
}

function parseOptionalNumber(value: string): number | null {
  return value === '' ? null : Math.max(0, Number(value))
}

function createDraftId(): string {
  return `${Date.now()}-${Math.random().toString(36).slice(2)}`
}
