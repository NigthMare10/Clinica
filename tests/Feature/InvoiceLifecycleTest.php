<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\MedicalDocumentStatus;
use App\Enums\MedicalDocumentType;
use App\Enums\TaxCategory;
use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\User;
use App\Services\Fiscal\InvoiceDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_draft_can_be_updated_with_recalculated_items_and_service_details(): void
    {
        [$user, $clinic] = $this->userAndClinic();
        $invoice = $this->draft($user, $clinic);

        $this->actingAs($user)->putJson(route('admin.invoices.update', $invoice), $this->updateData($clinic, [
            'recipient_name' => 'Edited recipient',
            'service_date' => '2026-08-15',
            'service_time' => '15:30',
            'paid_total' => 50,
            'items' => [$this->item('Revised service', 150)],
        ]))->assertOk()->assertJsonPath('items.0.description', 'Revised service');

        $invoice = $invoice->fresh();
        $this->assertSame('Edited recipient', $invoice->recipient_name);
        $this->assertSame('2026-08-15', $invoice->service_date?->toDateString());
        $this->assertSame('15:30', substr((string) $invoice->getRawOriginal('service_time'), 0, 5));
        $this->assertSame(150.0, (float) $invoice->total);
        $this->assertSame(100.0, (float) $invoice->balance);
        $this->assertSame('Revised service', $invoice->items()->sole()->description);
        $this->assertDatabaseHas('invoice_audits', ['invoice_id' => $invoice->id, 'action' => 'UPDATED']);
    }

    public function test_an_update_cannot_clear_a_linked_medical_document_or_override_its_snapshot(): void
    {
        [$user, $clinic] = $this->userAndClinic();
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
        $invoice = app(InvoiceDraftService::class)->create([
            'clinic_id' => $clinic->id,
            'medical_document_id' => $document->id,
            'payment_method' => 'EFECTIVO',
            'items' => [$this->item('Original service', 100)],
        ], $user);

        $this->actingAs($user)->putJson(route('admin.invoices.update', $invoice), $this->updateData($clinic, [
            'medical_document_id' => null,
            'items' => [$this->item('Edited service', 120)],
        ]))->assertOk();

        $invoice = $invoice->fresh();
        $this->assertSame($document->id, $invoice->medical_document_id);
        $this->assertSame($patient->id, $invoice->patient_id);
        $this->assertSame('Ana Paciente', $invoice->recipient_name);
        $this->assertSame('2026-08-13', $invoice->service_date?->toDateString());
        $this->assertSame('09:30', substr((string) $invoice->getRawOriginal('service_time'), 0, 5));
    }

    public function test_an_issued_invoice_cannot_be_directly_updated(): void
    {
        [$user, $clinic] = $this->userAndClinic();
        $invoice = $this->draft($user, $clinic);
        $invoice->forceFill(['status' => InvoiceStatus::ISSUED, 'ncf' => 'OLD-001'])->save();

        $this->actingAs($user)->putJson(route('admin.invoices.update', $invoice), $this->updateData($clinic))
            ->assertForbidden();
    }

    public function test_correction_voids_an_issued_invoice_and_creates_an_audited_linked_draft_without_its_ncf(): void
    {
        [$user, $clinic] = $this->userAndClinic();
        $invoice = $this->draft($user, $clinic, ['recipient_name' => 'Snapshot recipient', 'service_date' => '2026-08-13']);
        $invoice->forceFill(['status' => InvoiceStatus::ISSUED, 'ncf' => 'OLD-002'])->save();

        $response = $this->actingAs($user)->postJson(route('admin.invoices.corrections.store', $invoice), [
            'reason' => 'Incorrect service amount',
        ])->assertOk()->assertJsonPath('invoice.status', InvoiceStatus::VOID->value)
            ->assertJsonPath('replacement.status', InvoiceStatus::DRAFT->value)
            ->assertJsonPath('replacement.replacement_for_invoice_id', $invoice->id);

        $replacement = Invoice::findOrFail($response->json('replacement.id'));
        $this->assertNull($replacement->ncf);
        $this->assertSame($invoice->id, $replacement->replacement_for_invoice_id);
        $this->assertSame('Snapshot recipient', $replacement->recipient_name);
        $this->assertSame('2026-08-13', $replacement->service_date?->toDateString());
        $this->assertSame('Original service', $replacement->items()->sole()->description);
        $this->assertSame(100.0, (float) $replacement->total);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => InvoiceStatus::VOID->value, 'void_reason' => 'Incorrect service amount']);
        $this->assertDatabaseHas('invoice_audits', ['invoice_id' => $invoice->id, 'action' => 'VOIDED']);
        $this->assertDatabaseHas('invoice_audits', ['invoice_id' => $invoice->id, 'action' => 'CORRECTION_CREATED']);
        $this->assertDatabaseHas('invoice_audits', ['invoice_id' => $replacement->id, 'action' => 'CORRECTION_DRAFT_CREATED']);
    }

    private function userAndClinic(): array
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $clinic = Clinic::create(['code' => 'LIFECYCLE', 'slug' => 'invoice-lifecycle', 'name' => 'Invoice Lifecycle', 'department' => 'Test']);
        $user->clinics()->attach($clinic, ['role' => UserRole::ADMINISTRATOR->value, 'is_active' => true]);

        return [$user, $clinic];
    }

    private function draft(User $user, Clinic $clinic, array $overrides = []): Invoice
    {
        return app(InvoiceDraftService::class)->create(array_replace([
            'clinic_id' => $clinic->id,
            'recipient_name' => 'Original recipient',
            'payment_method' => 'EFECTIVO',
            'items' => [$this->item('Original service', 100)],
        ], $overrides), $user);
    }

    private function updateData(Clinic $clinic, array $overrides = []): array
    {
        return array_replace([
            'clinic_id' => $clinic->id,
            'payment_method' => 'EFECTIVO',
            'items' => [$this->item('Updated service', 100)],
        ], $overrides);
    }

    private function item(string $description, int $unitPrice): array
    {
        return [
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'discount' => 0,
            'tax_category' => TaxCategory::EXENTO->value,
        ];
    }
}
