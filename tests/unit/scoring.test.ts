import { describe, expect, it } from 'vitest'
import { getTeamTotal, normalizeScoreInput, orderTeamsByPinnedIds, sortTeamsByScore } from '../../app/utils/scoring'
import type { Round, Team } from '../../app/types/quiz'

const rounds: Round[] = [
  { id: 'round-1', title: 'Round 1' },
  { id: 'round-2', title: 'Round 2' },
  { id: 'round-3', title: 'Round 3' }
]

describe('sortTeamsByScore', () => {
  it('sorts teams by total score descending', () => {
    const teams: Team[] = [
      { id: 'a', name: 'Team A', scores: { 'round-1': 2, 'round-2': 2 } },
      { id: 'b', name: 'Team B', scores: { 'round-1': 5, 'round-2': 1 } }
    ]

    expect(sortTeamsByScore(teams, rounds).map((team) => team.id)).toEqual(['b', 'a'])
  })

  it('breaks equal totals by comparing later rounds first', () => {
    const teams: Team[] = [
      { id: 'a', name: 'Team A', scores: { 'round-1': 5, 'round-2': 7, 'round-3': 4 } },
      { id: 'b', name: 'Team B', scores: { 'round-1': 6, 'round-2': 6, 'round-3': 4 } }
    ]

    expect(sortTeamsByScore(teams, rounds).map((team) => team.id)).toEqual(['a', 'b'])
  })

  it('calculates totals and tie-breaks with decimal scores', () => {
    const teams: Team[] = [
      { id: 'a', name: 'Team A', scores: { 'round-1': 1, 'round-2': 2.5, 'round-3': 0.5 } },
      { id: 'b', name: 'Team B', scores: { 'round-1': 2, 'round-2': 1.5, 'round-3': 0.5 } }
    ]

    expect(getTeamTotal(teams[0], rounds)).toBe(4)
    expect(sortTeamsByScore(teams, rounds).map((team) => team.id)).toEqual(['a', 'b'])
  })

  it('normalizes score input as a safe number with fractional max score support', () => {
    expect(normalizeScoreInput('', { id: 'round-1', title: 'Round 1' })).toBe(0)
    expect(normalizeScoreInput('bad', { id: 'round-1', title: 'Round 1' })).toBe(0)
    expect(normalizeScoreInput(-1, { id: 'round-1', title: 'Round 1' })).toBe(0)
    expect(normalizeScoreInput('1.5', { id: 'round-1', title: 'Round 1' })).toBe(1.5)
    expect(normalizeScoreInput(3, { id: 'round-1', title: 'Round 1', maxScore: 2.5 })).toBe(2.5)
  })

  it('treats missing score values as zero and keeps stable order for identical scores', () => {
    const teams: Team[] = [
      { id: 'a', name: 'Team A', scores: {} },
      { id: 'b', name: 'Team B', scores: { 'round-1': 0, 'round-2': 0, 'round-3': 0 } }
    ]

    expect(sortTeamsByScore(teams, rounds).map((team) => team.id)).toEqual(['a', 'b'])
  })

  it('does not mutate the original teams array', () => {
    const teams: Team[] = [
      { id: 'a', name: 'Team A', scores: { 'round-1': 1 } },
      { id: 'b', name: 'Team B', scores: { 'round-1': 2 } }
    ]
    const originalOrder = teams.map((team) => team.id)

    sortTeamsByScore(teams, rounds)

    expect(teams.map((team) => team.id)).toEqual(originalOrder)
  })

  it('can keep a pinned visible order while scores are being edited', () => {
    const teams: Team[] = [
      { id: 'b', name: 'Team B', scores: { 'round-1': 10 } },
      { id: 'a', name: 'Team A', scores: { 'round-1': 12 } },
      { id: 'c', name: 'Team C', scores: { 'round-1': 3 } }
    ]

    expect(orderTeamsByPinnedIds(teams, ['a', 'b']).map((team) => team.id)).toEqual(['a', 'b', 'c'])
  })
})
