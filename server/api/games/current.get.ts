import { requireUserId } from '../../utils/auth'
import { findCurrentGame } from '../../utils/quiz'

export default defineEventHandler(async (event) => {
  const userId = await requireUserId(event)

  return findCurrentGame(userId)
})
