<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MedicalDocumentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateMedicalDocumentRequest;
use App\Models\BillingProfile;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Services\MedicalDocuments\GenerateMedicalDocumentService;
use App\Services\MedicalDocuments\MedicalDocumentAuditService;
use App\Services\MedicalDocuments\MedicalDocumentIssueService;
use App\Services\MedicalDocuments\MedicalTextExtractionService;
use App\Services\MedicalDocuments\QuickBillingCoordinator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeneratedMedicalDocumentController extends Controller
{
    public function create(string $kind): Response
    {
        abort_unless(in_array($kind, ['constancia', 'incapacidad'], true), 404);
        $this->authorize('create', MedicalDocument::class);

        $clinics = Clinic::where('status', 'ACTIVE')
            ->when(! request()->user()->hasAnyRole(UserRole::SUPER_ADMIN), fn ($query) => $query->whereIn('id', request()->user()->accessibleClinicIds()))
            ->orderByRaw("CASE WHEN code = 'HN-08' THEN 0 ELSE 1 END")->orderBy('sort_order')->get(['id', 'name', 'department', 'address', 'status']);
        $clinic = $clinics->first();
        $profile = $clinic ? BillingProfile::query()->with('service:id,code,name,default_price,tax_type,is_active')
            ->where('clinic_id', $clinic->id)->where('certificate_kind', strtoupper($kind))->where('is_active', true)
            ->whereHas('service', fn ($query) => $query->where('is_active', true))->first() : null;

        return Inertia::render('Admin/Documents/Generate', [
            'kind' => $kind,
            'patients' => Patient::accessibleTo(request()->user())->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'document_type', 'document_number', 'birth_date', 'age'])
                ->makeVisible('document_number'),
            'provider' => config('institution.provider'),
            'clinic' => $clinic,
            'clinics' => $clinics,
            'canIssue' => request()->user()->hasAnyRole(UserRole::SUPER_ADMIN, UserRole::ADMINISTRATOR)
                || (request()->user()->role === UserRole::DOCTOR && request()->user()->doctor?->credential_number === config('institution.provider.credential_number')),
            'quickBilling' => $profile && request()->user()->can('create', Invoice::class) ? [
                'service' => $profile->service->name,
                'quantity' => $profile->default_quantity,
                'unit_price' => $profile->unitPrice(),
                'tax_category' => $profile->tax_category->value,
                'payment_method' => $profile->default_payment_method,
            ] : null,
        ]);
    }

    public function analyze(Request $request, string $kind, MedicalTextExtractionService $service): JsonResponse
    {
        abort_unless(in_array($kind, ['constancia', 'incapacidad'], true), 404);
        $this->authorize('create', MedicalDocument::class);
        $validated = $request->validate(['text' => ['required', 'string', 'max:12000']]);
        $analysis = $service->extract($validated['text'], $kind);
        $identity = $analysis['fields']['identity'];
        $patient = $identity ? Patient::accessibleTo($request->user())->get()->first(
            fn (Patient $candidate) => preg_replace('/\D+/', '', (string) $candidate->document_number) === $identity
        ) : null;

        return response()->json($analysis + ['patient' => $patient ? [
            'id' => $patient->id,
            'name' => trim($patient->first_name.' '.$patient->last_name),
            'document_number' => $patient->document_number,
        ] : null]);
    }

    public function store(
        GenerateMedicalDocumentRequest $request,
        string $kind,
        GenerateMedicalDocumentService $service,
        MedicalDocumentIssueService $issuer,
        MedicalDocumentAuditService $audit,
        QuickBillingCoordinator $quickBilling,
    ): RedirectResponse {
        abort_unless(in_array($kind, ['constancia', 'incapacidad'], true), 404);
        $data = $request->safe()->except('confirm');
        abort_unless($request->user()->hasClinicAccess($data['clinic_id']), 403);
        $profile = null;
        if ($request->boolean('quick_invoice')) {
            $this->authorize('create', Invoice::class);
            $profile = BillingProfile::query()->with('service')
                ->where('clinic_id', $data['clinic_id'])->where('certificate_kind', strtoupper($kind))->where('is_active', true)
                ->whereHas('service', fn ($query) => $query->where('is_active', true))->first();
            abort_unless($profile, 422, 'No hay un perfil de facturación rápida activo para esta clínica y tipo de certificado.');
        }
        if (empty($data['patient_id'])) {
            abort_unless($request->boolean('create_patient'), 422, 'Debe confirmar la creación del paciente detectado.');
            [$firstName, $lastName] = $this->splitName($data['patient_name']);
            $patient = Patient::firstOrCreate(
                ['document_type' => 'DNI', 'document_number' => preg_replace('/\D+/', '', $data['identity'])],
                ['first_name' => $firstName, 'last_name' => $lastName, 'age' => $data['age_at_consultation'] ?? null],
            );
            $data['patient_id'] = $patient->id;
            if (! empty($data['clinic_id'])) {
                $patient->clinics()->syncWithoutDetaching([$data['clinic_id'] => ['first_seen_at' => now(), 'last_seen_at' => now()]]);
            }
        }
        $document = $service->generate($kind, $data, $request->user());

        if (($data['intent'] ?? 'draft') === 'issue') {
            $this->authorize('issue', $document);
            if ($profile) {
                $result = $quickBilling->issue($document, $profile, $request->user());

                return redirect()->route('admin.documents.review', $result['document'])
                    ->with('status', 'Documento emitido y borrador de factura creado. No se consumió NCF.');
            }
            $document->forceFill([
                'inconsistencies' => [],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'status' => MedicalDocumentStatus::READY,
            ])->save();
            $audit->record($document, 'approved', $request->user(), metadata: ['explicit_fast_issue_confirmation' => true]);
            $issuer->issue($document, $request->user());

            return redirect()->route('admin.documents.review', $document)->with('status', 'Documento firmado y emitido. El PDF, QR, hash y versión quedaron registrados.');
        }

        return redirect()->route('admin.documents.review', $document)->with('status', 'Borrador generado. Requiere revisión y emisión autorizada.');
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $middle = max(1, (int) ceil(count($parts) / 2));

        return [implode(' ', array_slice($parts, 0, $middle)), implode(' ', array_slice($parts, $middle)) ?: 'No indicado'];
    }
}
