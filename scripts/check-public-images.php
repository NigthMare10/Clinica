<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$scanDirectories = [$root.'/resources', $root.'/database/seeders'];
$errors = [];
$assets = [];

foreach ($scanDirectories as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (! $file->isFile() || ! in_array($file->getExtension(), ['vue', 'ts', 'css', 'php'], true)) {
            continue;
        }
        $contents = file_get_contents($file->getPathname()) ?: '';
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if (preg_match_all('/<img\b[^>]*>/iu', $contents, $tags)) {
            foreach ($tags[0] as $tag) {
                if (! preg_match('/\balt\s*=\s*["\'][^"\']*["\']/iu', $tag)) {
                    $errors[] = "{$relative}: imagen sin atributo alt";
                }
            }
        }
        $imageScan = str_replace('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', '', $contents);
        if (preg_match('/https?:\/\/(?:images\.unsplash\.com|[^\s"\']+\.(?:png|jpe?g|webp|gif|svg))(?:[?\s"\']|$)/iu', $imageScan, $remote)) {
            $errors[] = "{$relative}: dependencia remota de imagen {$remote[0]}";
        }
        if (preg_match_all('/["\'](\/images\/[^"\'?]+(?:\?[^"\']*)?)["\']/u', $contents, $matches)) {
            foreach ($matches[1] as $asset) {
                $assets[$asset] = $relative;
            }
        }
    }
}

foreach ($assets as $asset => $source) {
    $path = $root.'/public'.parse_url($asset, PHP_URL_PATH);
    $realPublic = realpath($root.'/public');
    $real = realpath($path);
    if (! $real || ! is_file($real) || ! str_starts_with($real, (string) $realPublic)) {
        $errors[] = "{$source}: no existe {$asset}";

        continue;
    }
    if (strtolower(pathinfo($real, PATHINFO_EXTENSION)) === 'svg') {
        $svg = file_get_contents($real) ?: '';
        if (! str_contains($svg, '<svg') || ! preg_match('/\bviewBox=/i', $svg) || preg_match('/<script|\bon\w+=|<foreignObject|https?:\/\//i', str_replace('http://www.w3.org/2000/svg', '', $svg))) {
            $errors[] = "{$source}: SVG inválido o inseguro {$asset}";
        }

        continue;
    }
    $size = @getimagesize($real);
    if (! $size || $size[0] < 1 || $size[1] < 1) {
        $errors[] = "{$source}: imagen no decodificable o sin dimensiones {$asset}";
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
    exit(1);
}

echo sprintf('OK: %d referencias locales verificadas; sin imágenes remotas.%s', count($assets), PHP_EOL);
