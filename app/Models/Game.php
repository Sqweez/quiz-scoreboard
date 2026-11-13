<?php

namespace App\Models;

use App\Enums\GameStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $dateFormat = 'Y-m-d H:i:s.vP';
    protected $casts = [
        'status' => GameStatus::class, 'finished_at' => 'datetime',
        'extra' => 'array', 'rating_include_scores' => 'boolean', 'rating_created_at' => 'datetime', 'rating_uploaded_at' => 'datetime',
    ];

    // Everything the rating module needs to create the game.
    public function isReadyForRating(): bool
    {
        return $this->status === GameStatus::Finished
            && $this->external_id !== null
            && $this->rating_category_id !== null
            && !empty($this->rating_package)
            && !empty($this->extra['title'])
            && !empty($this->extra['gameNumber'])
            && !empty($this->extra['packageNumber'])
            && !empty($this->extra['date']);
    }

    public function finish(): void
    {
        $this->forceFill(['status' => GameStatus::Finished, 'finished_at' => now()])->save();
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class)->orderBy('position');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class)->orderBy('position');
    }
}
