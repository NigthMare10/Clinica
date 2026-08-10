<?php

namespace App\Services\MedicalDocuments;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Str;

class QrCodeService
{
    public function token(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function publicCode(int|string|null $year = null): string
    {
        return 'CSA-'.($year ?: now()->year).'-'.strtoupper(Str::random(10));
    }

    public function verificationUrl(string $token): string
    {
        return route('public.verify.token', ['token' => $token]);
    }

    public function write(string $content, string $path): void
    {
        $writer = new PngWriter;
        $result = $writer->write(new QrCode($content, errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: config('medical_documents.qr.size'), margin: config('medical_documents.qr.margin')));
        $result->saveToFile($path);
    }
}
