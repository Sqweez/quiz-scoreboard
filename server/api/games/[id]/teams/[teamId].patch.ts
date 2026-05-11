import { getRouterParam, readBody } from 'h3'
import { requireUserId } from '../../../../utils/auth'
import { renameGameTeam } from '../../../../utils/quiz'

export default defineEventHandler(async (event) => {
  const userId = await requireUserId(event)
  const gameId = getRouterParam(event, 'id') ?? ''
  const teamId = getRouterParam(event, 'teamId') ?? ''
  const body = await readBody<{ name?: string }>(event)

  return renameGameTeam(userId, gameId, teamId, body.name ?? '')
})
