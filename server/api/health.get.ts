import { prisma } from '../utils/prisma'

export default defineEventHandler(async () => {
  try {
    // Выполняем простейший запрос для проверки подключения к БД
    await prisma.$queryRaw`SELECT 1`
    return {
      status: 'ok',
      database: 'connected',
      timestamp: new Date().toISOString()
    }
  } catch (error: any) {
    throw createError({
      statusCode: 500,
      statusMessage: `Database connection failed: ${error.message || error}`
    })
  }
})
