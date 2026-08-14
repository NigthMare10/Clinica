<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientRequest;
use App\Models\Invoice;
use App\Models\MedicalDocument;
use App\Models\Patient;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Patient::class);

        $patients = Patient::accessibleTo(request()->user())->orderBy('last_name')->paginate(30)->through(fn (Patient $patient) => [
            'id' => $patient->id,
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'document_type' => $patient->document_type,
            'document_number' => $patient->document_number,
            'birth_date' => $patient->birth_date?->toDateString(),
            'age' => $patient->age,
            'sex' => $patient->sex,
            'email' => $patient->email,
            'phone' => $patient->phone,
        ]);

        return Inertia::render('Admin/Patients/Index', ['patients' => $patients]);
    }

    public function create()
    {
        $this->authorize('create', Patient::class);

        return Inertia::render('Admin/Patients/Create');
    }

    public function show(Patient $patient): Response
    {
        $this->authorize('view', $patient);
        $user = request()->user();
        $canViewInvoices = $user->can('viewAny', Invoice::class);
        $documents = $patient->documents()->accessibleTo($user)->where('is_current_revision', true)
            ->select(['id', 'type', 'certificate_kind', 'status', 'consultation_date', 'consultation_time', 'public_code', 'doctor_id', 'clinic_id', 'issued_at', 'created_at'])
            ->with([
                'doctor:id,first_name,last_name',
                'clinic:id,name,department',
                'invoices' => fn ($query) => $query->accessibleTo($user)
                    ->select(['id', 'medical_document_id', 'status', 'ncf', 'issued_at', 'created_at'])
                    ->orderByDesc('issued_at')->orderByDesc('created_at'),
                'reissueOf.invoices' => fn ($query) => $query->accessibleTo($user)
                    ->select(['id', 'medical_document_id', 'status', 'ncf', 'issued_at', 'created_at'])
                    ->orderByDesc('issued_at')->orderByDesc('created_at'),
            ])->withCount('verificationLogs')
            ->orderByDesc('consultation_date')->orderByDesc('issued_at')->orderByDesc('created_at')
            ->paginate(15)->through(function (MedicalDocument $document) use ($canViewInvoices): array {
                $invoices = $canViewInvoices
                    ? $document->invoices->merge($document->reissueOf?->invoices ?? collect())->unique('id')->values()
                    : collect();
                $activeInvoice = $invoices->first(fn (Invoice $invoice) => $invoice->status !== InvoiceStatus::VOID);
                $linkedInvoice = $activeInvoice ?? $invoices->first();

                return [
                    'id' => $document->id,
                    'code' => $document->public_code,
                    'type' => $document->type->value,
                    'certificate_kind' => $document->certificate_kind,
                    'status' => $document->status->value,
                    'consultation_date' => $document->consultation_date?->toDateString(),
                    'consultation_time' => $document->consultation_time,
                    'issued_at' => $document->issued_at?->toIso8601String(),
                    'verification_logs_count' => $document->verification_logs_count,
                    'doctor' => $document->doctor ? [
                        'first_name' => $document->doctor->first_name,
                        'last_name' => $document->doctor->last_name,
                    ] : null,
                    'clinic' => $document->clinic ? [
                        'department' => $document->clinic->department,
                    ] : null,
                    'invoice' => [
                        'state' => $invoices->isEmpty() ? 'none' : ($activeInvoice ? 'active' : 'voided'),
                        'historical_count' => $invoices->count() - ($activeInvoice ? 1 : 0),
                        'linked' => $linkedInvoice ? [
                            'id' => $linkedInvoice->id,
                            'ncf' => $linkedInvoice->ncf,
                            'status' => $linkedInvoice->status->value,
                            'pdf_available' => $linkedInvoice->status === InvoiceStatus::ISSUED && $linkedInvoice->issued_at !== null,
                            'is_active' => $linkedInvoice === $activeInvoice,
                        ] : null,
                    ],
                ];
            });

        return Inertia::render('Admin/Patients/Show', [
            'patient' => [
                'id' => $patient->id,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'document_type' => $patient->document_type,
                'document_number' => $patient->document_number,
                'birth_date' => $patient->birth_date?->toDateString(),
                'age' => $patient->age,
                'sex' => $patient->sex,
                'email' => $patient->email,
                'phone' => $patient->phone,
            ],
            'documents' => $documents,
        ]);
    }

    public function store(PatientRequest $request)
    {
        Patient::create($request->validated());

        return redirect()->route('admin.patients.index');
    }

    public function edit(Patient $patient)
    {
        $this->authorize('update', $patient);

        return Inertia::render('Admin/Patients/Edit', ['patient' => $patient]);
    }

    public function update(PatientRequest $request, Patient $patient)
    {
        $patient->update($request->validated());

        return redirect()->route('admin.patients.index');
    }
}
