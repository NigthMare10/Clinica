<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\MedicalDocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\MedicalDocument;
use App\Services\MedicalDocuments\PdfEncryptionService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrivatePdfPreviewController extends Controller
{
    public function document(MedicalDocument $document, PdfEncryptionService $encryption)
    {
        $this->authorize('view', $document);
        abort_unless($document->status === MedicalDocumentStatus::ISSUED, 404);

        $issuedPath = $document->getRawOriginal('issued_path');
        $path = $issuedPath === null ? $document->getRawOriginal('original_path') : $issuedPath;
        $prefix = $issuedPath === null ? 'medical/original/' : 'medical/issued/';
        $disk = Storage::disk(config('medical_documents.disk'));

        abort_unless($this->isValidPath($path, $prefix) && $disk->exists($path), 404);

        // Chromium's built-in viewer is not reliable with owner-protected PDFs, even with an empty user password.
        // Stream an ephemeral, authorized viewing copy while keeping the encrypted issued bytes untouched.
        if ($issuedPath !== null && config('medical_documents.encryption_enabled')) {
            $directory = storage_path('runtime/previews');
            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                abort(500, 'Unable to prepare document preview.');
            }
            $preview = $directory.DIRECTORY_SEPARATOR.$document->id.'-'.Str::uuid().'.pdf';
            try {
                $encryption->decrypt($disk->path($path), $preview);

                return response()->stream(function () use ($preview): void {
                    readfile($preview);
                    @unlink($preview);
                }, 200, $this->headers());
            } catch (\Throwable $exception) {
                @unlink($preview);
                report($exception);
                abort(500, 'Unable to prepare document preview.');
            }
        }

        return $disk->response($path, 'medical-document.pdf', $this->headers(), 'inline');
    }

    public function invoice(Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        abort_unless($invoice->status === InvoiceStatus::ISSUED, 404);

        $path = $invoice->getRawOriginal('issued_path');
        $disk = Storage::disk(config('invoice_pdf.disk'));

        abort_unless($this->isValidPath($path, 'fiscal/invoices/') && $disk->exists($path), 404);

        return $disk->response($path, 'invoice.pdf', $this->headers(), 'inline');
    }

    private function isValidPath(mixed $path, string $prefix): bool
    {
        return is_string($path) && str_starts_with($path, $prefix) && ! str_contains($path, '..');
    }

    private function headers(): array
    {
        return [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="medical-document.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }
}
