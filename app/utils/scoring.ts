import type { Round, Team } from "../types/quiz";

export function getTeamTotal(team: Team, rounds: Round[]): number {
  return rounds.reduce(
    (total, round) => total + getRoundScore(team, round.id),
    0,
  );
}

export function sortTeamsByScore(teams: Team[], rounds: Round[]): Team[] {
  return teams
    .map((team, index) => ({ team, index }))
    .sort((left, right) => {
      const totalDifference =
        getTeamTotal(right.team, rounds) - getTeamTotal(left.team, rounds);

      if (totalDifference !== 0) {
        return totalDifference;
      }

      for (let index = rounds.length - 1; index >= 0; index -= 1) {
        const round = rounds[index];

        if (!round) {
          continue;
        }

        const scoreDifference =
          getRoundScore(right.team, round.id) -
          getRoundScore(left.team, round.id);

        if (scoreDifference !== 0) {
          return scoreDifference;
        }
      }

      return left.index - right.index;
    })
    .map(({ team }) => team);
}

export function normalizeScoreInput(
  value: number | string | null | undefined,
  round: Pick<Round, "maxScore">,
): number {
  const score =
    value === "" || value === null || value === undefined ? 0 : Number(value);
  const safeScore = Number.isFinite(score) ? Math.max(0, score) : 0;

  if (round.maxScore === null || round.maxScore === undefined) {
    return safeScore;
  }

  return Math.min(safeScore, round.maxScore);
}

export function orderTeamsByPinnedIds(
  teams: Team[],
  pinnedIds: string[] | null,
): Team[] {
  if (!pinnedIds) {
    return teams;
  }

  const teamsById = new Map(teams.map((team) => [team.id, team]));
  const pinnedTeams = pinnedIds
    .map((id) => teamsById.get(id))
    .filter((team): team is Team => Boolean(team));
  const pinnedIdSet = new Set(pinnedTeams.map((team) => team.id));
  const unpinnedTeams = teams.filter((team) => !pinnedIdSet.has(team.id));

  return [...pinnedTeams, ...unpinnedTeams];
}

export function exportResultsToTsv(teams: Team[], rounds: Round[]): string {
  const rows = teams.map((team, index) => [
    String(index + 1),
    team.name,
    formatScore(getTeamTotal(team, rounds)),
    ...rounds.map((round) => formatScore(getRoundScore(team, round.id))),
  ]);

  return rows.map((row) => row.map(sanitizeTsvCell).join("\t")).join("\n");
}

function formatScore(score: number): string {
  return Number.isInteger(score) ? String(score) : String(score);
}

function sanitizeTsvCell(value: string): string {
  return value.replace(/[\t\n\r]+/g, " ");
}

function getRoundScore(team: Team, roundId: string): number {
  return Number.isFinite(team.scores[roundId]) ? team.scores[roundId] : 0;
}
