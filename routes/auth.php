<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\ResetPasswordController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.reset');
});

// The verification link is authenticated by its signature (+ the {hash} check),
// so it needs no Bearer token — the email may open in a fresh browser. Named
// verification.verify to match VerifyEmail::createUrlUsing in AppServiceProvider.
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:auth'])
    ->name('verification.verify');

// These stay OUTSIDE the "verified" gate below — an unverified user must be able
// to check who they are and request a fresh link.
Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:auth')
        ->name('verification.send');
});

// Profile edits require a verified email like the rest of the app.
Route::middleware(['auth:api', 'verified'])->group(function () {
    Route::put('/user/profile', [ProfileController::class, 'update']);
    Route::put('/user/password', [ProfileController::class, 'updatePassword']);
    Route::get('/user/sessions', [ProfileController::class, 'sessions']);
    Route::delete('/user', [ProfileController::class, 'destroy']);
});
