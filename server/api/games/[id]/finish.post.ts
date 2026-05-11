import { getRouterParam } from 'h3'
import { requireUserId } from '../../../utils/auth'
import { finishGame } from '../../../utils/quiz'

export default defineEventHandler(async (event) => {
  const userId = await requireUserId(event)
  const gameId = getRouterParam(event, 'id') ?? ''

  return finishGame(userId, gameId)
})
