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
use App\Http\Requests\UpdateIssuedMedicalDocumentRequest;
use App\Jobs\ProcessMedicalDocument;
use App\Models\DocumentVersion;
use App\Models\Invoice;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\PdfTemplate;
use App\Services\Fiscal\InvoiceMedicalDocumentSnapshotService;
use App\Services\MedicalDocuments\DocumentHashService;
use App\Services\MedicalDocuments\MedicalDocumentAuditService;
use App\Services\MedicalDocuments\MedicalDocumentConsistencyService;
use App\Services\MedicalDocuments\MedicalDocumentIssueService;
use App\Services\MedicalDocuments\MedicalDocumentRevisionRenderService;
use App\Services\MedicalDocuments\MedicalDocumentRevisionService;
use App\Services\MedicalDocuments\MedicalDocumentEditorTextService;
use App\Services\MedicalDocuments\MedicalTextExtractionService;
use App\Support\InstitutionalMedicalProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
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
            ->latest()->paginate(25, ['id', 'patient_id', 'doctor_id', 'type', 'certificate_kind', 'source_kind', 'status', 'public_code', 'original_filename', 'created_at'])->withQueryString();
        $documents->through(fn (MedicalDocument $document) => [
            ...$document->toArray(),
            'can_edit' => $request->user()->can('correct', $document)
                && in_array($document->status, [MedicalDocumentStatus::ISSUED, MedicalDocumentStatus::REVOKED], true),
        ]);

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
            'canViewInternalHistory' => $request->user()->hasAnyRole(UserRole::SUPER_ADMIN, UserRole::ADMINISTRATOR),
            'doctors' => [app(InstitutionalMedicalProvider::class)->doctor()],
            'patients' => Patient::orderBy('last_name')->get(),
        ]);
    }

    public function edit(Request $request, MedicalDocument $document, MedicalDocumentEditorTextService $editorText): Response|RedirectResponse
    {
        $this->authorize('correct', $document);
        abort_unless(in_array($document->status, [MedicalDocumentStatus::ISSUED, MedicalDocumentStatus::REVOKED], true), 422);

        $current = MedicalDocument::query()->where('public_code', $document->public_code)->where('is_current_revision', true)->first();
        if ($current && $current->id !== $document->id) {
            return redirect()->route('admin.documents.edit', $current);
        }

        $document->load(['patient', 'clinic', 'revision']);
        $fields = $document->confirmed_fields ?? [];
        $revisionFields = $document->revision?->current_snapshot['confirmed_fields'] ?? [];
        $sourceText = $editorText->clean((string) ($revisionFields['free_text'] ?? $fields['source_text'] ?? $fields['free_text'] ?? $document->medical_reason ?? ''));
        $invoices = $request->user()->can('viewAny', Invoice::class) ? $document->invoices()->latest()->get(['id', 'status', 'ncf']) : collect();

        return Inertia::render('Admin/Documents/Edit', [
            'document' => $document,
            'previewUrl' => route('admin.documents.preview', $document),
            'sourceText' => $sourceText,
            'fields' => [
                'patient_name' => trim($document->patient?->first_name.' '.$document->patient?->last_name),
                'identity' => $document->patient?->document_number,
                'age_at_consultation' => $document->age_at_consultation ?? $document->patient?->age,
                'consultation_date' => $document->consultation_date?->format('d/m/Y'),
                'consultation_time' => $document->consultation_time ? substr($document->consultation_time, 0, 5) : null,
                'diagnosis' => $document->diagnosis ?? $fields['diagnosis'] ?? null,
                'leave_days' => $document->leave_days ?? $fields['leave_days'] ?? null,
                'leave_start_date' => $document->leave_start_date?->format('d/m/Y'),
                'leave_end_date' => $document->leave_end_date?->format('d/m/Y'),
                'recommendations' => $document->recommendations ?? $fields['recommendations'] ?? null,
            ],
            'currentRevisionId' => $document->id,
            'invoice' => $invoices->first(fn (Invoice $invoice) => $invoice->status === InvoiceStatus::ISSUED) ?? $invoices->first(),
        ]);
    }

    public function analyzeEdit(Request $request, MedicalDocument $document, MedicalTextExtractionService $extractor, MedicalDocumentEditorTextService $editorText): JsonResponse
    {
        $this->authorize('correct', $document);
        $data = $request->validate(['source_text' => ['required', 'string', 'max:12000'], 'fields' => ['required', 'array']]);
        $data['source_text'] = $editorText->clean($data['source_text']);
        $analysis = $extractor->extract($data['source_text'], strtolower((string) ($document->certificate_kind ?: 'constancia')));
        $mapped = [
            'patient_name' => $analysis['fields']['patient_name'], 'identity' => $analysis['fields']['identity'],
            'age_at_consultation' => $analysis['fields']['age'], 'consultation_date' => $this->displayDate($analysis['fields']['consultation_date']),
            'consultation_time' => $analysis['fields']['consultation_time'], 'diagnosis' => $analysis['fields']['diagnosis'],
            'leave_days' => $analysis['fields']['leave_days'], 'leave_start_date' => $this->displayDate($analysis['fields']['leave_start_date']),
            'leave_end_date' => $this->displayDate($analysis['fields']['leave_end_date']), 'recommendations' => $analysis['fields']['recommendations'],
        ];
        $changes = collect($mapped)->filter(fn ($value, $field) => $value !== null && (string) $value !== (string) ($data['fields'][$field] ?? null))
            ->map(fn ($value, $field) => ['field' => $field, 'before' => $data['fields'][$field] ?? null, 'after' => $value])->values();

        return response()->json(['fields' => $mapped, 'changes' => $changes]);
    }

    public function previewEdit(Request $request, MedicalDocument $document, MedicalDocumentRevisionRenderService $renderer, MedicalDocumentEditorTextService $editorText)
    {
        $this->authorize('correct', $document);
        $data = $request->validate(['source_text' => ['required', 'string', 'max:12000'], 'fields' => ['required', 'array']]);

        return response($renderer->preview($document, [...$data['fields'], 'source_text' => $editorText->clean($data['source_text'])]), 200, [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'no-store, private, max-age=0', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updateIssued(UpdateIssuedMedicalDocumentRequest $request, MedicalDocument $document, MedicalDocumentRevisionService $revisions,
        MedicalDocumentRevisionRenderService $renderer, MedicalDocumentIssueService $issuer, InvoiceMedicalDocumentSnapshotService $invoices,
        MedicalDocumentEditorTextService $editorText): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $data['source_text'] = $editorText->clean($data['source_text']);
        if ($data['current_revision_id'] !== $document->id) {
            return $this->editFailure($request, 'Este documento fue actualizado en otra sesión. Recarga antes de guardar.', 409);
        }
        try {
            logger()->info('correction.start', ['document_id' => $document->id, 'public_code' => $document->public_code]);
            $fields = array_filter($data['fields'], static fn ($value) => $value !== null && $value !== '');
            $fields = array_replace($document->confirmed_fields ?? [], $fields);
            $data['source_text'] = $editorText->clean($data['source_text']);
            $dates = ['consultation_date', 'leave_start_date', 'leave_end_date'];
            foreach ($dates as $field) {
                if (! empty($fields[$field])) {
                    $fields[$field] = \Carbon\CarbonImmutable::createFromFormat('d/m/Y', $fields[$field])->toDateString();
                }
            }
            if (! empty($fields['leave_days']) && ! empty($fields['leave_start_date']) && ! empty($fields['leave_end_date'])) {
                $days = \Carbon\CarbonImmutable::parse($fields['leave_start_date'])->diffInDays(\Carbon\CarbonImmutable::parse($fields['leave_end_date']), true) + 1;
                if ($days !== (int) $fields['leave_days']) {
                    return $this->editFailure($request, 'Los días de incapacidad no coinciden con las fechas seleccionadas.', 422, 'INCAPACITY_RANGE_MISMATCH');
                }
            }
            logger()->info('correction.validation.pass', ['document_id' => $document->id]);
            $copy = $revisions->create($document, $data['reason'], $request->user(), $document->revision_number);
            logger()->info('correction.revision.prepare', ['document_id' => $copy->id, 'revision' => $copy->revision_number]);
            [$firstName, $lastName] = $this->splitName($fields['patient_name']);
            $patient = $copy->patient;
            $patientData = ['first_name' => $firstName, 'last_name' => $lastName, 'age' => $fields['age_at_consultation'] ?? null];
            if (! empty($fields['identity'])) {
                $patientData['document_number'] = preg_replace('/\D+/', '', $fields['identity']);
            }
            $patient->fill($patientData)->save();
            $confirmed = array_replace($copy->confirmed_fields ?? [], $fields, ['free_text' => $data['source_text'], 'source_text' => $data['source_text']]);
            $copy->fill([
                'age_at_consultation' => $fields['age_at_consultation'] ?? null, 'consultation_date' => $fields['consultation_date'] ?? null,
                'consultation_time' => $fields['consultation_time'] ?? null, 'diagnosis' => $fields['diagnosis'] ?? null,
                'leave_days' => $fields['leave_days'] ?? null, 'leave_start_date' => $fields['leave_start_date'] ?? null,
                'leave_end_date' => $fields['leave_end_date'] ?? null, 'recommendations' => $fields['recommendations'] ?? null,
                'medical_reason' => $data['source_text'], 'confirmed_fields' => $confirmed,
            ])->save();
            logger()->info('correction.pdf.start', ['document_id' => $copy->id]);
            $renderer->regenerate($copy);
            logger()->info('correction.pdf.generated', ['document_id' => $copy->id]);
            $copy->forceFill(['inconsistencies' => [], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'status' => MedicalDocumentStatus::READY])->save();
            $issued = $issuer->issue($copy, $request->user());
            logger()->info('correction.revision.activated', ['document_id' => $issued->id, 'revision' => $issued->revision_number]);
            $invoices->synchronizeDrafts($issued, $request->user(), ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
            logger()->info('correction.success', ['document_id' => $issued->id]);
        } catch (\Throwable $exception) {
            report($exception);

            logger()->warning('correction.failed', ['document_id' => $document->id, 'error_code' => 'PDF_REGENERATION_FAILED', 'exception' => $exception::class]);
            return $this->editFailure($request, 'No fue posible regenerar el documento. La versión anterior continúa vigente.', 422, 'PDF_REGENERATION_FAILED');
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'document_id' => $issued->id, 'public_code' => $issued->public_code,
                'redirect_url' => route('admin.documents.edit', $issued)]);
        }

        return redirect()->route('admin.documents.edit', $issued)->with('status', 'DOCUMENTO ACTUALIZADO');
    }

    private function editFailure(Request $request, string $message, int $status, ?string $code = null): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(array_filter(['message' => $message, 'error_code' => $code]), $status);
        }

        return back()->withErrors(['source_text' => $message]);
    }

    private function displayDate(?string $date): ?string
    {
        return $date ? \Carbon\CarbonImmutable::parse($date)->format('d/m/Y') : null;
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $middle = max(1, (int) ceil(count($parts) / 2));

        return [implode(' ', array_slice($parts, 0, $middle)), implode(' ', array_slice($parts, $middle)) ?: 'No indicado'];
    }

    public function confirm(ReviewMedicalDocumentRequest $request, MedicalDocument $document, MedicalDocumentAuditService $audit, MedicalDocumentConsistencyService $consistency, MedicalDocumentRevisionRenderService $revisionRenderer, InvoiceMedicalDocumentSnapshotService $invoiceSnapshots): RedirectResponse
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
        if ($approved && $document->reissue_of_id) {
            $invoiceSnapshots->synchronizeDrafts($document, $request->user(), ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        }
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
        $copy = $service->create($document, $request->validated('reason'), $request->user(), $request->integer('expected_revision') ?: null);

        return redirect()->route('admin.documents.review', $copy);
    }
}
