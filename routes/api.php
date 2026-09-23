<?php

use App\Http\Controllers\Api\EnergyCommunityController;
use App\Http\Controllers\Api\EnergyCommunityUserController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\MeterPointController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', LoginController::class)
    ->middleware('throttle:6,1')
    ->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', fn (Request $request) => $request->user())->name('me');

    Route::get('/meter-points', [MeterPointController::class, 'index'])->name('meter-points.index');
    Route::post('/meter-points', [MeterPointController::class, 'store'])->name('meter-points.store');

    Route::get('/energy-communities', [EnergyCommunityController::class, 'index'])->name('energy-communities.index');
    Route::post('/energy-communities', [EnergyCommunityController::class, 'store'])->name('energy-communities.store');
    Route::get('/energy-communities/{energyCommunity}', [EnergyCommunityController::class, 'show'])->name('energy-communities.show');

    Route::post('/energy-communities/{energyCommunity}/users', [EnergyCommunityUserController::class, 'store'])->name('energy-communities.users.store');

    /// P2 / P3 endpoints go here.
});
