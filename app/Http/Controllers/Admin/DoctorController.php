<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorRequest;
use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DoctorController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Doctor::class);

        return Inertia::render('Admin/Doctors/Index', ['doctors' => Doctor::with('specialties')->orderBy('last_name')->paginate(30)]);
    }

    public function create()
    {
        $this->authorize('create', Doctor::class);

        return Inertia::render('Admin/Doctors/Create', ['specialties' => Specialty::orderBy('name')->get()]);
    }

    public function store(DoctorRequest $request)
    {
        $doctor = Doctor::create($request->safe()->except(['signature', 'seal', 'specialty_ids', 'primary_specialty_id']));
        $this->saveAssets($request, $doctor);
        $this->syncSpecialties($request, $doctor);

        return redirect()->route('admin.doctors.index');
    }

    public function edit(Doctor $doctor)
    {
        $this->authorize('update', $doctor);

        return Inertia::render('Admin/Doctors/Edit', ['doctor' => $doctor->load('specialties'), 'specialties' => Specialty::orderBy('name')->get()]);
    }

    public function update(DoctorRequest $request, Doctor $doctor)
    {
        $doctor->update($request->safe()->except(['signature', 'seal', 'specialty_ids', 'primary_specialty_id']));
        $this->saveAssets($request, $doctor);
        $this->syncSpecialties($request, $doctor);

        return redirect()->route('admin.doctors.index');
    }

    public function activate(Request $request, Doctor $doctor)
    {
        $this->authorize('update', $doctor);
        $doctor->update(['is_active' => $request->boolean('active')]);

        return back();
    }

    private function saveAssets(DoctorRequest $request, Doctor $doctor): void
    {
        $disk = Storage::disk(config('medical_documents.disk'));
        foreach (['signature' => 'signature_path', 'seal' => 'seal_path'] as $input => $column) {
            if (! $request->hasFile($input)) {
                continue;
            }
            $file = $request->file($input);
            $extension = strtolower($file->extension());
            $path = $file->storeAs('medical/doctor-assets/'.$doctor->id, Str::uuid().'.'.$extension, config('medical_documents.disk'));
            abort_unless($path, 500, 'Unable to store private doctor asset.');
            $old = $doctor->getRawOriginal($column);
            $doctor->forceFill([$column => $path])->save();
            if (is_string($old) && str_starts_with($old, 'medical/doctor-assets/'.$doctor->id.'/')) {
                $disk->delete($old);
            }
        }
    }

    private function syncSpecialties(DoctorRequest $request, Doctor $doctor): void
    {
        $primary = $request->validated('primary_specialty_id');
        $ids = $request->validated('specialty_ids', []);
        abort_if($primary && ! in_array($primary, $ids, true), 422, 'Primary specialty must be selected.');
        $doctor->specialties()->sync(collect($ids)->mapWithKeys(fn ($id) => [$id => ['is_primary' => $id === $primary]])->all());
    }
}
