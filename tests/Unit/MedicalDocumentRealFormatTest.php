<?php

namespace Tests\Unit;

use App\Services\MedicalDocuments\MedicalDocumentConsistencyService;
use App\Services\MedicalDocuments\MedicalDocumentParser;
use PHPUnit\Framework\TestCase;

class MedicalDocumentRealFormatTest extends TestCase
{
    public function test_santa_ana_header_and_body_dates_remain_independent_and_conflict_exactly(): void
    {
        $text = "CLINICA SANTA ANA\nFECHA: 10/08/2026\nPACIENTE: Maria Perez\nEDAD: 36 años\nDiagnóstico de bronquitis aguda.\nLa paciente acudió a consulta el día 11 de agosto de 2026.\nDra. Ana López\nCédula profesional: ABC-123";
        $candidates = (new MedicalDocumentParser)->parse($text);
        $dates = array_values(array_filter($candidates, fn ($candidate) => $candidate->field === 'consultation_date'));
        $this->assertCount(2, $dates);
        $this->assertSame(['10/08/2026', '11 de agosto de 2026'], array_map(fn ($candidate) => $candidate->value, $dates));
        $issues = (new MedicalDocumentConsistencyService)->check($candidates);
        $this->assertTrue(collect($issues)->contains(fn ($issue) => $issue['code'] === 'CONSULTATION_DATE_CONFLICT' && $issue['blocking']));
    }

    public function test_doctor_normalized_name_and_credential_mismatches_block(): void
    {
        $confirmed = ['patient_name' => 'Maria Perez', 'issue_date' => '2026-08-10', 'doctor_name' => 'Dra. Ana Lopez', 'doctor_credential' => 'ABC-123'];
        $matching = (new MedicalDocumentConsistencyService)->check([], $confirmed, ['first_name' => 'Ana', 'last_name' => 'López', 'credential_number' => 'ABC123']);
        $this->assertFalse(collect($matching)->contains(fn ($issue) => str_contains($issue['code'], 'doctor_')));
        $mismatch = (new MedicalDocumentConsistencyService)->check([], $confirmed, ['first_name' => 'Otra', 'last_name' => 'Persona', 'credential_number' => 'ZZZ']);
        $this->assertCount(2, collect($mismatch)->where('blocking', true)->filter(fn ($issue) => str_contains($issue['code'], 'doctor_')));
    }
}
