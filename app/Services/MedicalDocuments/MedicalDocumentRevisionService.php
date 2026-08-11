<?php

namespace App\Services\MedicalDocuments;

use App\Enums\MedicalDocumentStatus;
use App\Models\MedicalDocument;
use App\Models\MedicalDocumentRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MedicalDocumentRevisionService
{
    public function __construct(private MedicalDocumentAuditService $audit) {}

    public function create(MedicalDocument $document, string $reason, User $user): MedicalDocument
    {
        return DB::transaction(function () use ($document, $reason, $user) {
            $source = MedicalDocument::query()->lockForUpdate()->findOrFail($document->id);
            if (! in_array($source->status, [MedicalDocumentStatus::ISSUED, MedicalDocumentStatus::REVOKED], true)) {
                throw new RuntimeException('Only issued or revoked documents can be corrected.');
            }
            if (! $source->public_code) {
                throw new RuntimeException('A correction requires a public document code.');
            }
            if ($source->reissues()->whereIn('status', [MedicalDocumentStatus::REVIEW_REQUIRED, MedicalDocumentStatus::READY, MedicalDocumentStatus::ISSUED])->exists()) {
                throw new RuntimeException('The document already has a current correction.');
            }

            $revisionNumber = ((int) MedicalDocument::query()->where('public_code', $source->public_code)->max('revision_number')) + 1;
            $snapshot = $this->snapshot($source);
            $copy = $source->replicate(['status', 'token_hash', 'issued_path', 'issued_sha256', 'issued_by', 'issued_at',
                'revoked_by', 'revoked_at', 'revocation_reason', 'reviewed_by', 'reviewed_at', 'replaced_by_id']);
            $copy->forceFill([
                'id' => (string) Str::uuid(),
                'status' => MedicalDocumentStatus::REVIEW_REQUIRED,
                'public_code' => $source->public_code,
                'revision_number' => $revisionNumber,
                'is_current_revision' => false,
                'reissue_of_id' => $source->id,
                'uploaded_by' => $user->id,
                'inconsistencies' => [],
            ])->save();
            MedicalDocumentRevision::create(['medical_document_id' => $copy->id, 'source_document_id' => $source->id,
                'corrected_by' => $user->id, 'revision_number' => $revisionNumber, 'reason' => $reason,
                'source_snapshot' => $snapshot]);
            $this->audit->record($copy, 'correction_created', $user, metadata: ['source_id' => $source->id, 'reason' => $reason, 'revision' => $revisionNumber]);

            return $copy;
        });
    }

    private function snapshot(MedicalDocument $document): array
    {
        return collect($document->getAttributes())->except(['token_hash', 'original_path', 'issued_path'])->all();
    }
}
