<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\MedicalDocumentStatus;
use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivatePdfPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_preview_an_issued_medical_document(): void
    {
        Storage::fake('local');
        $clinic = $this->clinic();
        $user = $this->clinicUser($clinic);
        $path = 'medical/issued/document.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 issued');
        $document = MedicalDocument::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => MedicalDocumentStatus::ISSUED,
            'issued_path' => $path,
        ]);

        $this->actingAs($user)->get(route('admin.documents.preview', $document))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=medical-document.pdf')
            ->assertHeader('Cache-Control', 'no-store, private, max-age=0')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertStreamedContent('%PDF-1.4 issued');
    }

    public function test_issued_medical_document_falls_back_to_its_original_pdf_when_no_issued_path_exists(): void
    {
        Storage::fake('local');
        $clinic = $this->clinic();
        $user = $this->clinicUser($clinic);
        $path = 'medical/original/document.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 original');
        $document = MedicalDocument::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => MedicalDocumentStatus::ISSUED,
            'original_path' => $path,
            'issued_path' => null,
        ]);

        $this->actingAs($user)->get(route('admin.documents.preview', $document))
            ->assertOk()
            ->assertStreamedContent('%PDF-1.4 original');
    }

    public function test_unauthorized_user_cannot_preview_private_pdfs(): void
    {
        Storage::fake('local');
        $clinic = $this->clinic();
        $user = $this->clinicUser($clinic);
        $outsider = User::factory()->create(['role' => UserRole::AUDITOR]);
        $documentPath = 'medical/issued/document.pdf';
        $invoicePath = 'fiscal/invoices/invoice.pdf';
        Storage::disk('local')->put($documentPath, '%PDF-1.4 document');
        Storage::disk('local')->put($invoicePath, '%PDF-1.4 invoice');
        $document = MedicalDocument::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => MedicalDocumentStatus::ISSUED,
            'issued_path' => $documentPath,
        ]);
        $invoice = Invoice::create([
            'clinic_id' => $clinic->id,
            'created_by' => $user->id,
            'recipient_name' => 'Preview Recipient',
        ]);
        $invoice->forceFill([
            'status' => InvoiceStatus::ISSUED,
            'issued_path' => $invoicePath,
        ])->save();

        $this->actingAs($outsider)->get(route('admin.documents.preview', $document))->assertForbidden();
        $this->actingAs($outsider)->get(route('admin.invoices.preview', $invoice))->assertForbidden();
    }

    public function test_preview_rejects_invalid_or_missing_storage_paths(): void
    {
        Storage::fake('local');
        $clinic = $this->clinic();
        $user = $this->clinicUser($clinic);
        $document = MedicalDocument::factory()->create([
            'clinic_id' => $clinic->id,
            'status' => MedicalDocumentStatus::ISSUED,
            'issued_path' => 'medical/original/not-an-issued-document.pdf',
        ]);
        $invoice = Invoice::create([
            'clinic_id' => $clinic->id,
            'created_by' => $user->id,
            'recipient_name' => 'Preview Recipient',
        ]);
        $invoice->forceFill([
            'status' => InvoiceStatus::ISSUED,
            'issued_path' => 'fiscal/invoices/missing.pdf',
        ])->save();

        $this->actingAs($user)->get(route('admin.documents.preview', $document))->assertNotFound();
        $this->actingAs($user)->get(route('admin.invoices.preview', $invoice))->assertNotFound();
    }

    public function test_authorized_user_can_preview_an_issued_invoice(): void
    {
        Storage::fake('local');
        $clinic = $this->clinic();
        $user = $this->clinicUser($clinic);
        $path = 'fiscal/invoices/invoice.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 invoice');
        $invoice = Invoice::create([
            'clinic_id' => $clinic->id,
            'created_by' => $user->id,
            'recipient_name' => 'Preview Recipient',
        ]);
        $invoice->forceFill([
            'status' => InvoiceStatus::ISSUED,
            'issued_path' => $path,
        ])->save();

        $this->actingAs($user)->get(route('admin.invoices.preview', $invoice))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=invoice.pdf')
            ->assertHeader('Cache-Control', 'no-store, private, max-age=0')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertStreamedContent('%PDF-1.4 invoice');
    }

    private function clinic(): Clinic
    {
        return Clinic::create([
            'code' => fake()->unique()->bothify('PRV-##'),
            'slug' => fake()->unique()->slug(),
            'name' => 'Preview Clinic',
            'department' => 'Test',
        ]);
    }

    private function clinicUser(Clinic $clinic): User
    {
        $user = User::factory()->create(['role' => UserRole::AUDITOR]);
        $user->clinics()->attach($clinic, ['role' => UserRole::AUDITOR->value, 'is_active' => true]);

        return $user;
    }
}
