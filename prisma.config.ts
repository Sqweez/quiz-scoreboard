import 'dotenv/config'
import { defineConfig } from 'prisma/config'

const databaseUrl = process.env.DIRECT_URL
  ?? process.env.DIRECT_DB_URL
  ?? process.env.DATABASE_URL
  ?? 'postgresql://postgres:postgres@localhost:5432/quiz_scoreboard'

export default defineConfig({
  schema: 'prisma/schema.prisma',
  migrations: {
    path: 'prisma/migrations'
  },
  datasource: {
    url: databaseUrl
  }
})
