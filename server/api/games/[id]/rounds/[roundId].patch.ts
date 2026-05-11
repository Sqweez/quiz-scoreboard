import { getRouterParam, readBody } from 'h3'
import { requireUserId } from '../../../../utils/auth'
import { updateGameRound } from '../../../../utils/quiz'
import type { Round } from '../../../../../app/types/quiz'

export default defineEventHandler(async (event) => {
  const userId = await requireUserId(event)
  const gameId = getRouterParam(event, 'id') ?? ''
  const roundId = getRouterParam(event, 'roundId') ?? ''
  const body = await readBody<Partial<Pick<Round, 'title' | 'maxScore' | 'questionsCount'>>>(event)

  return updateGameRound(userId, gameId, roundId, body)
})
