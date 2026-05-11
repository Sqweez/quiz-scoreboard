import { readBody } from 'h3'
import { requireUserId } from '../../utils/auth'
import { createPersistedGame } from '../../utils/quiz'
import type { CreateGameInput } from '#shared/quiz'

export default defineEventHandler(async (event) => {
  const userId = await requireUserId(event)
  const body = await readBody<CreateGameInput>(event)

  return createPersistedGame(userId, body)
})
