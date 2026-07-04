<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'is_public' => 'boolean',
            'days' => 'sometimes|array|max:14',
            'days.*.id' => [
                'nullable',
                'integer',
                Rule::exists('program_days', 'id')->where('program_id', $this->route('program')?->id),
            ],
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
            'name.required' => 'The program name is required.',
            'days.*.id.exists' => 'The selected day does not belong to this program.',
            'days.*.day_name.required' => 'The day name is required.',
            'days.*.exercises.*.exercise_id.required' => 'Each selected exercise is required.',
            'days.*.exercises.*.exercise_id.exists' => 'The selected exercise does not exist.',
            'days.*.exercises.*.target_sets.max' => 'Target sets must be between 1 and 20.',
            'days.*.exercises.*.target_sets.min' => 'Target sets must be between 1 and 20.',
            'days.*.exercises.*.rep_range_min.min' => 'Reps must be at least 1.',
            'days.*.exercises.*.rep_range_max.gte' => 'Maximum reps must be greater than or equal to minimum reps.',
            'days.*.exercises.*.target_rpe.max' => 'Target RPE must be between 1 and 10.',
            'days.*.exercises.*.target_rpe.min' => 'Target RPE must be between 1 and 10.',
            'days.*.exercises.*.rest_seconds.max' => 'Rest time must be between 0 and 600 seconds.',
        ];
    }

    /**
     * Human-readable field names so nested errors don't surface raw paths
     * like "days.0.exercises.0.target_rpe".
     */
    public function attributes(): array
    {
        return [
            'name' => 'program name',
            'days.*.day_name' => 'day name',
            'days.*.exercises.*.exercise_id' => 'exercise',
            'days.*.exercises.*.target_sets' => 'target sets',
            'days.*.exercises.*.rep_range_min' => 'minimum reps',
            'days.*.exercises.*.rep_range_max' => 'maximum reps',
            'days.*.exercises.*.target_rpe' => 'target RPE',
            'days.*.exercises.*.rest_seconds' => 'rest time',
            'days.*.exercises.*.notes' => 'notes',
        ];
    }
}
