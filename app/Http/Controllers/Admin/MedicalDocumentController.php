<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\MedicalDocumentStatus;
use App\Enums\MedicalDocumentType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\CorrectMedicalDocumentRequest;
use App\Http\Requests\ReviewMedicalDocumentRequest;
use App\Http\Requests\StoreMedicalDocumentRequest;
use App\Jobs\ProcessMedicalDocument;
use App\Models\DocumentVersion;
use App\Models\Invoice;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\PdfTemplate;
use App\Services\MedicalDocuments\DocumentHashService;
use App\Services\MedicalDocuments\MedicalDocumentAuditService;
use App\Services\MedicalDocuments\MedicalDocumentConsistencyService;
use App\Services\MedicalDocuments\MedicalDocumentIssueService;
use App\Services\MedicalDocuments\MedicalDocumentRevisionRenderService;
use App\Services\MedicalDocuments\MedicalDocumentRevisionService;
use App\Support\InstitutionalMedicalProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MedicalDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', MedicalDocument::class);
        $search = trim($request->string('search')->toString());
        $documents = MedicalDocument::query()->accessibleTo($request->user())->with(['patient:id,first_name,last_name', 'doctor:id,first_name,last_name'])
            ->when($request->user()->role === UserRole::DOCTOR, fn ($q) => $q->where('doctor_id', $request->user()->doctor?->id))
            ->when($search, fn ($q) => $q->where(fn ($query) => $query->where('public_code', 'like', "%$search%")
                ->orWhere('original_filename', 'like', "%$search%")
                ->orWhereHas('patient', fn ($patient) => $patient->where('first_name', 'like', "%$search%")->orWhere('last_name', 'like', "%$search%"))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()->paginate(25)->withQueryString();

        return Inertia::render('Admin/Documents/Index', ['documents' => $documents, 'filters' => $request->only('search', 'status')]);
    }

    public function create(): Response
    {
        $this->authorize('create', MedicalDocument::class);

        return Inertia::render('Admin/Documents/Create', ['types' => MedicalDocumentType::cases(), 'doctors' => [app(InstitutionalMedicalProvider::class)->doctor()],
            'patients' => Patient::orderBy('last_name')->get(), 'templates' => PdfTemplate::where('is_active', true)->get()]);
    }

    public function store(StoreMedicalDocumentRequest $request, DocumentHashService $hashes, MedicalDocumentAuditService $audit): RedirectResponse
    {
        $file = $request->file('document');
        $id = (string) Str::uuid();
        $path = $file->storeAs('medical/original', $id.'-'.Str::random(16).'.pdf', config('medical_documents.disk'));
        abort_unless($path, 500, 'Unable to store document.');
        $document = MedicalDocument::forceCreate(['id' => $id, ...$request->safe()->except('document'),
            'doctor_id' => app(InstitutionalMedicalProvider::class)->doctor()->id, 'status' => MedicalDocumentStatus::DRAFT,
            'uploaded_by' => $request->user()->id, 'original_filename' => $file->getClientOriginalName(), 'original_path' => $path,
            'original_sha256' => $hashes->file(Storage::disk(config('medical_documents.disk'))->path($path))]);
        DocumentVersion::create(['medical_document_id' => $document->id, 'created_by' => $request->user()->id,
            'version' => 1, 'kind' => 'original', 'path' => $path, 'sha256' => $document->original_sha256]);
        $audit->record($document, 'uploaded', $request->user());
        $document->update(['status' => MedicalDocumentStatus::PROCESSING]);
        ProcessMedicalDocument::dispatch($document->id);

        return redirect()->route('admin.documents.review', $document);
    }

    public function review(Request $request, MedicalDocument $document): Response
    {
        $this->authorize('view', $document);

        $comparisonFields = ['consultation_date', 'consultation_time', 'age_at_consultation', 'symptoms', 'medical_reason', 'diagnosis',
            'leave_start_date', 'leave_end_date', 'leave_days', 'treatment', 'recommendations', 'observations'];
        $snapshotValues = static function (array $snapshot) use ($comparisonFields): array {
            $confirmed = $snapshot['confirmed_fields'] ?? [];
            if (is_string($confirmed)) {
                $confirmed = json_decode($confirmed, true) ?: [];
            }

            return collect($comparisonFields)->mapWithKeys(fn (string $field) => [$field => $snapshot[$field] ?? $confirmed[$field] ?? null])->all();
        };
        $revisions = MedicalDocument::query()
            ->when($document->public_code, fn ($query, $code) => $query->where('public_code', $code), fn ($query) => $query->whereKey($document->id))
            ->with(['revision.corrector:id,name', 'issuer:id,name', 'uploader:id,name'])
            ->orderBy('revision_number')->get();
        $revisionHistory = $revisions->map(function (MedicalDocument $item) use ($snapshotValues): array {
            $revision = $item->revision;
            $before = $revision ? $snapshotValues($revision->source_snapshot ?? []) : [];
            $after = $revision ? $snapshotValues($revision->current_snapshot ?? $item->getAttributes()) : [];
            $changes = collect($after)->filter(fn ($value, $field) => ($before[$field] ?? null) != $value)
                ->map(fn ($value, $field) => ['field' => $field, 'before' => $before[$field] ?? null, 'after' => $value])->values();

            return ['id' => $item->id, 'number' => $item->revision_number, 'status' => $item->status->value,
                'current' => $item->is_current_revision, 'reason' => $revision?->reason,
                'actor' => $revision?->corrector?->name ?? $item->issuer?->name ?? $item->uploader?->name,
                'created_at' => ($revision?->created_at ?? $item->issued_at ?? $item->created_at)?->toIso8601String(),
                'sha256' => $item->issued_sha256 ?: $item->original_sha256, 'changes' => $changes];
        });
        $canViewInvoices = $request->user()->can('viewAny', Invoice::class);
        $relatedInvoices = $canViewInvoices ? $document->invoices()->latest()->get(['id', 'status', 'ncf', 'issued_at'])->map(fn (Invoice $invoice) => [
            'id' => $invoice->id, 'status' => $invoice->status->value, 'ncf' => $invoice->ncf,
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'download_url' => $invoice->status === InvoiceStatus::ISSUED ? route('admin.invoices.download', $invoice) : null,
        ]) : collect();

        return Inertia::render('Admin/Documents/Review', [
            'document' => $document->load(['patient', 'doctor', 'extractions' => fn ($q) => $q->latest(), 'revision']),
            'revisionHistory' => $revisionHistory,
            'relatedInvoices' => $relatedInvoices,
            'hasIssuedInvoice' => $relatedInvoices->contains(fn (array $invoice) => $invoice['status'] === InvoiceStatus::ISSUED->value),
            'canCorrect' => $request->user()->can('correct', $document)
                && in_array($document->status, [MedicalDocumentStatus::ISSUED, MedicalDocumentStatus::REVOKED], true)
                && ! $document->reissues()->whereIn('status', [MedicalDocumentStatus::REVIEW_REQUIRED, MedicalDocumentStatus::READY, MedicalDocumentStatus::ISSUED])->exists(),
            'canCreateInvoice' => $document->status === MedicalDocumentStatus::ISSUED && $request->user()->can('create', Invoice::class),
            'doctors' => [app(InstitutionalMedicalProvider::class)->doctor()],
            'patients' => Patient::orderBy('last_name')->get(),
        ]);
    }

    public function confirm(ReviewMedicalDocumentRequest $request, MedicalDocument $document, MedicalDocumentAuditService $audit, MedicalDocumentConsistencyService $consistency, MedicalDocumentRevisionRenderService $revisionRenderer): RedirectResponse
    {
        abort_unless(in_array($document->status, [MedicalDocumentStatus::REVIEW_REQUIRED, MedicalDocumentStatus::READY], true), 422);
        $data = $request->validated();
        $old = $document->confirmed_fields ?? [];
        foreach ($data['fields'] as $field => $value) {
            if (($old[$field] ?? null) !== $value) {
                $audit->record($document, 'field_changed', $request->user(), $field, $old[$field] ?? null, $value);
            }
        }
        $document->doctor_id = app(InstitutionalMedicalProvider::class)->doctor()->id;
        $document->patient_id = $data['patient_id'] ?? $document->patient_id;
        $candidates = $document->extractions()->latest()->first()?->candidates ?? [];
        $issues = $consistency->check($candidates, $data['fields'], $document->doctor?->toArray());
        $approved = ($data['approve'] ?? false) && ! $consistency->hasBlockers($issues);
        $canonical = collect($data['fields'])->only([
            'consultation_date', 'consultation_time', 'symptoms', 'medical_reason', 'diagnosis',
            'leave_start_date', 'leave_end_date', 'leave_days', 'recommendations', 'age_at_consultation',
        ])->all();
        foreach (['issue_date' => 'consultation_date', 'start_date' => 'leave_start_date', 'end_date' => 'leave_end_date',
            'days' => 'leave_days', 'age' => 'age_at_consultation'] as $source => $target) {
            if (array_key_exists($source, $data['fields']) && ! array_key_exists($target, $canonical)) {
                $canonical[$target] = $data['fields'][$source];
            }
        }
        $document->fill([...$canonical, 'confirmed_fields' => $data['fields']]);
        $document->forceFill(['inconsistencies' => $issues, 'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(), 'status' => $approved ? MedicalDocumentStatus::READY : MedicalDocumentStatus::REVIEW_REQUIRED])->save();
        $revisionRenderer->regenerate($document);
        $audit->record($document, $approved ? 'approved' : 'review_saved', $request->user());

        return back()->with('status', $approved ? 'Document approved.' : 'Review saved; blocking issues remain.');
    }

    public function issue(Request $request, MedicalDocument $document, MedicalDocumentIssueService $service): RedirectResponse
    {
        $this->authorize('issue', $document);
        $service->issue($document, $request->user());

        return back()->with('status', 'Document issued.');
    }

    public function revoke(Request $request, MedicalDocument $document, MedicalDocumentAuditService $audit): RedirectResponse
    {
        $this->authorize('revoke', $document);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use (&$document, $request, $data): void {
            $document = MedicalDocument::query()->lockForUpdate()->findOrFail($document->id);
            abort_unless($document->status === MedicalDocumentStatus::ISSUED, 422);
            $document->forceFill(['status' => MedicalDocumentStatus::REVOKED, 'revoked_at' => now(),
                'revoked_by' => $request->user()->id, 'revocation_reason' => $data['reason']])->save();
        });
        $audit->record($document, 'revoked', $request->user(), metadata: ['reason' => $data['reason']]);

        return back()->with('status', 'Document revoked.');
    }

    public function correct(CorrectMedicalDocumentRequest $request, MedicalDocument $document, MedicalDocumentRevisionService $service): RedirectResponse
    {
        $copy = $service->create($document, $request->validated('reason'), $request->user());

        return redirect()->route('admin.documents.review', $copy);
    }
}
