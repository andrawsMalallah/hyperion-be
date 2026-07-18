<?php

use App\Http\Controllers\AdminExerciseController;
use App\Http\Controllers\BodyMetricController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\UserSettingController;
use App\Http\Controllers\WorkoutLogController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

// All app data requires a verified email (hard requirement — see ROADMAP 1.6).
// Auth/verification endpoints live in auth.php and stay outside this gate.
Route::middleware(['auth:api', 'verified'])->group(function () {
    // Per-exercise history for the Active Workout screen (defined before the
    // exercises resource so the static path isn't shadowed).
    Route::get('/exercises/recent-sets', [WorkoutLogController::class, 'recentSets']);
    // The contributor's own submissions (all statuses) — must precede the
    // /exercises/{exercise} binding so the static path isn't shadowed.
    Route::get('/exercises/mine', [ExerciseController::class, 'mine']);
    Route::get('/exercises/{exercise}/logs', [WorkoutLogController::class, 'exerciseLogs']);
    Route::apiResource('exercises', ExerciseController::class)->only(['index', 'store']);

    // Exercise approval dashboard — admin only.
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/exercises', [AdminExerciseController::class, 'index']);
        Route::get('/exercises/pending', [AdminExerciseController::class, 'pending']);
        Route::post('/exercises/{exercise}/approve', [AdminExerciseController::class, 'approve']);
        Route::post('/exercises/{exercise}/reject', [AdminExerciseController::class, 'reject']);
    });
    Route::get('/programs/discover', [ProgramController::class, 'discover']);
    // Create a program from an uploaded file (export is client-side — see
    // App\Services\ProgramFile).
    Route::post('/programs/import', [ProgramController::class, 'import']);
    Route::get('/programs/by-day/{day}', [ProgramController::class, 'getByDay']);
    Route::post('/programs/{program}/clone', [ProgramController::class, 'clone']);
    // Copy one of your OWN programs (clone is for other people's public ones).
    Route::post('/programs/{program}/duplicate', [ProgramController::class, 'duplicate']);
    Route::apiResource('programs', ProgramController::class);
    Route::get('/user/settings', [UserSettingController::class, 'show']);
    Route::put('/user/settings', [UserSettingController::class, 'update']);
    Route::get('/progress/stats', [ProgressController::class, 'stats']);
    Route::get('/progress/exercises/{exercise}/e1rm', [ProgressController::class, 'exerciseSeries']);
    // Body-weight tracking (one entry per day; store upserts on the date).
    Route::apiResource('body-metrics', BodyMetricController::class)->only(['index', 'store', 'destroy']);
    Route::apiResource('workout-logs', WorkoutLogController::class);
});
