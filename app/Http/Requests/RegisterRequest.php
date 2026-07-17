<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    /**
     * The honeypot field. The register form renders it hidden from people
     * (off-screen, aria-hidden, tabindex="-1"), so anything that fills it is
     * automation. Public Discover makes spam accounts worth creating, and the
     * 5/min/IP throttle alone doesn't stop a slow, distributed signup bot.
     *
     * Named for something a bot finds plausible but no browser autofills — an
     * autofilled honeypot would lock a real person out of registering.
     */
    private const HONEYPOT_FIELD = 'website';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', 'max:72', Password::defaults(), 'confirmed'],
        ];
    }

    /**
     * Trap check. Runs as an after() hook — like ExerciseGrouping::validateDays
     * on the program requests — so it fires regardless of the other rules.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! filled($this->input(self::HONEYPOT_FIELD))) {
                    return;
                }

                // Recorded so "is there actual abuse?" is answerable with
                // evidence — that's the condition for escalating to a CAPTCHA.
                // No submitted values are logged: the point is the rate, and the
                // body of a spam signup is not worth keeping.
                Log::info('Registration honeypot tripped', [
                    'ip' => $this->ip(),
                    'user_agent' => substr((string) $this->userAgent(), 0, 255),
                ]);

                // Deliberately NOT a validation error: that would put the field
                // name in the response's `errors` object and tell a bot exactly
                // which input to leave alone next time. A bare message also
                // renders correctly — the SPA's interceptor falls back to
                // `data.message` when `data.errors` is absent.
                throw new HttpResponseException(response()->json([
                    'message' => 'Registration could not be completed.',
                ], 422));
            },
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'email.required' => 'The email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address has already been registered.',
            'password.required' => 'The password field is required.',
            'password.min' => 'The password must be at least 8 characters long.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }
}
