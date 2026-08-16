<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\TaxCategory;
use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\FiscalAuthorization;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Fiscal\InvoiceIssueService;
use App\Services\Fiscal\InvoicePdfInfoService;
use App\Services\Fiscal\InvoicePdfLayoutCalculator;
use App\Services\Fiscal\InvoicePdfService;
use App\Services\Fiscal\InvoiceQrCodeService;
use App\Services\MedicalDocuments\PdfEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InvoicePdfSinglePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config([
            'invoice_pdf.disk' => 'local',
            'invoice_pdf.encryption_enabled' => true,
            'medical_documents.disk' => 'local',
            'medical_documents.pdf_password' => 'Fiscal-Pdf-Test-Password!',
            'fiscal_reference.reference_invoice_import.central_clinic_code' => 'PDF',
        ]);
    }

    public function test_issue_creates_one_encrypted_private_pdf_with_exact_hash_and_authorized_download(): void
    {
        [$user, $clinic] = $this->userAndClinic();
        $authorization = FiscalAuthorization::create($this->authorizationData($clinic));
        $invoice = $this->invoice($user, $clinic, 3);

        $result = app(InvoiceIssueService::class)->issue($invoice, $user, $authorization->id);
        $issued = $result['invoice']->fresh();
        $path = $issued->getRawOriginal('issued_path');
        $absolutePath = Storage::disk('local')->path($path);

        $this->assertSame(InvoiceStatus::ISSUED, $issued->status);
        $this->assertStringStartsWith('fiscal/invoices/', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame(hash_file('sha256', $absolutePath), $issued->issued_hash);
        app(PdfEncryptionService::class)->assertEncrypted($absolutePath);

        $decrypted = tempnam(sys_get_temp_dir(), 'invoice-check-');
        try {
            app(PdfEncryptionService::class)->decrypt($absolutePath, $decrypted);
            app(InvoicePdfInfoService::class)->assertOnePage($decrypted);
        } finally {
            @unlink($decrypted);
        }

        $this->actingAs($user)->get(route('admin.invoices.download', $issued))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseHas('invoice_audits', ['invoice_id' => $issued->id, 'action' => 'PDF_DOWNLOADED']);

        $unauthorized = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $this->actingAs($unauthorized)->get(route('admin.invoices.download', $issued))->assertForbidden();
    }

    public function test_layout_guard_rejects_before_ncf_consumption(): void
    {
        [$user, $clinic] = $this->userAndClinic();
        $authorization = FiscalAuthorization::create($this->authorizationData($clinic));
        $invoice = $this->invoice($user, $clinic, config('invoice_pdf.max_items') + 1);

        $this->expectException(\DomainException::class);
        try {
            app(InvoiceIssueService::class)->issue($invoice, $user, $authorization->id);
        } finally {
            $this->assertSame(1, $authorization->fresh()->next_number);
            $this->assertSame(InvoiceStatus::DRAFT, $invoice->fresh()->status);
            Storage::disk('local')->assertDirectoryEmpty('fiscal/invoices');
        }
    }

    public function test_pdf_failure_rolls_back_the_invoice_and_ncf(): void
    {
        [$user, $clinic] = $this->userAndClinic();
        $authorization = FiscalAuthorization::create($this->authorizationData($clinic));
        $invoice = $this->invoice($user, $clinic, 1);
        $this->mock(InvoicePdfService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')->once()->andThrow(new RuntimeException('PDF failure'));
        });

        $this->expectException(RuntimeException::class);
        try {
            app(InvoiceIssueService::class)->issue($invoice, $user, $authorization->id);
        } finally {
            $this->assertSame(1, $authorization->fresh()->next_number);
            $this->assertSame(InvoiceStatus::DRAFT, $invoice->fresh()->status);
            $this->assertNull($invoice->fresh()->ncf);
        }
    }

    public function test_invoice_qr_png_has_an_opaque_background(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'invoice-qr-');
        try {
            app(InvoiceQrCodeService::class)->write('https://example.test/verify/opaque', $path);
            $image = imagecreatefrompng($path);
            $rgba = imagecolorsforindex($image, imagecolorat($image, 0, 0));
            $this->assertSame(0, $rgba['alpha']);
            $this->assertGreaterThanOrEqual(250, min($rgba['red'], $rgba['green'], $rgba['blue']));
            imagedestroy($image);
        } finally {
            @unlink($path);
        }
    }

    public function test_five_item_fiscal_fixture_is_one_page_and_can_be_rendered_for_visual_qa(): void
    {
        [$user, $clinic] = $this->userAndClinic();
        $authorization = FiscalAuthorization::create($this->authorizationData($clinic));
        $invoice = $this->invoice($user, $clinic, 5);
        foreach ($invoice->items as $index => $item) {
            $item->update([
                'description' => ['Consulta médica', 'Medicamentos', 'Guantes', 'Jeringas', 'Exámenes'][$index],
                'unit_price' => [350, 450, 50, 50, 700][$index],
                'tax_category' => TaxCategory::EXENTO,
            ]);
        }

        $issued = app(InvoiceIssueService::class)->issue($invoice->fresh('items'), $user, $authorization->id)['invoice']->fresh();
        $decrypted = tempnam(sys_get_temp_dir(), 'invoice-five-items-');
        try {
            app(PdfEncryptionService::class)->decrypt(Storage::disk('local')->path($issued->getRawOriginal('issued_path')), $decrypted);
            app(InvoicePdfInfoService::class)->assertOnePage($decrypted);
            if (env('QA_RENDER_ARTIFACTS')) {
                $directory = base_path('artifacts/invoice-layout-qa');
                if (! is_dir($directory)) {
                    mkdir($directory, 0700, true);
                }
                $pdf = $directory.'/five-items.pdf';
                copy($decrypted, $pdf);
                $render = new Process(['pdftoppm', '-png', '-singlefile', '-r', '144', $pdf, $directory.'/five-items']);
                $render->mustRun();
                $this->assertFileExists($directory.'/five-items.png');
            }
        } finally {
            @unlink($decrypted);
        }
    }

    public function test_eight_compact_items_remain_on_one_page(): void
    {
        [$user, $clinic] = $this->userAndClinic();
        $authorization = FiscalAuthorization::create($this->authorizationData($clinic));
        $issued = app(InvoiceIssueService::class)->issue($this->invoice($user, $clinic, 8), $user, $authorization->id)['invoice']->fresh();
        $decrypted = tempnam(sys_get_temp_dir(), 'invoice-eight-items-');
        try {
            app(PdfEncryptionService::class)->decrypt(Storage::disk('local')->path($issued->getRawOriginal('issued_path')), $decrypted);
            app(InvoicePdfInfoService::class)->assertOnePage($decrypted);
        } finally {
            @unlink($decrypted);
        }
    }

    public function test_long_description_uses_a_measured_visual_row_before_issue(): void
    {
        [$user, $clinic] = $this->userAndClinic();
        $invoice = $this->invoice($user, $clinic, 5);
        $invoice->items()->first()->update(['description' => 'Servicio de laboratorio con preparación, toma de muestra, procesamiento y entrega de resultados verificables en línea']);

        $layout = app(InvoicePdfLayoutCalculator::class)->calculate($invoice->fresh('items'));

        $this->assertGreaterThan(1, $layout['items'][0]['line_count']);
        $this->assertGreaterThan(4.3, $layout['items'][0]['visual_row_height']);
    }

    private function userAndClinic(): array
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $clinic = Clinic::create(['code' => 'PDF', 'slug' => 'pdf-test', 'name' => 'Clinica Fiscal', 'department' => 'Distrito Central', 'address' => 'Avenida Principal', 'phone' => '2222-2222']);

        return [$user, $clinic];
    }

    private function invoice(User $user, Clinic $clinic, int $items): Invoice
    {
        $invoice = Invoice::create(['clinic_id' => $clinic->id, 'created_by' => $user->id, 'recipient_name' => 'Cliente de Prueba', 'recipient_tax_id' => '0801199912345']);
        foreach (range(1, $items) as $position) {
            $invoice->items()->create(['position' => $position, 'description' => 'Consulta médica '.$position, 'quantity' => '1.000', 'unit_price' => '100.00', 'discount' => '0.00', 'tax_category' => TaxCategory::GRAVADO_15]);
        }

        return $invoice;
    }

    private function authorizationData(Clinic $clinic): array
    {
        return ['clinic_id' => $clinic->id, 'cai' => 'TEST-CAI-ONE-PAGE', 'rtn' => '08019000000001', 'establishment' => '001', 'point_of_issue' => '001', 'document_type' => 'FACTURA_CONTADO', 'ncf_prefix' => 'B01', 'range_start' => 1, 'range_end' => 10, 'next_number' => 1, 'number_padding' => 8, 'valid_from' => today(), 'valid_until' => today()->addMonth(), 'is_active' => true];
    }
}
