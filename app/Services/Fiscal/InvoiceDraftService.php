<?php

namespace App\Services\Fiscal;

use App\Models\Invoice;
use App\Models\InvoiceAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvoiceDraftService
{
    public function __construct(private TaxCalculationService $taxes) {}

    public function create(array $data, User $user, array $auditContext = []): Invoice
    {
        $items = $data['items'];
        unset($data['items']);
        $calculation = $this->taxes->calculate($items);
        $paid = number_format((float) ($data['paid_total'] ?? 0), 2, '.', '');
        $data['balance'] = number_format(max(0, (float) $calculation['totals']['total'] - (float) $paid), 2, '.', '');

        return DB::transaction(function () use ($data, $items, $calculation, $user, $auditContext): Invoice {
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
}
