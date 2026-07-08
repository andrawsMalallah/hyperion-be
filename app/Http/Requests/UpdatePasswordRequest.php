<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
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
            // The guard must be explicit: API requests authenticate via Passport.
            'current_password' => ['required', 'string', 'current_password:api'],
            'password' => ['required', 'string', 'max:72', Password::defaults(), 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'The current password is required.',
            'current_password.current_password' => 'The current password is incorrect.',
            'password.required' => 'The new password field is required.',
            'password.min' => 'The new password must be at least 8 characters long.',
            'password.confirmed' => 'The new password confirmation does not match.',
        ];
    }

    /**
     * Human-readable field names in place of the raw snake_case keys.
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'current password',
            'password' => 'new password',
        ];
    }
}
