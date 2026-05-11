import { getRouterParam } from 'h3'
import { requireUserId } from '../../../../utils/auth'
import { deleteGameTeam } from '../../../../utils/quiz'

export default defineEventHandler(async (event) => {
  const userId = await requireUserId(event)
  const gameId = getRouterParam(event, 'id') ?? ''
  const teamId = getRouterParam(event, 'teamId') ?? ''

  return deleteGameTeam(userId, gameId, teamId)
})
