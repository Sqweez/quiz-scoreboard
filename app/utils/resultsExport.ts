import type { Round, Team } from "../types/quiz";
import { getRoundScore, getTeamTotal } from "./scoring";

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
  return String(score);
}

function sanitizeTsvCell(value: string): string {
  return value.replace(/[\t\n\r]+/g, " ");
}
