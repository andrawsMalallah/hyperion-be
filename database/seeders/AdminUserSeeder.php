<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the admin account used to review contributed exercises
     * (name "Hyperion Admin", email support.hyperion@gmail.com — matches the
     * account created in production).
     *
     * Uses firstOrNew so running this where the account already exists (e.g.
     * production, where it was created manually) never overwrites its password
     * or name — it only ensures the account exists and is a verified admin. On a
     * fresh install the password comes from ADMIN_PASSWORD (default "password"
     * for local dev — change it in real environments).
     */
    public function run(): void
    {
        $admin = User::firstOrNew(['email' => 'support.hyperion@gmail.com']);

        // Only set the name/password when creating the account, so re-running
        // this never clobbers an existing admin's credentials.
        if (! $admin->exists) {
            $admin->name = 'Hyperion Admin';
            $admin->password = Hash::make(env('ADMIN_PASSWORD', 'password'));
        }

        // email_verified_at is not mass-assignable, so set it directly; only
        // when not already verified.
        if (! $admin->hasVerifiedEmail()) {
            $admin->email_verified_at = now();
        }

        $admin->is_admin = true;
        $admin->save();
    }
}
