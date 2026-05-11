import { createError } from 'h3'
import { prisma } from './prisma'
import { normalizeScoreInput } from '../../app/utils/scoring'
import type { CreateGameInput, Game, Round } from '../../app/types/quiz'

const gameInclude = {
  rounds: {
    orderBy: { position: 'asc' as const }
  },
  teams: {
    orderBy: { position: 'asc' as const },
    include: {
      scores: true
    }
  }
}

type PersistedGame = NonNullable<Awaited<ReturnType<typeof findOwnedGame>>>

export async function findCurrentGame(userId: string): Promise<Game | null> {
  const game = await prisma.game.findFirst({
    where: { userId },
    orderBy: { updatedAt: 'desc' },
    include: gameInclude
  })

  return game ? mapGame(game) : null
}

export async function createPersistedGame(userId: string, input: CreateGameInput): Promise<Game> {
  const title = input.title.trim()
  const teamNames = input.teamNames.map((name) => name.trim()).filter(Boolean)
  const rounds = input.rounds
    .map((round) => ({
      title: round.title.trim(),
      maxScore: normalizeOptionalNumber(round.maxScore),
      questionsCount: normalizeOptionalNumber(round.questionsCount)
    }))
    .filter((round) => round.title)

  if (!title || teamNames.length < 1 || rounds.length < 1) {
    throw createError({
      statusCode: 400,
      statusMessage: 'Game title, teams, and rounds are required.'
    })
  }

  const game = await prisma.$transaction(async (tx) => {
    const createdGame = await tx.game.create({
      data: {
        userId,
        title
      }
    })
    const createdRounds = await Promise.all(
      rounds.map((round, index) => tx.round.create({
        data: {
          gameId: createdGame.id,
          title: round.title,
          maxScore: round.maxScore,
          questionsCount: round.questionsCount,
          position: index
        }
      }))
    )
    const createdTeams = await Promise.all(
      teamNames.map((name, index) => tx.team.create({
        data: {
          gameId: createdGame.id,
          name,
          position: index
        }
      }))
    )

    await tx.score.createMany({
      data: createdTeams.flatMap((team) => createdRounds.map((round) => ({
        teamId: team.id,
        roundId: round.id,
        value: 0
      })))
    })

    return tx.game.findUniqueOrThrow({
      where: { id: createdGame.id },
      include: gameInclude
    })
  })

  return mapGame(game)
}

export async function updateGameTitle(userId: string, gameId: string, title: string): Promise<Game> {
  const game = await requireOwnedGame(userId, gameId)
  const normalizedTitle = title.trim()

  if (!normalizedTitle) {
    throw createError({
      statusCode: 400,
      statusMessage: 'Game title is required.'
    })
  }

  await prisma.game.update({
    where: { id: game.id },
    data: { title: normalizedTitle }
  })

  return refreshGame(userId, gameId)
}

export async function deleteGame(userId: string, gameId: string): Promise<{ ok: true }> {
  const game = await requireOwnedGame(userId, gameId)

  await prisma.game.delete({
    where: { id: game.id }
  })

  return { ok: true }
}

export async function addGameTeam(userId: string, gameId: string, name: string): Promise<Game> {
  const game = await requireOwnedGame(userId, gameId)
  const normalizedName = name.trim()

  if (!normalizedName) {
    throw createError({
      statusCode: 400,
      statusMessage: 'Team name is required.'
    })
  }

  await prisma.$transaction(async (tx) => {
    const team = await tx.team.create({
      data: {
        gameId: game.id,
        name: normalizedName,
        position: game.teams.length
      }
    })

    await tx.score.createMany({
      data: game.rounds.map((round) => ({
        teamId: team.id,
        roundId: round.id,
        value: 0
      }))
    })

    await touchGame(tx, game.id)
  })

  return refreshGame(userId, gameId)
}

export async function renameGameTeam(userId: string, gameId: string, teamId: string, name: string): Promise<Game> {
  const game = await requireOwnedGame(userId, gameId)
  const normalizedName = name.trim()

  if (!normalizedName || !game.teams.some((team) => team.id === teamId)) {
    throw createError({
      statusCode: 400,
      statusMessage: 'Valid team name and team id are required.'
    })
  }

  await prisma.$transaction(async (tx) => {
    await tx.team.update({
      where: { id: teamId },
      data: { name: normalizedName }
    })
    await touchGame(tx, game.id)
  })

  return refreshGame(userId, gameId)
}

export async function deleteGameTeam(userId: string, gameId: string, teamId: string): Promise<Game> {
  const game = await requireOwnedGame(userId, gameId)

  if (game.teams.length <= 1 || !game.teams.some((team) => team.id === teamId)) {
    throw createError({
      statusCode: 400,
      statusMessage: 'At least one team must remain.'
    })
  }

  await prisma.$transaction(async (tx) => {
    await tx.team.delete({
      where: { id: teamId }
    })
    await touchGame(tx, game.id)
  })

  return refreshGame(userId, gameId)
}

export async function addGameRound(userId: string, gameId: string, title: string): Promise<Game> {
  const game = await requireOwnedGame(userId, gameId)
  const normalizedTitle = title.trim()

  if (!normalizedTitle) {
    throw createError({
      statusCode: 400,
      statusMessage: 'Round title is required.'
    })
  }

  await prisma.$transaction(async (tx) => {
    const round = await tx.round.create({
      data: {
        gameId: game.id,
        title: normalizedTitle,
        maxScore: null,
        questionsCount: null,
        position: game.rounds.length
      }
    })

    await tx.score.createMany({
      data: game.teams.map((team) => ({
        teamId: team.id,
        roundId: round.id,
        value: 0
      }))
    })

    await touchGame(tx, game.id)
  })

  return refreshGame(userId, gameId)
}

export async function updateGameRound(
  userId: string,
  gameId: string,
  roundId: string,
  updates: Partial<Pick<Round, 'title' | 'maxScore' | 'questionsCount'>>
): Promise<Game> {
  const game = await requireOwnedGame(userId, gameId)
  const round = game.rounds.find((item) => item.id === roundId)

  if (!round) {
    throw createError({
      statusCode: 404,
      statusMessage: 'Round not found.'
    })
  }

  const nextRound = {
    ...round,
    title: updates.title !== undefined ? updates.title.trim() : round.title,
    maxScore: updates.maxScore !== undefined ? normalizeOptionalNumber(updates.maxScore) : round.maxScore,
    questionsCount: updates.questionsCount !== undefined
      ? normalizeOptionalNumber(updates.questionsCount)
      : round.questionsCount
  }

  if (!nextRound.title) {
    throw createError({
      statusCode: 400,
      statusMessage: 'Round title is required.'
    })
  }

  await prisma.$transaction(async (tx) => {
    await tx.round.update({
      where: { id: roundId },
      data: {
        title: nextRound.title,
        maxScore: nextRound.maxScore,
        questionsCount: nextRound.questionsCount
      }
    })

    await Promise.all(game.teams.map((team) => tx.score.update({
      where: {
        teamId_roundId: {
          teamId: team.id,
          roundId
        }
      },
      data: {
        value: normalizeScoreInput(team.scores[roundId] ?? 0, nextRound)
      }
    })))
    await touchGame(tx, game.id)
  })

  return refreshGame(userId, gameId)
}

export async function deleteGameRound(userId: string, gameId: string, roundId: string): Promise<Game> {
  const game = await requireOwnedGame(userId, gameId)

  if (game.rounds.length <= 1 || !game.rounds.some((round) => round.id === roundId)) {
    throw createError({
      statusCode: 400,
      statusMessage: 'At least one round must remain.'
    })
  }

  await prisma.$transaction(async (tx) => {
    await tx.round.delete({
      where: { id: roundId }
    })
    await touchGame(tx, game.id)
  })

  return refreshGame(userId, gameId)
}

export async function updateGameScore(
  userId: string,
  gameId: string,
  teamId: string,
  roundId: string,
  score: number | string | null | undefined
): Promise<Game> {
  const game = await requireOwnedGame(userId, gameId)
  const team = game.teams.find((item) => item.id === teamId)
  const round = game.rounds.find((item) => item.id === roundId)

  if (!team || !round) {
    throw createError({
      statusCode: 404,
      statusMessage: 'Team or round not found.'
    })
  }

  await prisma.$transaction(async (tx) => {
    await tx.score.upsert({
      where: {
        teamId_roundId: {
          teamId,
          roundId
        }
      },
      create: {
        teamId,
        roundId,
        value: normalizeScoreInput(score, round)
      },
      update: {
        value: normalizeScoreInput(score, round)
      }
    })
    await touchGame(tx, game.id)
  })

  return refreshGame(userId, gameId)
}

async function requireOwnedGame(userId: string, gameId: string): Promise<PersistedGame> {
  const game = await findOwnedGame(userId, gameId)

  if (!game) {
    throw createError({
      statusCode: 404,
      statusMessage: 'Game not found.'
    })
  }

  return game
}

function findOwnedGame(userId: string, gameId: string) {
  return prisma.game.findFirst({
    where: {
      id: gameId,
      userId
    },
    include: gameInclude
  })
}

async function refreshGame(userId: string, gameId: string): Promise<Game> {
  return mapGame(await requireOwnedGame(userId, gameId))
}

function mapGame(game: PersistedGame): Game {
  const rounds = game.rounds.map((round) => ({
    id: round.id,
    title: round.title,
    maxScore: round.maxScore,
    questionsCount: round.questionsCount
  }))

  return {
    id: game.id,
    title: game.title,
    rounds,
    teams: game.teams.map((team) => ({
      id: team.id,
      name: team.name,
      scores: Object.fromEntries(rounds.map((round) => {
        const score = team.scores.find((item) => item.roundId === round.id)?.value ?? 0

        return [round.id, normalizeScoreInput(score, round)]
      }))
    })),
    createdAt: game.createdAt.toISOString(),
    updatedAt: game.updatedAt.toISOString()
  }
}

function normalizeOptionalNumber(value: number | string | null | undefined): number | null {
  if (value === '' || value === null || value === undefined || Number.isNaN(Number(value))) {
    return null
  }

  return Math.max(0, Number(value))
}

async function touchGame(tx: { game: { update: typeof prisma.game.update } }, gameId: string): Promise<void> {
  await tx.game.update({
    where: { id: gameId },
    data: { updatedAt: new Date() }
  })
}
