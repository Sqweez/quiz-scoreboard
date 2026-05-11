import { getRouterParam, readBody } from 'h3'
import { requireUserId } from '../../../../utils/auth'
import { addGameTeam } from '../../../../utils/quiz'

export default defineEventHandler(async (event) => {
  const userId = await requireUserId(event)
  const gameId = getRouterParam(event, 'id') ?? ''
  const body = await readBody<{ name?: string }>(event)

  return addGameTeam(userId, gameId, body.name ?? '')
})
