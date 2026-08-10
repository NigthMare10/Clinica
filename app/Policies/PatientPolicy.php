<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasAnyRole(UserRole::SUPER_ADMIN) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(UserRole::ADMINISTRATOR, UserRole::DOCUMENT_OPERATOR);
    }

    public function view(User $user, Patient $patient): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        $clinicIds = $patient->clinics()->pluck('clinics.id')->all();

        return $clinicIds === [] || array_intersect($clinicIds, $user->accessibleClinicIds()) !== [];
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(UserRole::ADMINISTRATOR);
    }

    public function update(User $user, Patient $patient): bool
    {
        return $this->create($user) && $this->view($user, $patient);
    }
}
