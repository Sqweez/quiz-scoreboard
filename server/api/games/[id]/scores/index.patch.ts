import { getRouterParam, readBody } from 'h3'
import { requireUserId } from '../../../../utils/auth'
import { updateGameScore } from '../../../../utils/quiz'

export default defineEventHandler(async (event) => {
  const userId = await requireUserId(event)
  const gameId = getRouterParam(event, 'id') ?? ''
  const body = await readBody<{
    teamId?: string
    roundId?: string
    score?: number | string | null
  }>(event)

  return updateGameScore(userId, gameId, body.teamId ?? '', body.roundId ?? '', body.score)
})
