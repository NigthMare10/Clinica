<?php

namespace Tests\Feature;

use App\Services\MedicalDocuments\MedicalTextExtractionService;
use Tests\TestCase;

class MidnightConsultationTest extends TestCase
{
    public function test_midnight_consultation_is_not_confused_with_noon(): void
    {
        $result = app(MedicalTextExtractionService::class)->extract('El paciente acudió a consulta a las 12:00 a. m.');

        $this->assertSame('00:00', $result['fields']['consultation_time']);
    }
}
