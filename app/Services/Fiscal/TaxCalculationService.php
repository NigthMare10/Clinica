<?php

namespace App\Services\Fiscal;

use App\Enums\TaxCategory;

class TaxCalculationService
{
    public function calculate(array $items): array
    {
        $totals = ['subtotal' => '0.00', 'discount_total' => '0.00', 'exempt_total' => '0.00', 'exonerated_total' => '0.00', 'taxable_15_total' => '0.00', 'taxable_18_total' => '0.00', 'isv_15_total' => '0.00', 'isv_18_total' => '0.00', 'tax_15_total' => '0.00', 'tax_18_total' => '0.00', 'tax_total' => '0.00', 'total' => '0.00'];
        $lines = [];
        foreach ($items as $item) {
            $category = $item['tax_category'] instanceof TaxCategory ? $item['tax_category'] : TaxCategory::from($item['tax_category']);
            $gross = $this->money((float) $item['quantity'] * (float) $item['unit_price']);
            $discount = $this->money((float) ($item['discount'] ?? 0));
            $net = $this->money((float) $gross - (float) $discount);
            if ((float) $net < 0) {
                throw new \DomainException('An invoice line discount cannot exceed its gross amount.');
            }
            $tax = $this->money((float) $net * (float) $category->rate());
            $total = $this->money((float) $net + (float) $tax);
            $bucket = match ($category) {
                TaxCategory::EXENTO => 'exempt_total', TaxCategory::EXONERADO => 'exonerated_total', TaxCategory::GRAVADO_15 => 'taxable_15_total', TaxCategory::GRAVADO_18 => 'taxable_18_total'
            };
            $totals['subtotal'] = $this->add($totals['subtotal'], $gross);
            $totals['discount_total'] = $this->add($totals['discount_total'], $discount);
            $totals[$bucket] = $this->add($totals[$bucket], $net);
            if ($category === TaxCategory::GRAVADO_15) {
                $totals['isv_15_total'] = $this->add($totals['isv_15_total'], $tax);
                $totals['tax_15_total'] = $this->add($totals['tax_15_total'], $tax);
            }
            if ($category === TaxCategory::GRAVADO_18) {
                $totals['isv_18_total'] = $this->add($totals['isv_18_total'], $tax);
                $totals['tax_18_total'] = $this->add($totals['tax_18_total'], $tax);
            }
            $totals['tax_total'] = $this->add($totals['tax_total'], $tax);
            $totals['total'] = $this->add($totals['total'], $total);
            $lines[] = compact('category', 'gross', 'discount', 'net', 'tax', 'total') + ['tax_rate' => $category->rate()];
        }

        return compact('lines', 'totals');
    }

    private function money(float $value): string
    {
        return number_format(round($value + 1e-9, 2), 2, '.', '');
    }

    private function add(string $left, string $right): string
    {
        return $this->money((float) $left + (float) $right);
    }
}
