<?php

declare(strict_types=1);

use App\Services\MedicalDocuments\PdfEncryptionService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php scripts/decrypt-local-qa-pdf.php <input> <output>\n");
    exit(2);
}

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$app->make(PdfEncryptionService::class)->decrypt($argv[1], $argv[2]);

fwrite(STDOUT, "PDF decrypted for local QA.\n");
