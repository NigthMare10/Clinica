<?php

namespace Tests\Feature;

use App\Enums\MedicalDocumentStatus;
use App\Enums\UserRole;
use App\Models\MedicalDocument;
use App\Models\MedicalDocumentRevision;
use App\Models\User;
use App\Services\MedicalDocuments\MedicalDocumentRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class MedicalDocumentRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_correction_preserves_the_public_code_and_records_immutable_source_snapshot(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $source = MedicalDocument::factory()->create(['status' => MedicalDocumentStatus::ISSUED,
            'public_code' => 'CSA-2026-REVISION', 'diagnosis' => 'Initial diagnosis', 'revision_number' => 1]);

        $correction = app(MedicalDocumentRevisionService::class)->create($source, 'Diagnosis wording correction.', $user);

        $this->assertSame('CSA-2026-REVISION', $correction->public_code);
        $this->assertSame(2, $correction->revision_number);
        $this->assertFalse($correction->is_current_revision);
        $this->assertSame(MedicalDocumentStatus::REVIEW_REQUIRED, $correction->status);
        $this->assertTrue($source->fresh()->is_current_revision);
        $this->assertDatabaseHas('medical_document_revisions', ['medical_document_id' => $correction->id,
            'source_document_id' => $source->id, 'reason' => 'Diagnosis wording correction.']);
        $this->assertSame('Initial diagnosis', MedicalDocumentRevision::firstOrFail()->source_snapshot['diagnosis']);
    }

    public function test_correction_requires_an_issued_or_revoked_source_and_prevents_parallel_current_corrections(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $draft = MedicalDocument::factory()->create(['status' => MedicalDocumentStatus::DRAFT, 'public_code' => 'CSA-DRAFT']);
        $service = app(MedicalDocumentRevisionService::class);

        try {
            $service->create($draft, 'Required correction.', $user);
            $this->fail('A draft was accepted as a correction source.');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        }

        $source = MedicalDocument::factory()->create(['status' => MedicalDocumentStatus::ISSUED, 'public_code' => 'CSA-LOCK']);
        $service->create($source, 'First correction.', $user);
        $this->expectException(RuntimeException::class);
        $service->create($source->fresh(), 'Second correction.', $user);
    }

    public function test_review_payload_exposes_revision_traceability_and_changed_fields(): void
    {
        $user = User::factory()->create(['name' => 'Revision Administrator', 'role' => UserRole::SUPER_ADMIN]);
        $source = MedicalDocument::factory()->create([
            'status' => MedicalDocumentStatus::ISSUED,
            'public_code' => 'CSA-REVIEW-HISTORY',
            'revision_number' => 1,
            'diagnosis' => 'Initial diagnosis',
            'issued_sha256' => str_repeat('b', 64),
        ]);
        $correction = app(MedicalDocumentRevisionService::class)->create($source, 'Diagnóstico o motivo médico: precisión clínica.', $user);
        $correction->update(['diagnosis' => 'Corrected diagnosis']);

        $this->actingAs($user)->get(route('admin.documents.review', $correction))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Documents/Review')
                ->has('revisionHistory', 2)
                ->where('revisionHistory.0.sha256', str_repeat('b', 64))
                ->where('revisionHistory.1.actor', 'Revision Administrator')
                ->where('revisionHistory.1.reason', 'Diagnóstico o motivo médico: precisión clínica.')
                ->where('revisionHistory.1.changes.0.field', 'diagnosis')
                ->where('revisionHistory.1.changes.0.before', 'Initial diagnosis')
                ->where('revisionHistory.1.changes.0.after', 'Corrected diagnosis'));
    }
}
