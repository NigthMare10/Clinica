<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DocumentVerificationLog;
use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\Specialty;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $documents = MedicalDocument::query()->accessibleTo(request()->user())->when(request()->user()->role === UserRole::DOCTOR,
            fn ($query) => $query->where('doctor_id', request()->user()->doctor?->id));

        return Inertia::render('Admin/Dashboard', [
            'counts' => [
                'documents' => (clone $documents)->count(),
                'documents_today' => (clone $documents)->whereDate('created_at', today())->count(),
                'pending_review' => (clone $documents)->where('status', 'REVIEW_REQUIRED')->count(),
                'generated' => (clone $documents)->where('source_kind', 'GENERATED')->count(),
                'certificates' => (clone $documents)->where('certificate_kind', 'CONSTANCIA')->count(),
                'incapacities' => (clone $documents)->where('certificate_kind', 'INCAPACIDAD')->count(),
                'verifications' => DocumentVerificationLog::where('successful', true)->count(),
                'doctors' => Doctor::count(),
                'patients' => Patient::count(),
                'specialties' => Specialty::count(),
                'active_clinics' => Clinic::where('status', 'ACTIVE')->count(),
            ],
            'recent' => (clone $documents)->with(['patient:id,first_name,last_name', 'doctor:id,first_name,last_name'])
                ->latest()->limit(6)->get(['id', 'type', 'status', 'patient_id', 'doctor_id', 'public_code', 'created_at']),
        ]);
    }
}
