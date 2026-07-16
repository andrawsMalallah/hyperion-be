<?php

namespace App\Services;

/**
 * The shared constants describing the program export/import file format.
 *
 * The file itself is generated CLIENT-SIDE (frontend src/utils/programFile.js) —
 * there is no export endpoint — so these two values are mirrored there and the
 * pair must be changed together.
 *
 * Bump SCHEMA_VERSION whenever the file's shape changes incompatibly; import
 * rejects any version it doesn't know rather than guessing (see
 * ImportProgramRequest).
 */
class ProgramFile
{
    /** Marks a file as ours, so a stray .json fails with a clear message. */
    public const APP_MARKER = 'hyperion';

    /** Version 1: { app, schema_version, exported_at, program: { name, days[] } } */
    public const SCHEMA_VERSION = 1;
}
