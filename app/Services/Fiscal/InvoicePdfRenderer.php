<?php

namespace App\Services\Fiscal;

use App\Models\InstitutionalAsset;
use App\Models\Invoice;
use App\Services\MedicalDocuments\InstitutionalAssetService;
use App\Services\MedicalDocuments\InstitutionalSignatureStampService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class InvoicePdfRenderer
{
    public function __construct(private InstitutionalSignatureStampService $institutionalMarks) {}

    /** @param array{items: array<int, array{lines: array<int, string>, line_count: int}>, total_lines: int} $layout */
    public function render(Invoice $invoice, array $layout, string $qrPath, string $output): array
    {
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetMargins(12, 10, 12);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();
        $pdf->SetTextColor(20, 28, 38);
        $pdf->SetFont('Helvetica', 'B', 15);
        $pdf->Cell(120, 8, $this->text($invoice->clinic->name), 0, 0);
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->Cell(66, 8, 'FACTURA FISCAL', 0, 1, 'R');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Cell(120, 5, $this->text($invoice->clinic->address ?: $invoice->clinic->department), 0, 0);
        $pdf->Cell(66, 5, 'NCF: '.$invoice->ncf, 0, 1, 'R');
        $pdf->Cell(120, 5, $this->text('Tel: '.($invoice->clinic->phone ?: 'N/D')), 0, 0);
        $pdf->Cell(66, 5, 'Fecha: '.$invoice->issued_at->format('d/m/Y H:i'), 0, 1, 'R');

        $authorization = $invoice->authorization;
        $pdf->SetFillColor(240, 243, 247);
        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->Cell(186, 6, $this->text('RTN emisor: '.$authorization->rtn.'   CAI: '.$authorization->cai), 0, 1, 'L', true);
        $pdf->Cell(186, 6, $this->text('Rango autorizado: '.$authorization->rangeStartNcf().' al '.$authorization->rangeEndNcf().'   Fecha límite: '.$authorization->valid_until->format('d/m/Y')), 0, 1, 'L', true);
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->Cell(22, 5, 'Cliente:', 0, 0);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Cell(100, 5, $this->text($invoice->recipient_name ?: 'Consumidor final'), 0, 0);
        $pdf->Cell(64, 5, $this->text('RTN: '.($invoice->recipient_tax_id ?: 'N/D')), 0, 1, 'R');
        $pdf->Ln(2);

        $widths = [12, 91, 18, 24, 18, 23];
        $headers = ['Cant.', 'Descripción', 'Imp.', 'Precio', 'Desc.', 'Total'];
        $pdf->SetFillColor(36, 54, 75);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 7.5);
        foreach ($headers as $index => $header) {
            $pdf->Cell($widths[$index], 7, $this->text($header), 0, 0, $index === 1 ? 'L' : 'R', true);
        }
        $pdf->Ln();
        $pdf->SetTextColor(20, 28, 38);
        $pdf->SetFont('Helvetica', '', 7.5);
        foreach ($invoice->items as $index => $item) {
            $height = $layout['items'][$index]['line_count'] * 5;
            $y = $pdf->GetY();
            $pdf->Cell($widths[0], $height, number_format((float) $item->quantity, 3), 'B', 0, 'R');
            $x = $pdf->GetX();
            foreach ($layout['items'][$index]['lines'] as $lineIndex => $line) {
                $pdf->SetXY($x, $y + ($lineIndex * 5));
                $pdf->Cell($widths[1], 5, $this->text($line));
            }
            $pdf->SetXY($x + $widths[1], $y);
            $pdf->Cell($widths[2], $height, $this->taxLabel($item->tax_category->value), 'B', 0, 'R');
            $pdf->Cell($widths[3], $height, number_format((float) $item->unit_price, 2), 'B', 0, 'R');
            $pdf->Cell($widths[4], $height, number_format((float) $item->discount, 2), 'B', 0, 'R');
            $pdf->Cell($widths[5], $height, number_format((float) $item->total_amount, 2), 'B', 1, 'R');
        }

        $pdf->SetY(148);
        $pdf->SetFont('Helvetica', '', 8);
        $totals = [
            'Subtotal' => $invoice->subtotal,
            'Exento' => $invoice->exempt_total,
            'Exonerado' => $invoice->exonerated_total,
            'ISV 15%' => $invoice->tax_15_total,
            'ISV 18%' => $invoice->tax_18_total,
        ];
        foreach ($totals as $label => $value) {
            $pdf->Cell(148, 5, $label, 0, 0, 'R');
            $pdf->Cell(38, 5, 'L '.number_format((float) $value, 2), 0, 1, 'R');
        }
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(148, 7, 'TOTAL', 'T', 0, 'R');
        $pdf->Cell(38, 7, 'L '.number_format((float) $invoice->total, 2), 'T', 1, 'R');

        $pdf->SetY(190);
        $pdf->SetFont('Helvetica', '', 7);
        $pdf->MultiCell(112, 4, $this->text('Documento fiscal emitido electrónicamente. Verifique su autenticidad institucional mediante el código QR. Hash de fuente: '.$invoice->source_hash), 0, 'L');
        $pdf->Image($qrPath, 158, 202, 34, 34, 'PNG');
        $pdf->SetXY(150, 237);
        $pdf->SetFont('Helvetica', 'B', 6.5);
        $pdf->Cell(50, 4, 'VERIFICACIÓN INSTITUCIONAL', 0, 1, 'C');
        $assetHashes = $this->applyMarks($pdf);
        $pdf->SetY(280);
        $pdf->SetFont('Helvetica', '', 6.5);
        $pdf->Cell(186, 4, $this->text('Original emitido por '.$invoice->clinic->name.' | NCF '.$invoice->ncf), 'T', 0, 'C');
        $pdf->Output('F', $output);

        return $assetHashes;
    }

    /** @return array<string, string> */
    private function applyMarks(\FPDF $pdf): array
    {
        $assets = InstitutionalAsset::query()->whereIn('kind', ['signature', 'stamp', InstitutionalAssetService::SIGNATURE_STAMP_COMBINED])->where('is_active', true)->get()->keyBy('kind');
        $hashes = [];
        foreach ($this->institutionalMarks->kindsToApply($assets) as $kind) {
            $asset = $assets->get($kind);
            $position = config("invoice_pdf.institutional_marks.$kind");
            if (! $asset || ! is_array($position)) {
                continue;
            }
            $path = Storage::disk(config('medical_documents.disk'))->path($asset->getRawOriginal('path'));
            if (! is_file($path)) {
                throw new RuntimeException('An active institutional mark is missing from private storage.');
            }
            $pdf->Image($path, $position['x'], $position['y'], $position['width']);
            $hashes[$kind] = $asset->sha256;
        }

        return $hashes;
    }

    private function text(string $value): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $value) ?: $value;
    }

    private function taxLabel(string $category): string
    {
        return match ($category) {
            'GRAVADO_15' => '15%',
            'GRAVADO_18' => '18%',
            'EXONERADO' => 'Exon.',
            default => 'Exento',
        };
    }
}
