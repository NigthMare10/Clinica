<?php

use App\Models\DocumentVerificationLog;
use App\Models\DocumentVersion;
use App\Models\Invoice;
use App\Models\InvoiceAudit;
use App\Models\InvoiceItem;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Services\Fiscal\ImportReferenceInvoiceAuthorization;
use App\Services\MedicalDocuments\PdfToolAvailabilityService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('fiscal:import-reference-invoice {--clinic=HN-08 : Target clinic code}', function (ImportReferenceInvoiceAuthorization $importer): int {
    $authorization = $importer->import((string) $this->option('clinic'));
    $this->info('Fiscal reference authorization is configured: '.$authorization->full_range_start.' to '.$authorization->full_range_end);

    return 0;
})->purpose('Idempotently import the reference invoice fiscal authorization');

Artisan::command('clinic:document-tools', function (PdfToolAvailabilityService $tools): int {
    foreach ($tools->report() as $tool => $report) {
        $this->newLine();
        $this->line(strtoupper($tool).': '.($report['found'] ? '<info>FOUND</info>' : '<error>MISSING</error>'));
        $this->line('Path: '.($report['path'] ?: '-'));
        $this->line('Version: '.($report['version'] ?: '-'));
    }

    return 0;
})->purpose('Report local document-rendering tool availability');

Artisan::command('clinic:qa-counts', function (): int {
    $this->line(json_encode([
        'patients' => Patient::count(),
        'medical_documents' => MedicalDocument::count(),
        'document_versions' => DocumentVersion::count(),
        'invoices' => Invoice::count(),
        'invoice_items' => InvoiceItem::count(),
        'invoice_audits' => InvoiceAudit::count(),
        'document_verification_logs' => DocumentVerificationLog::count(),
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Report persistent QA record counts without modifying data');

Artisan::command('clinic:diagnose', function (): int {
    $expected = [
        'PHP temporary directory' => storage_path('runtime/tmp'),
        'Upload temporary directory' => storage_path('runtime/uploads'),
        'Process runtime directory' => storage_path('runtime/process'),
    ];
    $configured = [
        'PHP temporary directory' => sys_get_temp_dir(),
        'Upload temporary directory' => (string) ini_get('upload_tmp_dir'),
        'Process runtime directory' => storage_path('runtime/process'),
    ];
    $failed = false;

    $samePath = static function (string $actual, string $wanted): bool {
        $actualPath = realpath($actual);
        $wantedPath = realpath($wanted);

        return $actualPath !== false
            && $wantedPath !== false
            && strcasecmp(str_replace('\\', '/', $actualPath), str_replace('\\', '/', $wantedPath)) === 0;
    };

    foreach ($expected as $label => $directory) {
        $actual = $configured[$label];
        $pathMatches = $samePath($actual, $directory);
        $writable = is_dir($directory) && is_writable($directory);
        $probe = $directory.DIRECTORY_SEPARATOR.'.clinic-diagnose-'.bin2hex(random_bytes(6));
        $written = $writable && @file_put_contents($probe, 'clinic-runtime-ok') === 17;
        $deleted = $written && @unlink($probe);
        $passed = $pathMatches && $writable && $written && $deleted;
        $failed = $failed || ! $passed;

        $this->line(($passed ? '<info>PASS</info>' : '<error>FAIL</error>')." $label");
        $this->line('  Configured: '.($actual !== '' ? $actual : '(empty)'));
        $this->line("  Expected:   $directory");
        $this->line('  Write/delete: '.($written && $deleted ? 'PASS' : 'FAIL'));
    }

    $process = new Process([
        PHP_BINARY,
        '-r',
        'fwrite(STDOUT, getcwd()."|process-ok");',
    ], $expected['Process runtime directory']);
    $process->setTimeout(10)->run();
    $processOutput = trim($process->getOutput());
    $processPassed = $process->isSuccessful()
        && str_ends_with(str_replace('\\', '/', $processOutput), '/storage/runtime/process|process-ok');
    $failed = $failed || ! $processPassed;

    $this->line(($processPassed ? '<info>PASS</info>' : '<error>FAIL</error>').' Symfony Process execution');
    $this->line('  Command: '.PHP_BINARY.' -r <probe>');
    $this->line('  Output:  '.($processOutput !== '' ? $processOutput : '(empty)'));
    if (! $processPassed && trim($process->getErrorOutput()) !== '') {
        $this->line('  Error:   '.trim($process->getErrorOutput()));
    }

    return $failed ? 1 : 0;
})->purpose('Verify the local PHP temporary and process runtime');
