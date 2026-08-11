<?php

namespace Tests\Feature;

use App\Enums\TaxCategory;
use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\User;
use App\Services\Fiscal\InvoiceDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDraftPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_paid_total_is_persisted_as_the_invoice_total(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $clinic = Clinic::create(['code' => 'PAYMENT', 'slug' => 'payment', 'name' => 'Payment clinic', 'department' => 'Test']);

        $invoice = app(InvoiceDraftService::class)->create([
            'clinic_id' => $clinic->id,
            'payment_method' => 'EFECTIVO',
            'items' => [[
                'description' => 'Consulta médica', 'quantity' => 1, 'unit_price' => 1200,
                'discount' => 0, 'tax_category' => TaxCategory::EXENTO->value,
            ]],
        ], $user);

        $this->assertSame(1200.0, (float) $invoice->fresh()->getRawOriginal('paid_total'));
        $this->assertSame(0.0, (float) $invoice->fresh()->getRawOriginal('balance'));
    }

    public function test_explicit_partial_payment_is_persisted_and_balanced(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $clinic = Clinic::create(['code' => 'PARTIAL', 'slug' => 'partial', 'name' => 'Partial clinic', 'department' => 'Test']);

        $invoice = app(InvoiceDraftService::class)->create([
            'clinic_id' => $clinic->id, 'payment_method' => 'MIXTO', 'paid_total' => 200,
            'items' => [[
                'description' => 'Consulta médica', 'quantity' => 1, 'unit_price' => 1200,
                'discount' => 0, 'tax_category' => TaxCategory::EXENTO->value,
            ]],
        ], $user);

        $this->assertSame(200.0, (float) $invoice->fresh()->getRawOriginal('paid_total'));
        $this->assertSame(1000.0, (float) $invoice->fresh()->getRawOriginal('balance'));
    }
}
