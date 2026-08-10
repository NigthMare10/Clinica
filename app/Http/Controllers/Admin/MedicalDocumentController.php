<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MedicalDocumentStatus;
use App\Enums\MedicalDocumentType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewMedicalDocumentRequest;
use App\Http\Requests\StoreMedicalDocumentRequest;
use App\Jobs\ProcessMedicalDocument;
use App\Models\DocumentVersion;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\PdfTemplate;
use App\Services\MedicalDocuments\DocumentHashService;
use App\Services\MedicalDocuments\MedicalDocumentAuditService;
use App\Services\MedicalDocuments\MedicalDocumentConsistencyService;
use App\Services\MedicalDocuments\MedicalDocumentIssueService;
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

    public function review(MedicalDocument $document): Response
    {
        $this->authorize('view', $document);

        return Inertia::render('Admin/Documents/Review', ['document' => $document->load(['patient', 'doctor', 'extractions' => fn ($q) => $q->latest()]),
            'doctors' => [app(InstitutionalMedicalProvider::class)->doctor()], 'patients' => Patient::orderBy('last_name')->get()]);
    }

    public function confirm(ReviewMedicalDocumentRequest $request, MedicalDocument $document, MedicalDocumentAuditService $audit, MedicalDocumentConsistencyService $consistency): RedirectResponse
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

    public function reissue(Request $request, MedicalDocument $document, MedicalDocumentAuditService $audit): RedirectResponse
    {
        $this->authorize('issue', $document);
        $copy = DB::transaction(function () use ($document, $request) {
            $source = MedicalDocument::query()->lockForUpdate()->findOrFail($document->id);
            abort_unless(in_array($source->status, [MedicalDocumentStatus::ISSUED, MedicalDocumentStatus::REVOKED], true), 422);
            abort_if($source->reissues()->whereNotIn('status', [MedicalDocumentStatus::FAILED->value, MedicalDocumentStatus::REVOKED->value])->exists(), 422, 'An active replacement already exists.');
            $copy = $source->replicate(['status', 'token_hash', 'public_code', 'issued_path', 'issued_sha256', 'issued_by', 'issued_at',
                'revoked_by', 'revoked_at', 'revocation_reason', 'reviewed_by', 'reviewed_at', 'replaced_by_id',
                'processing_metadata', 'inconsistencies', 'digital_signature_detected']);
            $copy->forceFill(['id' => (string) Str::uuid(), 'status' => MedicalDocumentStatus::PROCESSING,
                'reissue_of_id' => $source->id, 'uploaded_by' => $request->user()->id])->save();

            return $copy;
        });
        DocumentVersion::create(['medical_document_id' => $copy->id, 'created_by' => $request->user()->id,
            'version' => 1, 'kind' => 'original', 'path' => $copy->original_path, 'sha256' => $copy->original_sha256,
            'metadata' => ['reused_immutable_original_from' => $document->id]]);
        $audit->record($copy, 'reissue_created', $request->user(), metadata: ['source_id' => $document->id]);
        ProcessMedicalDocument::dispatch($copy->id);

        return redirect()->route('admin.documents.review', $copy);
    }
}
