<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DocumentAuditLog;
use App\Models\InstitutionalAsset;
use App\Models\User;
use App\Services\MedicalDocuments\InstitutionalAssetService;
use App\Services\MedicalDocuments\InstitutionalSignatureStampService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstitutionalCombinedAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_initial_import_stores_the_combined_asset_privately_and_audits_it(): void
    {
        Storage::fake('local');
        config(['medical_documents.disk' => 'local']);
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);

        $this->actingAs($user)->post(route('admin.settings.signature.import-combined'))->assertRedirect();

        $asset = InstitutionalAsset::sole();
        $this->assertSame(InstitutionalAssetService::SIGNATURE_STAMP_COMBINED, $asset->kind);
        $this->assertTrue($asset->is_active);
        Storage::disk('local')->assertExists($asset->getRawOriginal('path'));
        $this->assertDatabaseHas('document_audit_logs', [
            'action' => 'SIGNATURE_STAMP_COMBINED_IMPORTED',
            'user_id' => $user->id,
        ]);
        $this->assertSame($asset->id, DocumentAuditLog::sole()->metadata['asset_id']);
    }

    public function test_combined_assets_have_a_separate_active_slot_from_independent_marks(): void
    {
        Storage::fake('local');
        config(['medical_documents.disk' => 'local']);
        $user = User::factory()->create();
        $service = app(InstitutionalAssetService::class);
        $source = base_path('docs/SantaAna_Firma_Sello/firma_sello_combinado_transparente.png');

        $service->store(new UploadedFile($source, 'combined.png', 'image/png', null, true), 'signature', $user);
        $combined = $service->store(new UploadedFile($source, 'combined.png', 'image/png', null, true), InstitutionalAssetService::SIGNATURE_STAMP_COMBINED, $user);

        $this->assertTrue($combined->is_active);
        $this->assertDatabaseCount('institutional_assets', 2);
        $this->assertDatabaseHas('institutional_assets', ['kind' => 'signature', 'is_active' => true]);
    }

    public function test_combined_mark_takes_precedence_over_independent_marks_when_stamping(): void
    {
        $kinds = app(InstitutionalSignatureStampService::class)->kindsToApply(collect([
            'signature' => new InstitutionalAsset(['kind' => 'signature']),
            'stamp' => new InstitutionalAsset(['kind' => 'stamp']),
            InstitutionalAssetService::SIGNATURE_STAMP_COMBINED => new InstitutionalAsset(['kind' => InstitutionalAssetService::SIGNATURE_STAMP_COMBINED]),
        ]));

        $this->assertSame([InstitutionalAssetService::SIGNATURE_STAMP_COMBINED], $kinds);
    }
}
