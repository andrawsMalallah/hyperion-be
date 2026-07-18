<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesMeasuredSets;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkoutLogRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->measuredSetRules() + [
            'client_uuid' => 'nullable|uuid',
            // Scope the day to the authenticated user: a program_day_id must
            // belong to a program they own. Without the ownership constraint a
            // user could attach a workout to someone else's private day and the
            // response would leak that program back (IDOR).
            'program_day_id' => [
                'nullable',
                Rule::exists('program_days', 'id')->where(function ($query) {
                    $query->whereIn(
                        'program_id',
                        $this->user()->programs()->select('id')
                    );
                }),
            ],
            'date_timestamp' => 'required|date',
            'ended_at' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'sets' => 'sometimes|array|max:200',
            'sets.*.exercise_id' => 'required|exists:exercises,id',
            'sets.*.rpe' => 'nullable|integer|min:1|max:10',
            'sets.*.set_type' => 'nullable|string|in:warmup,working',
            'sets.*.set_order' => 'required|integer|min:0',
        ];
    }

    /**
     * A set's required fields depend on its exercise's measurement_type, which
     * only the database knows — so the check runs as an after hook (like
     * ExerciseGrouping::validateDays), regardless of which other rules passed.
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateMeasuredSets($validator)];
    }

    public function messages(): array
    {
        return $this->measuredSetMessages() + [
            'date_timestamp.required' => 'The workout date is required.',
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
            'date_timestamp' => 'workout date',
            'ended_at' => 'end time',
            'program_day_id' => 'program day',
            'client_uuid' => 'sync id',
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
