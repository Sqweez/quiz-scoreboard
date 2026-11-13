<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Round extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array { return ['max_score' => 'float', 'questions_count' => 'integer']; }

    // Scores are never negative and never exceed the round maximum when one is set.
    public function normalizeScore(mixed $value): float
    {
        $score = max(0, (float) $value);
        return $this->max_score === null ? $score : min($score, $this->max_score);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }
}
