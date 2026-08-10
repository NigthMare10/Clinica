<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatientRequest;
use App\Models\Patient;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Patient::class);

        $patients = Patient::accessibleTo(request()->user())->orderBy('last_name')->paginate(30)->through(fn (Patient $patient) => [
            'id' => $patient->id,
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'document_type' => $patient->document_type,
            'document_number' => $patient->document_number,
            'birth_date' => $patient->birth_date?->toDateString(),
            'age' => $patient->age,
            'sex' => $patient->sex,
            'email' => $patient->email,
            'phone' => $patient->phone,
        ]);

        return Inertia::render('Admin/Patients/Index', ['patients' => $patients]);
    }

    public function create()
    {
        $this->authorize('create', Patient::class);

        return Inertia::render('Admin/Patients/Create');
    }

    public function show(Patient $patient): Response
    {
        $this->authorize('view', $patient);
        $documents = $patient->documents()->accessibleTo(request()->user())->with(['doctor:id,first_name,last_name', 'clinic:id,name,department'])->withCount('verificationLogs')
            ->orderByDesc('consultation_date')->orderByDesc('issued_at')->orderByDesc('created_at')
            ->paginate(15);

        return Inertia::render('Admin/Patients/Show', [
            'patient' => [
                'id' => $patient->id,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'document_type' => $patient->document_type,
                'document_number' => $patient->document_number,
                'birth_date' => $patient->birth_date?->toDateString(),
                'age' => $patient->age,
                'sex' => $patient->sex,
                'email' => $patient->email,
                'phone' => $patient->phone,
            ],
            'documents' => $documents,
        ]);
    }

    public function store(PatientRequest $request)
    {
        Patient::create($request->validated());

        return redirect()->route('admin.patients.index');
    }

    public function edit(Patient $patient)
    {
        $this->authorize('update', $patient);

        return Inertia::render('Admin/Patients/Edit', ['patient' => $patient]);
    }

    public function update(PatientRequest $request, Patient $patient)
    {
        $patient->update($request->validated());

        return redirect()->route('admin.patients.index');
    }
}
