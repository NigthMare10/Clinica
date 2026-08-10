<?php

namespace App\Services\MedicalDocuments;

use App\Models\MedicalDocument;
use RuntimeException;
use Symfony\Component\Process\Process;
use Zxing\QrReader;

class PdfQrVerificationService
{
    public function __construct(private PdfToolAvailabilityService $tools) {}

    public function assertReadable(string $pdfPath, int $page, string $expectedUrl, string $workingDirectory, MedicalDocument $document): void
    {
        $pdftoppm = $this->tools->path('pdftoppm');
        if (! $pdftoppm) {
            throw new RuntimeException('The PDF renderer required for QR verification is not available.');
        }

        $template = $document->template;
        $defaults = config('medical_documents.stamp.qr');
        $qr = ($template?->coordinates ?? [])['qr'] ?? ($template ? [
            'x' => $template->qr_x ?: $defaults['x'],
            'y' => $template->qr_y ?: $defaults['y'],
            'width' => $template->qr_width ?: $defaults['width'],
        ] : $defaults);
        $dpi = 240;
        $pixelsPerMm = $dpi / 25.4;
        $padding = 4;
        $cropX = max(0, (int) floor(((float) $qr['x'] - $padding) * $pixelsPerMm));
        $cropY = max(0, (int) floor(((float) $qr['y'] - $padding) * $pixelsPerMm));
        $cropWidth = (int) ceil(((float) $qr['width'] + 2 * $padding) * $pixelsPerMm);
        $cropHeight = (int) ceil(((float) ($qr['height'] ?? $qr['width']) + 2 * $padding) * $pixelsPerMm);
        $prefix = $workingDirectory.DIRECTORY_SEPARATOR.'qr-check';
        $process = new Process([
            $pdftoppm,
            '-f', (string) $page,
            '-l', (string) $page,
            '-singlefile',
            '-r', (string) $dpi,
            '-x', (string) $cropX,
            '-y', (string) $cropY,
            '-W', (string) $cropWidth,
            '-H', (string) $cropHeight,
            '-png',
            $pdfPath,
            $prefix,
        ]);
        $process->setTimeout(config('medical_documents.process_timeout'));
        $process->run();

        if (! $process->isSuccessful() || ! is_file($prefix.'.png')) {
            throw new RuntimeException('The stamped PDF could not be rendered for QR verification.');
        }

        $decoded = (new QrReader($prefix.'.png'))->text();
        if (! is_string($decoded) || ! hash_equals($expectedUrl, $decoded)) {
            throw new RuntimeException('The QR embedded in the PDF is not readable or does not match the verification URL.');
        }
    }
}
