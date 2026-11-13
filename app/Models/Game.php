<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $dateFormat = 'Y-m-d H:i:s.vP';
    protected $casts = ['finished_at' => 'datetime'];

    public function finish(): void
    {
        $this->forceFill(['status' => 'finished', 'finished_at' => now()])->save();
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
