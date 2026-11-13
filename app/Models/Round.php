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

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }
}
