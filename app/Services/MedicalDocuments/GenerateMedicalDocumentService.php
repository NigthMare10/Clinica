<?php

namespace App\Services\MedicalDocuments;

use App\Enums\MedicalDocumentStatus;
use App\Enums\MedicalDocumentType;
use App\Models\Clinic;
use App\Models\DocumentVersion;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\PdfTemplate;
use App\Models\User;
use App\Support\InstitutionalMedicalProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GenerateMedicalDocumentService
{
    public function __construct(
        private PdfTemplateRenderService $renderer,
        private DocumentHashService $hashes,
        private MedicalDocumentAuditService $audit,
        private InstitutionalMedicalProvider $provider,
    ) {}

    public function generate(string $kind, array $data, User $user): MedicalDocument
    {
        $patient = Patient::findOrFail($data['patient_id']);
        $doctor = $this->provider->doctor();
        $clinic = Clinic::findOrFail($data['clinic_id']);
        abort_unless($user->hasClinicAccess($clinic->id), 403);
        abort_unless($clinic->status === 'ACTIVE', 422, 'The selected clinic is not active for document generation.');
        $template = isset($data['pdf_template_id']) ? PdfTemplate::where('is_active', true)->findOrFail($data['pdf_template_id']) : null;
        abort_if($template?->certificate_kind && $template->certificate_kind !== strtoupper($kind), 422, 'The template does not match the certificate kind.');
        abort_if($template?->clinic_id && $template->clinic_id !== $clinic->id, 422, 'The template does not belong to the selected clinic.');
        abort_if($template?->document_type && $template->document_type !== MedicalDocumentType::MEDICAL_CERTIFICATE->value, 422, 'The template does not support medical certificates.');

        $id = (string) Str::uuid();
        // The system TEMP directory may be unavailable to the web-server user on Windows.
        $temporaryDirectory = storage_path('app/private/tmp');
        if (! is_dir($temporaryDirectory) && ! mkdir($temporaryDirectory, 0700, true) && ! is_dir($temporaryDirectory)) {
            abort(500, 'Unable to prepare document generation.');
        }
        $temporary = tempnam($temporaryDirectory, 'csa-generated-');
        abort_if($temporary === false, 500, 'Unable to prepare document generation.');
        $path = 'medical/original/'.$id.'-generated.pdf';
        $stored = false;

        try {
            $this->renderer->render($kind, $patient, $doctor, $clinic, $data, $temporary, $template);
            $hash = $this->hashes->file($temporary);
            $stream = fopen($temporary, 'rb');
            abort_if($stream === false, 500, 'Unable to read generated document.');
            try {
                $stored = Storage::disk(config('medical_documents.disk'))->put($path, $stream);
            } finally {
                fclose($stream);
            }
            abort_unless($stored, 500, 'Unable to store generated document.');

            return DB::transaction(function () use ($id, $kind, $data, $user, $path, $hash, $template, $patient, $doctor, $clinic): MedicalDocument {
                $fields = [
                    'patient_name' => trim($patient->first_name.' '.$patient->last_name),
                    'patient_document' => $patient->document_number,
                    'age_at_consultation' => $data['age_at_consultation'] ?? $patient->age,
                    'issue_date' => $data['consultation_date'],
                    'consultation_date' => $data['consultation_date'],
                    'consultation_time' => $data['consultation_time'] ?? null,
                    'doctor_name' => config('institution.provider.name'),
                    'doctor_credential' => config('institution.provider.credential_number'),
                    'clinic_name' => $clinic->name,
                    'medical_reason' => $data['medical_reason'] ?? $data['free_text'],
                    'symptoms' => $data['symptoms'] ?? null,
                    'diagnosis' => $data['diagnosis'] ?? null,
                    'recommendations' => $data['recommendations'] ?? null,
                    'free_text' => $data['free_text'] ?? null,
                    'start_date' => $data['leave_start_date'] ?? null,
                    'end_date' => $data['leave_end_date'] ?? null,
                    'days' => $data['leave_days'] ?? null,
                ];
                $document = MedicalDocument::forceCreate([
                    'id' => $id,
                    'type' => MedicalDocumentType::MEDICAL_CERTIFICATE,
                    'status' => MedicalDocumentStatus::REVIEW_REQUIRED,
                    'source_kind' => 'GENERATED',
                    'certificate_kind' => strtoupper($kind),
                    'clinic_id' => $clinic->id,
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'pdf_template_id' => $template?->id,
                    'uploaded_by' => $user->id,
                    'original_filename' => $kind.'-'.$id.'.pdf',
                    'original_path' => $path,
                    'original_sha256' => $hash,
                    'consultation_date' => $data['consultation_date'],
                    'consultation_time' => $data['consultation_time'] ?? null,
                    'age_at_consultation' => $data['age_at_consultation'] ?? $patient->age,
                    'symptoms' => $data['symptoms'] ?? null,
                    'medical_reason' => $data['medical_reason'] ?? $data['free_text'],
                    'diagnosis' => $data['diagnosis'] ?? null,
                    'recommendations' => $data['recommendations'] ?? null,
                    'leave_start_date' => $data['leave_start_date'] ?? null,
                    'leave_end_date' => $data['leave_end_date'] ?? null,
                    'leave_days' => $data['leave_days'] ?? null,
                    'confirmed_fields' => $fields,
                    'template_snapshot' => ($template ? ['id' => $template->id, 'version' => $template->version, 'coordinates' => $template->coordinates] : ['renderer' => 'institutional-certificate-v2']) + [
                        'free_text' => $data['free_text'] ?? null,
                        // A document snapshot needs display metadata, never administrative credentials.
                        'institution' => array_replace(Arr::except(config('institution'), ['admin']), [
                            'address' => $clinic->address ?: config('institution.address'),
                        ]),
                    ],
                    'generated_at' => now(),
                ]);
                DocumentVersion::create(['medical_document_id' => $document->id, 'created_by' => $user->id, 'version' => 1,
                    'kind' => 'original', 'path' => $path, 'sha256' => $hash, 'metadata' => ['source_kind' => 'GENERATED', 'certificate_kind' => strtoupper($kind)]]);
                $this->audit->record($document, 'generated', $user, metadata: ['certificate_kind' => strtoupper($kind), 'template_version' => $template?->version]);

                return $document;
            });
        } catch (Throwable $exception) {
            if ($stored) {
                Storage::disk(config('medical_documents.disk'))->delete($path);
            }
            throw $exception;
        } finally {
            @unlink($temporary);
        }
    }
}
