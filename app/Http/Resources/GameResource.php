<?php

namespace App\Http\Resources;

use App\Models\Round;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Game */
class GameResource extends JsonResource
{
    // The client store consumes the game object at the top level.
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['rounds', 'teams.scores']);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'externalId' => $this->external_id,
            'extra' => $this->extra,
            'ratingCategoryId' => $this->rating_category_id,
            'ratingIncludeScores' => $this->rating_include_scores,
            'ratingPackage' => $this->rating_package,
            'ratingCreatedAt' => $this->rating_created_at?->toISOString(),
            'status' => $this->status,
            'statusLabel' => $this->status->label(),
            'statusActionLabel' => $this->status->actionLabel(),
            'rounds' => RoundResource::collection($this->rounds),
            'teams' => $this->teams->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'scores' => $this->teamScores($team),
            ])->all(),
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
        ];
    }

    private function teamScores(Team $team): array
    {
        $values = $team->scores->keyBy('round_id');

        return $this->rounds->mapWithKeys(fn (Round $round) => [
            $round->id => $round->normalizeScore($values->get($round->id)?->value),
        ])->all();
    }
}
