<?php

use App\Models\BillingProfile;
use App\Models\BillingService;
use App\Models\Clinic;
use App\Models\DocumentVerificationLog;
use App\Models\DocumentVersion;
use App\Models\Invoice;
use App\Models\InvoiceAudit;
use App\Models\InvoiceItem;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\User;
use App\Services\Fiscal\ImportReferenceInvoiceAuthorization;
use App\Services\MedicalDocuments\InstitutionalAssetService;
use App\Services\MedicalDocuments\PdfToolAvailabilityService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Process\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('fiscal:import-reference-invoice {--clinic=HN-08 : Target clinic code}', function (ImportReferenceInvoiceAuthorization $importer): int {
    $authorization = $importer->import((string) $this->option('clinic'));
    $this->info('Fiscal reference authorization is configured: '.$authorization->full_range_start.' to '.$authorization->full_range_end);

    return 0;
})->purpose('Idempotently import the reference invoice fiscal authorization');

Artisan::command('clinic:import-fiscal-reference {--clinic=HN-08 : Target clinic code}', function (ImportReferenceInvoiceAuthorization $importer): int {
    $authorization = $importer->import((string) $this->option('clinic'));
    $this->info('EXISTS/UPDATED: '.$authorization->rangeStartNcf().' to '.$authorization->rangeEndNcf().' | Next: '.$authorization->formatNcf($authorization->next_number));

    return 0;
})->purpose('Idempotently import Clínica Santa Ana fiscal reference data');

Artisan::command('clinic:import-signature-stamp', function (InstitutionalAssetService $assets): int {
    $source = base_path('docs/SantaAna_Firma_Sello/firma_sello_combinado_transparente.png');
    if (! is_file($source)) {
        $this->error('MISSING: authorized transparent signature-stamp source is unavailable.');

        return 1;
    }
    $user = User::query()->whereIn('role', ['SUPER_ADMIN', 'ADMINISTRATOR'])->first();
    if (! $user) {
        $this->error('MISSING: an administrator is required to audit this import.');

        return 1;
    }
    $asset = $assets->store(new UploadedFile($source, basename($source), 'image/png', null, true), InstitutionalAssetService::SIGNATURE_STAMP_COMBINED, $user, true);
    $this->info('IMPORTED: '.$asset->id.' (active combined signature and stamp).');

    return 0;
})->purpose('Import the authorized transparent signature and stamp from docs into private storage');

Artisan::command('clinic:configure-billing-profiles {--clinic=HN-08 : Target clinic code}', function (): int {
    $clinic = Clinic::query()->where('code', (string) $this->option('clinic'))->first();
    if (! $clinic) {
        $this->error('MISSING: target clinic does not exist.');

        return 1;
    }
    $service = BillingService::query()->updateOrCreate(['code' => 'CONSULTA_MEDICA'], [
        'name' => 'Consulta médica', 'description' => 'Precio QA configurable desde administración.',
        'default_price' => 1200, 'tax_type' => 'EXENTO', 'is_active' => true,
    ]);
    foreach (['CONSTANCIA', 'INCAPACIDAD', 'CONSULTA_MEDICA'] as $kind) {
        BillingProfile::query()->updateOrCreate(['clinic_id' => $clinic->id, 'certificate_kind' => $kind], [
            'billing_service_id' => $service->id, 'default_quantity' => 1, 'price_override' => 1200,
            'tax_category' => 'EXENTO', 'default_payment_method' => 'EFECTIVO', 'is_active' => true,
        ]);
    }
    $this->info('UPDATED: CONSTANCIA_MEDICA, INCAPACIDAD_MEDICA and CONSULTA_MEDICA billing profiles.');

    return 0;
})->purpose('Create/update configurable Clínica Santa Ana quick billing profiles');

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

Artisan::command('clinic:cleanup-temporary-files {--dry-run : List stale temporary entries without deleting them} {--hours=24 : Minimum age in hours}', function (): int {
    $cutoff = now()->subHours(max(1, (int) $this->option('hours')))->getTimestamp();
    $roots = [
        storage_path('app/tmp'),
        storage_path('app/private/tmp'),
        storage_path('runtime/tmp'),
        storage_path('runtime/uploads'),
        storage_path('runtime/process'),
    ];
    $removed = 0;

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }
        $rootPath = realpath($root);
        foreach (File::allFiles($root) as $file) {
            $path = $file->getRealPath();
            if ($file->getFilename()[0] === '.' || $path === false || $rootPath === false || ! str_starts_with($path, $rootPath.DIRECTORY_SEPARATOR) || $file->getMTime() > $cutoff) {
                continue;
            }
            $this->line(($this->option('dry-run') ? 'WOULD_DELETE ' : 'DELETE ').str_replace(storage_path().DIRECTORY_SEPARATOR, '', $path));
            if (! $this->option('dry-run')) {
                File::delete($path);
            }
            $removed++;
        }
        if (! $this->option('dry-run')) {
            collect(File::directories($root))->sortByDesc(fn (string $path) => substr_count($path, DIRECTORY_SEPARATOR))->each(fn (string $path) => File::isEmptyDirectory($path) ? File::deleteDirectory($path) : null);
        }
    }

    $this->info(($this->option('dry-run') ? 'Dry run: ' : 'Cleanup: ').$removed.' stale temporary file(s).');

    return 0;
})->purpose('Safely remove stale files only from Clinic temporary directories');

Schedule::command('clinic:cleanup-temporary-files')->dailyAt('03:20')->withoutOverlapping();
