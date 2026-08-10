<?php

declare(strict_types=1);

use App\Services\MedicalDocuments\MedicalDocumentConsistencyService;
use App\Services\MedicalDocuments\MedicalDocumentParser;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

if ($argc !== 2 || ! is_file($argv[1])) {
    fwrite(STDERR, "Usage: php scripts/inspect-local-qa-text.php <extracted-text>\n");
    exit(2);
}

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$text = file_get_contents($argv[1]);
if ($text === false) {
    throw new RuntimeException('Unable to read extracted text.');
}

$candidates = $app->make(MedicalDocumentParser::class)->parse($text);
$issues = $app->make(MedicalDocumentConsistencyService::class)->check($candidates);
$fields = [];
foreach ($candidates as $candidate) {
    $fields[$candidate->field][$candidate->source] = ($fields[$candidate->field][$candidate->source] ?? 0) + 1;
}

echo json_encode([
    'text_characters' => mb_strlen($text),
    'candidate_count' => count($candidates),
    'candidate_fields_and_sources' => $fields,
    'issue_codes' => array_values(array_unique(array_column($issues, 'code'))),
    'has_blockers' => $app->make(MedicalDocumentConsistencyService::class)->hasBlockers($issues),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
