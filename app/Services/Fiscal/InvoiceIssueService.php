<?php

namespace App\Services\Fiscal;

use App\Enums\FiscalAuthorizationStatus;
use App\Enums\InvoiceStatus;
use App\Models\FiscalAuthorization;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\InvoiceAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class InvoiceIssueService
{
    public function __construct(
        private TaxCalculationService $taxes,
        private InvoicePdfLayoutCalculator $layout,
        private InvoicePdfService $pdf,
    ) {}

    /** @return array{invoice: Invoice, qr_token: string} */
    public function issue(Invoice $invoice, User $issuer, ?string $authorizationId = null): array
    {
        $storedPath = null;
        try {
            return DB::transaction(function () use ($invoice, $issuer, $authorizationId, &$storedPath) {
                $invoice = Invoice::query()->with(['items', 'clinic', 'patient', 'medicalDocument', 'createdBy'])->lockForUpdate()->findOrFail($invoice->id);
                if ($invoice->status !== InvoiceStatus::DRAFT) {
                    throw new \DomainException('Only draft invoices can be issued.');
                }
                if ($invoice->items->isEmpty()) {
                    throw new \DomainException('An invoice requires at least one item.');
                }

                // This guard runs before authorization selection so an oversized invoice can never consume an NCF.
                $layout = $this->layout->calculate($invoice);

                $authorization = $this->lockedAuthorization($invoice, $authorizationId);
                if ($authorization->status !== FiscalAuthorizationStatus::ACTIVE || ! $authorization->cai || ! $authorization->rtn) {
                    throw new \DomainException('Fiscal authorization is not active or its CAI/RTN is incomplete.');
                }
                if ($authorization->next_number < $authorization->range_start || $authorization->next_number > $authorization->range_end) {
                    $authorization->forceFill(['status' => FiscalAuthorizationStatus::EXHAUSTED, 'is_active' => false, 'exhausted_at' => now()])->save();
                    throw new \DomainException('The fiscal authorization NCF range is exhausted.');
                }

                $calculation = $this->taxes->calculate($invoice->items->map(fn ($item) => $item->getAttributes())->all());
                foreach ($invoice->items as $index => $item) {
                    $line = $calculation['lines'][$index];
                    $item->forceFill(['tax_rate' => $line['tax_rate'], 'net_amount' => $line['net'], 'tax_amount' => $line['tax'], 'total_amount' => $line['total']])->save();
                }

                $number = $authorization->next_number;
                $ncf = $authorization->formatNcf($number);
                $sourceHash = hash('sha256', $this->canonicalSource($invoice));
                $token = Str::random(64);
                $totals = $calculation['totals'];
                $issuedAt = now();
                $invoice->forceFill($totals + [
                    'fiscal_authorization_id' => $authorization->id,
                    'ncf' => $ncf,
                    'order_number' => $invoice->order_number ?: $this->internalNumber('ORD'),
                    'invoice_control_number' => $invoice->invoice_control_number ?: $this->internalNumber('C'),
                    'source_hash' => $sourceHash,
                    'issued_at' => $issuedAt,
                ]);
                $invoice->setRelation('authorization', $authorization);
                $artifact = $this->pdf->generate($invoice, $layout, $token);
                $storedPath = $artifact['path'];

                $authorization->increment('next_number');
                if ($number >= $authorization->range_end) {
                    $authorization->forceFill(['status' => FiscalAuthorizationStatus::EXHAUSTED, 'is_active' => false, 'exhausted_at' => now()])->save();
                }
                $invoice->forceFill([
                    'issued_hash' => $artifact['sha256'], 'issued_path' => $artifact['path'],
                    'qr_token_hash' => hash('sha256', $token), 'status' => InvoiceStatus::ISSUED,
                    'issued_by' => $issuer->id,
                ])->save();
                $this->audit($invoice, $issuer, 'ISSUED', [
                    'ncf' => $ncf, 'source_hash' => $sourceHash, 'issued_hash' => $artifact['sha256'],
                    'institutional_marks' => $artifact['institutional_marks'],
                ]);

                return compact('invoice', 'token') + ['qr_token' => $token];
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk(config('invoice_pdf.disk'))->delete($storedPath);
            }

            throw $exception;
        }
    }

    public function void(Invoice $invoice, User $user, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $user, $reason) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status !== InvoiceStatus::ISSUED) {
                throw new \DomainException('Only issued invoices can be voided.');
            }
            $invoice->forceFill(['status' => InvoiceStatus::VOID, 'voided_by' => $user->id, 'voided_at' => now(), 'void_reason' => $reason])->save();
            $this->audit($invoice, $user, 'VOIDED', ['reason' => $reason, 'ncf' => $invoice->ncf]);

            return $invoice;
        });
    }

    private function lockedAuthorization(Invoice $invoice, ?string $authorizationId): FiscalAuthorization
    {
        $centralClinicId = Clinic::query()->where('code', config('fiscal_reference.reference_invoice_import.central_clinic_code'))->value('id');
        if (! $centralClinicId) {
            throw new \DomainException('The central fiscal clinic is not configured.');
        }
        FiscalAuthorization::query()->where('clinic_id', $centralClinicId)->where('status', FiscalAuthorizationStatus::ACTIVE)
            ->whereDate('valid_until', '<', today())->update(['status' => FiscalAuthorizationStatus::EXPIRED, 'is_active' => false]);
        $query = FiscalAuthorization::query()->where('clinic_id', $centralClinicId)->where('is_active', true)->where('status', FiscalAuthorizationStatus::ACTIVE)
            ->whereDate('valid_from', '<=', today())->whereDate('valid_until', '>=', today())->lockForUpdate();
        $authorization = $authorizationId ? $query->whereKey($authorizationId)->first() : $query->orderBy('valid_until')->orderBy('id')->first();
        if (! $authorization) {
            throw new \DomainException('No active fiscal authorization is available for this clinic.');
        }

        return $authorization;
    }

    private function canonicalSource(Invoice $invoice): string
    {
        return $this->canonicalJson(['clinic_id' => $invoice->clinic_id, 'patient_id' => $invoice->patient_id, 'medical_document_id' => $invoice->medical_document_id, 'recipient_name' => $invoice->recipient_name, 'recipient_tax_id' => $invoice->recipient_tax_id, 'payment_method' => $invoice->payment_method, 'paid_total' => $invoice->paid_total, 'balance' => $invoice->balance, 'items' => $invoice->items->map(fn ($item) => ['position' => $item->position, 'description' => $item->description, 'quantity' => $item->quantity, 'unit_price' => $item->unit_price, 'discount' => $item->discount, 'tax_category' => $item->tax_category->value])->all()]);
    }

    private function internalNumber(string $prefix): string
    {
        return $prefix === 'C'
            ? 'C-'.random_int(100000000, 999999999)
            : (string) random_int(1000000000, 9999999999);
    }

    private function canonicalJson(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    private function audit(Invoice $invoice, User $user, string $action, array $payload): void
    {
        InvoiceAudit::create(['invoice_id' => $invoice->id, 'user_id' => $user->id, 'action' => $action, 'payload' => $payload, 'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent()]);
    }
}
