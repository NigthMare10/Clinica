<?php

namespace Tests\Unit;

use App\Services\MedicalDocuments\MedicalDocumentEditorTextService;
use Tests\TestCase;

class MedicalDocumentEditorTextServiceTest extends TestCase
{
    public function test_it_removes_only_supported_presentation_markers(): void
    {
        $text = "**Paciente** con __dolor__ y `reposo`.\n\n## Diagnóstico: apendicitis.";

        $this->assertSame("Paciente con dolor y reposo.\n\nDiagnóstico: apendicitis.", app(MedicalDocumentEditorTextService::class)->clean($text));
    }
}
