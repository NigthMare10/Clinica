<?php

namespace Tests\Unit;

use App\Services\MedicalDocuments\MedicalTextExtractionService;
use PHPUnit\Framework\TestCase;

class MedicalTextExtractionServiceTest extends TestCase
{
    public function test_it_extracts_an_explicit_production_qa_patient_name(): void
    {
        $analysis = (new MedicalTextExtractionService)->extract(
            'Se hace constar que el paciente PRODUCTION QA 01, de 31 años de edad, con número de identidad 0000-0000-00001, acudió a consulta médica el día 11 de agosto de 2026 a las 2:00 p. m. Diagnóstico: síndrome viral agudo.',
        );

        $this->assertSame('PRODUCTION QA 01', $analysis['fields']['patient_name']);
    }

    public function test_it_extracts_the_genesis_regression_fixture(): void
    {
        $text = file_get_contents(__DIR__.'/../Fixtures/genesis-medical-text.txt');
        $result = (new MedicalTextExtractionService)->extract($text, 'incapacidad');

        $this->assertSame([
            'patient_name' => 'Genesis Mariela Duarte Flores',
            'age' => 28,
            'identity' => '0801199812345',
            'consultation_date' => '2026-08-10',
            'consultation_time' => '08:00:00',
            'diagnosis' => 'infección respiratoria aguda',
            'leave_days' => 2,
            'leave_start_date' => '2026-08-10',
            'leave_end_date' => '2026-08-11',
        ], array_intersect_key($result['fields'], array_flip([
            'patient_name', 'age', 'identity', 'consultation_date', 'consultation_time', 'diagnosis',
            'leave_days', 'leave_start_date', 'leave_end_date',
        ])));
        $this->assertFalse($result['requires_review']);
    }

    public function test_it_extracts_the_hashlin_incapacity_without_inventing_content(): void
    {
        $text = <<<'TEXT'
Por medio de la presente se hace constar que la paciente Hashlin Lizeth Espino Gómez, de 21 años de edad, con número de identidad 0801200519904, acudió a consulta médica el día 9 de agosto de 2026 a las 10:00 a. m., por presentar cuadro clínico caracterizado por diarrea frecuente, fiebre, dolor de cuerpo y huesos, dolor abdominal tipo cólico, náuseas, debilidad generalizada, cefalea, mareos, pérdida del apetito, fatiga y signos de deshidratación leve.

Durante la valoración médica se evidenciaron manifestaciones compatibles con un proceso gastrointestinal agudo de probable origen infeccioso, asociado a síndrome febril y pérdida de líquidos, limitando temporalmente la realización de sus actividades habituales.

De acuerdo con la evaluación clínica realizada, se establece diagnóstico presuntivo de gastroenteritis aguda de probable origen infeccioso asociada a síndrome febril, mialgias y deshidratación leve, recomendándose reposo, hidratación abundante, reposición de electrolitos, dieta blanda y tratamiento sintomático según indicación médica.

Por lo anterior, se extiende incapacidad médica por dos (2) días, correspondientes al 9 y 10 de agosto de 2026, pudiendo reincorporarse a sus actividades habituales una vez presente mejoría clínica satisfactoria.
TEXT;
        $result = (new MedicalTextExtractionService)->extract($text, 'incapacidad');
        $fields = $result['fields'];

        $this->assertSame('Hashlin Lizeth Espino Gómez', $fields['patient_name']);
        $this->assertSame(21, $fields['age']);
        $this->assertSame('0801200519904', $fields['identity']);
        $this->assertSame('2026-08-09', $fields['consultation_date']);
        $this->assertSame('10:00:00', $fields['consultation_time']);
        $this->assertStringContainsString('diarrea frecuente', $fields['symptoms']);
        $this->assertSame('gastroenteritis aguda de probable origen infeccioso asociada a síndrome febril, mialgias y deshidratación leve', $fields['diagnosis']);
        $this->assertSame(2, $fields['leave_days']);
        $this->assertSame('2026-08-09', $fields['leave_start_date']);
        $this->assertSame('2026-08-10', $fields['leave_end_date']);
        $this->assertStringContainsString('hidratación abundante', $fields['recommendations']);
        $this->assertSame([], $result['conflicts']);
        $this->assertFalse($result['requires_review']);
    }

    public function test_it_cleans_markdown_and_reports_inclusive_day_conflicts(): void
    {
        $service = new MedicalTextExtractionService;
        $this->assertSame('Texto importante', $service->cleanPresentation('**Texto importante**'));
        $result = $service->extract('La paciente Ana López, de 30 años, identidad 0801199012345, fue atendida el 9 de agosto de 2026. Se establece diagnóstico de infección aguda. Se otorgan 2 días, del 9 al 11 de agosto de 2026.', 'incapacidad');
        $this->assertSame('days_mismatch', $result['conflicts'][0]['code']);
        $this->assertTrue($result['conflicts'][0]['blocking']);
        $this->assertTrue($result['requires_review']);
    }

    public function test_it_extracts_fictitious_wording_variants(): void
    {
        $variants = [
            [
                'El paciente Mario Suazo, 42 años, identidad 0501198412345, fue atendido el 3 de julio de 2026 a las 8:00 AM. Se diagnosticó bronquitis aguda. Reposo por 3 días, 3, 4 y 5 de julio de 2026.',
                ['Mario Suazo', 42, '08:00:00', 'bronquitis aguda', 3, '2026-07-03', '2026-07-05'],
            ],
            [
                'La paciente Lucía Pineda, de 35 años de edad, número de identidad 0801199111111, acudió a consulta el 14 de junio de 2026 a las 08:00. Hallazgos compatibles con migraña sin aura. Se otorgan dos días, del 14 al 15 de junio de 2026.',
                ['Lucía Pineda', 35, '08:00:00', 'migraña sin aura', 2, '2026-06-14', '2026-06-15'],
            ],
            [
                'El paciente Óscar Mejía, de 51 años, identidad 0101197512345, se presentó a consulta médica el día 20 de mayo de 2026 a las 8:00 a. m. Se establece diagnóstico presuntivo de lumbalgia mecánica. Se extiende incapacidad por tres (3) días, correspondientes al 20, 21 y 22 de mayo de 2026.',
                ['Óscar Mejía', 51, '08:00:00', 'lumbalgia mecánica', 3, '2026-05-20', '2026-05-22'],
            ],
        ];

        foreach ($variants as [$text, $expected]) {
            $result = (new MedicalTextExtractionService)->extract($text, 'incapacidad');
            $fields = $result['fields'];
            $this->assertSame($expected, [
                $fields['patient_name'], $fields['age'], $fields['consultation_time'], $fields['diagnosis'],
                $fields['leave_days'], $fields['leave_start_date'], $fields['leave_end_date'],
            ]);
            $this->assertFalse($result['requires_review']);
        }
    }

    public function test_conflicting_consultation_dates_are_blocking(): void
    {
        $text = 'La paciente Elena Cruz, de 29 años, identidad 0801199712345, fue atendida el 10 de abril de 2026 y se presentó a consulta el 11 de abril de 2026. Se diagnosticó faringitis aguda.';
        $result = (new MedicalTextExtractionService)->extract($text);

        $this->assertTrue($result['requires_review']);
        $this->assertTrue(collect($result['conflicts'])->contains(
            fn (array $conflict) => $conflict['code'] === 'consultation_date_conflict' && $conflict['blocking']
        ));
    }
}
