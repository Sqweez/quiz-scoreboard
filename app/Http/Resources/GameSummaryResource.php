<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Game */
class GameSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'teamsCount' => $this->teams_count,
            'roundsCount' => $this->rounds_count,
            'updatedAt' => $this->updated_at->toISOString(),
            'finishedAt' => $this->finished_at?->toISOString(),
            // Progress in the Quiz Please rating: results queued, only the rating game created, or nothing yet.
            'ratingStatus' => match (true) {
                $this->rating_uploaded_at !== null => 'uploaded',
                $this->rating_created_at !== null => 'created',
                default => null,
            },
        ];
    }
}
