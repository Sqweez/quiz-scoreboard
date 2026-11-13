<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('games:finish-stale')->dailyAt('02:00');
