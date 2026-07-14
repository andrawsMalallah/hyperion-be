<?php

namespace App\Services;

use App\Models\Program;
use App\Models\ProgramDay;

/**
 * Reconciles a program's days (and their per-exercise prescriptions) with an
 * incoming days payload. Extracted so store() and update() on ProgramController
 * share one implementation — a prescription-field change now lives in exactly
 * one place (see PRESCRIPTION_KEYS) instead of being wired twice.
 */
class ProgramDaySync
{
    /**
     * Prescription columns carried on the day_exercise pivot. Keep this in sync
     * with the frontend PRESCRIPTION_KEYS (stores/program.js) when adding a
     * field.
     */
    private const PRESCRIPTION_KEYS = [
        'target_sets',
        'rep_range_min',
        'rep_range_max',
        'target_rpe',
        'rest_seconds',
        'notes',
    ];

    /**
     * Delete days no longer present in the payload, update the kept ones,
     * create the new ones, and sync each day's exercise prescriptions.
     *
     * @param  array  $daysData  the validated `days` array from the request
     */
    public function sync(Program $program, array $daysData): void
    {
        // Days whose id survives in the payload are kept; the rest are removed.
        // On a fresh program (store) there are none, so nothing is deleted.
        $keptDayIds = collect($daysData)->pluck('id')->filter()->all();
        $program->days()->whereNotIn('id', $keptDayIds)->delete();

        foreach ($daysData as $dayData) {
            $day = $this->upsertDay($program, $dayData);
            $day->exercises()->sync($this->buildExercisePivot($dayData['exercises'] ?? []));
        }
    }

    /**
     * Update an existing day (when the payload carries a real id belonging to
     * this program) or create a new one.
     */
    private function upsertDay(Program $program, array $dayData): ProgramDay
    {
        $attributes = [
            'day_name' => $dayData['day_name'],
            'display_order' => $dayData['display_order'] ?? 0,
        ];

        if (isset($dayData['id']) && $program->days()->whereKey($dayData['id'])->exists()) {
            $day = $program->days()->findOrFail($dayData['id']);
            $day->update($attributes);

            return $day;
        }

        return $program->days()->create($attributes);
    }

    /**
     * Build the `sync()` array for a day's exercises: keyed by exercise_id,
     * carrying display_order (from position) plus the prescription columns.
     */
    private function buildExercisePivot(array $exercises): array
    {
        $pivot = [];

        foreach ($exercises as $index => $exerciseData) {
            $attributes = ['display_order' => $index];
            foreach (self::PRESCRIPTION_KEYS as $key) {
                $attributes[$key] = $exerciseData[$key] ?? null;
            }
            $pivot[$exerciseData['exercise_id']] = $attributes;
        }

        return $pivot;
    }
}
