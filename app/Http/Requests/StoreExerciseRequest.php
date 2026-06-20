<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreExerciseRequest extends FormRequest
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
            'name' => 'required|string|max:255|unique:exercises,name',
            'target_muscle_group' => 'required|string|max:255',
            'mechanics_type' => 'required|string|in:Compound,Isolation',
        ];
    }

    /**
     * Get custom human-readable error messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please provide a name for the exercise.',
            'name.max' => 'The exercise name cannot exceed 255 characters.',
            'name.unique' => 'An exercise with this name already exists.',
            'target_muscle_group.required' => 'Please select a target muscle group.',
            'mechanics_type.required' => 'Please select a mechanics type.',
            'mechanics_type.in' => 'Mechanics type must be either Compound or Isolation.',
        ];
    }
}
