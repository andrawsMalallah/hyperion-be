<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProgramRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'days' => 'sometimes|array|max:14',
            'days.*.day_name' => 'required|string|max:255',
            'days.*.display_order' => 'integer|min:0',
            'days.*.exercises' => 'sometimes|array|max:30',
            'days.*.exercises.*.exercise_id' => 'required|exists:exercises,id',
            'days.*.exercises.*.target_sets' => 'nullable|integer|min:1|max:20',
            'days.*.exercises.*.rep_range_min' => 'nullable|integer|min:1|max:100',
            'days.*.exercises.*.rep_range_max' => 'nullable|integer|min:1|max:100|gte:days.*.exercises.*.rep_range_min',
            'days.*.exercises.*.target_rpe' => 'nullable|integer|min:1|max:10',
            'days.*.exercises.*.rest_seconds' => 'nullable|integer|min:0|max:600',
            'days.*.exercises.*.notes' => 'nullable|string|max:500',
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
