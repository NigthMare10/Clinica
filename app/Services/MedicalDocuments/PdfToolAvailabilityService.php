<?php

namespace App\Services\MedicalDocuments;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class PdfToolAvailabilityService
{
    public function path(string $tool): ?string
    {
        $configured = (string) config("medical_documents.binaries.$tool");
        if ($configured === '') {
            return null;
        }
        if (is_file($configured)) {
            return $configured;
        }

        $found = (new ExecutableFinder)->find($configured);
        if ($found) {
            return $found;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = match ($tool) {
                'qpdf' => glob('C:/Program Files/qpdf */bin/qpdf.exe') ?: [],
                'tesseract' => ['C:/Program Files/Tesseract-OCR/tesseract.exe'],
                'pdftotext', 'pdftoppm', 'pdfinfo' => glob((getenv('LOCALAPPDATA') ?: '').'/Microsoft/WinGet/Packages/oschwartz10612.Poppler_*/poppler-*/Library/bin/'.$tool.'.exe') ?: [],
                default => [],
            };
            foreach (array_reverse($candidates) as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    public function available(string $tool): bool
    {
        return $this->path($tool) !== null;
    }

    public function report(): array
    {
        $result = [];
        foreach (array_keys(config('medical_documents.binaries')) as $tool) {
            $path = $this->path($tool);
            $version = null;
            if ($path) {
                $argument = in_array($tool, ['pdftotext', 'pdftoppm', 'pdfinfo'], true) ? '-v' : '--version';
                $process = new Process([$path, $argument]);
                $process->setTimeout(10)->run();
                $version = trim($process->getOutput() ?: $process->getErrorOutput());
            }
            $result[$tool] = ['found' => (bool) $path, 'path' => $path, 'version' => $version];
        }

        return $result;
    }
}
