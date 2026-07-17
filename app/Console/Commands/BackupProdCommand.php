<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Dumps every table of the PRODUCTION Neon database to timestamped NDJSON
 * (one JSON object per line) plus a manifest of row counts.
 *
 * Why this exists: Neon's free tier keeps only ~24h of point-in-time history,
 * so a week-old mistake is unrecoverable without this (ROADMAP 6.4). Intended
 * to be run locally, by hand, roughly weekly:
 *
 *   php artisan hyperion:backup-prod     → paste the connection string when asked
 *
 * Dumps land in the hyperion root's db_backups/<timestamp>/.
 *
 * Four safety properties, all deliberate:
 *  - The connection string is PROMPTED for and never persisted: not in this
 *    repo (it's public), not in .env, not in the shell history, not in the
 *    process environment. It lives in memory for the length of the run.
 *  - It reads the dedicated `neon_backup` connection and NEVER the default one.
 *    The local .env points at a SQLite snapshot; a backup that quietly dumped
 *    that would look like insurance while being a copy of a copy.
 *  - It sets the session read-only before touching anything, so the standing
 *    rule (production Neon is never written to) holds even if this code is
 *    wrong — the database itself refuses the write.
 *  - It defaults to writing OUTSIDE both git repos, because the dump contains
 *    real user data (emails, password hashes, live tokens) and must never be
 *    committable.
 *
 * The dump is verified after writing (see verifyDump): an unchecked backup is
 * a liability, since you'd only discover a truncated file at restore time.
 */
class BackupProdCommand extends Command
{
    protected $signature = 'hyperion:backup-prod
                            {--path= : Directory to write the dump into (default: the hyperion root db_backups/, outside both repos)}';

    protected $description = 'Back up the production Neon database to timestamped NDJSON (read-only; local use)';

    /** Long-running reads are fine; a hung one is not. */
    private const STATEMENT_TIMEOUT = '300s';

    public function handle(): int
    {
        $url = $this->askForConnectionString();

        if ($url === null) {
            return self::FAILURE;
        }

        try {
            // Set on the config template at runtime — Laravel parses the URL
            // into host/user/password itself. purge() drops any connection
            // opened earlier in this process so the new url is definitely used.
            config(['database.connections.neon_backup.url' => $url]);
            DB::purge('neon_backup');

            $connection = DB::connection('neon_backup');
            // Any accidental write now fails at the database, not at review.
            $connection->statement('SET default_transaction_read_only = on');
            $connection->statement("SET statement_timeout = '".self::STATEMENT_TIMEOUT."'");
        } catch (Throwable) {
            $this->components->error('Could not connect to Neon.');
            // The message can echo the DSN, password and all — never print it.
            $this->line('  <fg=gray>Check the connection string is complete and the password is current.</>');

            return self::FAILURE;
        }

        $directory = $this->makeDirectory();
        $this->components->info("Backing up {$connection->getDatabaseName()} → {$directory}");

        // Every public table, read from the catalog rather than hardcoded, so a
        // future migration can't silently drop a table out of the backup.
        $tables = $connection->table('pg_tables')
            ->where('schemaname', 'public')
            ->orderBy('tablename')
            ->pluck('tablename');

        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = $this->dumpTable($connection, $table, $directory);
            $this->line(sprintf('  %-30s %6d rows', $table, $counts[$table]));
        }

        $manifest = [
            'database' => $connection->getDatabaseName(),
            'host' => $connection->getConfig('host'),
            'dumped_at' => now()->toIso8601String(),
            'tables' => $counts,
        ];
        file_put_contents(
            $directory.'/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $this->verifyDump($directory, $counts);
    }

    /**
     * Stream one table to <table>.ndjson, a JSON object per line.
     *
     * cursor() keeps memory flat regardless of table size, and NDJSON means a
     * restore can read row-by-row instead of parsing one huge array.
     *
     * @return int rows written
     */
    private function dumpTable($connection, string $table, string $directory): int
    {
        $handle = fopen("{$directory}/{$table}.ndjson", 'w');
        $rows = 0;

        foreach ($connection->table($table)->cursor() as $row) {
            fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            $rows++;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Re-read every file and confirm it holds exactly the rows the manifest
     * claims, and that each line parses. Catches a truncated or corrupt dump
     * now — when it can simply be re-run — instead of at restore time.
     */
    private function verifyDump(string $directory, array $counts): int
    {
        $problems = [];

        foreach ($counts as $table => $expected) {
            $file = "{$directory}/{$table}.ndjson";
            $actual = 0;

            $handle = fopen($file, 'r');
            while (($line = fgets($handle)) !== false) {
                if (trim($line) === '') {
                    continue;
                }
                if (json_decode($line) === null && json_last_error() !== JSON_ERROR_NONE) {
                    $problems[] = "{$table}: line ".($actual + 1).' is not valid JSON';
                    break;
                }
                $actual++;
            }
            fclose($handle);

            if ($actual !== $expected) {
                $problems[] = "{$table}: expected {$expected} rows, file holds {$actual}";
            }
        }

        if ($problems !== []) {
            $this->newLine();
            $this->components->error('Backup verification FAILED — do not rely on this dump:');
            foreach ($problems as $problem) {
                $this->line('  • '.$problem);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info(sprintf(
            'Verified: %d tables, %d rows, every line valid JSON.',
            count($counts),
            array_sum($counts)
        ));
        $this->line('  <fg=gray>Contains real user data (emails, password hashes, tokens) — keep it off shared drives.</>');

        return self::SUCCESS;
    }

    /**
     * Prompt for the Neon connection string.
     *
     * secret() so it isn't echoed: the string carries the password, and a
     * visible one would linger in the terminal scrollback. Pasting into a blind
     * prompt is normal — nothing appears as you paste, which is expected.
     *
     * @return string|null null when the input is unusable (already reported)
     */
    private function askForConnectionString(): ?string
    {
        $this->line('  <fg=gray>Paste the Neon connection string (Neon dashboard, or Render → hyperion-api → Environment).</>');
        $this->line('  <fg=gray>It is not echoed, is never saved, and is discarded when this command exits.</>');
        $this->newLine();

        // Windows terminals happily paste a trailing newline/space.
        $url = trim((string) $this->secret('Connection string'));

        if ($url === '') {
            $this->components->error('Nothing pasted — aborted.');

            return null;
        }

        // Catch the obvious paste mistakes now, with a message that can say what
        // is wrong, rather than letting the driver fail with a vaguer one.
        if (! preg_match('#^postgres(ql)?://#i', $url)) {
            $this->components->error('That does not look like a connection string.');
            $this->line('  <fg=gray>Expected it to start with postgres:// or postgresql://</>');

            return null;
        }

        return $url;
    }

    /** Timestamped directory, defaulting outside both repos so it can't be committed. */
    private function makeDirectory(): string
    {
        $root = $this->option('path') ?: base_path('../db_backups');
        $directory = rtrim($root, '/\\').'/'.now()->format('Y-m-d_His');

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Could not create {$directory}");
        }

        return $directory;
    }
}
