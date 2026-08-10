<?php

declare(strict_types=1);

if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
    fwrite(STDERR, "GD with WebP support is required.\n");
    exit(1);
}

$root = dirname(__DIR__).'/public/images';
$files = [
    'clinic/hero-clinic.webp',
    'clinic/reception.webp',
    'clinic/waiting-room.webp',
    'clinic/consultation-room.webp',
    'clinic/medical-corridor.webp',
    'procedures/general-consultation.webp',
    'procedures/injection.webp',
    'procedures/traumatology-cast.webp',
    'procedures/blood-pressure.webp',
    'procedures/ultrasound.webp',
    'procedures/dentistry.webp',
    'procedures/pediatric-consultation.webp',
    'procedures/gynecology-consultation.webp',
    'procedures/cardiology.webp',
    'procedures/ophthalmology.webp',
];

foreach ($files as $index => $relative) {
    $path = $root.'/'.$relative;
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }

    $width = str_starts_with($relative, 'clinic/') ? 1600 : 1200;
    $height = str_starts_with($relative, 'clinic/') ? 1000 : 800;
    $image = imagecreatetruecolor($width, $height);

    for ($y = 0; $y < $height; $y++) {
        $ratio = $y / $height;
        $red = (int) (8 + 224 * $ratio);
        $green = (int) (43 + 196 * $ratio);
        $blue = (int) (70 + 178 * $ratio);
        imageline($image, 0, $y, $width, $y, imagecolorallocate($image, $red, $green, $blue));
    }

    $accent = imagecolorallocatealpha($image, 21, 178, 211, 42);
    $white = imagecolorallocatealpha($image, 255, 255, 255, 52);
    imagefilledellipse($image, (int) ($width * .77), (int) ($height * .26), (int) ($width * .55), (int) ($width * .55), $accent);
    imagefilledellipse($image, (int) ($width * .22), (int) ($height * .9), (int) ($width * .72), (int) ($width * .32), $white);

    $cross = imagecolorallocatealpha($image, 255, 255, 255, 18);
    $cx = (int) ($width * (.28 + (($index % 4) * .12)));
    $cy = (int) ($height * .42);
    $unit = (int) ($height * .07);
    imagefilledrectangle($image, $cx - $unit * 2, $cy - $unit / 2, $cx + $unit * 2, $cy + $unit / 2, $cross);
    imagefilledrectangle($image, $cx - $unit / 2, $cy - $unit * 2, $cx + $unit / 2, $cy + $unit * 2, $cross);

    imagewebp($image, $path, 78);
    imagedestroy($image);
    echo $relative."\n";
}
