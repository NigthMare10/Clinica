<?php

namespace Tests\Feature;

use App\Enums\MedicalDocumentStatus;
use App\Enums\UserRole;
use App\Models\MedicalDocument;
use App\Models\User;
use App\Services\MedicalDocuments\MedicalDocumentIssueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MedicalDocumentIssueGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_requires_human_review_ready_status(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $document = MedicalDocument::factory()->create(['status' => MedicalDocumentStatus::DRAFT]);
        $this->expectException(RuntimeException::class);
        app(MedicalDocumentIssueService::class)->issue($document, $user);
    }

    public function test_blocking_inconsistency_prevents_issue_before_file_is_touched(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $document = MedicalDocument::factory()->create(['status' => MedicalDocumentStatus::READY, 'reviewed_by' => $user->id, 'reviewed_at' => now(), 'inconsistencies' => [['blocking' => true]]]);
        $this->expectException(RuntimeException::class);
        app(MedicalDocumentIssueService::class)->issue($document, $user);
    }
}
