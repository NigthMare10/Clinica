<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MedicalDocument;
use App\Models\User;

class MedicalDocumentPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasAnyRole(UserRole::SUPER_ADMIN) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(UserRole::ADMINISTRATOR, UserRole::DOCUMENT_OPERATOR, UserRole::AUDITOR, UserRole::DOCTOR);
    }

    public function view(User $user, MedicalDocument $document): bool
    {
        return $user->hasClinicAccess($document->clinic_id)
            && ($user->hasAnyRole(UserRole::ADMINISTRATOR, UserRole::DOCUMENT_OPERATOR, UserRole::AUDITOR)
                || ($user->role === UserRole::DOCTOR && $user->doctor?->id === $document->doctor_id));
    }

    public function create(User $user): bool
    {
        return $user->role->canManageDocuments();
    }

    public function update(User $user, MedicalDocument $document): bool
    {
        return $user->hasClinicAccess($document->clinic_id) && $user->hasAnyRole(UserRole::ADMINISTRATOR, UserRole::DOCUMENT_OPERATOR);
    }

    public function issue(User $user, MedicalDocument $document): bool
    {
        $authorizedDoctor = $user->role === UserRole::DOCTOR
            && $user->doctor?->credential_number === config('institution.provider.credential_number');

        return $user->hasClinicAccess($document->clinic_id)
            && ($user->hasAnyRole(UserRole::ADMINISTRATOR) || $authorizedDoctor);
    }

    public function revoke(User $user, MedicalDocument $document): bool
    {
        return $user->hasClinicAccess($document->clinic_id) && $user->hasAnyRole(UserRole::ADMINISTRATOR);
    }

    public function correct(User $user, MedicalDocument $document): bool
    {
        return $user->hasClinicAccess($document->clinic_id) && $user->hasAnyRole(UserRole::ADMINISTRATOR);
    }
}
