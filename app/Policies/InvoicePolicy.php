<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasAnyRole(UserRole::SUPER_ADMIN) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(UserRole::ADMINISTRATOR, UserRole::DOCUMENT_OPERATOR, UserRole::AUDITOR);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasClinicAccess($invoice->clinic_id) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(UserRole::ADMINISTRATOR, UserRole::DOCUMENT_OPERATOR);
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $user->hasClinicAccess($invoice->clinic_id) && $user->hasAnyRole(UserRole::ADMINISTRATOR, UserRole::DOCUMENT_OPERATOR);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::DRAFT
            && $user->hasClinicAccess($invoice->clinic_id)
            && $user->hasAnyRole(UserRole::ADMINISTRATOR, UserRole::DOCUMENT_OPERATOR);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $user->hasClinicAccess($invoice->clinic_id) && $user->hasAnyRole(UserRole::ADMINISTRATOR);
    }

    public function correct(User $user, Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::ISSUED && $this->void($user, $invoice);
    }
}
