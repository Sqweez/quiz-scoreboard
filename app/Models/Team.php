<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $guarded = [];

    

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }
}
