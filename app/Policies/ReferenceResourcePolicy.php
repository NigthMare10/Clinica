<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class ReferenceResourcePolicy extends AdminResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(UserRole::ADMINISTRATOR, UserRole::DOCUMENT_OPERATOR);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::ADMINISTRATOR;
    }

    public function update(User $user): bool
    {
        return $this->create($user);
    }
}
