<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\MedicalDocumentStatus;
use App\Models\Invoice;
use App\Models\MedicalDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicVerifiedPdfController extends Controller
{
    public function document(Request $request, MedicalDocument $document, string $disposition = 'inline')
    {
        abort_unless($request->session()->get('public_pdf_preview.document_id') === $document->id, 403);
        abort_unless($document->status === MedicalDocumentStatus::ISSUED, 404);
        $issuedPath = $document->getRawOriginal('issued_path');
        $path = $issuedPath ?? $document->getRawOriginal('original_path');
        $prefix = $issuedPath === null ? 'medical/original/' : 'medical/issued/';

        return $this->response(config('medical_documents.disk'), $path, $prefix, 'medical-document.pdf', $disposition);
    }

    public function invoice(Request $request, Invoice $invoice, string $disposition = 'inline')
    {
        abort_unless($request->session()->get('public_pdf_preview.invoice_id') === $invoice->id, 403);
        abort_unless($invoice->status === InvoiceStatus::ISSUED, 404);

        return $this->response(config('invoice_pdf.disk'), $invoice->getRawOriginal('issued_path'), 'fiscal/invoices/', 'invoice.pdf', $disposition);
    }

    private function response(string $diskName, mixed $path, string $prefix, string $name, string $disposition)
    {
        $disk = Storage::disk($diskName);
        abort_unless(is_string($path) && str_starts_with($path, $prefix) && ! str_contains($path, '..') && $disk->exists($path), 404);

        return $disk->response($path, $name, [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private, max-age=0', 'Pragma' => 'no-cache', 'Expires' => '0',
        ], $disposition);
    }
}
