<?php

use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

// Only for the authorization-code flow (resource-owner session). Passport registers /oauth/* itself.
Route::post('/login', LoginController::class)->middleware('throttle:login');
