<?php

namespace Tests\Feature;

use App\Enums\MedicalDocumentStatus;
use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\User;
use App\Services\MedicalDocuments\MedicalDocumentVerificationService;
use App\Services\MedicalDocuments\PdfDocumentInspectionService;
use App\Services\MedicalDocuments\PdfEncryptionService;
use App\Services\MedicalDocuments\PdfTextExtractionService;
use App\Services\MedicalDocuments\QrCodeService;
use App\Support\InstitutionalMedicalProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InstitutionalDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_detected_patient_can_be_created_and_linked_automatically(): void
    {
        Storage::fake('local');
        config(['medical_documents.disk' => 'local']);
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $clinic = Clinic::create(['code' => 'HN-08', 'slug' => 'principal', 'name' => 'Principal', 'department' => 'Francisco Morazan', 'status' => 'ACTIVE']);

        $this->actingAs($user)->post(route('admin.documents.generate.store', 'constancia'), [
            'patient_name' => 'Hashlin Lizeth Espino Gómez',
            'identity' => '0801200519904',
            'age_at_consultation' => 21,
            'create_patient' => true,
            'clinic_id' => $clinic->id,
            'consultation_date' => '2026-08-09',
            'diagnosis' => 'Gastroenteritis aguda.',
            'free_text' => 'Por medio de la presente se hace constar la atención médica indicada.',
            'confirm' => true,
            'intent' => 'draft',
        ])->assertRedirect();

        $patient = Patient::where('document_number', '0801200519904')->firstOrFail();
        $document = MedicalDocument::firstOrFail();
        $this->assertSame($patient->id, $document->patient_id);
        $this->assertSame(config('institution.provider.credential_number'), $document->doctor->credential_number);
        $this->assertSame(MedicalDocumentStatus::REVIEW_REQUIRED, $document->status);
        $pdfText = app(PdfTextExtractionService::class)->extract(Storage::disk('local')->path($document->getRawOriginal('original_path')));
        $this->assertStringContainsString('CLÍNICA MÉDICA SANTA ANA', $pdfText);
        $this->assertStringContainsString('Dra. Adriana Abelina Pinot Moncada', $pdfText);
        $this->assertStringContainsString('Constancia Médica', $pdfText);
        $this->assertSame(1, substr_count($pdfText, "\f"));
    }

    public function test_only_authorized_roles_can_sign_and_issue(): void
    {
        $clinic = Clinic::create(['code' => 'HN-08', 'slug' => 'principal', 'name' => 'Principal', 'department' => 'Francisco Morazan', 'status' => 'ACTIVE']);
        $operator = User::factory()->create(['role' => UserRole::DOCUMENT_OPERATOR]);
        $operator->clinics()->attach($clinic, ['role' => UserRole::DOCUMENT_OPERATOR->value, 'is_active' => true]);
        $document = MedicalDocument::factory()->create(['clinic_id' => $clinic->id]);
        $this->assertFalse($operator->can('issue', $document));

        $doctor = app(InstitutionalMedicalProvider::class)->doctor();
        $authorized = User::factory()->create(['role' => UserRole::DOCTOR]);
        $doctor->update(['user_id' => $authorized->id]);
        $authorized->clinics()->attach($clinic, ['role' => UserRole::DOCTOR->value, 'is_active' => true]);
        $this->assertTrue($authorized->fresh()->can('issue', $document));
    }

    public function test_authorized_fast_issue_creates_a_locked_pdf_qr_hash_version_and_audit(): void
    {
        Storage::fake('local');
        config([
            'medical_documents.disk' => 'local',
            'medical_documents.encryption_enabled' => true,
            'medical_documents.pdf_password' => 'Feature-Pdf-Encryption-Only!',
        ]);
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $patient = Patient::factory()->create(['age' => 21]);
        $clinic = Clinic::create(['code' => 'HN-08', 'slug' => 'principal', 'name' => 'Principal', 'department' => 'Francisco Morazan', 'status' => 'ACTIVE']);

        $response = $this->actingAs($user)->post(route('admin.documents.generate.store', 'constancia'), [
            'patient_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'consultation_date' => '2026-08-10',
            'diagnosis' => 'Evaluación clínica documentada.',
            'free_text' => 'Por medio de la presente se hace constar que el paciente fue atendido en esta clínica.',
            'confirm' => true,
            'intent' => 'issue',
        ]);

        $document = MedicalDocument::firstOrFail();
        $response->assertRedirect(route('admin.documents.review', $document));
        $this->assertSame(MedicalDocumentStatus::ISSUED, $document->fresh()->status);
        $this->assertNotNull($document->public_code);
        $this->assertNotNull($document->issued_sha256);
        $this->assertSame($user->id, $document->issued_by);
        $this->assertSame(2, $document->versions()->count());
        $this->assertTrue($document->auditLogs()->where('action', 'issued')->exists());
        Storage::disk('local')->assertExists($document->getRawOriginal('issued_path'));
        $issuedPath = Storage::disk('local')->path($document->getRawOriginal('issued_path'));
        app(PdfEncryptionService::class)->assertEncrypted($issuedPath);
        $decrypted = tempnam(sys_get_temp_dir(), 'csa-decrypted-');
        app(PdfEncryptionService::class)->decrypt($issuedPath, $decrypted);
        app(PdfDocumentInspectionService::class)->assertOnePage($decrypted);
        @unlink($decrypted);

        $this->expectException(\DomainException::class);
        $document->update(['diagnosis' => 'No debe cambiar']);
    }

    public function test_qr_and_pdf_verifications_record_method_and_exact_timestamp(): void
    {
        $qr = app(QrCodeService::class);
        $token = $qr->token();
        $path = tempnam(sys_get_temp_dir(), 'verify-');
        file_put_contents($path, 'issued pdf fixture');
        $document = MedicalDocument::factory()->create([
            'status' => MedicalDocumentStatus::ISSUED,
            'token_hash' => $qr->tokenHash($token),
            'public_code' => 'CSA-2026-VERIFY',
            'issued_sha256' => hash_file('sha256', $path),
            'issued_at' => now(),
        ]);
        $service = app(MedicalDocumentVerificationService::class);
        $this->assertSame('VALID', $service->byToken($token)['status']);
        $this->assertSame('VALID', $service->byFile($path)['status']);
        @unlink($path);

        $this->assertDatabaseHas('document_verification_logs', ['medical_document_id' => $document->id, 'method' => 'QR_LINK', 'result' => 'VALID']);
        $this->assertDatabaseHas('document_verification_logs', ['medical_document_id' => $document->id, 'method' => 'PDF_HASH', 'result' => 'VALID']);
        $this->assertNotNull($document->verificationLogs()->latest('verified_at')->first()->verified_at);
    }

    public function test_sensitive_details_require_the_identity_second_factor(): void
    {
        $qr = app(QrCodeService::class);
        $token = $qr->token();
        $patient = Patient::factory()->create(['document_number' => '0801200519904']);
        MedicalDocument::factory()->create([
            'patient_id' => $patient->id,
            'status' => MedicalDocumentStatus::ISSUED,
            'token_hash' => $qr->tokenHash($token),
            'public_code' => 'CSA-2026-PRIVATE',
            'diagnosis' => 'Contenido clínico autorizado.',
            'issued_at' => now(),
        ]);

        $locked = app(MedicalDocumentVerificationService::class)->byToken($token);
        $this->assertFalse($locked['document']['verification']['details_verified']);
        $this->assertNull($locked['document']['patient']['name']);
        $this->assertNull($locked['document']['diagnosis']);

        $unlocked = app(MedicalDocumentVerificationService::class)->byToken($token, '9904');
        $this->assertTrue($unlocked['document']['verification']['details_verified']);
        $this->assertNotNull($unlocked['document']['patient']['name']);
        $this->assertSame('Contenido clínico autorizado.', $unlocked['document']['diagnosis']);
    }

    public function test_operational_pages_render_for_an_authenticated_administrator(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        Clinic::create(['code' => 'HN-08', 'slug' => 'principal', 'name' => 'Principal', 'department' => 'Francisco Morazan', 'status' => 'ACTIVE']);

        foreach ([
            ['admin.dashboard', [], 'Admin/Dashboard'],
            ['admin.documents.index', [], 'Admin/Documents/Index'],
            ['admin.verifications.index', [], 'Admin/Verifications/Index'],
            ['admin.patients.index', [], 'Admin/Patients/Index'],
            ['admin.documents.generate', 'constancia', 'Admin/Documents/Generate'],
            ['admin.documents.generate', 'incapacidad', 'Admin/Documents/Generate'],
        ] as [$routeName, $parameters, $component]) {
            $this->actingAs($user)->get(route($routeName, $parameters))->assertOk()->assertInertia(
                fn (Assert $page) => $page->component($component)
            );
        }
    }
}
