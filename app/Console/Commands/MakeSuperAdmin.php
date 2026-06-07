<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role as SpatieRole;

class MakeSuperAdmin extends Command
{
    protected $signature = 'user:make-admin {email : Email of an existing user to promote}';

    protected $description = 'Grant the super_admin role to an existing user (US-ADMIN-4).';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        SpatieRole::findOrCreate(Role::SuperAdmin->value, 'web');

        if ($user->hasRole(Role::SuperAdmin->value)) {
            $this->info("{$email} is already a super admin.");

            return self::SUCCESS;
        }

        $user->assignRole(Role::SuperAdmin->value);
        $this->info("Granted super_admin to {$email}.");

        return self::SUCCESS;
    }
}
