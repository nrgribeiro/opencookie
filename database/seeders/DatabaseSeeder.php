<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Roles + tiers first so users can be assigned them below.
        $this->call([
            RolesSeeder::class,
            TiersSeeder::class,
        ]);

        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => Str::random(10),
            ]
        );

        $this->seedSuperAdmin();

        $this->call([
            CmpSeeder::class,
        ]);
    }

    /**
     * Create + promote the bootstrap super admin (US-ADMIN-1). Credentials
     * come from SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD, with a local
     * fallback. Idempotent.
     */
    private function seedSuperAdmin(): void
    {
        $email = (string) env('SUPER_ADMIN_EMAIL', 'admin@opencookie.test');
        $password = (string) env('SUPER_ADMIN_PASSWORD', 'password');

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'email_verified_at' => now(),
                'password' => Hash::make($password),
                'remember_token' => Str::random(10),
            ],
        );

        $admin->assignRole(Role::SuperAdmin->value);
    }
}
