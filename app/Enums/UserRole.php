<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case ADMINISTRATOR = 'ADMINISTRATOR';
    case DOCTOR = 'DOCTOR';
    case DOCUMENT_OPERATOR = 'DOCUMENT_OPERATOR';
    case AUDITOR = 'AUDITOR';

    public function canManageDocuments(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::ADMINISTRATOR, self::DOCUMENT_OPERATOR], true);
    }
}
