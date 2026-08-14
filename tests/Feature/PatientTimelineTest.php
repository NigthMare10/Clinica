<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\MedicalDocumentStatus;
use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PatientTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_timeline_only_returns_the_current_document_revision_and_its_active_invoice(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $patient = Patient::factory()->create();
        $clinic = Clinic::create(['code' => 'TIMELINE', 'slug' => 'timeline', 'name' => 'Timeline Clinic', 'department' => 'Test']);
        $superseded = MedicalDocument::factory()->create([
            'patient_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'public_code' => 'DOC-TIMELINE-1',
            'status' => MedicalDocumentStatus::REPLACED,
            'is_current_revision' => false,
        ]);
        $current = MedicalDocument::factory()->create([
            'patient_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'public_code' => 'DOC-TIMELINE-1',
            'status' => MedicalDocumentStatus::ISSUED,
            'is_current_revision' => true,
            'consultation_date' => '2026-08-14',
            'consultation_time' => '14:30:00',
        ]);
        Invoice::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'medical_document_id' => $current->id,
            'created_by' => $user->id,
            'status' => InvoiceStatus::VOID,
            'ncf' => '000-OLD',
        ]);
        $active = Invoice::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'medical_document_id' => $current->id,
            'created_by' => $user->id,
            'status' => InvoiceStatus::ISSUED,
            'ncf' => '000-CURRENT',
            'issued_at' => now(),
        ]);

        $this->actingAs($user)->get(route('admin.patients.show', $patient))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Patients/Show')
                ->has('documents.data', 1)
                ->where('documents.data.0.id', $current->id)
                ->where('documents.data.0.code', 'DOC-TIMELINE-1')
                ->where('documents.data.0.consultation_date', '2026-08-14')
                ->where('documents.data.0.consultation_time', '14:30:00')
                ->where('documents.data.0.invoice.state', 'active')
                ->where('documents.data.0.invoice.linked.id', $active->id)
                ->where('documents.data.0.invoice.linked.ncf', '000-CURRENT')
                ->where('documents.data.0.invoice.historical_count', 1));

        $this->assertDatabaseHas('medical_documents', ['id' => $superseded->id]);
    }
}
