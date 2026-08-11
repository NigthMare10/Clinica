<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\FiscalAuthorization;
use App\Models\Invoice;
use App\Models\InvoiceAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicInvoiceVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_renders_a_privacy_limited_inertia_result_and_records_a_safe_audit(): void
    {
        $this->travelTo(now()->startOfSecond());
        $token = str_repeat('A', 64);
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $clinic = Clinic::create(['code' => 'VERIFY', 'slug' => 'verify', 'name' => 'Verification clinic', 'department' => 'Test']);
        $authorization = FiscalAuthorization::create([
            'clinic_id' => $clinic->id,
            'cai' => 'PUBLIC-CAI',
            'rtn' => '08019000000001',
            'establishment' => '001',
            'point_of_issue' => '001',
            'document_type' => 'FACTURA_CONTADO',
            'ncf_prefix' => 'B01',
            'range_start' => 1,
            'range_end' => 100,
            'next_number' => 2,
            'number_padding' => 8,
            'valid_from' => today(),
            'valid_until' => today()->addMonth(),
            'is_active' => true,
        ]);
        $invoice = Invoice::create([
            'clinic_id' => $clinic->id,
            'fiscal_authorization_id' => $authorization->id,
            'created_by' => $user->id,
            'recipient_name' => 'Private Recipient',
            'recipient_tax_id' => '0801199912345',
        ]);
        $invoice->forceFill([
            'status' => InvoiceStatus::ISSUED,
            'ncf' => 'B0100000001',
            'issued_at' => now(),
            'subtotal' => '100.00',
            'tax_total' => '15.00',
            'total' => '115.00',
            'issued_hash' => hash('sha256', 'invoice-pdf'),
            'qr_token_hash' => hash('sha256', $token),
        ])->save();

        $this->withHeader('User-Agent', 'Verification test')->get(route('public.invoice.verify', $token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Invoices/Verify')
                ->where('invoice.ncf', 'B0100000001')
                ->where('invoice.status', 'ISSUED')
                ->where('invoice.total', '115.00')
                ->where('invoice.authorized_range', ['B0100000001', 'B0100000100'])
                ->missing('invoice.client')
                ->missing('invoice.recipient_name')
                ->missing('invoice.recipient_tax_id')
                ->missing('invoice.items')
            );

        $audit = InvoiceAudit::query()->where('invoice_id', $invoice->id)->where('action', 'VERIFIED')->sole();
        $this->assertSame([
            'method' => 'QR_LINK',
            'result' => 'ISSUED',
            'verified_at' => now(config('institution.timezone'))->toIso8601String(),
        ], $audit->payload);
        $this->assertNull($audit->user_id);
        $this->assertStringNotContainsString($token, json_encode($audit->payload, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Private Recipient', json_encode($audit->payload, JSON_THROW_ON_ERROR));

        $this->get(route('public.invoice.verify', str_repeat('B', 64)))->assertNotFound();
    }
}
