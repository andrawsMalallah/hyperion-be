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
        Schema::table('user_settings', function (Blueprint $table) {
            // Opt-in: fire a browser notification when the rest countdown ends
            // while the tab is backgrounded/the phone is locked. Off by default
            // (requires an explicit Settings toggle + notification permission).
            $table->boolean('rest_notifications')->default(false)->after('timer_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn('rest_notifications');
        });
    }
};
