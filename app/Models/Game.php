<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $dateFormat = 'Y-m-d H:i:s.v';

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class)->orderBy('position');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class)->orderBy('position');
    }
}
