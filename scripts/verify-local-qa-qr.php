<?php

declare(strict_types=1);

use App\Models\MedicalDocument;
use App\Models\PdfTemplate;
use App\Services\MedicalDocuments\PdfQrVerificationService;
use App\Services\MedicalDocuments\PdfStampService;
use App\Services\MedicalDocuments\QrCodeService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

if ($argc !== 3 || ! is_file($argv[1]) || ! is_dir(dirname($argv[2]))) {
    fwrite(STDERR, "Usage: php scripts/verify-local-qa-qr.php <decrypted-input> <output>\n");
    exit(2);
}

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$workingDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'clinic-qr-'.bin2hex(random_bytes(8));
if (! mkdir($workingDirectory, 0700, true) && ! is_dir($workingDirectory)) {
    throw new RuntimeException('Unable to create the local QA working directory.');
}

$template = (new PdfTemplate)->forceFill([
    'qr_page' => 1,
    'qr_x' => 165,
    'qr_y' => 220,
    'qr_width' => 28,
    'qr_height' => 28,
    'coordinates' => [
        'qr' => ['x' => 165, 'y' => 220, 'width' => 28, 'height' => 28],
        'code' => ['x' => 150, 'y' => 253, 'font_size' => 8],
    ],
]);
$document = (new MedicalDocument)->forceFill([
    'public_code' => 'LOCAL-QA-NOT-ISSUED',
    'digital_signature_detected' => false,
]);
$document->setRelation('template', $template);

$qr = $app->make(QrCodeService::class);
$token = $qr->token();
$url = $qr->verificationUrl($token);
$qrPath = $workingDirectory.DIRECTORY_SEPARATOR.'qr.png';

try {
    $qr->write($url, $qrPath);
    $page = $app->make(PdfStampService::class)->stamp($document, $argv[1], $argv[2], $qrPath);
    $app->make(PdfQrVerificationService::class)->assertReadable($argv[2], $page, $url, $workingDirectory, $document);

    echo json_encode([
        'qr_readable' => true,
        'page' => $page,
        'coordinates_mm' => $template->coordinates,
        'output_bytes' => filesize($argv[2]),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
} finally {
    foreach (glob($workingDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($workingDirectory);
}
