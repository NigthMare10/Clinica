<?php

namespace Tests\Feature;

use App\Enums\MedicalDocumentStatus;
use App\Models\MedicalDocument;
use App\Services\MedicalDocuments\MedicalDocumentVerificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultationVsIssuedTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_data_keeps_consultation_time_separate_from_real_issuance_time(): void
    {
        $issuedAt = CarbonImmutable::parse('2026-08-13 11:30:35', 'America/Tegucigalpa');
        $document = MedicalDocument::factory()->create([
            'status' => MedicalDocumentStatus::ISSUED,
            'consultation_date' => '2026-08-09',
            'consultation_time' => '14:00',
            'issued_at' => $issuedAt,
            'public_code' => 'CSA-TIME-TEST',
        ]);

        $result = app(MedicalDocumentVerificationService::class)->byCode($document->public_code);

        $this->assertSame('2026-08-09', $result['document']['consultation_date']);
        $this->assertSame('14:00', substr((string) $result['document']['consultation_time'], 0, 5));
        $this->assertSame($issuedAt->toIso8601String(), $result['document']['issued_at']);
    }
}
