<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Client-generated id for offline sync. A workout finished without a
        // connection is queued and uploaded later; the same payload may be
        // sent more than once if a response is lost, so this lets the server
        // dedupe replays. Nullable: older/online submissions carry no uuid,
        // and NULLs are exempt from the unique index in both Postgres and
        // SQLite, so they never collide.
        Schema::table('workout_logs', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->after('id');
            $table->unique(['user_id', 'client_uuid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workout_logs', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
