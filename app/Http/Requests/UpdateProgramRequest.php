<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProgramRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'is_active' => 'boolean',
            'days' => 'sometimes|array',
            'days.*.id' => 'nullable|integer|exists:Program_days,id',
            'days.*.day_name' => 'required|string|max:255',
            'days.*.display_order' => 'integer',
            'days.*.exercises' => 'sometimes|array',
            'days.*.exercises.*.exercise_id' => 'required|exists:exercises,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The Program name is required.',
            'days.*.day_name.required' => 'The day name is required.',
            'days.*.exercises.*.exercise_id.required' => 'Each selected exercise is required.',
            'days.*.exercises.*.exercise_id.exists' => 'The selected exercise does not exist.',
        ];
    }
}
