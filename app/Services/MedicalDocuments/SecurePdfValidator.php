<?php

namespace App\Services\MedicalDocuments;

class SecurePdfValidator
{
    public function valid(string $path, ?string $filename = null, ?int $maxBytes = null): bool
    {
        $size = is_file($path) ? filesize($path) : false;
        if ($size === false || $size === 0 || ($maxBytes !== null && $size > $maxBytes)
            || ($filename !== null && (mb_strlen($filename) > 255 || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf'))) {
            return false;
        }
        $handle = fopen($path, 'rb');
        $magic = $handle ? fread($handle, 5) : false;
        if (is_resource($handle)) {
            fclose($handle);
        }

        $tailHandle = fopen($path, 'rb');
        $tail = '';
        if ($tailHandle) {
            fseek($tailHandle, -min(2048, $size), SEEK_END);
            $tail = fread($tailHandle, min(2048, $size)) ?: '';
            fclose($tailHandle);
        }

        return $magic === '%PDF-' && str_contains($tail, '%%EOF')
            && (new \finfo(FILEINFO_MIME_TYPE))->file($path) === 'application/pdf';
    }
}
