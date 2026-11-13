import type { GameStatus } from './types'

export type GameStatusMeta = {
  statusLabel: string
  statusActionLabel: string
}

export const GAME_STATUS_META: Record<GameStatus, GameStatusMeta> = {
  draft: {
    statusLabel: 'Открытая',
    statusActionLabel: 'Открыть'
  },
  finished: {
    statusLabel: 'Завершенная',
    statusActionLabel: 'Просмотр'
  }
}

export function getGameStatusMeta(status: GameStatus): GameStatusMeta {
  return GAME_STATUS_META[status]
}
