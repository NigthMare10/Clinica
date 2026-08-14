<?php

namespace App\Services\Fiscal;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceAudit;
use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvoiceMedicalDocumentSnapshotService
{
    /**
     * Replace client-supplied linked-document fields with the document's canonical values.
     */
    public function apply(array $data): array
    {
        if (empty($data['medical_document_id'])) {
            return $data;
        }

        $document = MedicalDocument::query()
            ->with(['patient', 'doctor'])
            ->findOrFail($data['medical_document_id']);
        if (($data['clinic_id'] ?? null) !== $document->clinic_id) {
            throw new \DomainException('The medical document does not belong to the invoice clinic.');
        }

        $snapshot = $this->snapshot($document);
        foreach ($snapshot as $field => $value) {
            if (array_key_exists($field, $data) && $data[$field] !== null && ! $this->matches($field, $data[$field], $value)) {
                throw new \DomainException("The {$field} does not match the linked medical document.");
            }
        }

        return array_replace($data, $snapshot);
    }

    public function synchronizeDrafts(MedicalDocument $document, User $user, array $auditContext = []): int
    {
        return DB::transaction(function () use ($document, $user, $auditContext): int {
            $document = MedicalDocument::query()
                ->with(['patient', 'doctor'])
                ->lockForUpdate()
                ->findOrFail($document->id);
            $snapshot = $this->snapshot($document);
            $linkedDocumentIds = array_values(array_filter([$document->id, $document->reissue_of_id]));
            $count = 0;

            Invoice::query()
                ->whereIn('medical_document_id', $linkedDocumentIds)
                ->where('status', InvoiceStatus::DRAFT)
                ->lockForUpdate()
                ->each(function (Invoice $invoice) use ($snapshot, $document, $user, $auditContext, $linkedDocumentIds, &$count): void {
                    $invoice->forceFill($snapshot);
                    if ($document->reissue_of_id && $invoice->medical_document_id === $document->reissue_of_id) {
                        $invoice->medical_document_id = $document->id;
                    }
                    $changed = collect(array_keys($snapshot))->filter(fn (string $field) => $invoice->isDirty($field))->values()->all();
                    if ($invoice->isDirty('medical_document_id')) {
                        $changed[] = 'medical_document_id';
                    }
                    if ($changed === []) {
                        return;
                    }

                    $invoice->save();
                    if ($document->reissue_of_id) {
                        $invoice->items()->where('medical_document_id', $document->reissue_of_id)
                            ->update(['medical_document_id' => $document->id]);
                    }
                    InvoiceAudit::create([
                        'invoice_id' => $invoice->id,
                        'user_id' => $user->id,
                        'action' => 'MEDICAL_DOCUMENT_SNAPSHOT_SYNCED',
                        'payload' => ['medical_document_id' => $document->id, 'linked_document_ids' => $linkedDocumentIds, 'fields' => $changed],
                        'ip_address' => $auditContext['ip_address'] ?? request()?->ip(),
                        'user_agent' => $auditContext['user_agent'] ?? request()?->userAgent(),
                    ]);
                    $count++;
                });

            return $count;
        });
    }

    private function snapshot(MedicalDocument $document): array
    {
        $patient = $document->patient;
        $doctor = $document->doctor;
        $professional = $doctor?->professional_name ?: trim((string) $doctor?->first_name.' '.(string) $doctor?->last_name);

        return [
            'patient_id' => $document->patient_id,
            'recipient_name' => $patient ? trim($patient->first_name.' '.$patient->last_name) : null,
            'recipient_tax_id' => $patient?->document_number,
            'service_date' => $document->consultation_date?->toDateString(),
            'service_time' => $this->time($document->consultation_time),
            'medical_document_code' => $document->public_code,
            'medical_document_type' => $document->certificate_kind ?: $document->type?->value,
            'service_professional' => $professional ?: null,
        ];
    }

    private function matches(string $field, mixed $provided, mixed $expected): bool
    {
        if ($provided === null || $expected === null) {
            return $provided === $expected;
        }

        if ($field === 'service_time') {
            return $this->time((string) $provided) === $expected;
        }

        return (string) $provided === (string) $expected;
    }

    private function time(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 5);
    }
}
