import { createError, type H3Event } from 'h3'
import { serverSupabaseUser } from '#supabase/server'

export async function requireUserId(event: H3Event): Promise<string> {
  const user = await serverSupabaseUser(event)

  if (!user?.sub) {
    throw createError({
      statusCode: 401,
      statusMessage: 'Authentication required.'
    })
  }

  return user.sub
}
