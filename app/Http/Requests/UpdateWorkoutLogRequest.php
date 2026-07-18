<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesMeasuredSets;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateWorkoutLogRequest extends FormRequest
{
    use ValidatesMeasuredSets;

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
     * Both `notes` and `sets` are `sometimes`, so a single endpoint serves two
     * callers without clobbering: a notes-only patch (from the post-save
     * summary modal) omits `sets` and leaves them untouched, while the edit
     * modal sends the full `sets` array to replace them.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->measuredSetRules() + [
            'notes' => 'sometimes|nullable|string|max:1000',
            'sets' => 'sometimes|array|min:1|max:200',
            'sets.*.exercise_id' => 'required|exists:exercises,id',
            'sets.*.rpe' => 'nullable|integer|min:1|max:10',
            'sets.*.set_type' => 'nullable|string|in:warmup,working',
            'sets.*.set_order' => 'required|integer|min:0',
        ];
    }

    /** @see StoreWorkoutLogRequest::after() — same per-measurement-type check. */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateMeasuredSets($validator)];
    }

    public function messages(): array
    {
        return $this->measuredSetMessages() + [
            'sets.min' => 'A workout must keep at least one set — delete the workout instead.',
            'sets.*.exercise_id.required' => 'The exercise ID is required.',
            'sets.*.exercise_id.exists' => 'The selected exercise does not exist.',
            'sets.*.rpe.min' => 'The RPE must be at least 1.',
            'sets.*.rpe.max' => 'The RPE cannot be greater than 10.',
            'sets.*.set_type.in' => 'The set type must be either warm-up or working.',
            'sets.*.set_order.min' => 'The set order cannot be negative.',
        ];
    }

    /**
     * Human-readable field names so nested errors don't surface raw paths
     * like "sets.0.set_order".
     */
    public function attributes(): array
    {
        return [
            'sets.*.exercise_id' => 'exercise',
            'sets.*.weight' => 'weight',
            'sets.*.reps' => 'reps',
            'sets.*.duration_seconds' => 'duration',
            'sets.*.rpe' => 'RPE',
            'sets.*.set_type' => 'set type',
            'sets.*.set_order' => 'set order',
        ];
    }
}
