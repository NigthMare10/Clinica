<?php

namespace Tests\Unit;

use App\Services\MedicalDocuments\SecurePdfValidator;
use PHPUnit\Framework\TestCase;

class SecurePdfValidatorTest extends TestCase
{
    public function test_requires_pdf_mime_and_magic(): void
    {
        $pdf = tempnam(sys_get_temp_dir(), 'pdf');
        $fake = tempnam(sys_get_temp_dir(), 'pdf');
        try {
            file_put_contents($pdf, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
            file_put_contents($fake, 'not a pdf');
            $validator = new SecurePdfValidator;
            $this->assertTrue($validator->valid($pdf));
            $this->assertFalse($validator->valid($fake));
        } finally {
            @unlink($pdf);
            @unlink($fake);
        }
    }

    public function test_rejects_renamed_malformed_oversized_and_long_filename_files(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        try {
            file_put_contents($path, "%PDF-1.4\nnot complete");
            $validator = new SecurePdfValidator;
            $this->assertFalse($validator->valid($path, 'renamed.pdf'));
            file_put_contents($path, "%PDF-1.4\n%%EOF");
            $this->assertFalse($validator->valid($path, 'document.txt'));
            $this->assertFalse($validator->valid($path, str_repeat('a', 252).'.pdf'));
            $this->assertFalse($validator->valid($path, 'document.pdf', 5));
        } finally {
            @unlink($path);
        }
    }
}
