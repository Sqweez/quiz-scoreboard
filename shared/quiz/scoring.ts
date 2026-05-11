import type { Round, Team } from './types'

export function getTeamTotal(team: Team, rounds: Round[]): number {
  return rounds.reduce((total, round) => total + getRoundScore(team, round.id), 0)
}

export function sortTeamsByScore(teams: Team[], rounds: Round[]): Team[] {
  return teams
    .map((team, index) => ({ team, index }))
    .sort((left, right) => {
      const totalDifference = getTeamTotal(right.team, rounds) - getTeamTotal(left.team, rounds)

      if (totalDifference !== 0) {
        return totalDifference
      }

      for (let index = rounds.length - 1; index >= 0; index -= 1) {
        const round = rounds[index]

        if (!round) {
          continue
        }

        const scoreDifference = getRoundScore(right.team, round.id) - getRoundScore(left.team, round.id)

        if (scoreDifference !== 0) {
          return scoreDifference
        }
      }

      return left.index - right.index
    })
    .map(({ team }) => team)
}

export function normalizeScoreInput(
  value: number | string | null | undefined,
  round: Pick<Round, 'maxScore'>,
): number {
  const score = value === '' || value === null || value === undefined ? 0 : Number(value)
  const safeScore = Number.isFinite(score) ? Math.max(0, score) : 0

  if (round.maxScore === null || round.maxScore === undefined) {
    return safeScore
  }

  return Math.min(safeScore, round.maxScore)
}

export function getRoundScore(team: Team, roundId: string): number {
  const score = team.scores[roundId] ?? 0

  return Number.isFinite(score) ? score : 0
}
