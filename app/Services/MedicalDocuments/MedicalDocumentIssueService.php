<?php

namespace App\Services\MedicalDocuments;

use App\Enums\MedicalDocumentStatus;
use App\Models\DocumentVersion;
use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MedicalDocumentIssueService
{
    public function __construct(private DocumentHashService $hashes, private QrCodeService $qr,
        private PdfStampService $stamp, private InstitutionalSignatureStampService $institutionalMarks, private PdfEncryptionService $encryption,
        private PdfQrVerificationService $qrVerification, private MedicalDocumentConsistencyService $consistency,
        private MedicalDocumentAuditService $audit, private PdfDocumentInspectionService $inspection) {}

    public function issue(MedicalDocument $document, User $user): MedicalDocument
    {
        return DB::transaction(function () use ($document, $user) {
            $document = MedicalDocument::query()->lockForUpdate()->findOrFail($document->id);
            if ($document->status === MedicalDocumentStatus::ISSUED) {
                return $document;
            }
            if ($document->status !== MedicalDocumentStatus::READY || ! $document->reviewed_at || ! $document->reviewed_by) {
                throw new RuntimeException('Human review and READY status are required before issuance.');
            }
            if ($document->digital_signature_detected || $this->consistency->hasBlockers($document->inconsistencies ?? [])) {
                throw new RuntimeException('Blocking inconsistencies or a digital signature prevent issuance.');
            }
            $disk = Storage::disk(config('medical_documents.disk'));
            if (! is_string($document->original_path) || ! str_starts_with($document->original_path, 'medical/original/')) {
                throw new RuntimeException('Invalid original document path.');
            }
            $original = $disk->path($document->original_path);
            $this->inspection->assertOnePage($original);
            if (! $this->hashes->equals($original, $document->original_sha256)) {
                throw new RuntimeException('Original document integrity check failed.');
            }
            $token = '';
            $document->forceFill(['token_hash' => $this->uniqueTokenHash($token), 'public_code' => $document->public_code ?: $this->uniqueCode()])->save();
            $directory = storage_path('app/tmp/'.Str::uuid());
            if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new RuntimeException('Cannot create secure working directory.');
            }
            $qrPath = $directory.DIRECTORY_SEPARATOR.'qr.png';
            $marked = $directory.DIRECTORY_SEPARATOR.'institutional-marks.pdf';
            $stamped = $directory.DIRECTORY_SEPARATOR.'issued.pdf';
            $path = null;
            try {
                $verificationUrl = $this->qr->verificationUrl($token);
                $this->qr->write($verificationUrl, $qrPath);
                $assetHashes = $this->institutionalMarks->apply($document, $original, $marked);
                $qrPage = $this->stamp->stamp($document, $marked, $stamped, $qrPath);
                $this->inspection->assertOnePage($stamped);
                $this->qrVerification->assertReadable($stamped, $qrPage, $verificationUrl, $directory, $document);
                $source = $stamped;
                if (config('medical_documents.encryption_enabled')) {
                    $encrypted = $directory.DIRECTORY_SEPARATOR.'encrypted.pdf';
                    $this->encryption->encrypt($stamped, $encrypted);
                    $this->encryption->assertEncrypted($encrypted);
                    $decryptedCheck = $directory.DIRECTORY_SEPARATOR.'decrypted-check.pdf';
                    $this->encryption->decrypt($encrypted, $decryptedCheck);
                    $this->inspection->assertOnePage($decryptedCheck);
                    $this->qrVerification->assertReadable($decryptedCheck, $qrPage, $verificationUrl, $directory, $document);
                    $source = $encrypted;
                }
                $path = 'medical/issued/'.$document->id.'-'.Str::random(16).'.pdf';
                if (! $disk->put($path, file_get_contents($source))) {
                    throw new RuntimeException('Unable to store issued PDF.');
                }
                $issuedHash = $this->hashes->file($disk->path($path));
                if (! $this->hashes->equals($original, $document->original_sha256)) {
                    throw new RuntimeException('Original changed during issuance.');
                }
                if ($document->reissue_of_id) {
                    $source = MedicalDocument::query()->lockForUpdate()->findOrFail($document->reissue_of_id);
                    if ($source->replaced_by_id && $source->replaced_by_id !== $document->id) {
                        throw new RuntimeException('The source already has an active replacement.');
                    }
                    if (! in_array($source->status, [MedicalDocumentStatus::ISSUED, MedicalDocumentStatus::REVOKED], true)) {
                        throw new RuntimeException('The source cannot be replaced in its current state.');
                    }
                }
                $snapshot = $document->template_snapshot ?? [];
                $snapshot['security'] = [
                    'pdf_encrypted' => (bool) config('medical_documents.encryption_enabled'),
                    'qr_verified' => true,
                    'hash_algorithm' => 'SHA-256',
                ];
                if ($assetHashes !== []) {
                    $snapshot['institutional_marks'] = $assetHashes;
                }
                $document->forceFill(['issued_path' => $path, 'issued_sha256' => $issuedHash,
                    'issued_by' => $user->id, 'issued_at' => now(config('institution.timezone')), 'status' => MedicalDocumentStatus::ISSUED,
                    'template_snapshot' => $snapshot, 'is_current_revision' => true])->save();
                if ($document->reissue_of_id) {
                    $source->forceFill([
                        'status' => MedicalDocumentStatus::REPLACED,
                        'replaced_by_id' => $document->id,
                        'is_current_revision' => false,
                    ])->save();
                }
                DocumentVersion::create(['medical_document_id' => $document->id, 'created_by' => $user->id,
                    'version' => ((int) $document->versions()->max('version')) + 1, 'kind' => 'issued', 'path' => $path, 'sha256' => $issuedHash]);
                if ($document->revision) {
                    $document->revision->update(['current_snapshot' => $this->snapshot($document)]);
                }
                $this->audit->record($document, 'issued', $user, metadata: ['sha256' => $issuedHash]);
                foreach (array_keys($assetHashes) as $kind) {
                    $this->audit->record($document, strtoupper($kind).'_USED', $user, metadata: ['document_id' => $document->id, 'user_id' => $user->id, 'asset_sha256' => $assetHashes[$kind]]);
                }
            } catch (Throwable $exception) {
                if ($path) {
                    $disk->delete($path);
                }

                throw $exception;
            } finally {
                foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($directory);
            }

            return $document->fresh();
        });
    }

    private function uniqueTokenHash(string &$token): string
    {
        do {
            $token = $this->qr->token();
            $hash = $this->qr->tokenHash($token);
        } while (MedicalDocument::where('token_hash', $hash)->exists());

        return $hash;
    }

    private function uniqueCode(): string
    {
        do {
            $code = $this->qr->publicCode();
        } while (MedicalDocument::where('public_code', $code)->exists());

        return $code;
    }

    private function snapshot(MedicalDocument $document): array
    {
        return collect($document->getAttributes())->except(['token_hash', 'original_path', 'issued_path'])->all();
    }
}
