<?php

namespace App\Services\MedicalDocuments;

use App\Models\DocumentVersion;
use App\Models\MedicalDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MedicalDocumentRevisionRenderService
{
    public function __construct(
        private PdfTemplateRenderService $renderer,
        private DocumentHashService $hashes,
    ) {}

    public function regenerate(MedicalDocument $document): void
    {
        if (! $document->reissue_of_id) {
            return;
        }

        $document->loadMissing(['patient', 'doctor', 'clinic', 'template']);
        $directory = storage_path('app/private/tmp');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Unable to prepare corrected document rendering.');
        }
        $temporary = tempnam($directory, 'csa-correction-');
        if ($temporary === false) {
            throw new \RuntimeException('Unable to prepare corrected document rendering.');
        }

        $path = 'medical/original/'.$document->id.'-revision-'.Str::random(16).'.pdf';
        try {
            $fields = ($document->confirmed_fields ?? []) + [
                'consultation_date' => $document->consultation_date?->toDateString(),
                'consultation_time' => $document->consultation_time,
                'age_at_consultation' => $document->age_at_consultation,
                'medical_reason' => $document->medical_reason,
                'free_text' => $document->confirmed_fields['free_text'] ?? $document->medical_reason,
                'leave_start_date' => $document->leave_start_date?->toDateString(),
                'leave_end_date' => $document->leave_end_date?->toDateString(),
                'leave_days' => $document->leave_days,
            ];
            $kind = strtolower((string) ($document->certificate_kind ?: 'constancia'));
            $this->renderer->render($kind, $document->patient, $document->doctor, $document->clinic, $fields, $temporary, $document->template);
            $disk = Storage::disk(config('medical_documents.disk'));
            if (! $disk->put($path, file_get_contents($temporary))) {
                throw new \RuntimeException('Unable to store corrected document source.');
            }
            $hash = $this->hashes->file($disk->path($path));
            $document->forceFill(['original_path' => $path, 'original_sha256' => $hash])->save();
            DocumentVersion::create(['medical_document_id' => $document->id, 'created_by' => auth()->id(), 'version' => ((int) $document->versions()->max('version')) + 1, 'kind' => 'original', 'path' => $path, 'sha256' => $hash, 'metadata' => ['correction' => true]]);
        } catch (\Throwable $exception) {
            Storage::disk(config('medical_documents.disk'))->delete($path);
            throw $exception;
        } finally {
            @unlink($temporary);
        }
    }

    /** Render an unsaved edit without creating a document version or touching private storage. */
    public function preview(MedicalDocument $document, array $fields): string
    {
        $document->loadMissing(['patient', 'doctor', 'clinic', 'template']);
        $directory = storage_path('app/private/tmp');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Unable to prepare document preview.');
        }
        $temporary = tempnam($directory, 'csa-preview-');
        if ($temporary === false) {
            throw new \RuntimeException('Unable to prepare document preview.');
        }

        try {
            $this->renderer->render(strtolower((string) ($document->certificate_kind ?: 'constancia')), $document->patient,
                $document->doctor, $document->clinic, $fields + ['free_text' => $fields['source_text'] ?? ''], $temporary, $document->template);

            return (string) file_get_contents($temporary);
        } finally {
            @unlink($temporary);
        }
    }
}
