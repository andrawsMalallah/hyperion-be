<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBodyMetricRequest extends FormRequest
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
            // Canonical kilograms (the client converts from the display unit
            // before sending, like set weights). The bounds are generous in kg:
            // well under the ~1 kg noise floor is nonsense, and no human tops
            // ~700 kg, so 1000 leaves headroom without allowing an lbs value
            // typed in by mistake to look plausible.
            'weight' => 'required|numeric|min:1|max:1000',
            // A body weight is a calendar day and never in the future.
            'measured_on' => 'required|date|before_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'weight.required' => 'The weight field is required.',
            'weight.numeric' => 'The weight must be a number.',
            'weight.min' => 'The weight looks too low — enter your body weight.',
            'weight.max' => 'The weight looks too high — enter your body weight.',
            'measured_on.required' => 'The date is required.',
            'measured_on.before_or_equal' => 'The date cannot be in the future.',
        ];
    }

    public function attributes(): array
    {
        return [
            'measured_on' => 'date',
        ];
    }
}
