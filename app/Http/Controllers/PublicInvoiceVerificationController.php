<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceAudit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicInvoiceVerificationController extends Controller
{
    public function __invoke(Request $request, string $token): Response
    {
        abort_unless((bool) preg_match('/^[A-Za-z0-9]{64}$/', $token), 404);
        $invoice = Invoice::query()->where('qr_token_hash', hash('sha256', $token))->firstOrFail();

        return $this->result($request, $invoice);
    }

    public function linked(Request $request, Invoice $invoice): Response
    {
        abort_unless($request->session()->get('public_pdf_preview.invoice_id') === $invoice->id, 403);

        return $this->result($request, $invoice);
    }

    private function result(Request $request, Invoice $invoice): Response
    {
        $invoice->loadMissing('authorization');
        $verifiedAt = now(config('institution.timezone'));

        InvoiceAudit::create([
            'invoice_id' => $invoice->id,
            'action' => 'VERIFIED',
            'payload' => [
                'method' => 'QR_LINK',
                'result' => $invoice->status->value,
                'verified_at' => $verifiedAt->toIso8601String(),
            ],
            'ip_address' => $request->ip() ? substr(hash_hmac('sha256', $request->ip(), (string) config('app.key')), 0, 45) : null,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        return Inertia::render('Public/Invoices/Verify', [
            'invoice' => [
                'ncf' => $invoice->ncf,
                'status' => $invoice->status->value,
                'issued_at' => $invoice->issued_at?->toIso8601String(),
                'subtotal' => number_format((float) $invoice->subtotal, 2, '.', ''),
                'tax_total' => number_format((float) $invoice->tax_total, 2, '.', ''),
                'total' => number_format((float) $invoice->total, 2, '.', ''),
                'currency' => $invoice->currency,
                'cai' => $invoice->authorization?->cai,
                'issuer_rtn' => $invoice->authorization?->rtn,
                'emission_deadline' => $invoice->authorization?->valid_until?->toDateString(),
                'authorized_range' => $invoice->authorization ? [$invoice->authorization->rangeStartNcf(), $invoice->authorization->rangeEndNcf()] : null,
                'medical_document_code' => $invoice->medical_document_code,
                'service_date' => $invoice->service_date?->toDateString(),
                'service_time' => $invoice->service_time?->format('H:i'),
                'hash' => $invoice->issued_hash,
                'verified_at' => $verifiedAt->toIso8601String(),
                'method' => 'QR_LINK',
            ],
        ]);
    }
}
