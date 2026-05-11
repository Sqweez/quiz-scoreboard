import { getRouterParam, readBody } from 'h3'
import { requireUserId } from '../../utils/auth'
import { updateGameTitle } from '../../utils/quiz'

export default defineEventHandler(async (event) => {
  const userId = await requireUserId(event)
  const gameId = getRouterParam(event, 'id') ?? ''
  const body = await readBody<{ title?: string }>(event)

  return updateGameTitle(userId, gameId, body.title ?? '')
})
