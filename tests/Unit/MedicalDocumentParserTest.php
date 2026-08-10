<?php

namespace Tests\Unit;

use App\Services\MedicalDocuments\MedicalDocumentConsistencyService;
use App\Services\MedicalDocuments\MedicalDocumentParser;
use PHPUnit\Framework\TestCase;

class MedicalDocumentParserTest extends TestCase
{
    public function test_extracts_requested_medical_fields_and_keeps_duplicate_candidates(): void
    {
        $text = "CLINICA: Centro Salud Andina\nPACIENTE: Maria Perez\nDNI: 12345678\nFecha de nacimiento: 10/02/1990\nEdad: 36 anos\nFecha de emision: 10/08/2026\nDesde: 10/08/2026\nHasta: 12/08/2026\nReposo medico por 3 dias\nDiagnostico: Bronquitis\nDr. Juan Torres\nCMP: 88991\n\nEl cuerpo confirma reposo de 5 dias.";
        $candidates = (new MedicalDocumentParser)->parse($text);
        $fields = array_map(fn ($candidate) => $candidate->field, $candidates);
        foreach (['patient_name', 'patient_document', 'birth_date', 'age', 'issue_date', 'start_date', 'end_date', 'days', 'diagnosis', 'doctor_name', 'doctor_credential', 'clinic_name'] as $field) {
            $this->assertContains($field, $fields);
        }
        $days = array_values(array_filter($candidates, fn ($candidate) => $candidate->field === 'days'));
        $this->assertCount(2, $days);
        $this->assertSame(['3', '5'], array_map(fn ($candidate) => $candidate->value, $days));
    }

    public function test_duplicate_dates_and_days_are_blocking_conflicts(): void
    {
        $candidates = [
            ['field' => 'issue_date', 'value' => '10/08/2026'], ['field' => 'issue_date', 'value' => '11/08/2026'],
            ['field' => 'days', 'value' => '3'], ['field' => 'days', 'value' => '5'],
            ['field' => 'patient_name', 'value' => 'Maria Perez'], ['field' => 'doctor_name', 'value' => 'Juan Torres'],
            ['field' => 'doctor_credential', 'value' => '88991'],
        ];
        $issues = (new MedicalDocumentConsistencyService)->check($candidates);
        $this->assertTrue(collect($issues)->contains(fn ($issue) => $issue['field'] === 'issue_date' && $issue['blocking']));
        $this->assertTrue(collect($issues)->contains(fn ($issue) => $issue['field'] === 'days' && $issue['blocking']));
    }

    public function test_doctor_credential_record_conflict_is_blocking(): void
    {
        $confirmed = ['patient_name' => 'Maria Perez', 'issue_date' => '2026-08-10', 'doctor_name' => 'Juan Torres', 'doctor_credential' => '111'];
        $issues = (new MedicalDocumentConsistencyService)->check([], $confirmed, ['credential_number' => '222']);
        $this->assertTrue(collect($issues)->contains(fn ($issue) => $issue['code'] === 'doctor_record_mismatch' && $issue['blocking']));
    }
}
