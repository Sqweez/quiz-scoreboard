import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { http, getRequestMessage } from '../utils/http'
import type { CreateGameInput, Game, Round, ScoreUpdateInput, Team } from '~/lib/quiz'
import { getTeamTotal, normalizeScoreInput, sortTeamsByScore } from '~/lib/quiz'

type RoundUpdates = Partial<Pick<Round, 'title' | 'maxScore' | 'questionsCount'>>
type PendingScoreChange = {
  teamId: string
  roundId: string
  score: number
  revision: number
}

const SCORE_SAVE_DEBOUNCE_MS = 350
const SCORE_SAVE_SUCCESS_MS = 1200

export const useQuizStore = defineStore('quiz', () => {
  const currentGame = ref<Game | null>(null)
  const isLoading = ref(false)
  const error = ref('')
  const isEditable = computed(() => currentGame.value?.status !== 'finished')
  const scoreSaveState = ref<'idle' | 'saving' | 'saved' | 'error'>('idle')
  const scoreSaveMessage = ref('')
  const scoreSaveInFlight = ref(false)
  const scoreSaveQueued = ref(false)
  const pendingScoreChanges = ref<Record<string, PendingScoreChange>>({})
  const scoreRevisions = ref<Record<string, number>>({})
  const scoreFlushTimer = ref<ReturnType<typeof setTimeout> | null>(null)
  const scoreStatusTimer = ref<ReturnType<typeof setTimeout> | null>(null)
  let activeGameLoadId: string | null = null
  let activeScoreFlush: Promise<boolean> | null = null

  const sortedTeams = computed<Team[]>(() => {
    if (!currentGame.value) {
      return []
    }

    return sortTeamsByScore(currentGame.value.teams, currentGame.value.rounds)
  })

  const hasPendingScoreChanges = computed(() => Object.keys(pendingScoreChanges.value).length > 0)
  const isSavingScores = computed(() => scoreSaveInFlight.value || hasPendingScoreChanges.value)
  const scoreSyncText = computed(() => {
    if (scoreSaveState.value === 'error') {
      return scoreSaveMessage.value || 'Не удалось сохранить баллы.'
    }

    if (scoreSaveState.value === 'saved') {
      return 'Баллы сохранены'
    }

    if (scoreSaveState.value === 'saving' || hasPendingScoreChanges.value) {
      return 'Сохраняем баллы...'
    }

    return ''
  })
  const scoreSyncTone = computed(() => {
    if (scoreSaveState.value === 'error') {
      return 'error'
    }

    if (scoreSaveState.value === 'saved') {
      return 'saved'
    }

    if (scoreSaveState.value === 'saving' || hasPendingScoreChanges.value) {
      return 'saving'
    }

    return 'idle'
  })

  async function createGame(input: CreateGameInput): Promise<Game> {
    const game = await requestGame(() => http<Game>('/api/games', {
      method: 'POST',
      body: input
    }), true, true)

    return game
  }

  async function loadGame(gameId?: string): Promise<void> {
    activeGameLoadId = gameId || null

    if (!gameId || currentGame.value?.id !== gameId) {
      if (isSavingScores.value) {
        if (!await flushPendingScoreChanges(true)) {
          error.value = scoreSaveMessage.value
          return
        }

        if (activeGameLoadId !== (gameId || null)) {
          return
        }
      }

      resetScoreAutosaveState()
      // Drop the previous game so its results are not shown while the next one loads.
      currentGame.value = null
    }

    if (gameId) {
      await requestOptionalGame(() => http<Game>(`/api/games/${gameId}`), gameId)
      return
    }

    await requestOptionalGame(() => http<Game | null>('/api/games/current'))
  }

  async function openGame(gameId: string): Promise<void> {
    await loadGame(gameId)
  }

  async function finishGame(): Promise<void> {
    await flushPendingScoreChanges(true)

    if (scoreSaveState.value === 'error' || scoreSaveInFlight.value || hasPendingScoreChanges.value) {
      return
    }

    const gameId = currentGame.value?.id

    if (!gameId) {
      return
    }

    await requestGame(() => http<Game>(`/api/games/${gameId}/finish`, {
      method: 'POST'
    }), true, false, gameId)
  }

  // Title and external id stay editable after the game is finished.
  async function updateGameTitle(title: string): Promise<void> {
    if (!currentGame.value || !title.trim()) {
      return
    }

    const gameId = currentGame.value.id

    await requestGame(() => http<Game>(`/api/games/${gameId}`, {
      method: 'PATCH',
      body: { title }
    }), true, false, gameId)
  }

  // Throws so callers can react to Quiz Please errors such as a required login.
  async function updateGameDetails(details: Partial<Pick<Game, 'title' | 'externalId' | 'ratingCategoryId' | 'ratingIncludeScores'>>): Promise<void> {
    if (!currentGame.value) {
      return
    }

    const gameId = currentGame.value.id

    await requestGame(() => http<Game>(`/api/games/${gameId}`, {
      method: 'PATCH',
      body: details
    }), true, true, gameId)
  }

  async function addTeam(name = `Команда ${(currentGame.value?.teams.length ?? 0) + 1}`): Promise<void> {
    if (!currentGame.value || !name.trim() || !isEditable.value) {
      return
    }

    const gameId = currentGame.value.id

    await requestGame(() => http<Game>(`/api/games/${gameId}/teams`, {
      method: 'POST',
      body: { name }
    }), true, false, gameId)
  }

  async function deleteTeam(teamId: string): Promise<void> {
    if (!currentGame.value || currentGame.value.teams.length <= 1 || !isEditable.value) {
      return
    }

    const gameId = currentGame.value.id

    await requestGame(() => http<Game>(`/api/games/${gameId}/teams/${teamId}`, {
      method: 'DELETE'
    }), true, false, gameId)
  }

  async function renameTeam(teamId: string, name: string): Promise<void> {
    if (!currentGame.value || !name.trim() || !isEditable.value) {
      return
    }

    const gameId = currentGame.value.id

    await requestGame(() => http<Game>(`/api/games/${gameId}/teams/${teamId}`, {
      method: 'PATCH',
      body: { name }
    }), true, false, gameId)
  }

  async function addRound(title = `Раунд ${(currentGame.value?.rounds.length ?? 0) + 1}`): Promise<void> {
    if (!currentGame.value || !title.trim() || !isEditable.value) {
      return
    }

    const gameId = currentGame.value.id

    await requestGame(() => http<Game>(`/api/games/${gameId}/rounds`, {
      method: 'POST',
      body: { title }
    }), true, false, gameId)
  }

  async function deleteRound(roundId: string): Promise<void> {
    if (!currentGame.value || currentGame.value.rounds.length <= 1 || !isEditable.value) {
      return
    }

    const gameId = currentGame.value.id

    await requestGame(() => http<Game>(`/api/games/${gameId}/rounds/${roundId}`, {
      method: 'DELETE'
    }), true, false, gameId)
  }

  function renameRound(roundId: string, title: string): Promise<void> {
    return updateRoundSettings(roundId, { title })
  }

  async function updateRoundSettings(roundId: string, updates: RoundUpdates): Promise<void> {
    if (!currentGame.value || !isEditable.value) {
      return
    }

    const gameId = currentGame.value.id

    await requestGame(() => http<Game>(`/api/games/${gameId}/rounds/${roundId}`, {
      method: 'PATCH',
      body: updates
    }), true, false, gameId)
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

    const normalizedScore = normalizeScoreInput(score, round)
    const key = getScoreCellKey(teamId, roundId)
    const revision = (scoreRevisions.value[key] ?? 0) + 1

    team.scores[roundId] = normalizedScore
    scoreRevisions.value = {
      ...scoreRevisions.value,
      [key]: revision
    }
    pendingScoreChanges.value = {
      ...pendingScoreChanges.value,
      [key]: {
        teamId,
        roundId,
        score: normalizedScore,
        revision
      }
    }
    setScoreSaveState('saving')
    scheduleScoreFlush()
  }

  function getTotalScore(team: Team): number {
    return currentGame.value ? getTeamTotal(team, currentGame.value.rounds) : 0
  }

  async function clearGame(): Promise<void> {
    if (!currentGame.value) {
      return
    }

    const gameId = currentGame.value.id
    resetScoreAutosaveState()
    currentGame.value = null
    error.value = ''

    try {
      await http(`/api/games/${gameId}`, { method: 'DELETE' })
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

  async function requestOptionalGame(fetcher: () => Promise<Game | null>, expectedGameId?: string): Promise<void> {
    const requestGameId = expectedGameId || null

    isLoading.value = true
    error.value = ''

    try {
      const game = await fetcher()

      if (activeGameLoadId !== requestGameId || (expectedGameId && game?.id !== expectedGameId)) {
        return
      }

      applyServerGame(game)
    } catch (requestError) {
      if (activeGameLoadId !== requestGameId) {
        return
      }

      currentGame.value = null
      resetScoreAutosaveState()
      error.value = getRequestMessage(requestError)
    } finally {
      if (activeGameLoadId === requestGameId) {
        isLoading.value = false
      }
    }
  }

  async function requestGame(
    fetcher: () => Promise<Game>,
    showLoading = true,
    throwOnError = false,
    expectedGameId?: string
  ): Promise<Game> {
    if (showLoading) {
      isLoading.value = true
    }
    error.value = ''

    try {
      const game = await fetcher()

      if (expectedGameId && currentGame.value?.id !== expectedGameId) {
        return game
      }

      applyServerGame(game)

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

  async function flushPendingScoreChanges(immediate = false): Promise<boolean> {
    if (!currentGame.value || !isEditable.value) {
      return true
    }

    if (immediate) {
      clearScoreFlushTimer()
    }

    const updates = Object.values(pendingScoreChanges.value)

    if (updates.length === 0) {
      if (scoreSaveState.value === 'saved') {
        scheduleScoreStatusReset()
      }

      return true
    }

    if (scoreSaveInFlight.value) {
      scoreSaveQueued.value = true

      if (immediate && activeScoreFlush) {
        await activeScoreFlush

        return flushPendingScoreChanges(true)
      }

      return true
    }

    scoreSaveInFlight.value = true
    setScoreSaveState('saving')

    const gameId = currentGame.value.id
    const snapshot = new Map(updates.map((update) => [getScoreCellKey(update.teamId, update.roundId), update]))

    activeScoreFlush = persistScoreUpdates(gameId, snapshot, updates)

    return activeScoreFlush
  }

  async function persistScoreUpdates(
    gameId: string,
    snapshot: Map<string, PendingScoreChange>,
    updates: PendingScoreChange[]
  ): Promise<boolean> {
    try {
      const response = await http<Game>(`/api/games/${gameId}/scores/bulk`, {
        method: 'PATCH',
        body: {
          updates: updates.map((update) => ({
            teamId: update.teamId,
            roundId: update.roundId,
            score: update.score
          }) satisfies ScoreUpdateInput)
        }
      })

      if (currentGame.value?.id === gameId) {
        currentGame.value.updatedAt = response.updatedAt
      }

      const nextPending = { ...pendingScoreChanges.value }

      for (const [key, flushedUpdate] of snapshot) {
        if (nextPending[key]?.revision === flushedUpdate.revision) {
          delete nextPending[key]
        }
      }

      pendingScoreChanges.value = nextPending

      if (Object.keys(nextPending).length === 0) {
        setScoreSaveState('saved')
      } else {
        setScoreSaveState('saving')
        scoreSaveQueued.value = true
      }

      return true
    } catch (requestError) {
      setScoreSaveState('error', getRequestMessage(requestError))
      return false
    } finally {
      scoreSaveInFlight.value = false

      if (activeScoreFlush) {
        activeScoreFlush = null
      }

      if (scoreSaveQueued.value) {
        scoreSaveQueued.value = false
        scheduleScoreFlush(0)
      }
    }
  }

  function scheduleScoreFlush(delay = SCORE_SAVE_DEBOUNCE_MS): void {
    clearScoreFlushTimer()

    scoreFlushTimer.value = setTimeout(() => {
      scoreFlushTimer.value = null
      void flushPendingScoreChanges()
    }, delay)
  }

  function clearScoreFlushTimer(): void {
    if (scoreFlushTimer.value) {
      clearTimeout(scoreFlushTimer.value)
      scoreFlushTimer.value = null
    }
  }

  function scheduleScoreStatusReset(): void {
    clearScoreStatusTimer()

    scoreStatusTimer.value = setTimeout(() => {
      if (scoreSaveState.value === 'saved') {
        scoreSaveState.value = 'idle'
        scoreSaveMessage.value = ''
      }
    }, SCORE_SAVE_SUCCESS_MS)
  }

  function clearScoreStatusTimer(): void {
    if (scoreStatusTimer.value) {
      clearTimeout(scoreStatusTimer.value)
      scoreStatusTimer.value = null
    }
  }

  function setScoreSaveState(state: 'idle' | 'saving' | 'saved' | 'error', message = ''): void {
    clearScoreStatusTimer()
    scoreSaveState.value = state
    scoreSaveMessage.value = message

    if (state === 'saved') {
      scheduleScoreStatusReset()
    }
  }

  function resetScoreAutosaveState(): void {
    clearScoreFlushTimer()
    clearScoreStatusTimer()
    scoreSaveQueued.value = false
    scoreSaveInFlight.value = false
    activeScoreFlush = null
    pendingScoreChanges.value = {}
    scoreRevisions.value = {}
    scoreSaveMessage.value = ''
    scoreSaveState.value = 'idle'
  }

  function applyServerGame(game: Game | null): void {
    if (!game) {
      resetScoreAutosaveState()
      currentGame.value = null
      return
    }

    if (currentGame.value && currentGame.value.id !== game.id) {
      resetScoreAutosaveState()
    }

    const nextGame = mergePendingScoreChanges(game)
    currentGame.value = nextGame
  }

  function mergePendingScoreChanges(game: Game): Game {
    const localGame = currentGame.value

    if (!localGame || localGame.id !== game.id || Object.keys(pendingScoreChanges.value).length === 0) {
      return game
    }

    const nextGame = {
      ...game,
      rounds: game.rounds.map((round) => ({ ...round })),
      teams: game.teams.map((team) => ({
        ...team,
        scores: { ...team.scores }
      }))
    }

    for (const pendingChange of Object.values(pendingScoreChanges.value)) {
      const localTeam = localGame.teams.find((team) => team.id === pendingChange.teamId)
      const nextTeam = nextGame.teams.find((team) => team.id === pendingChange.teamId)

      if (!localTeam || !nextTeam) {
        continue
      }

      nextTeam.scores[pendingChange.roundId] = localTeam.scores[pendingChange.roundId] ?? pendingChange.score
    }

    return nextGame
  }

  function getScoreCellKey(teamId: string, roundId: string): string {
    return `${teamId}:${roundId}`
  }

  return {
    currentGame,
    isLoading,
    error,
    sortedTeams,
    createGame,
    loadGame,
    openGame,
    finishGame,
    updateGameTitle,
    updateGameDetails,
    addTeam,
    deleteTeam,
    renameTeam,
    addRound,
    deleteRound,
    renameRound,
    updateRoundSettings,
    updateTeamScore,
    flushPendingScoreChanges,
    getTotalScore,
    clearGame,
    isEditable,
    hasPendingScoreChanges,
    isSavingScores,
    scoreSyncText,
    scoreSyncTone
  }
})
