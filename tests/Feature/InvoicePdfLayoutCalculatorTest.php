<?php

namespace Tests\Feature;

use App\Enums\TaxCategory;
use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Fiscal\InvoicePdfLayoutCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfLayoutCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_layout_keeps_the_reserved_bottom_zones_fixed(): void
    {
        $layout = app(InvoicePdfLayoutCalculator::class)->calculate($this->invoice(5));

        $this->assertSame('NORMAL', $layout['mode']);
        $this->assertSame((float) config('invoice_pdf.footer_y'), $layout['zones']['authorizations']['y']);
        $this->assertLessThanOrEqual($layout['zones']['authorizations']['y'], $layout['zones']['totals']['y'] + $layout['zones']['totals']['height']);
        $this->assertLessThan($layout['zones']['authorizations']['y'], $layout['zones']['verification']['y'] + $layout['zones']['verification']['height']);
    }

    public function test_compact_layout_accepts_eight_short_lines_without_moving_footer_or_qr(): void
    {
        $layout = app(InvoicePdfLayoutCalculator::class)->calculate($this->invoice(8));

        $this->assertSame('COMPACT', $layout['mode']);
        $this->assertSame((float) config('invoice_pdf.footer_y'), $layout['zones']['footer']['y'] - 40.0);
        $this->assertLessThan($layout['zones']['authorizations']['y'], $layout['zones']['verification']['y'] + $layout['zones']['verification']['height']);
    }

    public function test_overflow_is_rejected_before_rendering(): void
    {
        $this->expectException(\DomainException::class);
        app(InvoicePdfLayoutCalculator::class)->calculate($this->invoice(9));
    }

    private function invoice(int $items): Invoice
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $clinic = Clinic::create(['code' => 'LAYOUT', 'slug' => 'layout-test', 'name' => 'Layout clinic', 'department' => 'Test']);
        $invoice = Invoice::create(['clinic_id' => $clinic->id, 'created_by' => $user->id]);
        foreach (range(1, $items) as $position) {
            $invoice->items()->create(['position' => $position, 'description' => 'Servicio fiscal '.$position, 'quantity' => 1, 'unit_price' => 100, 'tax_category' => TaxCategory::EXENTO]);
        }

        return $invoice->load('items');
    }
}
