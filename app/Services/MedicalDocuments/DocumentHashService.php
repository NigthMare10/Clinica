<?php

namespace App\Services\MedicalDocuments;

use RuntimeException;

class DocumentHashService
{
    public function file(string $path): string
    {
        $hash = is_file($path) ? hash_file('sha256', $path) : false;
        if ($hash === false) {
            throw new RuntimeException('Unable to hash document.');
        }

        return $hash;
    }

    public function equals(string $path, string $expected): bool
    {
        return hash_equals(strtolower($expected), $this->file($path));
    }
}
