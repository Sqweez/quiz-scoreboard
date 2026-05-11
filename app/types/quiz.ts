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

export type Game = {
  id: string
  title: string
  rounds: Round[]
  teams: Team[]
  createdAt: string
  updatedAt: string
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
