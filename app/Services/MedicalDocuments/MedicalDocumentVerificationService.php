<?php

namespace App\Services\MedicalDocuments;

use App\Enums\MedicalDocumentStatus;
use App\Models\DocumentVerificationLog;
use App\Models\Invoice;
use App\Models\MedicalDocument;
use Throwable;

class MedicalDocumentVerificationService
{
    public function __construct(private DocumentHashService $hashes) {}

    public function byToken(string $token, ?string $identityLast4 = null, string $method = 'QR_LINK'): array
    {
        $document = MedicalDocument::query()->where('token_hash', hash('sha256', $token))->first();

        return $this->result($document, in_array($method, ['QR_LINK', 'QR_CAMERA'], true) ? $method : 'QR_LINK', $identityLast4);
    }

    public function byCode(string $code, ?string $identityLast4 = null, string $method = 'MANUAL_CODE'): array
    {
        $document = MedicalDocument::query()->where('public_code', strtoupper(trim($code)))->where('is_current_revision', true)->first();

        return $this->result($document, in_array($method, ['MANUAL_CODE', 'PDF_HASH'], true) ? $method : 'MANUAL_CODE', $identityLast4);
    }

    public function byFile(string $path, ?string $identityLast4 = null): array
    {
        $hash = $this->hashes->file($path);
        $document = MedicalDocument::query()->where('issued_sha256', $hash)->first();

        return $this->result($document, 'PDF_HASH', $identityLast4, $hash);
    }

    private function result(?MedicalDocument $document, string $method, ?string $last4, ?string $uploadedHash = null): array
    {
        $status = match ($document?->status) {
            MedicalDocumentStatus::REVOKED => 'REVOKED',
            MedicalDocumentStatus::ISSUED => 'VALID',
            MedicalDocumentStatus::REPLACED => 'REPLACED',
            null => 'NOT_FOUND',
            default => 'NOT_ISSUED',
        };
        $identityVerified = $this->identityMatches($document, $last4);
        $verifiedAt = now(config('institution.timezone'));

        try {
            $ip = request()?->ip();
            DocumentVerificationLog::create([
                'medical_document_id' => $document?->id,
                'method' => $method,
                'successful' => $status === 'VALID',
                'result' => $status,
                'verified_at' => $verifiedAt,
                'identity_verified' => $identityVerified,
                'ip_address' => $ip ? substr(hash_hmac('sha256', $ip, (string) config('app.key')), 0, 40) : null,
                'user_agent' => mb_substr((string) request()?->userAgent(), 0, 255),
                'uploaded_sha256' => $uploadedHash,
                'context' => ['timezone' => config('institution.timezone')],
            ]);
        } catch (Throwable) {
            // Public verification remains available if best-effort logging fails.
        }

        $data = [
            'status' => $status,
            'document' => $document && $status !== 'NOT_ISSUED' ? $this->publicData($document, $identityVerified, $verifiedAt, $method) : null,
        ];

        return $data;
    }

    private function publicData(MedicalDocument $document, bool $identityVerified, mixed $verifiedAt, string $method): array
    {
        $document->loadMissing(['patient', 'doctor', 'clinic', 'verificationLogs', 'replacedBy', 'reissueOf']);
        $patient = $document->patient;
        $patientName = (string) ($document->confirmed_fields['patient_name'] ?? trim((string) $patient?->first_name.' '.(string) $patient?->last_name));
        $patientDocument = (string) ($document->confirmed_fields['patient_document'] ?? $patient?->document_number);
        $identity = preg_replace('/\D+/', '', $patientDocument);
        $verificationHistory = $document->verificationLogs->sortBy(fn (DocumentVerificationLog $log) => $log->verified_at ?? $log->created_at)
            ->values()->map(fn (DocumentVerificationLog $log, int $index) => [
                'event' => $index === 0 ? 'VERIFIED' : 'REVALIDATED',
                'at' => ($log->verified_at ?? $log->created_at)?->toIso8601String(),
                'method' => $log->method,
                'result' => $log->result,
            ])->take(-10)->all();
        $history = collect([
            $document->revoked_at ? ['event' => 'REVOKED', 'at' => $document->revoked_at->toIso8601String(), 'method' => null] : null,
            $document->status === MedicalDocumentStatus::REPLACED ? ['event' => 'REPLACED', 'at' => $document->updated_at?->toIso8601String(), 'method' => null] : null,
            ...$verificationHistory,
        ])->filter()->sortBy('at')->values()->all();
        $institution = $document->template_snapshot['institution'] ?? config('institution');
        $provider = $institution['provider'] ?? config('institution.provider');
        $security = $document->template_snapshot['security'] ?? [];

        $data = [
            'code' => $document->public_code,
            'type' => $document->certificate_kind === 'INCAPACIDAD' ? 'Incapacidad Médica' : 'Constancia Médica',
            'status' => $document->status->value,
            'consultation_date' => $document->consultation_date?->toDateString(),
            'consultation_time' => $document->consultation_time,
            'patient' => $patient ? [
                'name' => $identityVerified ? $patientName : null,
                'identity' => $identityVerified ? $patientDocument : ($identity ? str_repeat('•', max(4, strlen($identity) - 2)).substr($identity, -2) : null),
                'age' => $document->age_at_consultation,
            ] : null,
            'diagnosis' => $identityVerified ? $document->diagnosis : null,
            'reason' => $identityVerified ? $document->medical_reason : null,
            'observations' => $identityVerified ? $document->recommendations : null,
            'provider' => $provider,
            'leave' => [
                'start' => $document->leave_start_date?->toDateString(),
                'end' => $document->leave_end_date?->toDateString(),
                'days' => $document->leave_days,
                'return_date' => $document->leave_end_date?->copy()->addDay()->toDateString(),
            ],
            'clinic' => [
                'name' => $institution['short_name'] ?? config('institution.short_name'),
                'address' => $institution['address'] ?? config('institution.address'),
                'phone' => $institution['phone'] ?? config('institution.phone'),
                'hours' => 'Atención 24/7',
            ],
            'verification' => [
                'method' => $method,
                'verified_at' => $verifiedAt->toIso8601String(),
                'hash' => $document->issued_sha256,
                'details_verified' => $identityVerified,
            ],
            'security' => [
                'pdf_encrypted' => (bool) ($security['pdf_encrypted'] ?? false),
                'qr_verified' => (bool) ($security['qr_verified'] ?? true),
                'institutional_registry' => true,
                'active_audit' => true,
            ],
            'history' => $history,
            'replacement_code' => $document->replacedBy?->public_code,
        ];
        $invoice = $this->currentInvoice($document);
        if ($invoice) {
            $data['invoice'] = [
                'ncf' => $invoice->ncf,
                'service_date' => $invoice->service_date?->toDateString(),
                'total' => number_format((float) $invoice->total, 2, '.', ''),
                'currency' => $invoice->currency,
                'status' => $invoice->status->value,
                'verification_code' => $invoice->medical_document_code ?: $document->public_code,
                'preview_url' => $identityVerified ? route('public.verify.invoice.preview', $invoice) : null,
                'download_url' => $identityVerified ? route('public.verify.invoice.download', $invoice) : null,
                'validation_url' => $identityVerified ? route('public.invoice.linked', $invoice) : null,
            ];
            $data['access_invoice_id'] = $invoice->id;
        }
        if ($identityVerified) {
            $data['preview_url'] = route('public.verify.document.preview', $document);
            $data['download_url'] = route('public.verify.document.download', $document);
        }
        $data['access_document_id'] = $document->id;

        return $data;
    }

    private function currentInvoice(MedicalDocument $document): ?Invoice
    {
        $sourceId = $document->reissueOf?->id;

        return Invoice::query()->currentIssued()
            ->whereIn('medical_document_id', array_filter([$document->id, $sourceId]))
            ->latest('issued_at')->first();
    }

    private function identityMatches(?MedicalDocument $document, ?string $last4): bool
    {
        $actual = preg_replace('/\D+/', '', (string) ($document?->confirmed_fields['patient_document'] ?? $document?->patient?->document_number));
        $candidate = preg_replace('/\D+/', '', (string) $last4);

        return strlen($candidate) === 4 && strlen($actual) >= 4 && hash_equals(substr($actual, -4), $candidate);
    }
}
