<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Automatically create a personal access client if one doesn't exist
        // This prevents the "Personal access client not found" error when migrating fresh
        Artisan::call('passport:client', [
            '--personal' => true,
            '--name' => 'Hyperion Personal Access Client',
            '--no-interaction' => true,
        ]);

        $this->call(ExerciseSeeder::class);
    }
}
