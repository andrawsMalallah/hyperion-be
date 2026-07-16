<?php

namespace App\Services;

use Illuminate\Validation\Validator;

/**
 * The exercise "type" carried on the day_exercise pivot, and the rules that
 * govern it.
 *
 * A type is exclusive — an exercise is at most one of these. Two of them are
 * tags describing a single exercise (drop set, pyramid set); the other two join
 * several exercises of a day into one group via a shared group_key.
 *
 * The count rules span a whole day's exercises, so they can't be expressed as
 * plain rule strings. validateDays() is shared by StoreProgramRequest (payload
 * under `days`) and ImportProgramRequest (under `program.days`) so the two can't
 * drift apart.
 */
class ExerciseGrouping
{
    /** Weight is dropped and reps continue with no rest. Tag only. */
    public const DROP_SET = 'drop_set';

    /** Load climbs (or falls) across the sets. Tag only. */
    public const PYRAMID_SET = 'pyramid_set';

    /** Exactly 2 exercises performed back-to-back, rest after the pair. */
    public const SUPERSET = 'superset';

    /** 3 or more exercises performed back-to-back, rest after the group. */
    public const GIANT_SET = 'giant_set';

    /** Types describing one exercise on its own — these never carry a group_key. */
    public const TAG_TYPES = [self::DROP_SET, self::PYRAMID_SET];

    /** Types joining several exercises — these always carry a group_key. */
    public const GROUP_TYPES = [self::SUPERSET, self::GIANT_SET];

    /**
     * Exact member count a group type requires. A null max means "no upper
     * bound beyond the day's own exercise limit".
     *
     * @var array<string, array{min: int, max: int|null}>
     */
    public const GROUP_SIZES = [
        self::SUPERSET => ['min' => 2, 'max' => 2],
        self::GIANT_SET => ['min' => 3, 'max' => null],
    ];

    /**
     * Every accepted group_type, for an `in:` rule.
     *
     * @return list<string>
     */
    public static function allTypes(): array
    {
        return [...self::TAG_TYPES, ...self::GROUP_TYPES];
    }

    /**
     * Check each day's grouping and attach human-readable errors to the
     * offending exercise's group_type field.
     *
     * @param  array<int, array{exercises?: array<int, array<string, mixed>>}>  $days
     * @param  string  $pathPrefix  where `days` sits in the payload — "days" or "program.days"
     */
    public static function validateDays(Validator $validator, array $days, string $pathPrefix): void
    {
        foreach ($days as $dayIndex => $day) {
            self::validateDay($validator, $day['exercises'] ?? [], "{$pathPrefix}.{$dayIndex}.exercises");
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $exercises
     */
    private static function validateDay(Validator $validator, array $exercises, string $dayPath): void
    {
        // Members of each group, keyed by group_key, so sizes can be counted
        // once the whole day has been walked.
        $groups = [];

        foreach ($exercises as $index => $exercise) {
            $type = $exercise['group_type'] ?? null;
            $key = $exercise['group_key'] ?? null;

            if ($type === null) {
                // An ungrouped exercise has no business carrying a key — it
                // would silently widen someone else's group.
                if ($key !== null) {
                    $validator->errors()->add(
                        "{$dayPath}.{$index}.group_key",
                        'An exercise can only belong to a group when it has a superset or giant set type.'
                    );
                }

                continue;
            }

            if (in_array($type, self::TAG_TYPES, true) && $key !== null) {
                $validator->errors()->add(
                    "{$dayPath}.{$index}.group_key",
                    'A drop set or pyramid set applies to a single exercise and cannot be part of a group.'
                );

                continue;
            }

            if (in_array($type, self::GROUP_TYPES, true)) {
                if ($key === null) {
                    $validator->errors()->add(
                        "{$dayPath}.{$index}.group_key",
                        'A superset or giant set must be joined with the other exercises in its group.'
                    );

                    continue;
                }

                $groups[$key][] = ['index' => $index, 'type' => $type];
            }
        }

        foreach ($groups as $members) {
            self::validateGroup($validator, $members, $dayPath);
        }
    }

    /**
     * @param  array<int, array{index: int, type: string}>  $members
     */
    private static function validateGroup(Validator $validator, array $members, string $dayPath): void
    {
        $firstIndex = $members[0]['index'];
        $types = array_unique(array_column($members, 'type'));

        // A key with both a superset and a giant set in it has no single
        // correct size to check against — reject rather than guess.
        if (count($types) > 1) {
            $validator->errors()->add(
                "{$dayPath}.{$firstIndex}.group_type",
                'The exercises in one group must all have the same type.'
            );

            return;
        }

        $type = $types[0];
        $size = count($members);
        $bounds = self::GROUP_SIZES[$type];

        if ($size < $bounds['min']) {
            $validator->errors()->add(
                "{$dayPath}.{$firstIndex}.group_type",
                $type === self::SUPERSET
                    ? 'A superset must join exactly 2 exercises.'
                    : 'A giant set must join at least 3 exercises.'
            );

            return;
        }

        if ($bounds['max'] !== null && $size > $bounds['max']) {
            $validator->errors()->add(
                "{$dayPath}.{$firstIndex}.group_type",
                'A superset must join exactly 2 exercises. Use a giant set for 3 or more.'
            );
        }
    }
}
