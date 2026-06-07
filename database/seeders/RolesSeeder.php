<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Seed the platform roles (US-ADMIN-1). Account owners are role-less;
     * only super admins carry a role.
     */
    public function run(): void
    {
        Role::findOrCreate(RoleEnum::SuperAdmin->value, 'web');
    }
}
