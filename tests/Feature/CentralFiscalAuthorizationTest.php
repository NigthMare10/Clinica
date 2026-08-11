<?php

namespace Tests\Feature;

use App\Enums\TaxCategory;
use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\FiscalAuthorization;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Fiscal\InvoiceIssueService;
use Database\Seeders\ClinicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CentralFiscalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_clinics_issue_from_the_single_hn_08_authorization(): void
    {
        $this->seed(ClinicSeeder::class);
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $central = Clinic::query()->where('code', 'HN-08')->sole();
        $authorization = FiscalAuthorization::create([
            'clinic_id' => $central->id, 'cai' => 'CENTRAL-CAI', 'rtn' => '08019995307719',
            'establishment' => '008', 'point_of_issue' => '001', 'document_type' => 'FACTURA_CONTADO',
            'ncf_prefix' => '008-001-01-', 'range_start' => 134099, 'range_end' => 134200,
            'next_number' => 134099, 'number_padding' => 8, 'valid_from' => today(),
            'valid_until' => today()->addYear(), 'is_active' => true,
        ]);
        $this->mock(\App\Services\Fiscal\InvoicePdfService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')->times(18)->andReturnUsing(fn (Invoice $invoice) => [
                'path' => 'fiscal/invoices/'.$invoice->id.'.pdf', 'sha256' => hash('sha256', $invoice->id), 'institutional_marks' => [],
            ]);
        });

        foreach (Clinic::query()->orderBy('code')->get() as $index => $clinic) {
            $invoice = Invoice::create(['clinic_id' => $clinic->id, 'created_by' => $user->id, 'recipient_name' => 'Paciente de prueba']);
            $invoice->items()->create([
                'position' => 1, 'description' => 'Consulta médica', 'quantity' => '1.000',
                'unit_price' => '100.00', 'discount' => '0.00', 'tax_category' => TaxCategory::EXENTO,
            ]);

            $issued = app(InvoiceIssueService::class)->issue($invoice, $user, $authorization->id)['invoice'];

            $this->assertSame($authorization->id, $issued->fiscal_authorization_id);
            $this->assertSame(sprintf('008-001-01-%08d', 134099 + $index), $issued->ncf);
        }

        $this->assertSame(134117, $authorization->fresh()->next_number);
        $this->assertDatabaseCount('fiscal_authorizations', 1);
    }
}
