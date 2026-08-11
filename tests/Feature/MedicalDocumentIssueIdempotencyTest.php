<?php

namespace Tests\Feature;

use App\Enums\MedicalDocumentStatus;
use App\Models\MedicalDocument;
use App\Models\User;
use App\Services\MedicalDocuments\DocumentHashService;
use App\Services\MedicalDocuments\InstitutionalSignatureStampService;
use App\Services\MedicalDocuments\MedicalDocumentAuditService;
use App\Services\MedicalDocuments\MedicalDocumentConsistencyService;
use App\Services\MedicalDocuments\MedicalDocumentIssueService;
use App\Services\MedicalDocuments\PdfDocumentInspectionService;
use App\Services\MedicalDocuments\PdfEncryptionService;
use App\Services\MedicalDocuments\PdfQrVerificationService;
use App\Services\MedicalDocuments\PdfStampService;
use App\Services\MedicalDocuments\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class MedicalDocumentIssueIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sequential_double_issue_is_idempotent_and_never_returns_plaintext_token(): void
    {
        Storage::fake('local');
        config(['medical_documents.disk' => 'local', 'medical_documents.encryption_enabled' => false]);
        $path = 'medical/original/source.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\n%%EOF");
        $hash = hash_file('sha256', Storage::disk('local')->path($path));
        $user = User::factory()->create();
        $document = MedicalDocument::factory()->create(['status' => MedicalDocumentStatus::READY, 'reviewed_by' => $user->id,
            'reviewed_at' => now(), 'original_path' => $path, 'original_sha256' => $hash, 'inconsistencies' => []]);

        $qr = Mockery::mock(QrCodeService::class);
        $qr->shouldReceive('token')->once()->andReturn(str_repeat('A', 43));
        $qr->shouldReceive('tokenHash')->once()->andReturn(hash('sha256', str_repeat('A', 43)));
        $qr->shouldReceive('publicCode')->once()->andReturn('CSA-2026-UNIQUECODE');
        $qr->shouldReceive('verificationUrl')->once()->andReturn('https://clinic.test/verificar/'.str_repeat('A', 43));
        $qr->shouldReceive('write')->once()->andReturnUsing(fn ($content, $target) => file_put_contents($target, 'qr'));
        $stamp = Mockery::mock(PdfStampService::class);
        $stamp->shouldReceive('stamp')->once()->andReturnUsing(function ($document, $input, $output) {
            copy($input, $output);

            return 1;
        });
        $verify = Mockery::mock(PdfQrVerificationService::class);
        $verify->shouldReceive('assertReadable')->once();
        $audit = Mockery::mock(MedicalDocumentAuditService::class);
        $audit->shouldReceive('record')->once();
        $encryption = Mockery::mock(PdfEncryptionService::class);
        $institutionalMarks = Mockery::mock(InstitutionalSignatureStampService::class);
        $institutionalMarks->shouldReceive('apply')->once()->andReturnUsing(function ($document, $input, $output) {
            copy($input, $output);

            return [];
        });
        $inspection = Mockery::mock(PdfDocumentInspectionService::class);
        $inspection->shouldReceive('assertOnePage')->twice();
        $service = new MedicalDocumentIssueService(new DocumentHashService, $qr, $stamp, $institutionalMarks, $encryption, $verify, new MedicalDocumentConsistencyService, $audit, $inspection);

        $first = $service->issue($document, $user);
        $second = $service->issue($document, $user);
        $this->assertInstanceOf(MedicalDocument::class, $first);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(MedicalDocumentStatus::ISSUED, $second->status);
        $this->assertSame(1, $second->versions()->count());
        $this->assertDatabaseMissing('medical_documents', ['token_hash' => str_repeat('A', 43)]);
    }

    public function test_public_human_codes_are_unique(): void
    {
        $codes = collect(range(1, 500))->map(fn () => app(QrCodeService::class)->publicCode());
        $this->assertCount(500, $codes->unique());
    }
}
