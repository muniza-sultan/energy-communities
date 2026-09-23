<?php

use App\Http\Controllers\Api\EnergyCommunityController;
use App\Http\Controllers\Api\EnergyCommunityMeterPointController;
use App\Http\Controllers\Api\EnergyCommunityUserController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\MeterPointController;
use App\Http\Controllers\Api\RegistrationController;
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

    // P2: registrations
    Route::get('/energy-communities/{energyCommunity}/meter-points', [EnergyCommunityMeterPointController::class, 'index'])->name('energy-communities.meter-points.index');
    Route::post('/energy-communities/{energyCommunity}/meter-points', [EnergyCommunityMeterPointController::class, 'store'])->name('energy-communities.meter-points.store');

    Route::post('/registrations/{registration}/transition', [RegistrationController::class, 'transition'])->name('registrations.transition');
    Route::delete('/registrations/{registration}', [RegistrationController::class, 'destroy'])->name('registrations.destroy');

    // P3: community lifecycle
    Route::post('/energy-communities/{energyCommunity}/activate', [EnergyCommunityController::class, 'activate'])->name('energy-communities.activate');
    Route::post('/energy-communities/{energyCommunity}/reject', [EnergyCommunityController::class, 'reject'])->name('energy-communities.reject');
});
