<?php

namespace App\Services\Fiscal;

use App\Enums\FiscalAuthorizationStatus;
use App\Models\Clinic;
use App\Models\FiscalAuthorization;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportReferenceInvoiceAuthorization
{
    public function import(?string $clinicCode = null): FiscalAuthorization
    {
        $reference = config('fiscal_reference.reference_invoice_import');
        $clinic = Clinic::query()->where('code', $clinicCode ?: $reference['clinic_code'])->first();

        if (! $clinic) {
            throw new RuntimeException('The target clinic does not exist. Seed clinics first or pass --clinic=CODE.');
        }

        return DB::transaction(function () use ($clinic, $reference) {
            $authorization = FiscalAuthorization::query()
                ->where('cai', $reference['cai'])
                ->where('rtn', $reference['rtn'])
                ->where(function ($query) use ($reference) {
                    $query->where(function ($exact) use ($reference) {
                        $exact->where('full_range_start', $reference['full_range_start'])
                            ->where('full_range_end', $reference['full_range_end']);
                    })->orWhere(function ($legacy) use ($reference) {
                        $legacy->where('establishment', $reference['establishment'])
                            ->where('point_of_issue', $reference['point_of_issue'])
                            ->where('ncf_prefix', $reference['ncf_prefix'])
                            ->where('range_start', $reference['sequence_start'])
                            ->where('range_end', $reference['sequence_end']);
                    });
                })
                ->lockForUpdate()
                ->first();

            $values = [
                'document_type' => $reference['document_type'],
                'ncf_type' => $reference['ncf_type'],
                'establishment' => $reference['establishment'],
                'point_of_issue' => $reference['point_of_issue'],
                'ncf_prefix' => $reference['ncf_prefix'],
                'range_start' => $reference['sequence_start'],
                'range_end' => $reference['sequence_end'],
                'full_range_start' => $reference['full_range_start'],
                'full_range_end' => $reference['full_range_end'],
                'number_padding' => $reference['number_padding'],
                'valid_until' => $reference['valid_until'],
                'source' => $reference['source'],
            ];

            if ($authorization) {
                $authorization->fill($values)->save();

                return $authorization->refresh();
            }

            $lastIssued = Invoice::query()
                ->where('clinic_id', $clinic->id)
                ->whereNotNull('ncf')
                ->where('ncf', 'like', $reference['ncf_prefix'].'%')
                ->pluck('ncf')
                ->map(fn (string $ncf) => (int) substr($ncf, -$reference['number_padding']))
                ->filter(fn (int $sequence) => $sequence >= $reference['sequence_start'] && $sequence <= $reference['sequence_end'])
                ->max();

            return FiscalAuthorization::create($values + [
                'clinic_id' => $clinic->id,
                'cai' => $reference['cai'],
                'rtn' => $reference['rtn'],
                'next_number' => $lastIssued ? $lastIssued + 1 : $reference['sequence_start'],
                'valid_from' => today(),
                'status' => FiscalAuthorizationStatus::ACTIVE,
                'is_active' => true,
                'activated_at' => now(),
            ]);
        });
    }
}
