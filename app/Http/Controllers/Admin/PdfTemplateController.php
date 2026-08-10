<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PdfTemplateRequest;
use App\Models\PdfTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PdfTemplateController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', PdfTemplate::class);

        return Inertia::render('Admin/Templates/Index', ['templates' => PdfTemplate::latest()->paginate(30)]);
    }

    public function create()
    {
        $this->authorize('create', PdfTemplate::class);

        return Inertia::render('Admin/Templates/Create');
    }

    public function store(PdfTemplateRequest $request)
    {
        PdfTemplate::create($request->validated());

        return redirect()->route('admin.templates.index');
    }

    public function edit(PdfTemplate $template)
    {
        $this->authorize('update', $template);

        return Inertia::render('Admin/Templates/Edit', ['template' => $template]);
    }

    public function update(PdfTemplateRequest $request, PdfTemplate $template)
    {
        $template->update($request->validated());

        return redirect()->route('admin.templates.index');
    }

    public function activate(Request $request, PdfTemplate $template)
    {
        $this->authorize('update', $template);
        $template->update(['is_active' => $request->boolean('active')]);

        return back();
    }
}
