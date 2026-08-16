<?php

namespace Database\Seeders;

use App\Enums\MedicalDocumentStatus;
use App\Enums\MedicalDocumentType;
use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DocumentExtraction;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\PdfTemplate;
use App\Models\Setting;
use App\Models\SitePage;
use App\Models\Specialty;
use App\Models\User;
use FPDF;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class E2ESeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('e2e'), 403, 'E2E fixtures are restricted to the e2e environment.');

        $password = 'E2E-Clinic-Only-2026!';
        $admin = User::factory()->create(['name' => 'Administradora E2E', 'email' => 'admin@e2e.invalid',
            'password' => Hash::make($password), 'role' => UserRole::SUPER_ADMIN]);
        User::factory()->create(['name' => 'Auditora E2E', 'email' => 'auditor@e2e.invalid',
            'password' => Hash::make($password), 'role' => UserRole::AUDITOR]);
        $this->call(ClinicSeeder::class);
        $clinic = Clinic::where('code', 'HN-08')->firstOrFail();

        $specialty = Specialty::forceCreate(['id' => '10000000-0000-4000-8000-000000000001', 'name' => 'Medicina E2E',
            'slug' => 'medicina-e2e', 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $doctor = Doctor::forceCreate(['id' => '10000000-0000-4000-8000-000000000002',
            'first_name' => config('institution.provider.first_name'), 'last_name' => config('institution.provider.last_name'),
            'professional_name' => config('institution.provider.name'), 'credential_type' => config('institution.provider.credential_type'),
            'credential_number' => config('institution.provider.credential_number'), 'is_active' => true, 'is_public' => true]);
        $doctor->specialties()->attach($specialty->id, ['is_primary' => true]);
        $patient = Patient::forceCreate(['id' => '10000000-0000-4000-8000-000000000003', 'document_type' => 'Identidad',
            'document_number' => '0801-1999-12345', 'first_name' => 'Paciente', 'last_name' => 'Ficticia', 'age' => 27]);
        $template = PdfTemplate::forceCreate(['id' => '10000000-0000-4000-8000-000000000004', 'name' => 'Plantilla E2E Letter',
            'document_type' => MedicalDocumentType::MEDICAL_CERTIFICATE->value, 'page_size' => 'LETTER', 'qr_page' => 1,
            'qr_x' => 165, 'qr_y' => 220, 'qr_width' => 28, 'qr_height' => 28, 'coordinates' => [
                'qr' => ['x' => 165, 'y' => 220, 'width' => 28],
                'code' => ['x' => 155, 'y' => 250, 'font_size' => 7],
            ], 'is_active' => true]);
        $page = SitePage::forceCreate(['id' => '10000000-0000-4000-8000-000000000005', 'slug' => 'clinica',
            'title' => 'Clinica E2E', 'content' => '<p>Contenido institucional ficticio.</p>', 'is_published' => true]);
        SitePage::forceCreate(['slug' => 'contacto', 'title' => 'Contacto E2E', 'content' => '<p>Canales ficticios.</p>', 'is_published' => true]);

        $setting = Setting::forceCreate(['id' => '10000000-0000-4000-8000-000000000006',
            'key' => 'verification.show_diagnosis', 'value' => false, 'is_public' => false]);
        $identitySetting = Setting::forceCreate(['id' => '10000000-0000-4000-8000-000000000007',
            'key' => 'verification.require_identity_last4', 'value' => false, 'is_public' => false]);
        foreach (['verification.show_patient_name' => false, 'verification.show_full_identity' => false,
            'privacy.public_doctor_credentials' => false] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'is_public' => false]);
        }

        $fixtureDir = base_path('tests/Fixtures');
        if (! is_dir($fixtureDir)) {
            mkdir($fixtureDir, 0755, true);
        }
        $fixture = $fixtureDir.'/e2e-medical-certificate.pdf';
        $unknown = $fixtureDir.'/e2e-unknown.pdf';
        $this->createPdf($fixture, true);
        $this->createPdf($unknown, false);
        $bytes = file_get_contents($fixture);
        Storage::disk('local')->put('medical/original/e2e-original.pdf', $bytes);
        Storage::disk('local')->put('medical/issued/e2e-issued.pdf', $bytes);
        $hash = hash('sha256', $bytes);

        $base = ['type' => MedicalDocumentType::MEDICAL_CERTIFICATE, 'clinic_id' => $clinic->id, 'patient_id' => $patient->id,
            'doctor_id' => $doctor->id, 'specialty_id' => $specialty->id, 'pdf_template_id' => $template->id,
            'uploaded_by' => $admin->id, 'original_filename' => 'constancia-e2e.pdf',
            'original_path' => 'medical/original/e2e-original.pdf', 'original_sha256' => $hash];
        $normalCandidates = [
            ['field' => 'patient_name', 'value' => 'Paciente Ficticia', 'source' => 'HEADER', 'confidence' => .99],
            ['field' => 'issue_date', 'value' => '10/08/2026', 'source' => 'HEADER', 'confidence' => .99],
            ['field' => 'doctor_name', 'value' => config('institution.provider.name'), 'source' => 'BODY', 'confidence' => .99],
            ['field' => 'doctor_credential', 'value' => config('institution.provider.credential_number'), 'source' => 'BODY', 'confidence' => .99],
        ];
        $review = $this->document('20000000-0000-4000-8000-000000000001', $base, MedicalDocumentStatus::REVIEW_REQUIRED);
        DocumentExtraction::forceCreate(['medical_document_id' => $review->id, 'engine' => 'pdftotext',
            'raw_text' => 'Texto ficticio E2E', 'quality_score' => .99, 'candidates' => $normalCandidates, 'warnings' => []]);
        $conflict = $this->document('20000000-0000-4000-8000-000000000002', $base, MedicalDocumentStatus::REVIEW_REQUIRED,
            ['inconsistencies' => [['field' => 'consultation_date', 'code' => 'CONSULTATION_DATE_CONFLICT', 'blocking' => true]]]);
        DocumentExtraction::forceCreate(['medical_document_id' => $conflict->id, 'engine' => 'pdftotext', 'raw_text' => 'Texto ficticio con conflicto',
            'quality_score' => .99, 'candidates' => [...$normalCandidates,
                ['field' => 'consultation_date', 'value' => '06/07/2026', 'source' => 'HEADER', 'confidence' => .99],
                ['field' => 'consultation_date', 'value' => '06/08/2026', 'source' => 'BODY', 'confidence' => .99]], 'warnings' => []]);
        $approvable = $this->document('20000000-0000-4000-8000-000000000003', $base, MedicalDocumentStatus::REVIEW_REQUIRED);
        DocumentExtraction::forceCreate(['medical_document_id' => $approvable->id, 'engine' => 'pdftotext', 'raw_text' => 'Texto ficticio aprobable',
            'quality_score' => .99, 'candidates' => $normalCandidates, 'warnings' => []]);
        $ready = $this->document('20000000-0000-4000-8000-000000000004', $base, MedicalDocumentStatus::READY,
            ['reviewed_by' => $admin->id, 'reviewed_at' => now(), 'confirmed_fields' => [
                'patient_name' => 'Paciente Ficticia', 'issue_date' => '10/08/2026', 'doctor_name' => config('institution.provider.name'),
                'doctor_credential' => config('institution.provider.credential_number')], 'inconsistencies' => []]);
        $this->document('20000000-0000-4000-8000-000000000005', $base, MedicalDocumentStatus::PROCESSING);
        $this->document('20000000-0000-4000-8000-000000000006', $base, MedicalDocumentStatus::FAILED,
            ['processing_metadata' => ['error' => 'Fallo ficticio controlado']]);

        $tokens = [
            'valid' => rtrim(strtr(base64_encode(str_repeat("\x11", 32)), '+/', '-_'), '='),
            'revoked' => rtrim(strtr(base64_encode(str_repeat("\x22", 32)), '+/', '-_'), '='),
            'replaced' => rtrim(strtr(base64_encode(str_repeat("\x33", 32)), '+/', '-_'), '='),
            'identity' => rtrim(strtr(base64_encode(str_repeat("\x44", 32)), '+/', '-_'), '='),
        ];
        $valid = $this->issued('20000000-0000-4000-8000-000000000007', $base, $admin, $tokens['valid'], 'CSA-2026-E2E1-VALID', $hash,
            MedicalDocumentStatus::ISSUED, ['consultation_date' => '2026-08-10', 'consultation_time' => '11:00', 'age_at_consultation' => 27,
                'confirmed_fields' => ['free_text' => 'Por medio de la presente se hace constar que la paciente ficticia fue atendida en consulta médica.']]);
        $this->issued('20000000-0000-4000-8000-000000000008', $base, $admin, $tokens['revoked'], 'CSA-2026-E2E2-REVO', null,
            MedicalDocumentStatus::REVOKED, ['revoked_at' => now(), 'revoked_by' => $admin->id,
                'revocation_reason' => 'Revocacion ficticia E2E']);
        $this->issued('20000000-0000-4000-8000-000000000009', $base, $admin, $tokens['replaced'], 'CSA-2026-E2E3-REPL', null,
            MedicalDocumentStatus::REPLACED);
        $this->issued('20000000-0000-4000-8000-000000000010', $base, $admin, $tokens['identity'], 'CSA-2026-E2E4-GATE', null);

        $manifest = ['E2E_ADMIN_EMAIL' => 'admin@e2e.invalid', 'E2E_ADMIN_PASSWORD' => $password,
            'E2E_AUDITOR_EMAIL' => 'auditor@e2e.invalid', 'E2E_AUDITOR_PASSWORD' => $password,
            'E2E_SPECIALTY_ID' => $specialty->id, 'E2E_DOCTOR_ID' => $doctor->id, 'E2E_PATIENT_ID' => $patient->id,
            'E2E_TEMPLATE_ID' => $template->id, 'E2E_SITE_PAGE_ID' => $page->id, 'E2E_SETTING_ID' => $setting->id,
            'E2E_IDENTITY_SETTING_ID' => $identitySetting->id,
            'E2E_REVIEW_DOCUMENT_ID' => $review->id, 'E2E_CONFLICT_DOCUMENT_ID' => $conflict->id,
            'E2E_APPROVABLE_DOCUMENT_ID' => $approvable->id, 'E2E_READY_DOCUMENT_ID' => $ready->id, 'E2E_VALID_DOCUMENT_ID' => $valid->id,
            'E2E_VALID_TOKEN' => $tokens['valid'], 'E2E_REVOKED_TOKEN' => $tokens['revoked'],
            'E2E_REPLACED_TOKEN' => $tokens['replaced'], 'E2E_UPLOAD_PDF_PATH' => $fixture,
            'E2E_IDENTITY_TOKEN' => $tokens['identity'],
            'E2E_ISSUED_PDF_PATH' => Storage::disk('local')->path($valid->issued_path), 'E2E_UNKNOWN_PDF_PATH' => $unknown];
        $output = storage_path('framework/testing');
        if (! is_dir($output)) {
            mkdir($output, 0755, true);
        }
        file_put_contents($output.'/e2e-fixtures.json', json_encode($manifest, JSON_PRETTY_PRINT));
    }

    private function document(string $id, array $base, MedicalDocumentStatus $status, array $extra = []): MedicalDocument
    {
        return MedicalDocument::forceCreate(['id' => $id, ...$base, 'status' => $status, ...$extra]);
    }

    private function issued(string $id, array $base, User $admin, string $token, string $code, ?string $hash,
        MedicalDocumentStatus $status = MedicalDocumentStatus::ISSUED, array $extra = []): MedicalDocument
    {
        return $this->document($id, $base, $status, ['reviewed_by' => $admin->id, 'reviewed_at' => now(),
            'issued_by' => $admin->id, 'issued_at' => now(), 'token_hash' => hash('sha256', $token), 'public_code' => $code,
            'issued_path' => 'medical/issued/e2e-issued.pdf', 'issued_sha256' => $hash, 'confirmed_fields' => [], 'inconsistencies' => [], ...$extra]);
    }

    private function createPdf(string $path, bool $medical): void
    {
        $pdf = new FPDF('P', 'mm', 'Letter');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 12, $medical ? 'CONSTANCIA MEDICA E2E' : 'ARCHIVO DIFERENTE E2E', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 11);
        $pdf->MultiCell(0, 7, $medical
            ? "PACIENTE: Paciente Ficticia   EDAD: 27   FECHA: 10/08/2026\n".config('institution.provider.name')."\nCedula profesional: ".config('institution.provider.credential_number')."\nDocumento exclusivamente ficticio para pruebas automatizadas."
            : 'Documento ficticio sin correspondencia de hash.');
        $pdf->Output('F', $path);
    }
}
