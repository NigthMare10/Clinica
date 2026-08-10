<?php

namespace App\Jobs;

use App\Enums\MedicalDocumentStatus;
use App\Models\DocumentExtraction;
use App\Models\MedicalDocument;
use App\Services\MedicalDocuments\MedicalDocumentConsistencyService;
use App\Services\MedicalDocuments\MedicalDocumentParser;
use App\Services\MedicalDocuments\PdfEncryptionService;
use App\Services\MedicalDocuments\PdfOcrService;
use App\Services\MedicalDocuments\PdfStampService;
use App\Services\MedicalDocuments\PdfTextExtractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessMedicalDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public string $documentId) {}

    public function handle(PdfTextExtractionService $textService, PdfOcrService $ocr, MedicalDocumentParser $parser,
        MedicalDocumentConsistencyService $consistency, PdfEncryptionService $encryption, PdfStampService $stamp): void
    {
        $document = MedicalDocument::findOrFail($this->documentId);
        if ($document->status !== MedicalDocumentStatus::PROCESSING) {
            return;
        }
        if (! is_string($document->original_path) || ! str_starts_with($document->original_path, 'medical/original/') || str_contains($document->original_path, '..')) {
            $document->update(['status' => MedicalDocumentStatus::FAILED,
                'processing_metadata' => ['failed_at' => now()->toIso8601String(), 'error' => 'Invalid original document path.']]);

            return;
        }
        $original = Storage::disk(config('medical_documents.disk'))->path($document->original_path);
        $directory = storage_path('app/tmp/'.Str::uuid());
        mkdir($directory, 0700, true);
        $working = $original;
        $warnings = [];
        try {
            $head = file_get_contents($original, false, null, 0, 65536) ?: '';
            if (str_contains($head, '/Encrypt')) {
                $working = $directory.DIRECTORY_SEPARATOR.'decrypted.pdf';
                $encryption->decrypt($original, $working);
            }
            $document->update(['digital_signature_detected' => $stamp->hasDigitalSignature($working)]);
            try {
                $text = $textService->extract($working);
                $quality = $textService->quality($text);
                $engine = 'pdftotext';
            } catch (Throwable $e) {
                $text = '';
                $quality = 0.0;
                $engine = 'none';
                $warnings[] = $e->getMessage();
            }
            if ($quality < config('medical_documents.text_quality_threshold')) {
                try {
                    $text = $ocr->extract($working, $directory.DIRECTORY_SEPARATOR.'ocr');
                    $quality = $textService->quality($text);
                    $engine = 'ocr';
                } catch (Throwable $e) {
                    $warnings[] = $e->getMessage();
                }
            }
            if (trim($text) === '') {
                throw new \RuntimeException('No extraction tool produced document text.');
            }
            $candidates = array_map(fn ($candidate) => $candidate->toArray(), $parser->parse($text));
            $issues = $consistency->check($candidates, doctor: $document->doctor?->toArray());
            DocumentExtraction::create(['medical_document_id' => $document->id, 'engine' => $engine, 'raw_text' => $text,
                'quality_score' => $quality, 'candidates' => $candidates, 'warnings' => $warnings]);
            $document->update(['status' => MedicalDocumentStatus::REVIEW_REQUIRED, 'inconsistencies' => $issues,
                'processing_metadata' => ['engine' => $engine, 'quality' => $quality, 'warnings' => $warnings, 'processed_at' => now()->toIso8601String()]]);
        } catch (Throwable $e) {
            $document->update(['status' => MedicalDocumentStatus::FAILED,
                'processing_metadata' => ['failed_at' => now()->toIso8601String(), 'error' => $e->getMessage(), 'warnings' => $warnings]]);
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
