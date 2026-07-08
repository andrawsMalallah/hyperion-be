<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function sendResetLinkEmail(ForgotPasswordRequest $request)
    {
        // Always respond identically so the endpoint can't be used to
        // discover which email addresses are registered.
        Password::sendResetLink($request->only('email'));

        return $this->messageResponse('If an account exists for that email, a password reset link has been sent.');
    }
}
