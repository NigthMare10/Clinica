<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\MedicalDocumentStatus;
use App\Enums\MedicalDocumentType;
use App\Enums\TaxCategory;
use App\Enums\UserRole;
use App\Models\BillingProfile;
use App\Models\BillingService;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\User;
use App\Services\Fiscal\InvoiceDraftService;
use App\Services\Fiscal\InvoiceMedicalDocumentSnapshotService;
use App\Services\MedicalDocuments\MedicalDocumentAuditService;
use App\Services\MedicalDocuments\MedicalDocumentIssueService;
use App\Services\MedicalDocuments\QuickBillingCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class InvoiceMedicalDocumentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_draft_inherits_document_snapshots_and_rejects_conflicting_input(): void
    {
        [$user, $document] = $this->document();
        $invoice = app(InvoiceDraftService::class)->create($this->draftData($document), $user);

        $this->assertSame($document->patient_id, $invoice->patient_id);
        $this->assertSame('Ana Paciente', $invoice->recipient_name);
        $this->assertSame('0801199912345', $invoice->recipient_tax_id);
        $this->assertSame('2026-08-13', $invoice->service_date?->toDateString());
        $this->assertSame('09:30', substr((string) $invoice->getRawOriginal('service_time'), 0, 5));
        $this->assertSame('MED-2026-0001', $invoice->medical_document_code);
        $this->assertSame('CONSTANCIA', $invoice->medical_document_type);
        $this->assertSame('Dra. Prueba', $invoice->service_professional);

        try {
            app(InvoiceDraftService::class)->create($this->draftData($document, ['recipient_name' => 'Nombre manipulado']), $user);
            $this->fail('Conflicting document snapshot data was accepted.');
        } catch (\DomainException $exception) {
            $this->assertSame('The recipient_name does not match the linked medical document.', $exception->getMessage());
        }
    }

    public function test_quick_billing_uses_the_current_configured_profile_price_and_document_snapshot(): void
    {
        [$user, $document, $clinic] = $this->document();
        $service = BillingService::create(['code' => 'CERT-QB', 'name' => 'Certificado', 'default_price' => 100, 'tax_type' => TaxCategory::EXENTO]);
        $profile = BillingProfile::create([
            'clinic_id' => $clinic->id,
            'certificate_kind' => 'CONSTANCIA',
            'billing_service_id' => $service->id,
            'default_quantity' => 1,
            'price_override' => 425,
            'tax_category' => TaxCategory::EXENTO,
            'default_payment_method' => 'TARJETA',
            'is_active' => true,
        ])->load('service');
        BillingProfile::query()->whereKey($profile->id)->update(['price_override' => 510]);

        $issuer = Mockery::mock(MedicalDocumentIssueService::class);
        $issuer->shouldReceive('issue')->once()->andReturnUsing(function (MedicalDocument $medical): MedicalDocument {
            $medical->forceFill(['status' => MedicalDocumentStatus::ISSUED, 'issued_at' => now()])->save();

            return $medical;
        });
        $coordinator = new QuickBillingCoordinator(app(InvoiceDraftService::class), $issuer, app(MedicalDocumentAuditService::class));

        $result = $coordinator->issue($document, $profile, $user);

        $this->assertEquals(510, $result['invoice']->items()->sole()->unit_price);
        $this->assertSame($document->patient_id, $result['invoice']->patient_id);
        $this->assertSame('MED-2026-0001', $result['invoice']->medical_document_code);
    }

    public function test_snapshot_synchronization_updates_only_draft_linked_invoices_and_audits_it(): void
    {
        [$user, $source] = $this->document();
        $draft = Invoice::create($this->snapshotInvoiceData($user, $source));
        $issued = Invoice::create($this->snapshotInvoiceData($user, $source));
        $issued->forceFill(['status' => InvoiceStatus::ISSUED])->save();
        $correction = MedicalDocument::factory()->create([
            'clinic_id' => $source->clinic_id,
            'patient_id' => $source->patient_id,
            'doctor_id' => $source->doctor_id,
            'certificate_kind' => $source->certificate_kind,
            'type' => $source->type,
            'status' => MedicalDocumentStatus::REVIEW_REQUIRED,
            'consultation_date' => '2026-08-14',
            'consultation_time' => '14:45:00',
            'public_code' => 'MED-2026-0002',
            'reissue_of_id' => $source->id,
        ]);

        $updated = app(InvoiceMedicalDocumentSnapshotService::class)->synchronizeDrafts($correction, $user);

        $this->assertSame(1, $updated);
        $this->assertSame('2026-08-14', $draft->fresh()->service_date?->toDateString());
        $this->assertSame('14:45', substr((string) $draft->fresh()->getRawOriginal('service_time'), 0, 5));
        $this->assertSame('MED-2026-0002', $draft->fresh()->medical_document_code);
        $this->assertSame('2026-08-13', $issued->fresh()->service_date?->toDateString());
        $this->assertDatabaseHas('invoice_audits', ['invoice_id' => $draft->id, 'action' => 'MEDICAL_DOCUMENT_SNAPSHOT_SYNCED']);
    }

    private function document(): array
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $clinic = Clinic::create(['code' => 'INV-SNAP', 'slug' => 'inv-snap', 'name' => 'Invoice Snapshot', 'department' => 'QA']);
        $patient = Patient::factory()->create(['first_name' => 'Ana', 'last_name' => 'Paciente', 'document_number' => '0801199912345']);
        $doctor = Doctor::factory()->create(['professional_name' => 'Dra. Prueba']);
        $document = MedicalDocument::factory()->create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'certificate_kind' => 'CONSTANCIA',
            'type' => MedicalDocumentType::MEDICAL_CERTIFICATE,
            'status' => MedicalDocumentStatus::REVIEW_REQUIRED,
            'consultation_date' => '2026-08-13',
            'consultation_time' => '09:30:00',
            'public_code' => 'MED-2026-0001',
        ]);

        return [$user, $document, $clinic];
    }

    private function draftData(MedicalDocument $document, array $overrides = []): array
    {
        return array_replace([
            'clinic_id' => $document->clinic_id,
            'medical_document_id' => $document->id,
            'payment_method' => 'EFECTIVO',
            'items' => [[
                'description' => 'Consulta',
                'quantity' => 1,
                'unit_price' => 100,
                'discount' => 0,
                'tax_category' => TaxCategory::EXENTO->value,
            ]],
        ], $overrides);
    }

    private function snapshotInvoiceData(User $user, MedicalDocument $document): array
    {
        return [
            'clinic_id' => $document->clinic_id,
            'patient_id' => $document->patient_id,
            'medical_document_id' => $document->id,
            'recipient_name' => 'Original recipient',
            'recipient_tax_id' => 'ORIGINAL',
            'service_date' => '2026-08-13',
            'service_time' => '09:30',
            'medical_document_code' => 'MED-2026-0001',
            'medical_document_type' => MedicalDocumentType::MEDICAL_CERTIFICATE->value,
            'service_professional' => 'Dra. Prueba',
            'created_by' => $user->id,
        ];
    }
}
