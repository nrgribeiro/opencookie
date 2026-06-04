<?php

namespace App\Policies;

use App\Models\Cookie;
use App\Models\User;

class CookiePolicy
{
    public function update(User $user, Cookie $cookie): bool
    {
        return $cookie->domain->user_id === $user->id;
    }
}
