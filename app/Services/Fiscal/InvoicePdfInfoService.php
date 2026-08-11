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
        $pdfinfo = $this->tools->path('pdfinfo');
        if ($pdfinfo) {
            $process = new Process([$pdfinfo, $path]);
            $process->setTimeout(config('medical_documents.process_timeout'))->run();
            if ($process->isSuccessful() && preg_match('/^Pages:\s+1\s*$/mi', $process->getOutput())) {
                return;
            }

            throw new RuntimeException('The fiscal PDF must have exactly one page according to pdfinfo.');
        }

        $ghostscript = $this->tools->path('gs') ?? throw new RuntimeException('No PDF page counter is available for fiscal PDF validation.');
        $escapedPath = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $path);
        $process = new Process([$ghostscript, '-q', '-dSAFER', '--permit-file-read='.$path, '-dNODISPLAY', '-c', '('.$escapedPath.') (r) file runpdfbegin pdfpagecount = quit']);
        $process->setTimeout(config('medical_documents.process_timeout'))->run();
        if (! $process->isSuccessful() || ! preg_match('/^1\s*$/m', $process->getOutput())) {
            throw new RuntimeException('The fiscal PDF must have exactly one page.');
        }
    }
}
