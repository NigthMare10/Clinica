<?php

namespace Tests\Unit;

use App\Services\MedicalDocuments\DocumentHashService;
use PHPUnit\Framework\TestCase;

class DocumentHashServiceTest extends TestCase
{
    public function test_hash_equality_and_difference(): void
    {
        $first = tempnam(sys_get_temp_dir(), 'hash');
        $second = tempnam(sys_get_temp_dir(), 'hash');
        try {
            file_put_contents($first, 'same');
            file_put_contents($second, 'same');
            $service = new DocumentHashService;
            $hash = $service->file($first);
            $this->assertTrue($service->equals($second, $hash));
            file_put_contents($second, 'different');
            $this->assertFalse($service->equals($second, $hash));
        } finally {
            @unlink($first);
            @unlink($second);
        }
    }
}
