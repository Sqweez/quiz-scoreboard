import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import type { CreateGameInput, Game, Round, Team } from '#shared/quiz'
import { getTeamTotal, normalizeScoreInput, sortTeamsByScore } from '#shared/quiz'

type RoundUpdates = Partial<Pick<Round, 'title' | 'maxScore' | 'questionsCount'>>

export const useQuizStore = defineStore('quiz', () => {
  const currentGame = ref<Game | null>(null)
  const games = ref<Game[]>([])
  const isLoading = ref(false)
  const error = ref('')
  const isEditable = computed(() => currentGame.value?.status !== 'finished')

  const sortedTeams = computed<Team[]>(() => {
    if (!currentGame.value) {
      return []
    }

    return sortTeamsByScore(currentGame.value.teams, currentGame.value.rounds)
  })

  async function createGame(input: CreateGameInput): Promise<Game> {
    const game = await requestGame(() => $fetch<Game>('/api/games', {
      method: 'POST',
      body: input
    }), true, true)

    upsertGame(game)

    return game
  }

  async function loadGame(gameId?: string): Promise<void> {
    if (gameId) {
      await requestOptionalGame(() => $fetch<Game>(`/api/games/${gameId}`))
      return
    }

    await requestOptionalGame(() => $fetch<Game | null>('/api/games/current'))
  }

  async function loadGames(): Promise<void> {
    await requestGames(() => $fetch<Game[]>('/api/games'))
  }

  async function openGame(gameId: string): Promise<void> {
    await requestOptionalGame(() => $fetch<Game>(`/api/games/${gameId}`))
  }

  async function finishGame(): Promise<void> {
    const gameId = currentGame.value?.id

    if (!gameId) {
      return
    }

    await requestGame(() => $fetch<Game>(`/api/games/${gameId}/finish`, {
      method: 'POST'
    }))
  }

  async function updateGameTitle(title: string): Promise<void> {
    if (!currentGame.value || !title.trim() || !isEditable.value) {
      return
    }

    await requestGame(() => $fetch<Game>(`/api/games/${currentGame.value?.id}`, {
      method: 'PATCH',
      body: { title }
    }))
  }

  async function addTeam(name = `Команда ${(currentGame.value?.teams.length ?? 0) + 1}`): Promise<void> {
    if (!currentGame.value || !name.trim() || !isEditable.value) {
      return
    }

    await requestGame(() => $fetch<Game>(`/api/games/${currentGame.value?.id}/teams`, {
      method: 'POST',
      body: { name }
    }))
  }

  async function deleteTeam(teamId: string): Promise<void> {
    if (!currentGame.value || currentGame.value.teams.length <= 1 || !isEditable.value) {
      return
    }

    await requestGame(() => $fetch<Game>(`/api/games/${currentGame.value?.id}/teams/${teamId}`, {
      method: 'DELETE'
    }))
  }

  async function renameTeam(teamId: string, name: string): Promise<void> {
    if (!currentGame.value || !name.trim() || !isEditable.value) {
      return
    }

    await requestGame(() => $fetch<Game>(`/api/games/${currentGame.value?.id}/teams/${teamId}`, {
      method: 'PATCH',
      body: { name }
    }))
  }

  async function addRound(title = `Раунд ${(currentGame.value?.rounds.length ?? 0) + 1}`): Promise<void> {
    if (!currentGame.value || !title.trim() || !isEditable.value) {
      return
    }

    await requestGame(() => $fetch<Game>(`/api/games/${currentGame.value?.id}/rounds`, {
      method: 'POST',
      body: { title }
    }))
  }

  async function deleteRound(roundId: string): Promise<void> {
    if (!currentGame.value || currentGame.value.rounds.length <= 1 || !isEditable.value) {
      return
    }

    await requestGame(() => $fetch<Game>(`/api/games/${currentGame.value?.id}/rounds/${roundId}`, {
      method: 'DELETE'
    }))
  }

  function renameRound(roundId: string, title: string): Promise<void> {
    return updateRoundSettings(roundId, { title })
  }

  async function updateRoundSettings(roundId: string, updates: RoundUpdates): Promise<void> {
    if (!currentGame.value || !isEditable.value) {
      return
    }

    await requestGame(() => $fetch<Game>(`/api/games/${currentGame.value?.id}/rounds/${roundId}`, {
      method: 'PATCH',
      body: updates
    }))
  }

  async function updateTeamScore(
    teamId: string,
    roundId: string,
    score: number | string | null | undefined
  ): Promise<void> {
    if (!currentGame.value || !isEditable.value) {
      return
    }

    const team = findTeam(teamId)
    const round = findRound(roundId)

    if (!team || !round) {
      return
    }

    team.scores[roundId] = normalizeScoreInput(score, round)

    await requestGame(() => $fetch<Game>(`/api/games/${currentGame.value?.id}/scores`, {
      method: 'PATCH',
      body: {
        teamId,
        roundId,
        score
      }
    }), false)
  }

  function getTotalScore(team: Team): number {
    return currentGame.value ? getTeamTotal(team, currentGame.value.rounds) : 0
  }

  async function clearGame(): Promise<void> {
    if (!currentGame.value) {
      return
    }

    const gameId = currentGame.value.id
    currentGame.value = null
    error.value = ''

    try {
      const response = await fetch(`/api/games/${gameId}`, {
        method: 'DELETE'
      })

      if (!response.ok) {
        throw new Error('Failed to delete game.')
      }

      games.value = games.value.filter((game) => game.id !== gameId)
    } catch (requestError) {
      error.value = getRequestMessage(requestError)
      await loadGame(gameId)
    }
  }

  function findTeam(teamId: string): Team | undefined {
    return currentGame.value?.teams.find((team) => team.id === teamId)
  }

  function findRound(roundId: string): Round | undefined {
    return currentGame.value?.rounds.find((round) => round.id === roundId)
  }

  async function requestOptionalGame(fetcher: () => Promise<Game | null>): Promise<void> {
    isLoading.value = true
    error.value = ''

    try {
      const game = await fetcher()
      currentGame.value = game
      if (game) {
        upsertGame(game)
      }
    } catch (requestError) {
      currentGame.value = null
      error.value = getRequestMessage(requestError)
    } finally {
      isLoading.value = false
    }
  }

  async function requestGame(fetcher: () => Promise<Game>, showLoading = true, throwOnError = false): Promise<Game> {
    if (showLoading) {
      isLoading.value = true
    }
    error.value = ''

    try {
      const game = await fetcher()

      currentGame.value = game
      upsertGame(game)

      return game
    } catch (requestError) {
      error.value = getRequestMessage(requestError)
      if (!throwOnError) {
        return currentGame.value as Game
      }

      throw requestError
    } finally {
      if (showLoading) {
        isLoading.value = false
      }
    }
  }

  async function requestGames(fetcher: () => Promise<Game[]>): Promise<void> {
    isLoading.value = true
    error.value = ''

    try {
      games.value = await fetcher()
    } catch (requestError) {
      games.value = []
      error.value = getRequestMessage(requestError)
    } finally {
      isLoading.value = false
    }
  }

  function upsertGame(game: Game): void {
    const index = games.value.findIndex((item) => item.id === game.id)

    if (index >= 0) {
      games.value[index] = game
      return
    }

    games.value = [game, ...games.value].sort((left, right) =>
      new Date(right.updatedAt).getTime() - new Date(left.updatedAt).getTime()
    )
  }

  return {
    currentGame,
    games,
    isLoading,
    error,
    sortedTeams,
    createGame,
    loadGame,
    loadGames,
    openGame,
    finishGame,
    updateGameTitle,
    addTeam,
    deleteTeam,
    renameTeam,
    addRound,
    deleteRound,
    renameRound,
    updateRoundSettings,
    updateTeamScore,
    getTotalScore,
    clearGame,
    isEditable
  }
})

function getRequestMessage(error: unknown): string {
  if (typeof error === 'object' && error !== null && 'statusMessage' in error) {
    return String(error.statusMessage)
  }

  return 'Не удалось сохранить изменения.'
}
