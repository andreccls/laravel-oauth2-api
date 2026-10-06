<?php

use App\Http\Controllers\MeController;
use App\Http\Controllers\RevokeTokenController;
use App\Http\Controllers\TaskController;
use App\Support\Scopes;
use Illuminate\Support\Facades\Route;

// `oauth:<scope>` validates the token and its scope; `throttle:api` runs AFTER it so the limit is keyed by client.
$cache = 'cache.headers:private;max_age=0;must_revalidate;etag'; // ETag + 304 revalidation on GETs

// Any valid token (no scope needed).
Route::middleware(['oauth', 'throttle:api'])->group(function () {
    Route::get('me', MeController::class);
    Route::post('oauth/revoke', RevokeTokenController::class);
});

Route::middleware(['oauth:'.Scopes::TASKS_READ, 'throttle:api'])->group(function () use ($cache) {
    Route::get('tasks', [TaskController::class, 'index'])->middleware($cache);
    Route::get('tasks/{id}', [TaskController::class, 'show'])->whereNumber('id')->middleware($cache);
});

Route::middleware(['oauth:'.Scopes::TASKS_WRITE, 'throttle:api'])->group(function () {
    Route::post('tasks', [TaskController::class, 'store']);
    Route::match(['put', 'patch'], 'tasks/{id}', [TaskController::class, 'update'])->whereNumber('id');
    Route::delete('tasks/{id}', [TaskController::class, 'destroy'])->whereNumber('id');
});
