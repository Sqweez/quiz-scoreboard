import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import type { CreateGameInput, Game, Round, Team } from '../types/quiz'
import { getTeamTotal, normalizeScoreInput, sortTeamsByScore } from '../utils/scoring'

type RoundUpdates = Partial<Pick<Round, 'title' | 'maxScore' | 'questionsCount'>>

export const useQuizStore = defineStore('quiz', () => {
  const currentGame = ref<Game | null>(null)
  const isLoading = ref(false)
  const error = ref('')

  const sortedTeams = computed<Team[]>(() => {
    if (!currentGame.value) {
      return []
    }

    return sortTeamsByScore(currentGame.value.teams, currentGame.value.rounds)
  })

  async function createGame(input: CreateGameInput): Promise<Game> {
    return requestGame(() => $fetch<Game>('/api/games', {
      method: 'POST',
      body: input
    }), true, true)
  }

  async function loadGame(): Promise<void> {
    await requestOptionalGame(() => $fetch<Game | null>('/api/games/current'))
  }

  async function updateGameTitle(title: string): Promise<void> {
    if (!currentGame.value || !title.trim()) {
      return
    }

    await requestGame(() => $fetch<Game>(`/api/games/${currentGame.value?.id}`, {
      method: 'PATCH',
      body: { title }
    }))
  }

  async function addTeam(name = `Команда ${(currentGame.value?.teams.length ?? 0) + 1}`): Promise<void> {
    if (!currentGame.value || !name.trim()) {
      return
    }

    await requestGame(() => $fetch<Game>(`/api/games/${currentGame.value?.id}/teams`, {
      method: 'POST',
      body: { name }
    }))
  }

  async function deleteTeam(teamId: string): Promise<void> {
    if (!currentGame.value || currentGame.value.teams.length <= 1) {
      return
    }

    await requestGame(() => $fetch<Game>(`/api/games/${currentGame.value?.id}/teams/${teamId}`, {
      method: 'DELETE'
    }))
  }

  async function renameTeam(teamId: string, name: string): Promise<void> {
    if (!currentGame.value || !name.trim()) {
      return
    }

    await requestGame(() => $fetch<Game>(`/api/games/${currentGame.value?.id}/teams/${teamId}`, {
      method: 'PATCH',
      body: { name }
    }))
  }

  async function addRound(title = `Раунд ${(currentGame.value?.rounds.length ?? 0) + 1}`): Promise<void> {
    if (!currentGame.value || !title.trim()) {
      return
    }

    await requestGame(() => $fetch<Game>(`/api/games/${currentGame.value?.id}/rounds`, {
      method: 'POST',
      body: { title }
    }))
  }

  async function deleteRound(roundId: string): Promise<void> {
    if (!currentGame.value || currentGame.value.rounds.length <= 1) {
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
    if (!currentGame.value) {
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
    if (!currentGame.value) {
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
      await $fetch(`/api/games/${gameId}`, {
        method: 'DELETE'
      })
    } catch (requestError) {
      error.value = getRequestMessage(requestError)
      await loadGame()
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
      currentGame.value = await fetcher()
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

  return {
    currentGame,
    isLoading,
    error,
    sortedTeams,
    createGame,
    loadGame,
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
    clearGame
  }
})

function getRequestMessage(error: unknown): string {
  if (typeof error === 'object' && error !== null && 'statusMessage' in error) {
    return String(error.statusMessage)
  }

  return 'Не удалось сохранить изменения.'
}
