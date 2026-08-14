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
        $today = now(config('institution.timezone'))->startOfDay()->utc();
        $documentCounts = (clone $documents)->selectRaw(
            'COUNT(*) as documents,
            SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as documents_today,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_review,
            SUM(CASE WHEN source_kind = ? THEN 1 ELSE 0 END) as generated,
            SUM(CASE WHEN certificate_kind = ? THEN 1 ELSE 0 END) as certificates,
            SUM(CASE WHEN certificate_kind = ? THEN 1 ELSE 0 END) as incapacities',
            [$today, $today->addDay(), 'REVIEW_REQUIRED', 'GENERATED', 'CONSTANCIA', 'INCAPACIDAD']
        )->first();

        return Inertia::render('Admin/Dashboard', [
            'counts' => [
                'documents' => (int) $documentCounts->documents,
                'documents_today' => (int) $documentCounts->documents_today,
                'pending_review' => (int) $documentCounts->pending_review,
                'generated' => (int) $documentCounts->generated,
                'certificates' => (int) $documentCounts->certificates,
                'incapacities' => (int) $documentCounts->incapacities,
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
