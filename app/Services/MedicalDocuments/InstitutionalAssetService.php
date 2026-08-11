<?php

namespace App\Services\MedicalDocuments;

use App\Models\InstitutionalAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class InstitutionalAssetService
{
    public const SIGNATURE_STAMP_COMBINED = 'SIGNATURE_STAMP_COMBINED';

    public function store(UploadedFile $file, string $kind, User $user, bool $active = true): InstitutionalAsset
    {
        abort_unless(in_array($kind, ['signature', 'stamp', self::SIGNATURE_STAMP_COMBINED], true), 422);
        $this->assertImage($file);
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new RuntimeException('Unable to read institutional asset.');
        }
        $path = "institutional/{$kind}/".Str::uuid().'.png';
        $disk = Storage::disk(config('medical_documents.disk'));
        if (! $disk->put($path, $this->transparentPng($contents))) {
            throw new RuntimeException('Unable to store institutional asset.');
        }

        return DB::transaction(function () use ($kind, $user, $active, $path, $disk): InstitutionalAsset {
            if ($active) {
                InstitutionalAsset::where('kind', $kind)->where('is_active', true)->update(['is_active' => false]);
            }
            $asset = InstitutionalAsset::create([
                'kind' => $kind, 'path' => $path, 'sha256' => hash_file('sha256', $disk->path($path)),
                'mime_type' => 'image/png', 'is_active' => $active, 'created_by' => $user->id,
                'activated_at' => $active ? now() : null,
            ]);

            return $asset;
        });
    }

    public function activate(InstitutionalAsset $asset): void
    {
        DB::transaction(function () use ($asset): void {
            InstitutionalAsset::where('kind', $asset->kind)->where('is_active', true)->update(['is_active' => false]);
            $asset->forceFill(['is_active' => true, 'activated_at' => now()])->save();
        });
    }

    private function assertImage(UploadedFile $file): void
    {
        $image = @getimagesize($file->getRealPath());
        abort_unless($image && in_array($image[2], [IMAGETYPE_PNG, IMAGETYPE_WEBP], true), 422, 'Only PNG and WebP institutional assets are accepted.');
        abort_if($image[0] > 4000 || $image[1] > 4000, 422, 'Institutional asset dimensions are too large.');
    }

    private function transparentPng(string $contents): string
    {
        $image = imagecreatefromstring($contents);
        if (! $image) {
            throw new RuntimeException('Unable to decode institutional asset.');
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $width = imagesx($image);
        $height = imagesy($image);
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($r > 242 && $g > 242 && $b > 242) {
                    imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $r, $g, $b, 127));
                }
            }
        }
        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
