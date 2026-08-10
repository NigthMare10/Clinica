<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\MedicalDocument;
use App\Models\Patient;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $this->authorize('viewAny', Patient::class);
        $query = trim($request->string('q')->toString());
        $results = ['patients' => [], 'documents' => [], 'clinics' => []];

        if (mb_strlen($query) >= 2) {
            $like = '%'.addcslashes($query, '%_\\').'%';
            $results['patients'] = Patient::query()->accessibleTo($request->user())
                ->where(fn ($builder) => $builder->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like))
                ->limit(8)->get(['id', 'first_name', 'last_name', 'document_type']);
            $results['documents'] = MedicalDocument::query()->accessibleTo($request->user())
                ->where(fn ($builder) => $builder->where('public_code', 'like', $like)->orWhere('original_filename', 'like', $like))
                ->limit(8)->get(['id', 'type', 'status', 'public_code', 'original_filename', 'created_at']);
            $results['clinics'] = Clinic::query()->whereIn('id', $request->user()->accessibleClinicIds())
                ->where(fn ($builder) => $builder->where('name', 'like', $like)->orWhere('department', 'like', $like))
                ->limit(8)->get(['id', 'name', 'department', 'status']);
        }

        return Inertia::render('Admin/Search/Index', ['query' => $query, 'results' => $results]);
    }
}
