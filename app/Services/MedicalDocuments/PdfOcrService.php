<?php

namespace App\Services\MedicalDocuments;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class PdfOcrService
{
    public function __construct(private PdfToolAvailabilityService $tools) {}

    public function extract(string $pdf, string $workingDirectory): string
    {
        $pdftoppm = $this->tools->path('pdftoppm') ?? throw new RuntimeException('pdftoppm is unavailable.');
        $tesseract = $this->tools->path('tesseract') ?? throw new RuntimeException('tesseract is unavailable.');
        File::ensureDirectoryExists($workingDirectory);
        $prefix = $workingDirectory.DIRECTORY_SEPARATOR.'page';
        $render = new Process([$pdftoppm, '-png', '-r', '200', $pdf, $prefix]);
        $render->setTimeout(config('medical_documents.process_timeout'))->run();
        if (! $render->isSuccessful()) {
            throw new RuntimeException('PDF image rendering failed.');
        }
        $text = [];
        foreach (glob($prefix.'-*.png') ?: [] as $image) {
            $ocr = new Process([$tesseract, $image, 'stdout', '-l', config('medical_documents.ocr_languages')]);
            $ocr->setTimeout(config('medical_documents.process_timeout'))->run();
            if ($ocr->isSuccessful()) {
                $text[] = $ocr->getOutput();
            }
        }
        if ($text === []) {
            throw new RuntimeException('OCR produced no readable pages.');
        }

        return implode("\n\f\n", $text);
    }
}
