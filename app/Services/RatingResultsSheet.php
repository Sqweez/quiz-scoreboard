<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Round;
use App\Models\Team;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

// Results table in the layout the Quiz Please rating accepts for upload:
// "Место", "Название команды", "Итого", "Раунд 1".."Раунд N".
class RatingResultsSheet
{
    public function write(Game $game, string $path): void
    {
        $game->loadMissing(['rounds', 'teams.scores']);
        $rounds = $game->rounds;
        $border = new Border(...array_map(fn (BorderName $name) => new BorderPart($name, '999999', BorderWidth::THIN), BorderName::cases()));
        $cellStyle = (new Style())->withBorder($border);
        $headerStyle = $cellStyle->withFontBold(true);

        $writer = new Writer();
        $writer->openToFile($path);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Результаты');
        $sheet->setColumnWidth(25, 2);

        $header = ['Место', 'Название команды', 'Итого', ...$rounds->map(fn (Round $round, int $index) => 'Раунд '.($index + 1))];
        $writer->addRow(new Row(array_map(fn (string $title) => Cell::fromValue($title, $headerStyle), $header)));

        foreach ($this->rankedTeams($game) as $place => [$team, $scores]) {
            $values = [$place + 1, $team->name, $this->number(array_sum($scores)), ...array_map($this->number(...), $scores)];
            $writer->addRow(new Row(array_map(fn ($value) => Cell::fromValue($value, $cellStyle), $values)));
        }

        $writer->close();
    }

    // Same order as the score table (sortTeamsByScore): total, then the latest rounds, then the team order.
    /** @return list<array{Team, list<float>}> */
    private function rankedTeams(Game $game): array
    {
        $teams = $game->teams->values()->map(function (Team $team, int $index) use ($game) {
            $values = $team->scores->keyBy('round_id');
            $scores = $game->rounds->map(fn (Round $round) => $round->normalizeScore($values->get($round->id)?->value))->all();

            return ['team' => $team, 'scores' => $scores, 'index' => $index];
        })->all();

        usort($teams, function (array $left, array $right) {
            $byTotal = array_sum($right['scores']) <=> array_sum($left['scores']);
            if ($byTotal !== 0) {
                return $byTotal;
            }

            for ($round = count($left['scores']) - 1; $round >= 0; $round--) {
                $byRound = $right['scores'][$round] <=> $left['scores'][$round];
                if ($byRound !== 0) {
                    return $byRound;
                }
            }

            return $left['index'] <=> $right['index'];
        });

        return array_map(fn (array $entry) => [$entry['team'], $entry['scores']], $teams);
    }

    // Whole scores are written as integers, like in the sample from the rating.
    private function number(float $score): int|float
    {
        return floor($score) === $score ? (int) $score : $score;
    }
}
