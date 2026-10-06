<?php

use Illuminate\Support\Facades\Schedule;

// Keeps the token tables small: removes revoked tokens and tokens expired for more than 7 days.
Schedule::command('passport:purge')->daily()->onOneServer()->withoutOverlapping();
