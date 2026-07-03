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
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The Program name is required.',
            'days.*.id.exists' => 'The selected day does not belong to this program.',
            'days.*.day_name.required' => 'The day name is required.',
            'days.*.exercises.*.exercise_id.required' => 'Each selected exercise is required.',
            'days.*.exercises.*.exercise_id.exists' => 'The selected exercise does not exist.',
        ];
    }
}
