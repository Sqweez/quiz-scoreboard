import { getRouterParam } from 'h3'
import { requireUserId } from '../../../../utils/auth'
import { deleteGameRound } from '../../../../utils/quiz'

export default defineEventHandler(async (event) => {
  const userId = await requireUserId(event)
  const gameId = getRouterParam(event, 'id') ?? ''
  const roundId = getRouterParam(event, 'roundId') ?? ''

  return deleteGameRound(userId, gameId, roundId)
})
