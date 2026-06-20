<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\SplitController;
use App\Http\Controllers\WorkoutLogController;

require __DIR__.'/auth.php';

Route::middleware('auth:api')->group(function () {
    Route::apiResource('exercises', ExerciseController::class)->only(['index', 'store']);
    Route::get('/splits/discover', [SplitController::class, 'discover']);
    Route::get('/splits/by-day/{day}', [SplitController::class, 'getByDay']);
    Route::apiResource('splits', SplitController::class);
    Route::get('/user/settings', [App\Http\Controllers\UserSettingController::class, 'show']);
    Route::put('/user/settings', [App\Http\Controllers\UserSettingController::class, 'update']);
    Route::apiResource('workout-logs', WorkoutLogController::class)->except(['update']);
});
