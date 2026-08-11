<?php

namespace Tests\Unit;

use App\Enums\TaxCategory;
use App\Services\Fiscal\TaxCalculationService;
use PHPUnit\Framework\TestCase;

class TaxCalculationServiceTest extends TestCase
{
    public function test_it_calculates_each_fiscal_tax_category(): void
    {
        $result = (new TaxCalculationService)->calculate([
            ['quantity' => '1', 'unit_price' => '100.00', 'discount' => '0', 'tax_category' => TaxCategory::EXENTO],
            ['quantity' => '1', 'unit_price' => '100.00', 'discount' => '0', 'tax_category' => TaxCategory::EXONERADO],
            ['quantity' => '1', 'unit_price' => '100.00', 'discount' => '0', 'tax_category' => TaxCategory::GRAVADO_15],
            ['quantity' => '1', 'unit_price' => '100.00', 'discount' => '0', 'tax_category' => TaxCategory::GRAVADO_18],
        ]);

        $this->assertSame('400.00', $result['totals']['subtotal']);
        $this->assertSame('15.00', $result['totals']['tax_15_total']);
        $this->assertSame('18.00', $result['totals']['tax_18_total']);
        $this->assertSame('33.00', $result['totals']['tax_total']);
        $this->assertSame('433.00', $result['totals']['total']);
    }

    public function test_it_separates_gross_subtotal_and_discounts(): void
    {
        $result = (new TaxCalculationService)->calculate([
            ['quantity' => 2, 'unit_price' => 100, 'discount' => 25, 'tax_category' => TaxCategory::EXENTO],
        ]);

        $this->assertSame('200.00', $result['totals']['subtotal']);
        $this->assertSame('25.00', $result['totals']['discount_total']);
        $this->assertSame('175.00', $result['totals']['exempt_total']);
        $this->assertSame('175.00', $result['totals']['total']);
    }
}
