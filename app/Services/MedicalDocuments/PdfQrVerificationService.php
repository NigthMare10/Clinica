<?php

namespace App\Services\MedicalDocuments;

use App\Models\MedicalDocument;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use Zxing\QrReader;

class PdfQrVerificationService
{
    public function __construct(private PdfToolAvailabilityService $tools) {}

    public function assertReadable(string $pdfPath, int $page, string $expectedUrl, string $workingDirectory, MedicalDocument $document): void
    {
        $pdftoppm = $this->tools->path('pdftoppm');
        $ghostscript = $this->tools->path('gs');
        if (! $pdftoppm && ! $ghostscript) {
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
        $image = $workingDirectory.DIRECTORY_SEPARATOR.'qr-check.png';
        $process = $pdftoppm
            ? new Process([$pdftoppm, '-f', (string) $page, '-l', (string) $page, '-singlefile', '-r', (string) $dpi, '-x', (string) $cropX, '-y', (string) $cropY, '-W', (string) $cropWidth, '-H', (string) $cropHeight, '-png', $pdfPath, substr($image, 0, -4)])
            : new Process([$ghostscript, '-q', '-dSAFER', '-dBATCH', '-dNOPAUSE', '-sDEVICE=pngalpha', '-r'.$dpi, '-dFirstPage='.$page, '-dLastPage='.$page, '-sOutputFile='.$image, $pdfPath]);
        $process->setWorkingDirectory($workingDirectory);
        $process->setEnv(['TMPDIR' => storage_path('runtime/tmp')]);
        $process->setTimeout(config('medical_documents.process_timeout'));
        $process->run();

        if (! $process->isSuccessful() || ! is_file($image)) {
            Log::error('PDF QR renderer failed.', [
                'renderer' => $pdftoppm ? 'poppler' : 'ghostscript',
                'binary' => $pdftoppm ?: $ghostscript,
                'working_directory' => $workingDirectory,
                'exit_code' => $process->getExitCode(),
                'stdout' => mb_substr($process->getOutput(), 0, 2000),
                'stderr' => mb_substr($process->getErrorOutput(), 0, 2000),
            ]);
            throw new RuntimeException('The stamped PDF could not be rendered for QR verification.');
        }

        if (! $pdftoppm) {
            $pageImage = imagecreatefrompng($image);
            $cropped = $pageImage ? imagecrop($pageImage, ['x' => $cropX, 'y' => $cropY, 'width' => $cropWidth, 'height' => $cropHeight]) : false;
            if (! $pageImage || ! $cropped || ! imagepng($cropped, $image)) {
                throw new RuntimeException('The rendered PDF QR region could not be prepared for verification.');
            }
            imagedestroy($pageImage);
            imagedestroy($cropped);
        }

        $decoded = (new QrReader($image))->text();
        if (! is_string($decoded) || ! hash_equals($expectedUrl, $decoded)) {
            throw new RuntimeException('The QR embedded in the PDF is not readable or does not match the verification URL.');
        }
    }
}
