<?php

namespace App\Services\MedicalDocuments;

use App\Models\DocumentAuditLog;
use App\Models\MedicalDocument;
use App\Models\User;

class MedicalDocumentAuditService
{
    public function record(MedicalDocument $document, string $action, ?User $user = null, ?string $field = null, mixed $old = null, mixed $new = null, array $metadata = []): DocumentAuditLog
    {
        $request = app()->bound('request') ? request() : null;

        return DocumentAuditLog::create([
            'medical_document_id' => $document->id, 'user_id' => $user?->id, 'action' => $action,
            'field' => $field, 'old_value' => $old, 'new_value' => $new,
            'ip_address' => $request?->ip(), 'user_agent' => $request?->userAgent(), 'metadata' => $metadata,
        ]);
    }
}
