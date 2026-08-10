<?php

namespace Tests\Feature;

use App\Enums\MedicalDocumentStatus;
use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\ClinicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClinicNetworkAndGeneratedDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_network_has_all_departments_with_referential_coordinates(): void
    {
        $this->seed(ClinicSeeder::class);

        $this->assertDatabaseCount('clinics', 18);
        $this->assertSame(18, Clinic::distinct()->count('department'));
        $this->assertSame(18, Clinic::whereNotNull('latitude')->whereNotNull('longitude')->count());
        $this->assertSame(18, Clinic::where('status', 'ACTIVE')->where('is_public', true)->count());
    }

    public function test_generated_certificate_creates_a_private_reviewable_original(): void
    {
        Storage::fake('local');
        config(['medical_documents.disk' => 'local']);
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();
        $clinic = Clinic::create(['code' => 'HN-QA', 'slug' => 'qa', 'name' => 'QA', 'department' => 'QA', 'status' => 'ACTIVE']);

        $response = $this->actingAs($user)->post(route('admin.documents.generate.store', 'constancia'), [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'consultation_date' => '2026-08-10',
            'medical_reason' => 'Contenido autorizado para prueba automatizada.',
            'recommendations' => 'Revision humana requerida.',
            'confirm' => true,
        ]);

        $document = MedicalDocument::firstOrFail();
        $response->assertRedirect(route('admin.documents.review', $document));
        $this->assertSame(MedicalDocumentStatus::REVIEW_REQUIRED, $document->status);
        $this->assertSame('GENERATED', $document->source_kind);
        $this->assertSame('CONSTANCIA', $document->certificate_kind);
        $this->assertNull($document->issued_at);
        $this->assertCount(1, $document->versions);
        Storage::disk('local')->assertExists($document->getRawOriginal('original_path'));
    }

    public function test_incapacity_rejects_a_day_count_that_does_not_match_its_range(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();
        $clinic = Clinic::create(['code' => 'HN-QA', 'slug' => 'qa', 'name' => 'QA', 'department' => 'QA', 'status' => 'ACTIVE']);

        $this->actingAs($user)->post(route('admin.documents.generate.store', 'incapacidad'), [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'consultation_date' => '2026-08-10',
            'medical_reason' => 'Contenido autorizado para prueba automatizada.',
            'leave_start_date' => '2026-08-10',
            'leave_end_date' => '2026-08-12',
            'leave_days' => 2,
            'confirm' => true,
        ])->assertSessionHasErrors('leave_days');

        $this->assertDatabaseCount('medical_documents', 0);
    }

    public function test_clinic_membership_blocks_cross_clinic_patient_and_document_access(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $allowed = Clinic::create(['code' => 'HN-A', 'slug' => 'a', 'name' => 'A', 'department' => 'A', 'status' => 'ACTIVE']);
        $restricted = Clinic::create(['code' => 'HN-B', 'slug' => 'b', 'name' => 'B', 'department' => 'B', 'status' => 'ACTIVE']);
        $user->clinics()->attach($allowed, ['role' => UserRole::ADMINISTRATOR->value, 'is_active' => true]);
        $patient = Patient::factory()->create();
        $patient->clinics()->attach($restricted, ['medical_record_number' => 'QA-1']);
        $document = MedicalDocument::factory()->create(['clinic_id' => $restricted->id, 'patient_id' => $patient->id]);

        $this->actingAs($user)->get(route('admin.patients.show', $patient))->assertForbidden();
        $this->actingAs($user)->get(route('admin.documents.review', $document))->assertForbidden();
    }
}
