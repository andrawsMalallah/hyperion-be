<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Promote the project owner's account to admin (exercise-approval
     * dashboard). Idempotent: only flips the flag if the account exists, so it
     * is safe to run on any environment (local snapshot or production) once the
     * account has registered.
     */
    public function run(): void
    {
        User::where('email', 'andraws.malallah@gmail.com')
            ->update(['is_admin' => true]);
    }
}
