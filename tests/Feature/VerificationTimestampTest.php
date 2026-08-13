<?php

namespace Tests\Feature;

use App\Enums\MedicalDocumentStatus;
use App\Models\DocumentVerificationLog;
use App\Models\MedicalDocument;
use App\Services\MedicalDocuments\MedicalDocumentVerificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationTimestampTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_records_the_real_honduras_timestamp_with_seconds(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-10 17:34:55', 'America/Tegucigalpa'));
        $document = MedicalDocument::factory()->create(['status' => MedicalDocumentStatus::ISSUED, 'public_code' => 'CSA-VERIFY-TIME']);

        app(MedicalDocumentVerificationService::class)->byCode($document->public_code);

        $this->assertSame('2026-08-10 17:34:55', DocumentVerificationLog::firstOrFail()->verified_at->timezone('America/Tegucigalpa')->format('Y-m-d H:i:s'));
    }
}
