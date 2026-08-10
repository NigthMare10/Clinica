<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingRequest;
use App\Models\Setting;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Setting::class);

        return Inertia::render('Admin/Settings/Index', ['settings' => Setting::orderBy('key')->get()]);
    }

    public function create()
    {
        $this->authorize('create', Setting::class);

        return Inertia::render('Admin/Settings/Create', ['keys' => SettingRequest::KEYS]);
    }

    public function store(SettingRequest $request)
    {
        Setting::create($request->validated());

        return redirect()->route('admin.settings.index');
    }

    public function edit(Setting $setting)
    {
        $this->authorize('update', $setting);

        return Inertia::render('Admin/Settings/Edit', ['setting' => $setting, 'keys' => SettingRequest::KEYS]);
    }

    public function update(SettingRequest $request, Setting $setting)
    {
        $setting->update($request->validated());

        return redirect()->route('admin.settings.index');
    }
}
