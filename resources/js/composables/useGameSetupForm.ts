import { computed, ref } from 'vue'
import type { Game } from '~/lib/quiz'
import { HttpError } from '~/utils/http'

export type DraftTeam = {
  id: string
  name: string
}

type CreateGamePayload = {
  title: string
  externalId?: string
  teamNames: string[]
  rounds: Array<{ title: string }>
}

type UseGameSetupFormOptions = {
  createGame: (payload: CreateGamePayload) => Promise<Game>
  navigateToGame: (game: Game) => Promise<unknown> | unknown
}

export const MIN_ROUNDS = 1
export const MAX_ROUNDS = 30
const DEFAULT_ROUNDS = 7
const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

export function useGameSetupForm(options: UseGameSetupFormOptions) {
  const title = ref('')
  const teams = ref<DraftTeam[]>([createDraftTeam(), createDraftTeam()])
  const roundsCount = ref(DEFAULT_ROUNDS)
  const error = ref('')
  const isSubmitting = ref(false)
  const showErrors = ref(false)
  const externalId = ref('')

  const filledTeamNames = computed(() => teams.value.map((team) => team.name.trim()).filter(Boolean))

  const titleError = computed(() => title.value.trim() ? '' : 'Введите название игры.')

  const duplicateTeamIds = computed(() => {
    const seen = new Set<string>()
    const duplicates = new Set<string>()

    for (const team of teams.value) {
      const key = team.name.trim().toLocaleLowerCase('ru')

      if (!key) {
        continue
      }

      if (seen.has(key)) {
        duplicates.add(team.id)
      }

      seen.add(key)
    }

    return duplicates
  })

  const teamsError = computed(() => {
    if (filledTeamNames.value.length < 1) {
      return 'Добавьте хотя бы одну команду.'
    }

    if (duplicateTeamIds.value.size > 0) {
      return 'Названия команд не должны повторяться.'
    }

    return ''
  })

  const externalIdError = computed(() => {
    const value = externalId.value.trim()
    return value && !UUID_PATTERN.test(value) ? 'ID игры в Квиз, плиз! должен быть UUID.' : ''
  })

  const isValid = computed(() => !titleError.value && !teamsError.value && !externalIdError.value)

  function addTeam(afterId?: string, name = ''): DraftTeam {
    const team = createDraftTeam(name)
    const index = afterId ? teams.value.findIndex((item) => item.id === afterId) : -1

    if (index === -1) {
      teams.value.push(team)
    } else {
      teams.value.splice(index + 1, 0, team)
    }

    return team
  }

  function removeTeam(id: string): void {
    if (teams.value.length > 1) {
      teams.value = teams.value.filter((team) => team.id !== id)
    }
  }

  // Splits pasted multi-line text into separate teams, starting at the given row.
  function pasteTeams(id: string, text: string): DraftTeam | null {
    const names = text.split(/\r?\n/).map((name) => name.trim()).filter(Boolean)
    const team = teams.value.find((item) => item.id === id)

    if (!team || names.length === 0) {
      return null
    }

    team.name = names[0]
    let last = team

    for (const name of names.slice(1)) {
      last = addTeam(last.id, name)
    }

    return last
  }

  // Fills the form from an external game. Teams are replaced, so a repeated import never duplicates them;
  // rounds stay as the user set them.
  function applyImport(input: { externalId: string, title: string, teamNames: string[] }): void {
    externalId.value = input.externalId
    title.value = input.title

    if (input.teamNames.length > 0) {
      teams.value = input.teamNames.map((name) => createDraftTeam(name))
    }
  }

  function setRoundsCount(value: number): void {
    roundsCount.value = Math.min(MAX_ROUNDS, Math.max(MIN_ROUNDS, value))
  }

  async function createGame(): Promise<void> {
    if (isSubmitting.value) {
      return
    }

    showErrors.value = true
    error.value = ''

    if (!isValid.value) {
      return
    }

    try {
      isSubmitting.value = true
      const game = await options.createGame({
        title: title.value.trim(),
        ...(externalId.value.trim() ? { externalId: externalId.value.trim() } : {}),
        teamNames: filledTeamNames.value,
        rounds: Array.from({ length: roundsCount.value }, (_, index) => ({ title: `Раунд ${index + 1}` }))
      })
      await options.navigateToGame(game)
    } catch (requestError) {
      // Validation messages (e.g. an already used external id) are actionable, so show them as is.
      error.value = requestError instanceof HttpError && requestError.status === 422
        ? requestError.message
        : 'Не удалось создать игру. Проверьте подключение и авторизацию.'
    } finally {
      isSubmitting.value = false
    }
  }

  return {
    title,
    teams,
    roundsCount,
    externalId,
    externalIdError,
    filledTeamNames,
    error,
    isSubmitting,
    showErrors,
    titleError,
    teamsError,
    duplicateTeamIds,
    addTeam,
    removeTeam,
    pasteTeams,
    applyImport,
    setRoundsCount,
    createGame
  }
}

function createDraftTeam(name = ''): DraftTeam {
  return { id: `${Date.now()}-${Math.random().toString(36).slice(2)}`, name }
}
