<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SpecialtyRequest;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SpecialtyController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Specialty::class);

        return Inertia::render('Admin/Specialties/Index', ['specialties' => Specialty::orderBy('name')->paginate(30)]);
    }

    public function create()
    {
        $this->authorize('create', Specialty::class);

        return Inertia::render('Admin/Specialties/Create');
    }

    public function store(SpecialtyRequest $request)
    {
        Specialty::create($request->validated());

        return redirect()->route('admin.specialties.index');
    }

    public function edit(Specialty $specialty)
    {
        $this->authorize('update', $specialty);

        return Inertia::render('Admin/Specialties/Edit', ['specialty' => $specialty]);
    }

    public function update(SpecialtyRequest $request, Specialty $specialty)
    {
        $specialty->update($request->validated());

        return redirect()->route('admin.specialties.index');
    }

    public function activate(Request $request, Specialty $specialty)
    {
        $this->authorize('update', $specialty);
        $specialty->update(['is_active' => $request->boolean('active')]);

        return back();
    }
}
