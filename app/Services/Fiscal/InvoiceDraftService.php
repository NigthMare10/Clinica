<?php

namespace App\Services\Fiscal;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvoiceDraftService
{
    public function __construct(private TaxCalculationService $taxes, private InvoiceMedicalDocumentSnapshotService $documentSnapshots) {}

    public function create(array $data, User $user, array $auditContext = []): Invoice
    {
        $items = $data['items'];
        unset($data['items']);
        $calculation = $this->taxes->calculate($items);
        $total = (float) $calculation['totals']['total'];
        // A normal cash sale is fully paid unless the operator explicitly records another amount.
        $paid = array_key_exists('paid_total', $data) && $data['paid_total'] !== null
            ? (float) $data['paid_total']
            : $total;
        $data['paid_total'] = number_format($paid, 2, '.', '');
        $data['balance'] = number_format(max($total - $paid, 0), 2, '.', '');

        return DB::transaction(function () use ($data, $items, $calculation, $user, $auditContext): Invoice {
            $data = $this->documentSnapshots->apply($data);
            if ($data['medical_document_id'] ?? null) {
                $items = array_map(fn (array $item) => array_replace($item, ['medical_document_id' => $data['medical_document_id']]), $items);
            }
            $invoice = Invoice::create($data + ['created_by' => $user->id]);
            $invoice->forceFill($calculation['totals'])->save();
            foreach ($items as $position => $item) {
                $line = $calculation['lines'][$position];
                $invoice->items()->create($item + [
                    'position' => $position + 1,
                    'tax_rate' => $line['tax_rate'],
                    'net_amount' => $line['net'],
                    'tax_amount' => $line['tax'],
                    'total_amount' => $line['total'],
                ]);
            }
            InvoiceAudit::create([
                'invoice_id' => $invoice->id,
                'user_id' => $user->id,
                'action' => 'CREATED',
                'payload' => ['items' => count($items)],
                'ip_address' => $auditContext['ip_address'] ?? null,
                'user_agent' => $auditContext['user_agent'] ?? null,
            ]);

            return $invoice;
        });
    }

    public function update(Invoice $invoice, array $data, User $user, array $auditContext = []): Invoice
    {
        $items = $data['items'];
        unset($data['items']);
        $calculation = $this->taxes->calculate($items);
        $total = (float) $calculation['totals']['total'];
        $paid = array_key_exists('paid_total', $data) && $data['paid_total'] !== null
            ? (float) $data['paid_total']
            : $total;
        $data['paid_total'] = number_format($paid, 2, '.', '');
        $data['balance'] = number_format(max($total - $paid, 0), 2, '.', '');

        return DB::transaction(function () use ($invoice, $data, $items, $calculation, $user, $auditContext): Invoice {
            $invoice = Invoice::query()->with('items')->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status !== InvoiceStatus::DRAFT) {
                throw new \DomainException('Only draft invoices can be updated.');
            }

            // A linked document owns its snapshot and cannot be detached by an invoice edit.
            if ($invoice->medical_document_id) {
                $data['medical_document_id'] = $invoice->medical_document_id;
            }
            $data = $this->documentSnapshots->apply($data);
            if ($data['medical_document_id'] ?? null) {
                $items = array_map(fn (array $item) => array_replace($item, ['medical_document_id' => $data['medical_document_id']]), $items);
            }
            $invoice->fill($data);
            $changed = array_keys($invoice->getDirty());
            $invoice->save();
            $invoice->forceFill($calculation['totals'])->save();
            $invoice->items()->delete();

            foreach ($items as $position => $item) {
                $line = $calculation['lines'][$position];
                $invoice->items()->create($item + [
                    'position' => $position + 1,
                    'tax_rate' => $line['tax_rate'],
                    'net_amount' => $line['net'],
                    'tax_amount' => $line['tax'],
                    'total_amount' => $line['total'],
                ]);
            }
            InvoiceAudit::create([
                'invoice_id' => $invoice->id,
                'user_id' => $user->id,
                'action' => 'UPDATED',
                'payload' => ['fields' => $changed, 'items' => count($items)],
                'ip_address' => $auditContext['ip_address'] ?? null,
                'user_agent' => $auditContext['user_agent'] ?? null,
            ]);

            return $invoice->load('items');
        });
    }
}
