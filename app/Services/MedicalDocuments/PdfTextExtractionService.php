<?php

namespace App\Services\MedicalDocuments;

use RuntimeException;
use Symfony\Component\Process\Process;

class PdfTextExtractionService
{
    public function __construct(private PdfToolAvailabilityService $tools) {}

    public function extract(string $pdf): string
    {
        $binary = $this->tools->path('pdftotext') ?? throw new RuntimeException('pdftotext is unavailable.');
        $process = new Process([$binary, '-layout', '-enc', 'UTF-8', $pdf, '-']);
        $process->setTimeout(config('medical_documents.process_timeout'))->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('PDF text extraction failed.');
        }

        return $process->getOutput();
    }

    public function quality(string $text): float
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return 0.0;
        }
        $length = mb_strlen($trimmed);
        $readable = preg_match_all('/[\p{L}\p{N}\s.,:;\/-]/u', $trimmed);
        $wordCount = preg_match_all('/\p{L}{2,}/u', $trimmed);

        return round(min(1, (($readable / max(1, $length)) * .7) + (min(1, $wordCount / 30) * .3)), 2);
    }
}
