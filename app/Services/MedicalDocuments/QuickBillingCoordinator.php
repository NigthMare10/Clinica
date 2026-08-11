<?php

namespace App\Services\MedicalDocuments;

use App\Enums\MedicalDocumentStatus;
use App\Models\BillingProfile;
use App\Models\Invoice;
use App\Models\MedicalDocument;
use App\Models\User;
use App\Services\Fiscal\InvoiceDraftService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class QuickBillingCoordinator
{
    public function __construct(
        private InvoiceDraftService $drafts,
        private MedicalDocumentIssueService $medicalIssuer,
        private MedicalDocumentAuditService $audit,
    ) {}

    /** @return array{document: MedicalDocument, invoice: Invoice} */
    public function issue(MedicalDocument $document, BillingProfile $profile, User $user): array
    {
        if ($profile->clinic_id !== $document->clinic_id || $profile->certificate_kind !== $document->certificate_kind || ! $profile->is_active || ! $profile->service->is_active) {
            throw new DomainException('The billing profile does not apply to this medical document.');
        }

        $medicalPath = null;
        $issuedDocument = null;

        try {
            return DB::transaction(function () use ($document, $profile, $user, &$medicalPath, &$issuedDocument): array {
                $document = MedicalDocument::query()->lockForUpdate()->findOrFail($document->id);
                $document->forceFill([
                    'inconsistencies' => [],
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                    'status' => MedicalDocumentStatus::READY,
                ])->save();
                $this->audit->record($document, 'approved', $user, metadata: ['explicit_fast_issue_confirmation' => true, 'quick_billing' => true]);

                // The medical document may be issued now; fiscal NCF issuance remains an explicit later action.
                $issuedDocument = $document;
                $document = $this->medicalIssuer->issue($document, $user);
                $issuedDocument = $document;
                $medicalPath = $document->getRawOriginal('issued_path') ?: $document->getAttribute('issued_path');

                $service = $profile->service;
                $invoice = $this->drafts->create([
                    'clinic_id' => $document->clinic_id,
                    'patient_id' => $document->patient_id,
                    'medical_document_id' => $document->id,
                    'recipient_name' => trim($document->patient->first_name.' '.$document->patient->last_name),
                    'recipient_tax_id' => $document->patient->document_number,
                    'payment_method' => $profile->default_payment_method,
                    'items' => [[
                        'service_code' => $service->code,
                        'description' => $service->name,
                        'quantity' => $profile->default_quantity,
                        'unit_price' => $profile->unitPrice(),
                        'discount' => 0,
                        'tax_category' => $profile->tax_category->value,
                        'medical_document_id' => $document->id,
                    ]],
                ], $user, ['ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent()]);

                return compact('document', 'invoice');
            });
        } catch (Throwable $exception) {
            $medicalPath ??= $issuedDocument?->getAttribute('issued_path');
            if (is_string($medicalPath)) {
                Storage::disk(config('medical_documents.disk'))->delete($medicalPath);
            }

            throw $exception;
        }
    }
}
