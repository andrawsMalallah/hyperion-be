<?php

use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\UserSettingController;
use App\Http\Controllers\WorkoutLogController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::middleware('auth:api')->group(function () {
    // Per-exercise history for the Active Workout screen (defined before the
    // exercises resource so the static path isn't shadowed).
    Route::get('/exercises/recent-sets', [WorkoutLogController::class, 'recentSets']);
    Route::get('/exercises/{exercise}/logs', [WorkoutLogController::class, 'exerciseLogs']);
    Route::apiResource('exercises', ExerciseController::class)->only(['index', 'store']);
    Route::get('/programs/discover', [ProgramController::class, 'discover']);
    Route::get('/programs/by-day/{day}', [ProgramController::class, 'getByDay']);
    Route::post('/programs/{program}/clone', [ProgramController::class, 'clone']);
    Route::apiResource('programs', ProgramController::class);
    Route::get('/user/settings', [UserSettingController::class, 'show']);
    Route::put('/user/settings', [UserSettingController::class, 'update']);
    Route::get('/progress/stats', ProgressController::class);
    Route::apiResource('workout-logs', WorkoutLogController::class);
});
