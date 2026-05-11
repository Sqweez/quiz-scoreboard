import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import type { CreateGameInput, Game, Round, Team } from '../types/quiz'
import { getTeamTotal, normalizeScoreInput, sortTeamsByScore } from '../utils/scoring'

const STORAGE_KEY = 'quiz-scoreboard-game'

export const useQuizStore = defineStore('quiz', () => {
  const currentGame = ref<Game | null>(null)

  const sortedTeams = computed<Team[]>(() => {
    if (!currentGame.value) {
      return []
    }

    return sortTeamsByScore(currentGame.value.teams, currentGame.value.rounds)
  })

  function createGame(input: CreateGameInput): Game {
    const now = new Date().toISOString()
    const rounds = input.rounds.map((round) => ({
      id: createId(),
      title: round.title.trim(),
      maxScore: normalizeOptionalNumber(round.maxScore),
      questionsCount: normalizeOptionalNumber(round.questionsCount)
    }))

    const teams = input.teamNames.map((name) => ({
      id: createId(),
      name: name.trim(),
      scores: createScoresForRounds(rounds)
    }))

    currentGame.value = {
      id: createId(),
      title: input.title.trim(),
      rounds,
      teams,
      createdAt: now,
      updatedAt: now
    }

    saveGame()

    return currentGame.value
  }

  function loadGame(): void {
    if (!import.meta.client) {
      return
    }

    const rawGame = localStorage.getItem(STORAGE_KEY)

    if (!rawGame) {
      return
    }

    try {
      currentGame.value = normalizeGame(JSON.parse(rawGame) as Game)
    } catch {
      localStorage.removeItem(STORAGE_KEY)
      currentGame.value = null
    }
  }

  function saveGame(): void {
    if (!import.meta.client) {
      return
    }

    if (!currentGame.value) {
      localStorage.removeItem(STORAGE_KEY)
      return
    }

    localStorage.setItem(STORAGE_KEY, JSON.stringify(currentGame.value))
  }

  function updateGameTitle(title: string): void {
    if (!currentGame.value || !title.trim()) {
      return
    }

    currentGame.value.title = title.trim()
    touchGame()
  }

  function addTeam(name = `Команда ${(currentGame.value?.teams.length ?? 0) + 1}`): void {
    if (!currentGame.value || !name.trim()) {
      return
    }

    currentGame.value.teams.push({
      id: createId(),
      name: name.trim(),
      scores: createScoresForRounds(currentGame.value.rounds)
    })
    touchGame()
  }

  function deleteTeam(teamId: string): void {
    if (!currentGame.value || currentGame.value.teams.length <= 1) {
      return
    }

    currentGame.value.teams = currentGame.value.teams.filter((team) => team.id !== teamId)
    touchGame()
  }

  function renameTeam(teamId: string, name: string): void {
    const team = findTeam(teamId)

    if (!team || !name.trim()) {
      return
    }

    team.name = name.trim()
    touchGame()
  }

  function addRound(title = `Раунд ${(currentGame.value?.rounds.length ?? 0) + 1}`): void {
    if (!currentGame.value || !title.trim()) {
      return
    }

    const round: Round = {
      id: createId(),
      title: title.trim(),
      maxScore: null,
      questionsCount: null
    }

    currentGame.value.rounds.push(round)
    currentGame.value.teams.forEach((team) => {
      team.scores[round.id] = 0
    })
    touchGame()
  }

  function deleteRound(roundId: string): void {
    if (!currentGame.value || currentGame.value.rounds.length <= 1) {
      return
    }

    currentGame.value.rounds = currentGame.value.rounds.filter((round) => round.id !== roundId)
    currentGame.value.teams.forEach((team) => {
      delete team.scores[roundId]
    })
    touchGame()
  }

  function renameRound(roundId: string, title: string): void {
    updateRoundSettings(roundId, { title })
  }

  function updateRoundSettings(
    roundId: string,
    updates: Partial<Pick<Round, 'title' | 'maxScore' | 'questionsCount'>>
  ): void {
    const round = findRound(roundId)

    if (!round) {
      return
    }

    if (updates.title !== undefined) {
      if (!updates.title.trim()) {
        return
      }

      round.title = updates.title.trim()
    }

    if (updates.maxScore !== undefined) {
      round.maxScore = normalizeOptionalNumber(updates.maxScore)
      clampScoresForRound(round)
    }

    if (updates.questionsCount !== undefined) {
      round.questionsCount = normalizeOptionalNumber(updates.questionsCount)
    }

    touchGame()
  }

  function updateTeamScore(teamId: string, roundId: string, score: number | string | null | undefined): void {
    const team = findTeam(teamId)
    const round = findRound(roundId)

    if (!team || !round) {
      return
    }

    team.scores[roundId] = normalizeScoreInput(score, round)
    touchGame()
  }

  function getTotalScore(team: Team): number {
    return currentGame.value ? getTeamTotal(team, currentGame.value.rounds) : 0
  }

  function clearGame(): void {
    currentGame.value = null
    saveGame()
  }

  function findTeam(teamId: string): Team | undefined {
    return currentGame.value?.teams.find((team) => team.id === teamId)
  }

  function findRound(roundId: string): Round | undefined {
    return currentGame.value?.rounds.find((round) => round.id === roundId)
  }

  function touchGame(): void {
    if (!currentGame.value) {
      return
    }

    currentGame.value.updatedAt = new Date().toISOString()
    saveGame()
  }

  function clampScoresForRound(round: Round): void {
    currentGame.value?.teams.forEach((team) => {
      team.scores[round.id] = normalizeScoreInput(team.scores[round.id] ?? 0, round)
    })
  }

  return {
    currentGame,
    sortedTeams,
    createGame,
    loadGame,
    saveGame,
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

function createScoresForRounds(rounds: Round[]): Record<string, number> {
  return Object.fromEntries(rounds.map((round) => [round.id, 0]))
}

function normalizeGame(game: Game): Game {
  const rounds = Array.isArray(game.rounds) ? game.rounds : []
  const teams = Array.isArray(game.teams) ? game.teams : []
  const normalizedRounds = rounds.map((round) => ({
    ...round,
    maxScore: normalizeOptionalNumber(round.maxScore),
    questionsCount: normalizeOptionalNumber(round.questionsCount)
  }))

  return {
    ...game,
    rounds: normalizedRounds,
    teams: teams.map((team) => ({
      ...team,
      scores: normalizeScoresForRounds(team.scores ?? {}, normalizedRounds)
    }))
  }
}

function normalizeScoresForRounds(scores: Record<string, number>, rounds: Round[]): Record<string, number> {
  return Object.fromEntries(
    rounds.map((round) => [round.id, normalizeScoreInput(scores[round.id] ?? 0, round)])
  )
}

function normalizeOptionalNumber(value: number | string | null | undefined): number | null {
  if (value === '' || value === null || value === undefined || Number.isNaN(Number(value))) {
    return null
  }

  return Math.max(0, Number(value))
}

function createId(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
    return crypto.randomUUID()
  }

  return `${Date.now()}-${Math.random().toString(36).slice(2)}`
}
