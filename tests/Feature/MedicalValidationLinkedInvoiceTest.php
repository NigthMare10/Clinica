<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\MedicalDocumentStatus;
use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\MedicalDocument;
use App\Models\User;
use App\Services\MedicalDocuments\MedicalDocumentVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalValidationLinkedInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_medical_validation_shows_the_linked_current_invoice_only(): void
    {
        [$document, $user, $clinic] = $this->document();
        $voided = $this->issuedInvoice($document, $user, $clinic, 'B0100000001');
        $voided->forceFill(['status' => InvoiceStatus::VOID])->save();
        $current = $this->issuedInvoice($document, $user, $clinic, 'B0100000002', $voided->id);

        $result = app(MedicalDocumentVerificationService::class)->byCode($document->public_code);

        $this->assertSame($current->ncf, $result['document']['invoice']['ncf']);
        $this->assertSame('ISSUED', $result['document']['invoice']['status']);
    }

    public function test_medical_validation_without_an_issued_invoice_omits_invoice_data(): void
    {
        [$document] = $this->document();

        $result = app(MedicalDocumentVerificationService::class)->byCode($document->public_code);

        $this->assertArrayNotHasKey('invoice', $result['document']);
    }

    private function document(): array
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $clinic = Clinic::create(['code' => 'LINKED', 'slug' => 'linked-test', 'name' => 'Linked clinic', 'department' => 'Test']);
        $document = MedicalDocument::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => MedicalDocumentStatus::ISSUED,
            'public_code' => 'CSA-2026-LINKED'.strtoupper(fake()->lexify('????')),
            'issued_at' => now(),
            'is_current_revision' => true,
        ]);

        return [$document, $user, $clinic];
    }

    private function issuedInvoice(MedicalDocument $document, User $user, Clinic $clinic, string $ncf, ?string $replacementFor = null): Invoice
    {
        $invoice = Invoice::create(['clinic_id' => $clinic->id, 'medical_document_id' => $document->id, 'replacement_for_invoice_id' => $replacementFor, 'created_by' => $user->id, 'medical_document_code' => $document->public_code]);
        $invoice->forceFill(['status' => InvoiceStatus::ISSUED, 'ncf' => $ncf, 'issued_at' => now(), 'total' => 100])->save();

        return $invoice;
    }
}
