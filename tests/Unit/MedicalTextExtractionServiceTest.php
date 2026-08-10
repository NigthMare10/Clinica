<?php

namespace Tests\Unit;

use App\Services\MedicalDocuments\MedicalTextExtractionService;
use PHPUnit\Framework\TestCase;

class MedicalTextExtractionServiceTest extends TestCase
{
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
        $this->assertSame('10:00', $fields['consultation_time']);
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
    }
}
