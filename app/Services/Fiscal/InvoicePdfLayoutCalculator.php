<?php

namespace App\Services\Fiscal;

use App\Models\Invoice;
use DomainException;

class InvoicePdfLayoutCalculator
{
    private const ITEMS_Y = 82.0;
    private const TABLE_FIXED_HEIGHT = 11.0;
    private const FINANCIAL_HEIGHT = 77.0;

    /**
     * Calculates every variable vertical zone before an NCF is selected. The footer is
     * deliberately page-anchored; item rows are never allowed to enter its reserved area.
     *
     * @return array{mode: 'NORMAL'|'COMPACT', items: array<int, array{lines: array<int, string>, line_count: int, visual_row_height: float}>, total_lines: int, zones: array{header: array{y: float, height: float}, customer: array{y: float, height: float}, services: array{y: float, height: float}, totals: array{y: float, height: float}, verification: array{y: float, height: float}, authorizations: array{y: float, height: float}, footer: array{y: float, height: float}, signature_stamp: array{y: float}}} */
    public function calculate(Invoice $invoice): array
    {
        if ($invoice->items->count() > config('invoice_pdf.max_items')) {
            throw new DomainException('La cantidad o descripción de servicios excede el formato fiscal de una página. Ajuste las líneas antes de emitir.');
        }

        $mode = $invoice->items->count() <= 5 ? 'NORMAL' : 'COMPACT';
        $rowHeight = $mode === 'NORMAL' ? 4.3 : 3.6;
        $items = [];
        $totalLines = 0;
        foreach ($invoice->items as $item) {
            $lines = $this->wrap((string) $item->description, 70.0, $mode === 'NORMAL' ? 7.5 : 7.8);
            $lineCount = max(1, count($lines));
            $totalLines += $lineCount;
            $items[] = ['lines' => $lines, 'line_count' => $lineCount, 'visual_row_height' => $lineCount * $rowHeight];
        }

        $servicesHeight = self::TABLE_FIXED_HEIGHT + collect($items)->sum('visual_row_height');
        $financialY = self::ITEMS_Y + $servicesHeight;
        $footerY = (float) config('invoice_pdf.footer_y');
        if ($financialY + self::FINANCIAL_HEIGHT > $footerY) {
            throw new DomainException('La cantidad o descripción de servicios excede el formato fiscal de una página. Ajuste las líneas antes de emitir.');
        }

        return [
            'mode' => $mode,
            'items' => $items,
            'total_lines' => $totalLines,
            'zones' => [
                'header' => ['y' => 10.0, 'height' => 33.0],
                'customer' => ['y' => 51.0, 'height' => 31.0],
                'services' => ['y' => self::ITEMS_Y, 'height' => $servicesHeight],
                'totals' => ['y' => $financialY, 'height' => self::FINANCIAL_HEIGHT],
                'verification' => ['y' => $financialY + 48.0, 'height' => 29.0],
                'authorizations' => ['y' => $footerY, 'height' => 45.0],
                'footer' => ['y' => $footerY + 40.0, 'height' => 8.0],
                'signature_stamp' => ['y' => (float) config('invoice_pdf.institutional_marks.stamp.y')],
            ],
        ];
    }

    /** @return array<int, string> */
    private function wrap(string $description, float $width, float $fontSize): array
    {
        $words = preg_split('/\s+/u', trim($description)) ?: [];
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetFont('Helvetica', '', $fontSize);
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            while ($pdf->GetStringWidth($this->text($word)) > $width) {
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }
                $part = '';
                foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
                    if ($pdf->GetStringWidth($this->text($part.$character)) > $width) {
                        break;
                    }
                    $part .= $character;
                }
                $lines[] = $part;
                $word = mb_substr($word, mb_strlen($part));
            }
            $candidate = $line === '' ? $word : $line.' '.$word;
            if ($line !== '' && $pdf->GetStringWidth($this->text($candidate)) > $width) {
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

    private function text(string $value): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $value) ?: $value;
    }
}
