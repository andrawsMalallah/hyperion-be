<?php

namespace App\Services;

use Illuminate\Validation\Validator;

/**
 * How an exercise's sets are measured, and the rules that follow from it.
 *
 * The type lives on the exercise (a plank is timed no matter which program it
 * appears in), so a logged set's required fields depend on a column the request
 * payload doesn't carry. That's why validateSets() is an after-hook helper
 * rather than a set of rule strings — it needs the exercises looked up first.
 *
 * Mirrored on the frontend by src/utils/measurement.js: THE TWO MUST CHANGE
 * TOGETHER, same as ExerciseGrouping/grouping.js and ProgramFile/programFile.js.
 */
class ExerciseMeasurement
{
    /** External load, logged as weight x reps. The default and the majority. */
    public const WEIGHTED = 'weighted';

    /**
     * Body weight moves the load, logged as reps. Any weight logged is *added*
     * to body weight (a belt-loaded pull-up), so 0 is the normal case rather
     * than missing data.
     */
    public const BODYWEIGHT = 'bodyweight';

    /** Held for time rather than repeated — planks, hangs, loaded carries. */
    public const TIMED = 'timed';

    /** Longest single set we'll accept, in seconds. */
    public const MAX_DURATION_SECONDS = 3600;

    /**
     * Every accepted measurement_type, for an `in:` rule.
     *
     * @return list<string>
     */
    public static function allTypes(): array
    {
        return [self::WEIGHTED, self::BODYWEIGHT, self::TIMED];
    }

    /**
     * Whether a set of this type counts toward weight-based training math
     * (tonnage volume and estimated 1RM).
     *
     * Only 'weighted' does. This is the load-bearing rule of ROADMAP 1.9: a
     * +20kg pull-up stores weight = 20, so filtering on `weight > 0` alone
     * would fold it into e1RM as though it were a 20kg lift. The stats queries
     * must therefore filter on the EXERCISE's type, not the set's weight.
     */
    public static function countsTowardTonnage(string $type): bool
    {
        return $type === self::WEIGHTED;
    }

    /** Rep-based types log reps; timed sets log a duration instead. */
    public static function usesReps(string $type): bool
    {
        return $type !== self::TIMED;
    }

    /**
     * Whether weight is the load itself (required) rather than optional extra
     * resistance on top of body weight.
     */
    public static function requiresWeight(string $type): bool
    {
        return $type === self::WEIGHTED;
    }

    /**
     * Check each logged set against its exercise's measurement type and attach
     * human-readable errors to the offending field.
     *
     * Field-level bounds (numeric, min, max) are already covered by the
     * request's own rules; this only decides which fields must be present and
     * which must be absent.
     *
     * @param  array<int, array<string, mixed>>  $sets
     * @param  array<int, string>  $typeByExerciseId  exercise id => measurement_type
     */
    public static function validateSets(Validator $validator, array $sets, array $typeByExerciseId): void
    {
        foreach ($sets as $index => $set) {
            $exerciseId = $set['exercise_id'] ?? null;

            // An unknown exercise is already an `exists` failure — don't pile a
            // confusing second error on top of it.
            if ($exerciseId === null || ! isset($typeByExerciseId[(int) $exerciseId])) {
                continue;
            }

            self::validateSet($validator, $set, $typeByExerciseId[(int) $exerciseId], "sets.{$index}");
        }
    }

    /**
     * @param  array<string, mixed>  $set
     */
    private static function validateSet(Validator $validator, array $set, string $type, string $path): void
    {
        $hasReps = isset($set['reps']) && $set['reps'] !== '';
        $hasDuration = isset($set['duration_seconds']) && $set['duration_seconds'] !== '';
        $hasWeight = isset($set['weight']) && $set['weight'] !== '';

        if (self::usesReps($type)) {
            if (! $hasReps) {
                $validator->errors()->add("{$path}.reps", 'The reps field is required.');
            }

            if ($hasDuration) {
                $validator->errors()->add(
                    "{$path}.duration_seconds",
                    'This exercise is logged in reps, not time.'
                );
            }
        } else {
            if (! $hasDuration) {
                $validator->errors()->add("{$path}.duration_seconds", 'The duration field is required.');
            }

            if ($hasReps) {
                $validator->errors()->add("{$path}.reps", 'This exercise is logged in time, not reps.');
            }
        }

        // Bodyweight and timed sets may carry added weight, but never need to.
        if (self::requiresWeight($type) && ! $hasWeight) {
            $validator->errors()->add("{$path}.weight", 'The weight field is required.');
        }
    }
}
