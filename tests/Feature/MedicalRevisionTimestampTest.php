<?php

namespace Tests\Feature;

use App\Enums\MedicalDocumentStatus;
use App\Enums\UserRole;
use App\Models\MedicalDocument;
use App\Models\User;
use App\Services\MedicalDocuments\MedicalDocumentRevisionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalRevisionTimestampTest extends TestCase
{
    use RefreshDatabase;

    public function test_correction_preserves_consultation_time_and_leaves_the_new_issuance_timestamp_empty(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-11 00:10:00', 'America/Tegucigalpa'));
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $source = MedicalDocument::factory()->create([
            'status' => MedicalDocumentStatus::ISSUED,
            'public_code' => 'CSA-REVISION-TIME',
            'consultation_date' => '2026-08-10',
            'consultation_time' => '23:30',
            'issued_at' => CarbonImmutable::parse('2026-08-10 23:40:00', 'America/Tegucigalpa'),
        ]);

        $correction = app(MedicalDocumentRevisionService::class)->create($source, 'Clinical wording correction.', $user);

        $this->assertSame('23:30', substr((string) $correction->consultation_time, 0, 5));
        $this->assertNull($correction->issued_at);
    }
}
