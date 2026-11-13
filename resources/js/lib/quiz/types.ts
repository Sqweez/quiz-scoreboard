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
  externalId: string | null
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

export type GameSummary = {
  id: string
  title: string
  teamsCount: number
  roundsCount: number
  updatedAt: string
  finishedAt: string | null
}

export type ResourceCollection<T> = {
  data: T[]
}

export type PaginatedResourceCollection<T> = ResourceCollection<T> & {
  meta: {
    current_page: number
    last_page: number
    total: number
  }
}

export type CreateGameInput = {
  title: string
  externalId?: string
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
