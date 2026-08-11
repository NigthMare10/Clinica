<?php

namespace App\Services\Fiscal;

use App\Models\Invoice;
use DomainException;

class InvoicePdfLayoutCalculator
{
    /** @return array{items: array<int, array{lines: array<int, string>, line_count: int}>, total_lines: int} */
    public function calculate(Invoice $invoice): array
    {
        if ($invoice->items->count() > config('invoice_pdf.max_items')) {
            throw new DomainException('The invoice has too many items for the one-page fiscal PDF.');
        }

        $items = [];
        $totalLines = 0;
        foreach ($invoice->items as $item) {
            $lines = $this->wrap((string) $item->description);
            $lineCount = max(1, count($lines));
            $totalLines += $lineCount;
            $items[] = ['lines' => $lines, 'line_count' => $lineCount];
        }

        if ($totalLines > config('invoice_pdf.max_item_lines')) {
            throw new DomainException('The invoice item descriptions do not fit on one fiscal PDF page.');
        }

        return ['items' => $items, 'total_lines' => $totalLines];
    }

    /** @return array<int, string> */
    private function wrap(string $description): array
    {
        $limit = max(10, (int) config('invoice_pdf.description_characters_per_line'));
        $words = preg_split('/\s+/u', trim($description)) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            while (mb_strlen($word) > $limit) {
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }
                $lines[] = mb_substr($word, 0, $limit);
                $word = mb_substr($word, $limit);
            }
            $candidate = $line === '' ? $word : $line.' '.$word;
            if ($line !== '' && mb_strlen($candidate) > $limit) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '' || $lines === []) {
            $lines[] = $line;
        }

        return $lines;
    }
}
