<?php

namespace App\Services\MedicalDocuments;

use RuntimeException;
use setasign\Fpdi\Fpdi;
use Throwable;

class PdfDocumentInspectionService
{
    public function assertOnePage(string $path): void
    {
        try {
            $pages = (new Fpdi)->setSourceFile($path);
        } catch (Throwable $exception) {
            throw new RuntimeException('El PDF no pudo validarse estructuralmente.', previous: $exception);
        }

        if ($pages !== 1) {
            throw new RuntimeException('El documento debe contener exactamente una página antes de emitirse.');
        }
    }
}
