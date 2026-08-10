<?php

declare(strict_types=1);

use App\Services\MedicalDocuments\PdfTextExtractionService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

if ($argc !== 3 || ! is_file($argv[1]) || ! is_dir(dirname($argv[2]))) {
    fwrite(STDERR, "Usage: php scripts/extract-local-qa-text.php <input-pdf> <output-text>\n");
    exit(2);
}

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$text = $app->make(PdfTextExtractionService::class)->extract($argv[1]);

if (file_put_contents($argv[2], $text, LOCK_EX) === false) {
    throw new RuntimeException('Unable to write the local QA extraction.');
}

fwrite(STDOUT, json_encode([
    'text_characters' => mb_strlen($text),
    'output_bytes' => filesize($argv[2]),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);
