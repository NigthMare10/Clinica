<?php

declare(strict_types=1);

$sourcePath = dirname(__DIR__).'/docs/DrS31.png';
$outputDirectory = dirname(__DIR__).'/resources/pdf';
if (! is_file($sourcePath)) {
    throw new RuntimeException('The attached medical logo was not found.');
}
if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0755, true) && ! is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create the PDF asset directory.');
}

$source = imagecreatefrompng($sourcePath);
foreach (['caduceus-header.png' => 20, 'caduceus-watermark.png' => 108] as $filename => $alpha) {
    $target = imagecreatetruecolor(imagesx($source), imagesy($source));
    imagealphablending($target, false);
    imagesavealpha($target, true);
    $transparent = imagecolorallocatealpha($target, 255, 255, 255, 127);
    imagefill($target, 0, 0, $transparent);
    for ($y = 0; $y < imagesy($source); $y++) {
        for ($x = 0; $x < imagesx($source); $x++) {
            $color = imagecolorsforindex($source, imagecolorat($source, $x, $y));
            if ($color['red'] > 245 && $color['green'] > 245 && $color['blue'] > 245) {
                continue;
            }
            imagesetpixel($target, $x, $y, imagecolorallocatealpha($target, $color['red'], $color['green'], $color['blue'], $alpha));
        }
    }
    imagepng($target, "$outputDirectory/$filename", 9);
    imagedestroy($target);
}
imagedestroy($source);
