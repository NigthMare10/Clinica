<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DocumentAuditLog;
use App\Models\Patient;
use App\Models\PdfTemplate;
use App\Models\Setting;
use App\Models\Specialty;
use Inertia\Inertia;

class CatalogController extends Controller
{
    public function specialties()
    {
        return Inertia::render('Admin/Specialties/Index', ['specialties' => Specialty::orderBy('name')->paginate(30)]);
    }

    public function doctors()
    {
        return Inertia::render('Admin/Doctors/Index', ['doctors' => Doctor::with('specialties')->orderBy('last_name')->paginate(30)]);
    }

    public function patients()
    {
        return Inertia::render('Admin/Patients/Index', ['patients' => Patient::orderBy('last_name')->paginate(30)]);
    }

    public function templates()
    {
        return Inertia::render('Admin/Templates/Index', ['templates' => PdfTemplate::latest()->paginate(30)]);
    }

    public function audit()
    {
        abort_unless(request()->user()->hasAnyRole(UserRole::SUPER_ADMIN, UserRole::ADMINISTRATOR, UserRole::AUDITOR), 403);

        return Inertia::render('Admin/Audit/Index', ['logs' => DocumentAuditLog::with(['document'])->latest()->paginate(50)]);
    }

    public function settings()
    {
        return Inertia::render('Admin/Settings/Index', ['settings' => Setting::orderBy('key')->get()]);
    }
}
