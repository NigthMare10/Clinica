<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\SitePage;
use App\Models\Specialty;
use Inertia\Inertia;
use Inertia\Response;

class PublicSiteController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('Public/Home', [
            'specialties' => $this->specialtyQuery()->limit(15)->get(),
            'clinics' => Clinic::query()->where('status', 'ACTIVE')->where('is_public', true)->orderBy('sort_order')->get([
                'id', 'code', 'slug', 'name', 'department', 'latitude', 'longitude', 'status', 'is_public',
            ]),
        ]);
    }

    public function specialties(): Response
    {
        return Inertia::render('Public/Specialties/Index', ['specialties' => $this->specialtyQuery()->paginate(20)]);
    }

    public function specialty(Specialty $specialty): Response
    {
        abort_unless($specialty->is_active && $specialty->is_public, 404);

        return Inertia::render('Public/Specialties/Show', ['specialty' => $specialty]);
    }

    public function clinics(): Response
    {
        return Inertia::render('Public/Clinics/Index', [
            'clinics' => Clinic::query()->where('status', 'ACTIVE')->where('is_public', true)->orderBy('sort_order')->get([
                'id', 'code', 'slug', 'name', 'department', 'latitude', 'longitude', 'address', 'status', 'is_public',
            ]),
        ]);
    }

    public function page(string $slug, string $component): Response
    {
        return Inertia::render($component, ['page' => SitePage::where('slug', $slug)->where('is_published', true)->first()]);
    }

    private function specialtyQuery()
    {
        return Specialty::query()->where('is_active', true)->where('is_public', true)->orderBy('sort_order')->orderBy('name');
    }
}
