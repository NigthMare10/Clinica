<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\MedicalDocumentStatus;
use App\Enums\TaxCategory;
use App\Enums\UserRole;
use App\Models\BillingProfile;
use App\Models\BillingService;
use App\Models\Clinic;
use App\Models\FiscalAuthorization;
use App\Models\Invoice;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\User;
use App\Services\Fiscal\InvoiceDraftService;
use App\Services\Fiscal\InvoiceIssueService;
use App\Services\MedicalDocuments\MedicalDocumentAuditService;
use App\Services\MedicalDocuments\MedicalDocumentIssueService;
use App\Services\MedicalDocuments\QuickBillingCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class QuickBillingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_billing_issues_invoice_before_medical_document_and_links_both_downloads(): void
    {
        Storage::fake('local');
        config(['invoice_pdf.disk' => 'local', 'medical_documents.disk' => 'local']);
        [$user, $document, $profile] = $this->fixture();
        $order = [];

        $invoiceIssuer = Mockery::mock(InvoiceIssueService::class);
        $invoiceIssuer->shouldReceive('issue')->once()->andReturnUsing(function (Invoice $invoice) use (&$order): array {
            $order[] = 'invoice';
            $path = 'fiscal/invoices/'.$invoice->id.'.pdf';
            Storage::disk('local')->put($path, 'invoice');
            $invoice->forceFill(['status' => InvoiceStatus::ISSUED, 'ncf' => 'B010001', 'issued_path' => $path, 'issued_at' => now()])->save();

            return ['invoice' => $invoice, 'qr_token' => 'token'];
        });
        $medicalIssuer = Mockery::mock(MedicalDocumentIssueService::class);
        $medicalIssuer->shouldReceive('issue')->once()->andReturnUsing(function (MedicalDocument $medical) use (&$order): MedicalDocument {
            $order[] = 'medical';
            $path = 'medical/issued/'.$medical->id.'.pdf';
            Storage::disk('local')->put($path, 'medical');
            $medical->forceFill(['status' => MedicalDocumentStatus::ISSUED, 'issued_path' => $path, 'issued_at' => now()])->save();

            return $medical;
        });

        $result = $this->coordinator($invoiceIssuer, $medicalIssuer)->issue($document, $profile, $user);

        $this->assertSame(['invoice', 'medical'], $order);
        $this->assertSame($result['document']->id, $result['invoice']->medical_document_id);
        $this->assertSame(InvoiceStatus::ISSUED, $result['invoice']->status);
        $this->assertSame(MedicalDocumentStatus::ISSUED, $result['document']->status);
        Storage::disk('local')->assertExists($result['invoice']->getRawOriginal('issued_path'));
        Storage::disk('local')->assertExists($result['document']->getRawOriginal('issued_path'));

        $this->actingAs($user)->get(route('admin.documents.review', $result['document']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('relatedInvoices.0.id', $result['invoice']->id)
                ->where('relatedInvoices.0.download_url', route('admin.invoices.download', $result['invoice']))
                ->where('hasIssuedInvoice', true));
    }

    public function test_outer_failure_rolls_back_ncf_and_database_state_and_removes_both_artifacts(): void
    {
        Storage::fake('local');
        config(['invoice_pdf.disk' => 'local', 'medical_documents.disk' => 'local']);
        [$user, $document, $profile, $clinic] = $this->fixture();
        $authorization = FiscalAuthorization::create([
            'clinic_id' => $clinic->id, 'cai' => 'CAI', 'rtn' => 'RTN', 'establishment' => '001', 'point_of_issue' => '001',
            'document_type' => 'FACTURA_CONTADO', 'ncf_prefix' => 'B01', 'range_start' => 1, 'range_end' => 20,
            'next_number' => 7, 'number_padding' => 4, 'valid_from' => today(), 'valid_until' => today()->addDay(), 'is_active' => true,
        ]);
        $invoicePath = null;
        $medicalPath = 'medical/issued/failing.pdf';

        $invoiceIssuer = Mockery::mock(InvoiceIssueService::class);
        $invoiceIssuer->shouldReceive('issue')->once()->andReturnUsing(function (Invoice $invoice) use ($authorization, &$invoicePath): array {
            $authorization->increment('next_number');
            $invoicePath = 'fiscal/invoices/'.$invoice->id.'.pdf';
            Storage::disk('local')->put($invoicePath, 'invoice');
            $invoice->forceFill(['status' => InvoiceStatus::ISSUED, 'ncf' => 'B010007', 'issued_path' => $invoicePath, 'issued_at' => now()])->save();

            return ['invoice' => $invoice, 'qr_token' => 'token'];
        });
        $medicalIssuer = Mockery::mock(MedicalDocumentIssueService::class);
        $medicalIssuer->shouldReceive('issue')->once()->andReturnUsing(function (MedicalDocument $medical) use ($medicalPath): never {
            Storage::disk('local')->put($medicalPath, 'medical');
            $medical->forceFill(['issued_path' => $medicalPath]);
            throw new RuntimeException('medical failure');
        });

        try {
            $this->coordinator($invoiceIssuer, $medicalIssuer)->issue($document, $profile, $user);
            $this->fail('The coordinated operation must fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('medical failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseHas('fiscal_authorizations', ['id' => $authorization->id, 'next_number' => 7]);
        $this->assertDatabaseHas('medical_documents', ['id' => $document->id, 'status' => MedicalDocumentStatus::REVIEW_REQUIRED->value]);
        Storage::disk('local')->assertMissing($invoicePath);
        Storage::disk('local')->assertMissing($medicalPath);
    }

    public function test_generate_page_exposes_only_the_compact_active_profile_payload(): void
    {
        [$user, , $profile] = $this->fixture();

        $this->actingAs($user)->get(route('admin.documents.generate', 'constancia'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Documents/Generate')
                ->where('quickBilling.service', $profile->service->name)
                ->where('quickBilling.quantity', '1.000')
                ->where('quickBilling.unit_price', '425.00')
                ->where('quickBilling.tax_category', TaxCategory::EXENTO->value)
                ->where('quickBilling.payment_method', 'TARJETA')
                ->missing('quickBilling.billing_service_id'));
    }

    private function coordinator(InvoiceIssueService $invoiceIssuer, MedicalDocumentIssueService $medicalIssuer): QuickBillingCoordinator
    {
        return new QuickBillingCoordinator(
            app(InvoiceDraftService::class),
            $invoiceIssuer,
            $medicalIssuer,
            app(MedicalDocumentAuditService::class),
        );
    }

    private function fixture(): array
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $clinic = Clinic::create(['code' => 'QB', 'slug' => 'qb', 'name' => 'Quick Billing', 'department' => 'QA', 'status' => 'ACTIVE']);
        $patient = Patient::factory()->create(['first_name' => 'Ana', 'last_name' => 'Prueba', 'document_number' => '0801199912345']);
        $service = BillingService::create(['code' => 'CERT', 'name' => 'Certificado médico', 'default_price' => 350, 'tax_type' => TaxCategory::EXENTO, 'is_active' => true]);
        $profile = BillingProfile::create([
            'clinic_id' => $clinic->id, 'certificate_kind' => 'CONSTANCIA', 'billing_service_id' => $service->id,
            'default_quantity' => 1, 'price_override' => 425, 'tax_category' => TaxCategory::EXENTO,
            'default_payment_method' => 'TARJETA', 'is_active' => true,
        ])->load('service');
        $document = MedicalDocument::factory()->create([
            'clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'certificate_kind' => 'CONSTANCIA',
            'status' => MedicalDocumentStatus::REVIEW_REQUIRED,
        ]);

        return [$user, $document, $profile, $clinic];
    }
}
