<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SitePageRequest;
use App\Models\SitePage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SitePageController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', SitePage::class);

        return Inertia::render('Admin/Content/Index', ['pages' => SitePage::orderBy('title')->paginate(30)]);
    }

    public function create()
    {
        $this->authorize('create', SitePage::class);

        return Inertia::render('Admin/Content/Create');
    }

    public function store(SitePageRequest $request)
    {
        SitePage::create($request->validated());

        return redirect()->route('admin.content.index');
    }

    public function edit(SitePage $page)
    {
        $this->authorize('update', $page);

        return Inertia::render('Admin/Content/Edit', ['page' => $page]);
    }

    public function update(SitePageRequest $request, SitePage $page)
    {
        $page->update($request->validated());

        return redirect()->route('admin.content.index');
    }

    public function activate(Request $request, SitePage $page)
    {
        $this->authorize('update', $page);
        $page->update(['is_published' => $request->boolean('active')]);

        return back();
    }
}
