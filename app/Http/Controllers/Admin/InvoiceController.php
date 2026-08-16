<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CorrectInvoiceRequest;
use App\Http\Requests\IssueInvoiceRequest;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Requests\VoidInvoiceRequest;
use App\Models\BillingService;
use App\Models\Clinic;
use App\Models\FiscalAuthorization;
use App\Models\Invoice;
use App\Models\InvoiceAudit;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Services\Fiscal\InvoiceDraftService;
use App\Services\Fiscal\InvoiceIssueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        $clinicIds = $request->user()->hasAnyRole('SUPER_ADMIN') ? Clinic::pluck('id') : $request->user()->accessibleClinicIds();
        $invoices = Invoice::query()->accessibleTo($request->user())->with(['patient:id,first_name,last_name', 'medicalDocument:id,public_code', 'clinic:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('clinic_id'), fn ($query) => $query->where('clinic_id', $request->string('clinic_id')))
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($search) => $search->where('ncf', 'like', '%'.$request->string('q').'%')->orWhere('recipient_name', 'like', '%'.$request->string('q').'%')->orWhereHas('patient', fn ($patient) => $patient->where('first_name', 'like', '%'.$request->string('q').'%')->orWhere('last_name', 'like', '%'.$request->string('q').'%'))))
            ->latest()->paginate(25)->withQueryString();

        return Inertia::render('Admin/Invoices/Index', [
            'invoices' => $invoices,
            'clinics' => Clinic::query()->whereIn('id', $clinicIds)->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['status', 'clinic_id', 'q']),
            'canCreate' => $request->user()->hasAnyRole('SUPER_ADMIN', 'ADMINISTRATOR', 'DOCUMENT_OPERATOR'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Invoice::class);
        $clinicIds = $request->user()->hasAnyRole('SUPER_ADMIN') ? Clinic::pluck('id') : $request->user()->accessibleClinicIds();
        $draftInvoice = null;
        if ($request->filled('invoice_id')) {
            $draftInvoice = Invoice::query()->accessibleTo($request->user())->with('items')->findOrFail($request->string('invoice_id'));
            $this->authorize('update', $draftInvoice);
        }

        return Inertia::render('Admin/Invoices/Create', [
            'clinics' => Clinic::query()->whereIn('id', $clinicIds)->orderBy('name')->get(['id', 'name']),
            'patients' => Patient::query()->accessibleTo($request->user())->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'document_number'])->makeVisible('document_number'),
            'documents' => MedicalDocument::query()->accessibleTo($request->user())->where('is_current_revision', true)->whereNotNull('public_code')->with('patient:id,first_name,last_name,document_number')->latest()->get(['id', 'clinic_id', 'patient_id', 'public_code', 'consultation_date', 'consultation_time']),
            'sourceDocumentId' => $request->string('medical_document_id')->toString(),
            'draftInvoice' => $draftInvoice,
            'services' => BillingService::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'default_price', 'tax_type']),
        ]);
    }

    public function store(StoreInvoiceRequest $request, InvoiceDraftService $drafts): JsonResponse
    {
        $this->authorize('create', Invoice::class);
        $data = $request->validated();
        abort_unless($request->user()->hasClinicAccess($data['clinic_id']), 403);
        if (! empty($data['patient_id'])) {
            abort_unless(Patient::query()->accessibleTo($request->user())->whereKey($data['patient_id'])->exists(), 422, 'El paciente no está disponible para esta clínica.');
        }
        if (! empty($data['medical_document_id'])) {
            abort_unless(MedicalDocument::query()->accessibleTo($request->user())->whereKey($data['medical_document_id'])->where('clinic_id', $data['clinic_id'])->exists(), 422, 'El documento médico no pertenece a la clínica seleccionada.');
        }
        $invoice = $drafts->create($data, $request->user(), ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return response()->json($invoice->load('items'), 201);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return Inertia::render('Admin/Invoices/Show', [
            'invoice' => $invoice->load(['items', 'authorization', 'audits.user:id,name', 'patient:id,first_name,last_name', 'clinic:id,name', 'medicalDocument:id,public_code,status,age_at_consultation,leave_start_date,leave_end_date,leave_days'])->makeVisible('recipient_tax_id'),
            'authorizations' => FiscalAuthorization::query()
                ->whereHas('clinic', fn ($query) => $query->where('code', config('fiscal_reference.reference_invoice_import.central_clinic_code')))
                ->latest()->get(['id', 'ncf_prefix', 'next_number', 'range_end', 'valid_from', 'valid_until', 'status', 'is_active']),
            'canIssue' => $request->user()->can('issue', $invoice),
            'canVoid' => $request->user()->can('void', $invoice),
            'canUpdate' => $request->user()->can('update', $invoice),
            'canCorrect' => $request->user()->can('correct', $invoice),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice, InvoiceDraftService $drafts): JsonResponse
    {
        $this->authorize('update', $invoice);
        abort_unless($invoice->status === InvoiceStatus::DRAFT, 403);
        $data = $request->validated();
        abort_unless($request->user()->hasClinicAccess($data['clinic_id']), 403);
        if (! empty($data['patient_id'])) {
            abort_unless(Patient::query()->accessibleTo($request->user())->whereKey($data['patient_id'])->exists(), 422, 'El paciente no está disponible para esta clínica.');
        }
        if (! empty($data['medical_document_id'])) {
            abort_unless(MedicalDocument::query()->accessibleTo($request->user())->whereKey($data['medical_document_id'])->where('clinic_id', $data['clinic_id'])->exists(), 422, 'El documento médico no pertenece a la clínica seleccionada.');
        }

        return response()->json($drafts->update($invoice, $data, $request->user(), ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]));
    }

    public function issue(IssueInvoiceRequest $request, Invoice $invoice, InvoiceIssueService $service): JsonResponse
    {
        $this->authorize('issue', $invoice);
        try {
            $result = $service->issue($invoice, $request->user(), $request->validated('fiscal_authorization_id'));
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['items' => $exception->getMessage()]);
        }

        return response()->json(['invoice' => $result['invoice']->load('items'), 'qr_token' => $result['qr_token'], 'verification_url' => route('public.invoice.verify', $result['qr_token']), 'download_url' => route('admin.invoices.download', $invoice)]);
    }

    public function download(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        $path = $invoice->getRawOriginal('issued_path');
        abort_unless(is_string($path) && str_starts_with($path, 'fiscal/invoices/') && ! str_contains($path, '..'), 404);
        $disk = Storage::disk(config('invoice_pdf.disk'));
        abort_unless($disk->exists($path), 404);
        $invoice->loadMissing('patient');
        InvoiceAudit::create([
            'invoice_id' => $invoice->id, 'user_id' => $request->user()->id, 'action' => 'PDF_DOWNLOADED',
            'payload' => ['issued_hash' => $invoice->issued_hash], 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);

        $recipient = $invoice->patient ? trim($invoice->patient->first_name.' '.$invoice->patient->last_name) : ($invoice->recipient_name ?: 'Consumidor_Final');
        $date = ($invoice->issued_at ?? $invoice->created_at)->format('Y-m-d');

        return $disk->download($path, 'Factura_'.$this->filenamePart($recipient).'_'.$date.'.pdf', [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private, max-age=0', 'Pragma' => 'no-cache', 'Expires' => '0',
        ]);
    }

    private function filenamePart(string $value): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', Str::ascii($value)), '_') ?: 'Consumidor_Final';
    }

    public function void(VoidInvoiceRequest $request, Invoice $invoice, InvoiceIssueService $service): JsonResponse
    {
        $this->authorize('void', $invoice);

        return response()->json($service->void($invoice, $request->user(), $request->validated('reason')));
    }

    public function correct(CorrectInvoiceRequest $request, Invoice $invoice, InvoiceIssueService $service): JsonResponse
    {
        $this->authorize('correct', $invoice);
        abort_unless($invoice->status === InvoiceStatus::ISSUED, 403);

        return response()->json($service->correct($invoice, $request->user(), $request->validated('reason')));
    }
}
