<?php

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php validate-vite-release.php <release>\n");
    exit(2);
}

$public = rtrim($argv[1], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build';
$manifest = $public.DIRECTORY_SEPARATOR.'manifest.json';
if (! is_file($manifest) || ! is_readable($manifest)) {
    fwrite(STDERR, "Missing or unreadable manifest.\n");
    exit(1);
}
$entries = json_decode((string) file_get_contents($manifest), true);
if (! is_array($entries) || json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, "Invalid manifest JSON.\n");
    exit(1);
}
$assets = [];
foreach ($entries as $entry) {
    foreach (array_merge([$entry['file'] ?? null], $entry['css'] ?? []) as $asset) {
        if (is_string($asset) && $asset !== '') {
            $assets[] = $asset;
        }
    }
}
$missing = array_filter(array_unique($assets), fn ($asset) => ! is_file($public.DIRECTORY_SEPARATOR.$asset) || ! is_readable($public.DIRECTORY_SEPARATOR.$asset));
if ($missing !== []) {
    fwrite(STDERR, "Missing Vite assets:\n".implode("\n", $missing)."\n");
    exit(1);
}
echo "Vite manifest valid: ".count($assets)." referenced assets.\n";
