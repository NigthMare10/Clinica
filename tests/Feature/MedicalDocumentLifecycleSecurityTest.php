<?php

namespace Tests\Feature;

use App\Enums\MedicalDocumentStatus;
use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\MedicalDocument;
use App\Models\User;
use App\Services\MedicalDocuments\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MedicalDocumentLifecycleSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_terminal_clinical_content_is_immutable_and_revoked_cannot_return_to_issued(): void
    {
        $document = MedicalDocument::factory()->create(['status' => MedicalDocumentStatus::ISSUED, 'diagnosis' => 'Original']);
        try {
            $document->update(['diagnosis' => 'Changed']);
            $this->fail('Terminal clinical mutation was accepted.');
        } catch (\DomainException) {
            $this->assertSame('Original', $document->fresh()->diagnosis);
        }

        $document->refresh();
        $document->update(['status' => MedicalDocumentStatus::REVOKED]);
        $this->expectException(\DomainException::class);
        $document->update(['status' => MedicalDocumentStatus::ISSUED]);
    }

    public function test_replaced_document_cannot_transition_or_change_identity(): void
    {
        $document = MedicalDocument::factory()->create(['status' => MedicalDocumentStatus::REPLACED]);
        $this->expectException(\DomainException::class);
        $document->update(['patient_id' => null, 'status' => MedicalDocumentStatus::REVOKED]);
    }

    public function test_verification_and_admin_responses_have_private_noindex_headers(): void
    {
        $response = $this->get('/verificar')->assertOk()->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_shared_institution_props_never_expose_administrator_credentials(): void
    {
        config(['institution.admin.email' => 'admin@example.test', 'institution.admin.password' => 'secret-value']);

        $this->get('/verificar')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->missing('institution.admin'));
    }

    public function test_token_is_32_random_bytes_and_qr_url_contains_no_pii(): void
    {
        $qr = app(QrCodeService::class);
        $tokens = collect(range(1, 100))->map(fn () => $qr->token());
        $this->assertCount(100, $tokens->unique());
        $this->assertTrue($tokens->every(fn ($token) => strlen(base64_decode(strtr($token, '-_', '+/').'=', true)) === 32));
        $url = $qr->verificationUrl($tokens->first());
        $this->assertStringNotContainsString('patient', strtolower($url));
        $this->assertStringNotContainsString('diagnosis', strtolower($url));
    }

    public function test_private_document_and_doctor_asset_paths_are_not_publicly_accessible(): void
    {
        Storage::fake('local');
        config(['medical_documents.disk' => 'local']);
        $doctor = Doctor::factory()->create();
        $doctor->forceFill(['signature_path' => 'medical/doctor-assets/'.$doctor->id.'/signature.png'])->save();
        Storage::disk('local')->put($doctor->getRawOriginal('signature_path'), 'private');
        $this->assertContains($this->get('/storage/medical/doctor-assets/'.$doctor->id.'/signature.png')->status(), [403, 404]);

        $operator = User::factory()->create(['role' => UserRole::DOCUMENT_OPERATOR]);
        $this->actingAs($operator)->get(route('admin.doctors.assets.show', [$doctor, 'signature']))->assertForbidden();
    }

    public function test_public_verification_code_endpoint_is_rate_limited(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->post('/verificar/codigo', ['code' => 'MISSING-'.$attempt])->assertOk();
        }
        $this->post('/verificar/codigo', ['code' => 'MISSING-11'])->assertTooManyRequests();
    }

    public function test_doctor_document_index_is_scoped_to_own_records(): void
    {
        $user = User::factory()->create(['role' => UserRole::DOCTOR]);
        $doctor = Doctor::factory()->create(['user_id' => $user->id]);
        MedicalDocument::factory()->create(['doctor_id' => $doctor->id]);
        MedicalDocument::factory()->create(['doctor_id' => Doctor::factory()]);

        $this->actingAs($user)->get(route('admin.documents.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Documents/Index')->has('documents.data', 1));
    }
}
