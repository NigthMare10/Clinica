<?php

namespace App\Services\Fiscal;

use App\Models\Invoice;
use App\Services\MedicalDocuments\DocumentHashService;
use App\Services\MedicalDocuments\PdfEncryptionService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class InvoicePdfService
{
    public function __construct(
        private InvoicePdfRenderer $renderer,
        private InvoicePdfInfoService $pdfInfo,
        private PdfEncryptionService $encryption,
        private DocumentHashService $hashes,
        private InvoiceQrCodeService $qr,
    ) {}

    /** @param array{items: array<int, array{lines: array<int, string>, line_count: int}>, total_lines: int} $layout
     * @return array{path: string, sha256: string, institutional_marks: array<string, string>}
     */
    public function generate(Invoice $invoice, array $layout, string $token): array
    {
        $directory = storage_path('app/tmp/'.Str::uuid());
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create the secure fiscal PDF working directory.');
        }

        $qrPath = $directory.DIRECTORY_SEPARATOR.'qr-opaque.png';
        $plainPath = $directory.DIRECTORY_SEPARATOR.'invoice.pdf';
        $encryptedPath = $directory.DIRECTORY_SEPARATOR.'invoice-encrypted.pdf';
        $storedPath = 'fiscal/invoices/'.$invoice->id.'-'.Str::random(24).'.pdf';
        $disk = Storage::disk(config('invoice_pdf.disk'));

        try {
            // The fiscal QR has an explicit opaque white background so underlying artwork cannot reduce readability.
            $this->qr->write(route('public.invoice.verify', $token), $qrPath);
            $assetHashes = $this->renderer->render($invoice, $layout, $qrPath, $plainPath);
            $this->pdfInfo->assertOnePage($plainPath);

            $source = $plainPath;
            if (config('invoice_pdf.encryption_enabled')) {
                $this->encryption->encrypt($plainPath, $encryptedPath);
                $this->encryption->assertEncrypted($encryptedPath);
                $source = $encryptedPath;
            }
            $contents = file_get_contents($source);
            if ($contents === false || ! $disk->put($storedPath, $contents)) {
                throw new RuntimeException('Unable to store the issued fiscal PDF privately.');
            }
            $storedAbsolutePath = $disk->path($storedPath);

            return [
                'path' => $storedPath,
                'sha256' => $this->hashes->file($storedAbsolutePath),
                'institutional_marks' => $assetHashes,
            ];
        } catch (\Throwable $exception) {
            $disk->delete($storedPath);

            throw $exception;
        } finally {
            foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);
        }
    }
}
