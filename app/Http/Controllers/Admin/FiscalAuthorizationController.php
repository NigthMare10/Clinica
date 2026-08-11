<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFiscalAuthorizationRequest;
use App\Http\Requests\UpsertBillingProfileRequest;
use App\Models\BillingProfile;
use App\Models\BillingService;
use App\Models\Clinic;
use App\Models\FiscalAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FiscalAuthorizationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole('SUPER_ADMIN', 'ADMINISTRATOR'), 403);
        $clinicIds = $request->user()->hasAnyRole('SUPER_ADMIN') ? Clinic::pluck('id') : $request->user()->accessibleClinicIds();

        $authorizations = FiscalAuthorization::query()
            ->whereIn('clinic_id', $clinicIds)
            ->with('clinic:id,name')
            ->latest()
            ->get()
            ->map(function (FiscalAuthorization $authorization): array {
                $total = max(0, $authorization->range_end - $authorization->range_start + 1);
                $consumed = min($total, max(0, $authorization->next_number - $authorization->range_start));

                return [
                    'id' => $authorization->id,
                    'clinic_id' => $authorization->clinic_id,
                    'clinic' => $authorization->clinic,
                    'cai' => $authorization->cai,
                    'rtn' => $authorization->rtn,
                    'document_type' => $authorization->document_type,
                    'status' => $authorization->status->value,
                    'is_active' => $authorization->is_active,
                    'source' => $authorization->source,
                    'full_range_start' => $authorization->rangeStartNcf(),
                    'full_range_end' => $authorization->rangeEndNcf(),
                    'next_ncf' => $authorization->formatNcf($authorization->next_number),
                    'available_count' => max(0, $authorization->range_end - $authorization->next_number + 1),
                    'consumption_percentage' => $total === 0 ? 0 : round(($consumed / $total) * 100, 2),
                    'valid_from' => $authorization->valid_from?->toDateString(),
                    'valid_until' => $authorization->valid_until?->toDateString(),
                ];
            });

        return Inertia::render('Admin/FiscalSettings/Index', [
            'clinics' => Clinic::query()->whereIn('id', $clinicIds)->orderBy('name')->get(['id', 'name']),
            'authorizations' => $authorizations,
            'billingProfiles' => BillingProfile::query()
                ->whereIn('clinic_id', $clinicIds)
                ->whereIn('certificate_kind', ['CONSTANCIA', 'INCAPACIDAD'])
                ->with('service:id,code,name,default_price,tax_type')
                ->get(),
        ]);
    }

    public function store(StoreFiscalAuthorizationRequest $request): JsonResponse
    {
        $data = $request->validated();
        abort_unless($request->user()->hasClinicAccess($data['clinic_id']), 403);
        $data['next_number'] = $data['range_start'];
        $data['full_range_start'] = $data['ncf_prefix'].str_pad((string) $data['range_start'], $data['number_padding'] ?? 8, '0', STR_PAD_LEFT);
        $data['full_range_end'] = $data['ncf_prefix'].str_pad((string) $data['range_end'], $data['number_padding'] ?? 8, '0', STR_PAD_LEFT);
        $data['created_by'] = $request->user()->id;
        $data['activated_at'] = now();
        $authorization = FiscalAuthorization::create($data);

        return response()->json($authorization, 201);
    }

    public function upsertBillingProfile(UpsertBillingProfileRequest $request): JsonResponse
    {
        $data = $request->validated();
        abort_unless($request->user()->hasClinicAccess($data['clinic_id']), 403);

        $profile = DB::transaction(function () use ($data): BillingProfile {
            $service = BillingService::query()->updateOrCreate(
                ['code' => $data['service_code']],
                [
                    'name' => $data['service_name'],
                    'default_price' => $data['price'],
                    'tax_type' => $data['tax_category'],
                    'is_active' => true,
                ],
            );

            return BillingProfile::query()->updateOrCreate(
                ['clinic_id' => $data['clinic_id'], 'certificate_kind' => $data['kind']],
                [
                    'billing_service_id' => $service->id,
                    'default_quantity' => $data['quantity'],
                    'price_override' => $data['price'],
                    'tax_category' => $data['tax_category'],
                    'default_payment_method' => $data['default_payment_method'],
                    'is_active' => true,
                ],
            );
        });

        return response()->json($profile->load('service:id,code,name,default_price,tax_type'));
    }
}
