<?php

namespace Tests\Unit;

use App\Services\MedicalDocuments\MedicalTextExtractionService;
use Tests\TestCase;

class ConsultationTimeExtractionTest extends TestCase
{
    public function test_it_normalizes_explicit_consultation_time_formats(): void
    {
        $service = new MedicalTextExtractionService;

        foreach ([
            '2:00 p. m.' => '14:00:00', '2:00 pm' => '14:00:00', '2:00 PM' => '14:00:00',
            '14:00' => '14:00:00', '02:00 p.m.' => '14:00:00', '8:35 a. m.' => '08:35:00',
            '08:35' => '08:35:00', '12:00 p. m.' => '12:00:00', '12:00 a. m.' => '00:00:00',
        ] as $source => $expected) {
            $result = $service->extract("El paciente acudió a consulta a las {$source}.");

            $this->assertSame($expected, $result['fields']['consultation_time'], $source);
        }
    }
}
