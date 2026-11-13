<?php

namespace App\Policies;

use App\Models\Game;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GamePolicy
{
    // Foreign games respond as missing so their ids are not disclosed.
    public function manage(User $user, Game $game): Response
    {
        return $user->id === $game->user_id ? Response::allow() : Response::denyAsNotFound();
    }
}
