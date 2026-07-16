<?php

namespace App\Services;

/**
 * The shared constants describing the program export/import file format.
 *
 * The file itself is generated CLIENT-SIDE (frontend src/utils/programFile.js) —
 * there is no export endpoint — so these two values are mirrored there and the
 * pair must be changed together.
 *
 * Bump SCHEMA_VERSION whenever the file's shape changes; import rejects any
 * version not listed in SUPPORTED_SCHEMA_VERSIONS rather than guessing (see
 * ImportProgramRequest).
 */
class ProgramFile
{
    /** Marks a file as ours, so a stray .json fails with a clear message. */
    public const APP_MARKER = 'hyperion';

    /**
     * The version written by new exports.
     *
     * Version 1: { app, schema_version, exported_at, program: { name, days[] } }
     * Version 2: adds the optional group_type / group_key pair to each exercise.
     */
    public const SCHEMA_VERSION = 2;

    /**
     * Versions import can read. The grouping fields added in 2 are optional, so
     * a version 1 file is still valid — it just describes no groups. Drop a
     * version from this list only when its shape can no longer be honoured.
     *
     * @var list<int>
     */
    public const SUPPORTED_SCHEMA_VERSIONS = [1, 2];
}
