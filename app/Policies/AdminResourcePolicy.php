<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class AdminResourcePolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasAnyRole(UserRole::SUPER_ADMIN) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::ADMINISTRATOR;
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user): bool
    {
        return $this->viewAny($user);
    }
}
