<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
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
            // Deleting the account is irreversible, so re-confirm identity with
            // the current password — the same guard the password-change flow uses.
            // The 'api' guard is explicit: requests authenticate via Passport.
            'current_password' => ['required', 'string', 'current_password:api'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Your password is required to delete your account.',
            'current_password.current_password' => 'The password is incorrect.',
        ];
    }

    /**
     * Human-readable field names in place of the raw snake_case keys.
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'password',
        ];
    }
}
