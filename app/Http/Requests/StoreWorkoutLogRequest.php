<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkoutLogRequest extends FormRequest
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
            'program_day_id' => 'nullable|exists:program_days,id',
            'date_timestamp' => 'required|date',
            'sets' => 'sometimes|array',
            'sets.*.exercise_id' => 'required|exists:exercises,id',
            'sets.*.weight' => 'required|numeric|min:1',
            'sets.*.reps' => 'required|integer|min:1',
            'sets.*.rpe' => 'nullable|integer|min:1',
            'sets.*.set_order' => 'required|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'date_timestamp.required' => 'The workout date is required.',
            'sets.*.exercise_id.required' => 'The exercise ID is required.',
            'sets.*.exercise_id.exists' => 'The selected exercise does not exist.',
            'sets.*.weight.required' => 'The weight field is required.',
            'sets.*.weight.numeric' => 'The weight must be a number.',
            'sets.*.weight.min' => 'The weight must be at least 1.',
            'sets.*.reps.required' => 'The reps field is required.',
            'sets.*.reps.integer' => 'The reps must be an integer.',
            'sets.*.reps.min' => 'The reps must be at least 1.',
            'sets.*.rpe.min' => 'The RPE must be at least 1.',
        ];
    }
}
