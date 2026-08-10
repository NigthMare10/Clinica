<?php

namespace Tests\Feature;

use App\Enums\MedicalDocumentStatus;
use App\Models\MedicalDocument;
use App\Services\MedicalDocuments\MedicalDocumentVerificationService;
use App\Services\MedicalDocuments\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalDocumentVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_opaque_token_returns_valid_and_revoked_statuses_without_storing_plaintext(): void
    {
        $qr = app(QrCodeService::class);
        $token = $qr->token();
        $document = MedicalDocument::factory()->create(['status' => MedicalDocumentStatus::ISSUED, 'token_hash' => $qr->tokenHash($token), 'public_code' => 'CSA-2026-ABCDEFGHIJ', 'issued_at' => now()]);
        $this->assertDatabaseMissing('medical_documents', ['token_hash' => $token]);
        $this->assertSame('VALID', app(MedicalDocumentVerificationService::class)->byToken($token)['status']);
        $document->update(['status' => MedicalDocumentStatus::REVOKED, 'revoked_at' => now()]);
        $this->assertSame('REVOKED', app(MedicalDocumentVerificationService::class)->byToken($token)['status']);
        $this->assertSame('NOT_FOUND', app(MedicalDocumentVerificationService::class)->byToken($qr->token())['status']);
    }
}
