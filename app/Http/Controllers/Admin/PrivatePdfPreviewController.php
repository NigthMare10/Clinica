<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\MedicalDocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\MedicalDocument;
use Illuminate\Support\Facades\Storage;

class PrivatePdfPreviewController extends Controller
{
    public function document(MedicalDocument $document)
    {
        $this->authorize('view', $document);
        abort_unless($document->status === MedicalDocumentStatus::ISSUED, 404);

        $issuedPath = $document->getRawOriginal('issued_path');
        $path = $issuedPath === null ? $document->getRawOriginal('original_path') : $issuedPath;
        $prefix = $issuedPath === null ? 'medical/original/' : 'medical/issued/';
        $disk = Storage::disk(config('medical_documents.disk'));

        abort_unless($this->isValidPath($path, $prefix) && $disk->exists($path), 404);

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
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }
}
