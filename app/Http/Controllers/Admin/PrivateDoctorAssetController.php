<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PrivateDoctorAssetController extends Controller
{
    public function __invoke(Request $request, Doctor $doctor, string $asset)
    {
        abort_unless($request->user()->hasAnyRole(UserRole::SUPER_ADMIN, UserRole::ADMINISTRATOR), 403);
        abort_unless(in_array($asset, ['signature', 'seal'], true), 404);
        $path = $doctor->getRawOriginal($asset.'_path');
        $prefix = 'medical/doctor-assets/'.$doctor->id.'/';
        abort_unless(is_string($path) && str_starts_with($path, $prefix) && ! str_contains($path, '..'), 404);
        abort_unless(Storage::disk(config('medical_documents.disk'))->exists($path), 404);

        return Storage::disk(config('medical_documents.disk'))->response($path, null, [
            'Cache-Control' => 'no-store, private, max-age=0', 'Pragma' => 'no-cache', 'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff', 'Content-Disposition' => 'inline',
        ]);
    }
}
