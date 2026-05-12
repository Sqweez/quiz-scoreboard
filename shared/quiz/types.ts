export type GameStatus = 'draft' | 'finished'

export type Round = {
  id: string
  title: string
  maxScore?: number | null
  questionsCount?: number | null
}

export type Team = {
  id: string
  name: string
  scores: Record<string, number>
}

export type GameCore = {
  id: string
  title: string
  status: GameStatus
  rounds: Round[]
  teams: Team[]
  createdAt: string
  updatedAt: string
}

export type Game = GameCore & {
  statusLabel: string
  statusActionLabel: string
}

export type CreateGameInput = {
  title: string
  teamNames: string[]
  rounds: Array<{
    title: string
    maxScore?: number | null
    questionsCount?: number | null
  }>
}

export type ScoreUpdateInput = {
  teamId: string
  roundId: string
  score: number | string | null
}

export type BulkScoreUpdateInput = {
  updates: ScoreUpdateInput[]
}
