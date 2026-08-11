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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceIssueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_allocates_the_next_ncf_and_only_stores_a_token_hash(): void
    {
        [$user, $clinic] = $this->userAndClinic();
        $authorization = FiscalAuthorization::create($this->authorizationData($clinic, 1, 2, 1));
        $invoice = $this->invoice($user, $clinic);

        $result = app(InvoiceIssueService::class)->issue($invoice, $user, $authorization->id);

        $this->assertSame('B010001', $result['invoice']->ncf);
        $this->assertSame(InvoiceStatus::ISSUED, $result['invoice']->status);
        $this->assertDatabaseHas('fiscal_authorizations', ['id' => $authorization->id, 'next_number' => 2]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'qr_token_hash' => hash('sha256', $result['qr_token'])]);
        $this->assertDatabaseMissing('invoices', ['qr_token_hash' => $result['qr_token']]);
    }

    public function test_issue_rejects_an_exhausted_ncf_range_without_consuming_a_number(): void
    {
        [$user, $clinic] = $this->userAndClinic();
        $authorization = FiscalAuthorization::create($this->authorizationData($clinic, 1, 1, 2));

        try {
            app(InvoiceIssueService::class)->issue($this->invoice($user, $clinic), $user, $authorization->id);
            $this->fail('An exhausted authorization must reject issue.');
        } catch (\DomainException $exception) {
            $this->assertSame('The fiscal authorization NCF range is exhausted.', $exception->getMessage());
        }

        $this->assertDatabaseHas('fiscal_authorizations', ['id' => $authorization->id, 'next_number' => 2]);
    }

    public function test_competing_drafts_are_serialized_to_distinct_ncf_numbers(): void
    {
        [$user, $clinic] = $this->userAndClinic();
        $authorization = FiscalAuthorization::create($this->authorizationData($clinic, 40, 50, 40));
        $first = $this->invoice($user, $clinic);
        $second = $this->invoice($user, $clinic);

        $firstResult = app(InvoiceIssueService::class)->issue($first, $user, $authorization->id);
        $secondResult = app(InvoiceIssueService::class)->issue($second, $user, $authorization->id);

        $this->assertSame('B010040', $firstResult['invoice']->ncf);
        $this->assertSame('B010041', $secondResult['invoice']->ncf);
        $this->assertNotSame($firstResult['invoice']->ncf, $secondResult['invoice']->ncf);
        $this->assertDatabaseHas('fiscal_authorizations', ['id' => $authorization->id, 'next_number' => 42]);
    }

    private function userAndClinic(): array
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $clinic = Clinic::create(['code' => 'TEST', 'slug' => 'test', 'name' => 'Test clinic', 'department' => 'Test']);

        return [$user, $clinic];
    }

    private function invoice(User $user, Clinic $clinic): Invoice
    {
        $invoice = Invoice::create(['clinic_id' => $clinic->id, 'created_by' => $user->id, 'recipient_name' => 'Test recipient']);
        $invoice->items()->create(['position' => 1, 'description' => 'Consultation', 'quantity' => '1.000', 'unit_price' => '100.00', 'discount' => '0.00', 'tax_category' => TaxCategory::GRAVADO_18]);

        return $invoice;
    }

    private function authorizationData(Clinic $clinic, int $start, int $end, int $next): array
    {
        return ['clinic_id' => $clinic->id, 'cai' => 'TEST-CAI', 'rtn' => 'TEST-RTN', 'establishment' => '001', 'point_of_issue' => '001', 'document_type' => 'FACTURA_CONTADO', 'ncf_prefix' => 'B01', 'range_start' => $start, 'range_end' => $end, 'next_number' => $next, 'number_padding' => 4, 'valid_from' => today(), 'valid_until' => today()->addDay(), 'is_active' => true];
    }
}
