<?php

namespace App\Services\Fiscal;

use App\Services\MedicalDocuments\PdfToolAvailabilityService;
use RuntimeException;
use Symfony\Component\Process\Process;

class InvoicePdfInfoService
{
    public function __construct(private PdfToolAvailabilityService $tools) {}

    public function assertOnePage(string $path): void
    {
        $pdfinfo = $this->tools->path('pdfinfo') ?? throw new RuntimeException('pdfinfo is unavailable for fiscal PDF validation.');
        $process = new Process([$pdfinfo, $path]);
        $process->setTimeout(config('medical_documents.process_timeout'))->run();
        if (! $process->isSuccessful() || ! preg_match('/^Pages:\s+1\s*$/mi', $process->getOutput())) {
            throw new RuntimeException('The fiscal PDF must have exactly one page according to pdfinfo.');
        }
    }
}
