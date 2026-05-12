import { getRouterParam, readBody } from 'h3'
import { requireUserId } from '../../../../utils/auth'
import { updateGameScores } from '../../../../utils/quiz'
import type { BulkScoreUpdateInput } from '#shared/quiz'

export default defineEventHandler(async (event) => {
  const userId = await requireUserId(event)
  const gameId = getRouterParam(event, 'id') ?? ''
  const body = await readBody<BulkScoreUpdateInput>(event)

  return updateGameScores(userId, gameId, body?.updates ?? [])
})
