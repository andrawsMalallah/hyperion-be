<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserSettingRequest extends FormRequest
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
            'timer_enabled' => 'sometimes|boolean',
            'rest_notifications' => 'sometimes|boolean',
            'default_rest_time' => 'sometimes|integer|min:0|max:600',
            'weight_unit' => 'sometimes|string|in:kg,lbs',
        ];
    }

    public function messages(): array
    {
        return [
            'default_rest_time.max' => 'The default rest time must be between 0 and 600 seconds.',
            'default_rest_time.min' => 'The default rest time must be between 0 and 600 seconds.',
            'weight_unit.in' => 'The weight unit must be either kg or lbs.',
        ];
    }

    /**
     * Human-readable field names in place of the raw snake_case keys.
     */
    public function attributes(): array
    {
        return [
            'timer_enabled' => 'rest timer',
            'rest_notifications' => 'rest timer alerts',
            'default_rest_time' => 'default rest time',
            'weight_unit' => 'weight unit',
        ];
    }
}
