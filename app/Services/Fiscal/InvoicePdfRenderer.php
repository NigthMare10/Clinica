<?php

namespace App\Services\Fiscal;

use App\Models\Invoice;

class InvoicePdfRenderer
{
    /** @param array{mode: 'NORMAL'|'COMPACT', items: array<int, array{lines: array<int, string>, line_count: int, visual_row_height: float}>, total_lines: int, zones: array<string, array{y: float, height?: float}>} $layout */
    public function render(Invoice $invoice, array $layout, string $qrPath, string $output): array
    {
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(10, 8, 10);
        $pdf->AddPage();
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetTextColor(0, 0, 0);
        $authorization = $invoice->authorization;
        $patient = $invoice->patient;
        $document = $invoice->medicalDocument;

        $logo = resource_path('pdf/caduceus-header.png');
        if (is_file($logo)) {
            $pdf->Image($logo, 14, 11, 23);
        }
        $pdf->SetXY(40, 10);
        $pdf->SetFont('Helvetica', 'B', 17);
        $pdf->Cell(155, 7, $this->text('CLÍNICA MÉDICA SANTA ANA'), 0, 1, 'C');
        $pdf->SetX(40);
        $pdf->SetFont('Helvetica', 'B', 15);
        $pdf->Cell(155, 7, 'FACTURA CONTADO', 0, 1, 'C');
        $pdf->SetX(40);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Cell(155, 4, 'NCF: '.$invoice->ncf, 0, 1, 'C');
        $pdf->SetX(40);
        $pdf->Cell(155, 4, $this->text('Fecha límite de emisión: '.$authorization->valid_until->format('d/m/Y')), 0, 1, 'C');
        $pdf->SetX(40);
        $pdf->Cell(155, 4, $this->text('Número inicial: '.$authorization->rangeStartNcf().'    Número final: '.$authorization->rangeEndNcf()), 0, 1, 'C');
        $pdf->SetX(40);
        $pdf->Cell(155, 4, 'RTN: '.$authorization->rtn.'    CAI: '.$authorization->cai, 0, 1, 'C');

        $pdf->SetY(43);
        $this->controlRow($pdf, $invoice);
        $this->customerBlock($pdf, $invoice, $patient, $document);
        $this->itemsTable($pdf, $invoice, $layout);
        $this->financialBlocks($pdf, $invoice, $qrPath, $layout['zones']['totals']['y']);
        $this->footerBlocks($pdf, $invoice, $document, $layout['zones']['authorizations']['y']);
        $pdf->Output('F', $output);

        return [];
    }

    private function controlRow(\FPDF $pdf, Invoice $invoice): void
    {
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell(44, 8, 'Orden: '.$invoice->order_number, 1, 0);
        $pdf->Cell(44, 8, $this->text('Fecha: '.$invoice->issued_at->format('d/m/Y')), 1, 0, 'C');
        $pdf->Cell(38, 8, $this->text('Hora: '.$invoice->issued_at->format('h:i A')), 1, 0, 'C');
        $pdf->Cell(64, 8, 'Factura: '.$invoice->invoice_control_number, 1, 1);
    }

    private function customerBlock(\FPDF $pdf, Invoice $invoice, mixed $patient, mixed $document): void
    {
        $y = $pdf->GetY();
        $pdf->Rect(10, $y, 190, 31);
        $pdf->SetXY(14, $y + 3);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(92, 5, $this->text('Fecha de atención/servicio: '.$this->serviceDate($invoice)));
        $pdf->Cell(92, 5, $this->text('Hora de atención: '.$this->serviceTime($invoice)), 0, 1);
        $pdf->SetFont('Helvetica', '', 9.5);
        $pdf->SetXY(14, $y + 10);
        $pdf->Cell(82, 5, $this->text('Paciente: '.($invoice->recipient_name ?: 'Consumidor final')));
        $pdf->SetFont('Helvetica', 'B', 7.7);
        $pdf->Cell(48, 5, 'ID/RTN: '.($invoice->recipient_tax_id ?: 'No indicado'));
        $pdf->Cell(48, 5, $this->text('Edad: '.($document?->age_at_consultation ?? $patient?->age ?? 'No indicada')), 0, 1);
        $pdf->SetXY(14, $y + 17);
        $pdf->Cell(92, 4, $this->text('Documento médico: '.($invoice->medical_document_code ?: 'No relacionado')));
        $pdf->Cell(92, 4, $this->text('Profesional: '.($invoice->service_professional ?: config('institution.provider.name'))), 0, 1);
        $pdf->SetY($y + 31);
    }

    private function itemsTable(\FPDF $pdf, Invoice $invoice, array $layout): float
    {
        $widths = [20, 18, 70, 21, 17, 22, 22];
        $headers = ['Código', 'Cantidad', 'Descripción', 'Precio', 'Desc.', 'Cost. Conv.', 'CoPago'];
        $pdf->SetFont('Helvetica', 'B', 7.6);
        foreach ($headers as $index => $header) {
            $pdf->Cell($widths[$index], 6, $this->text($header), 0, 0, $index === 2 ? 'L' : 'C');
        }
        $pdf->Ln();
        $pdf->SetFont('Helvetica', '', 7.5);
        foreach ($invoice->items as $index => $item) {
            $height = $layout['items'][$index]['visual_row_height'];
            $lineHeight = $height / $layout['items'][$index]['line_count'];
            $y = $pdf->GetY();
            $pdf->Cell($widths[0], $height, $item->service_code ?: '', 0, 0, 'C');
            $pdf->Cell($widths[1], $height, number_format((float) $item->quantity, 0), 0, 0, 'C');
            $x = $pdf->GetX();
            foreach ($layout['items'][$index]['lines'] as $lineIndex => $line) {
                $pdf->SetXY($x, $y + ($lineIndex * $lineHeight));
                $pdf->Cell($widths[2], $lineHeight, $this->text($line));
            }
            $pdf->SetXY($x + $widths[2], $y);
            $pdf->Cell($widths[3], $height, number_format((float) $item->unit_price, 2), 0, 0, 'R');
            $pdf->Cell($widths[4], $height, number_format((float) $item->discount, 2), 0, 0, 'R');
            $pdf->Cell($widths[5], $height, '0.00', 0, 0, 'R');
            $pdf->Cell($widths[6], $height, number_format((float) $item->total_amount, 2), 0, 1, 'R');
        }
        $pdf->Cell(190, 5, 'Sub Totales: ==> LPS      '.number_format((float) $invoice->subtotal, 2), 1, 1, 'C');

        return $pdf->GetY();
    }

    private function financialBlocks(\FPDF $pdf, Invoice $invoice, string $qrPath, float $y): void
    {
        $left = 104;
        $right = 86;
        $height = 77;
        $pdf->Rect(10, $y, $left, $height);
        $pdf->Rect(114, $y, $right, $height);
        $lines = [
            'Descuentos y Rebajas:' => $invoice->discount_total,
            'Importe Exonerado:' => $invoice->exonerated_total,
            'Importe Exento:' => $invoice->exempt_total,
            'Base gravada 15%:' => $invoice->taxable_15_total,
            'Base gravada 18%:' => $invoice->taxable_18_total,
            'ISV 15%:' => $invoice->isv_15_total,
            'ISV 18%:' => $invoice->isv_18_total,
        ];
        $pdf->SetFont('Helvetica', '', 7.6);
        foreach ($lines as $label => $value) {
            $pdf->SetX(13);
            $pdf->Cell(72, 4.5, $this->text($label.' ==> LPS'), 0, 0, 'R');
            $pdf->Cell(25, 4.5, number_format((float) $value, 2), 0, 1, 'R');
        }
        $pdf->SetXY(13, $y + 36);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell(72, 5, 'Total a Pagar: ==> LPS', 'T', 0, 'R');
        $pdf->Cell(25, 5, number_format((float) $invoice->total, 2), 'T', 1, 'R');
        $pdf->SetXY(10, $y + 42);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->Cell($left, 6, $this->text(app(MoneyToWordsService::class)->lempiras((float) $invoice->total)), 1, 0, 'C');

        $pdf->SetXY(117, $y + 3);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->Cell(55, 5, 'Descuentos y pagos:');
        $pdf->SetFont('Helvetica', '', 7.8);
        $pdf->SetXY(117, $y + 12);
        $pdf->Cell(55, 5, $this->text('Pago con '.strtolower($invoice->payment_method ?: 'efectivo').' en ==> LPS'));
        $pdf->Cell(25, 5, number_format((float) $invoice->paid_total, 2), 0, 1, 'R');
        $pdf->SetXY(114, $y + 31);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell($right, 6, 'Total Pagado: ==>    LPS   '.number_format((float) $invoice->paid_total, 2), 'T', 1, 'R');
        $pdf->SetX(114);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Cell($right, 5, 'Saldo: ==>       LPS   '.number_format((float) $invoice->balance, 2), 0, 1, 'R');
        $pdf->SetX(114);
        $pdf->SetFillColor(0, 0, 0);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell($right, 6, (float) $invoice->balance > 0 ? 'PENDIENTE' : 'CANCELADO', 1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->Line(10, $y + 48, 114, $y + 48);
        $pdf->SetXY(16, $y + 51);
        $pdf->SetFont('Helvetica', 'B', 7.8);
        $pdf->Cell(70, 4, $this->text('Resultados / documentos verificables en línea'));
        $pdf->SetFont('Helvetica', '', 7.2);
        $pdf->SetXY(16, $y + 58);
        $pdf->Cell(70, 4, $this->text('Código institucional: '.($invoice->medicalDocument?->public_code ?: 'Factura '.$invoice->ncf)));
        $pdf->SetXY(16, $y + 65);
        $pdf->Cell(65, 4, $this->text('Verificación: Clínica Médica Santa Ana'));
        $pdf->Image($qrPath, 86, $y + 51, 23, 23, 'PNG');
    }

    private function footerBlocks(\FPDF $pdf, Invoice $invoice, mixed $document, float $y): void
    {
        $pdf->Rect(10, $y, 95, 45);
        $pdf->Rect(105, $y, 95, 45);
        $pdf->SetFont('Helvetica', 'B', 7.2);
        $pdf->SetXY(13, $y + 4);
        $pdf->Cell(88, 4, $this->text('Autorización Entrega Resultados a Terceros:'));
        $pdf->SetFont('Helvetica', '', 7.2);
        foreach (['Nombre:', 'Parentesco:', 'Fecha de entrega:', 'Firma:'] as $offset => $label) {
            $pdf->SetXY(13, $y + 11 + ($offset * 7));
            $pdf->Cell(85, 4, $label.' __________________________________');
        }
        $pdf->SetFont('Helvetica', 'B', 7.2);
        $pdf->SetXY(108, $y + 4);
        $pdf->Cell(88, 4, $this->text('Datos fiscales complementarios:'));
        $pdf->SetFont('Helvetica', '', 7.2);
        foreach (['Número orden de compra exenta:', 'Número registro exonerado:', 'Número identificativo registro SAG:', 'Número código de carné diplomático:'] as $offset => $label) {
            $pdf->SetXY(108, $y + 11 + ($offset * 7));
            $pdf->Cell(85, 4, $this->text($label.' ____________________'));
        }
        $pdf->SetXY(10, $y + 36);
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->Cell(95, 4, 'ORIGINAL - CLIENTE');
        $pdf->Cell(95, 4, 'COPIA - OBLIGADO TRIBUTARIO EMISOR', 0, 1, 'R');
        $pdf->SetFont('Helvetica', '', 7.2);
        $pdf->Cell(95, 4, $this->text('Vigencia de muestras pendientes - 30 días.'));
        $pdf->Cell(95, 4, $this->text('La factura es beneficio de todos. Exígela.'), 0, 1, 'R');
        $pdf->SetXY(116, $y - 5);
        $pdf->SetFont('Helvetica', '', 7);
        $pdf->Cell(78, 4, $this->text('Original: Cliente   Copia: Emisor   Atendido por: '.($invoice->createdBy?->name ?? '')), 0, 0, 'R');
    }

    private function text(string $value): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $value) ?: $value;
    }

    private function serviceDate(Invoice $invoice): string
    {
        return $invoice->service_date?->format('d/m/Y') ?: 'No registrada';
    }

    private function serviceTime(Invoice $invoice): string
    {
        if (! $invoice->service_time) {
            return 'No registrada';
        }

        [$hours, $minutes] = array_map('intval', explode(':', $invoice->service_time->format('H:i')));

        return sprintf('%d:%02d %s', $hours % 12 ?: 12, $minutes, $hours < 12 ? 'AM' : 'PM');
    }
}
