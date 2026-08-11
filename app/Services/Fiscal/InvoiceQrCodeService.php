<?php

namespace App\Services\Fiscal;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class InvoiceQrCodeService
{
    public function write(string $content, string $path): void
    {
        $qr = new QrCode(
            $content,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: config('medical_documents.qr.size'),
            margin: config('medical_documents.qr.margin'),
            foregroundColor: new Color(0, 0, 0, 0),
            backgroundColor: new Color(255, 255, 255, 0),
        );
        (new PngWriter)->write($qr)->saveToFile($path);
    }
}
