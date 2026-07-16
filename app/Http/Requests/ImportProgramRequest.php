<?php

namespace App\Http\Requests;

use App\Services\ProgramFile;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an uploaded program file's envelope and shape. The client parses the
 * chosen .json file and POSTs its contents as the request body, so what arrives
 * here is arbitrary user-supplied JSON — every field is validated, and unknown
 * ones are simply ignored by validated().
 *
 * Exercises arrive by NAME, not id: an id from another instance means nothing
 * here. Resolving those names to catalog rows happens in ProgramImporter.
 */
class ImportProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Envelope — rejects files that aren't ours, and any future format
            // this version doesn't know how to read.
            'app' => 'required|string|in:'.ProgramFile::APP_MARKER,
            'schema_version' => 'required|integer|in:'.ProgramFile::SCHEMA_VERSION,

            'program' => 'required|array',
            'program.name' => 'required|string|max:255',
            'program.days' => 'sometimes|array|max:14',
            'program.days.*.day_name' => 'required|string|max:255',
            'program.days.*.display_order' => 'nullable|integer|min:0',
            'program.days.*.exercises' => 'sometimes|array|max:30',

            // The identifying fields used to resolve against the catalog.
            'program.days.*.exercises.*.name' => 'required|string|max:255',
            'program.days.*.exercises.*.target_muscle_group' => 'nullable|string|max:255',

            // Prescription — same bounds as StoreProgramRequest.
            'program.days.*.exercises.*.target_sets' => 'nullable|integer|min:1|max:20',
            'program.days.*.exercises.*.rep_range_min' => 'nullable|integer|min:1|max:100',
            'program.days.*.exercises.*.rep_range_max' => 'nullable|integer|min:1|max:100|gte:program.days.*.exercises.*.rep_range_min',
            'program.days.*.exercises.*.target_rpe' => 'nullable|integer|min:1|max:10',
            'program.days.*.exercises.*.rest_seconds' => 'nullable|integer|min:0|max:600',
            'program.days.*.exercises.*.notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'app.required' => 'This file is not a Hyperion program file.',
            'app.in' => 'This file is not a Hyperion program file.',
            'schema_version.required' => 'This file is not a Hyperion program file.',
            'schema_version.in' => 'This program file was made by a different version of Hyperion and cannot be imported.',
            'program.required' => 'This file does not contain a program.',
            'program.name.required' => 'The program in this file has no name.',
            'program.days.max' => 'A program cannot have more than 14 days.',
            'program.days.*.day_name.required' => 'A day in this file has no name.',
            'program.days.*.exercises.max' => 'A day cannot have more than 30 exercises.',
            'program.days.*.exercises.*.name.required' => 'An exercise in this file has no name.',
        ];
    }

    /**
     * Human-readable field names so nested errors don't surface raw paths like
     * "program.days.0.exercises.0.target_rpe".
     */
    public function attributes(): array
    {
        return [
            'program.name' => 'program name',
            'program.days.*.day_name' => 'day name',
            'program.days.*.exercises.*.name' => 'exercise name',
            'program.days.*.exercises.*.target_sets' => 'target sets',
            'program.days.*.exercises.*.rep_range_min' => 'minimum reps',
            'program.days.*.exercises.*.rep_range_max' => 'maximum reps',
            'program.days.*.exercises.*.target_rpe' => 'target RPE',
            'program.days.*.exercises.*.rest_seconds' => 'rest time',
            'program.days.*.exercises.*.notes' => 'notes',
        ];
    }
}
