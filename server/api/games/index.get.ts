import { requireUserId } from '../../utils/auth'
import { listGames } from '../../utils/quiz'

export default defineEventHandler(async (event) => {
  const userId = await requireUserId(event)

  return listGames(userId)
})
