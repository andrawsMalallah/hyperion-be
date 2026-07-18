<?php

namespace App\Http\Requests\Concerns;

use App\Models\Exercise;
use App\Services\ExerciseMeasurement;
use Illuminate\Validation\Validator;

/**
 * Shared validation for a payload's `sets` array, where which fields are
 * required depends on each set's exercise measurement_type.
 *
 * Used by StoreWorkoutLogRequest and UpdateWorkoutLogRequest, which accept the
 * same set shape from two different callers (the live workout screen and the
 * history edit modal). They previously spelled these rules out twice — a new
 * set column would have silently applied to only one of them.
 */
trait ValidatesMeasuredSets
{
    /**
     * Bounds for the measurement-dependent fields. Presence is decided in the
     * after hook, since it needs the exercise rows.
     *
     * @return array<string, string>
     */
    protected function measuredSetRules(): array
    {
        return [
            'sets.*.weight' => 'nullable|numeric|min:0|max:1500',
            'sets.*.reps' => 'nullable|integer|min:1|max:100',
            'sets.*.duration_seconds' => 'nullable|integer|min:1|max:'.ExerciseMeasurement::MAX_DURATION_SECONDS,
        ];
    }

    /**
     * Look the submitted exercises' measurement types up once, then check every
     * set against its own type.
     */
    protected function validateMeasuredSets(Validator $validator): void
    {
        $sets = $this->input('sets', []);

        if (! is_array($sets) || $sets === []) {
            return;
        }

        $types = Exercise::whereIn('id', collect($sets)->pluck('exercise_id')->filter()->unique())
            ->pluck('measurement_type', 'id')
            ->all();

        ExerciseMeasurement::validateSets($validator, $sets, $types);
    }

    /**
     * Human-readable messages for the bounds above.
     *
     * @return array<string, string>
     */
    protected function measuredSetMessages(): array
    {
        return [
            'sets.*.weight.numeric' => 'The weight must be a number.',
            'sets.*.weight.min' => 'The weight cannot be negative.',
            'sets.*.weight.max' => 'The weight cannot be greater than 1500.',
            'sets.*.reps.integer' => 'The reps must be an integer.',
            'sets.*.reps.min' => 'The reps must be at least 1.',
            'sets.*.reps.max' => 'The reps cannot be greater than 100.',
            'sets.*.duration_seconds.integer' => 'The duration must be a whole number of seconds.',
            'sets.*.duration_seconds.min' => 'The duration must be at least 1 second.',
            'sets.*.duration_seconds.max' => 'The duration cannot be longer than an hour.',
        ];
    }
}
