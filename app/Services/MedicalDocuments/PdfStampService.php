<?php

namespace App\Services\MedicalDocuments;

use App\Models\MedicalDocument;
use RuntimeException;
use setasign\Fpdi\Fpdi;

class PdfStampService
{
    public function stamp(MedicalDocument $document, string $input, string $output, string $qrPath): int
    {
        if ($document->digital_signature_detected || $this->hasDigitalSignature($input)) {
            throw new RuntimeException('Digitally signed PDFs cannot be stamped.');
        }
        $templateModel = $document->template;
        $coordinates = $templateModel?->coordinates ?? [];
        $defaults = config('medical_documents.stamp');
        $qr = $coordinates['qr'] ?? ($templateModel ? [
            'x' => $templateModel->qr_x ?: $defaults['qr']['x'],
            'y' => $templateModel->qr_y ?: $defaults['qr']['y'],
            'width' => $templateModel->qr_width ?: $defaults['qr']['width'],
        ] : $defaults['qr']);
        $code = $coordinates['code'] ?? $defaults['code'];
        $pdf = new Fpdi;
        $pages = $pdf->setSourceFile($input);
        if ($pages !== 1) {
            throw new RuntimeException('El documento debe contener exactamente una página antes de emitirse.');
        }
        $pdf->SetAutoPageBreak(false);
        $qrPage = max(1, min($pages, (int) ($templateModel?->qr_page ?? $pages)));
        for ($page = 1; $page <= $pages; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            if ((float) $qr['x'] < 0 || (float) $qr['y'] < 0 || (float) $qr['x'] + (float) $qr['width'] > $size['width'] || (float) $qr['y'] + (float) $qr['width'] > $size['height'] || (float) $code['y'] + 8 > $size['height']) {
                throw new RuntimeException('La posición configurada del QR no cabe en la página del PDF.');
            }
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
            if ($page === $qrPage) {
                $pdf->Image($qrPath, (float) $qr['x'], (float) $qr['y'], (float) $qr['width']);
                $pdf->SetFont('Arial', '', (float) $code['font_size']);
                $pdf->SetXY((float) $code['x'], (float) $code['y']);
                $pdf->Cell(50, 4, 'Documento verificable digitalmente');
                $pdf->SetXY((float) $code['x'], (float) $code['y'] + 4);
                $pdf->Cell(50, 4, 'Codigo: '.(string) $document->public_code);
            }
        }
        $pdf->Output('F', $output);

        return $qrPage;
    }

    public function hasDigitalSignature(string $path): bool
    {
        $contents = file_get_contents($path);

        return $contents !== false && (str_contains($contents, '/ByteRange') || str_contains($contents, '/Sig') || str_contains($contents, '/DocMDP'));
    }
}
