<?php

namespace Tests\Feature;

use App\Services\MedicalDocuments\MedicalTextExtractionService;
use Tests\TestCase;

class NoConsultationTimeTest extends TestCase
{
    public function test_absent_consultation_time_remains_unspecified(): void
    {
        $result = app(MedicalTextExtractionService::class)->extract('El paciente acudió a consulta el 10 de agosto de 2026.');

        $this->assertNull($result['fields']['consultation_time']);
    }
}
