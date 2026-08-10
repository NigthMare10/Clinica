<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MedicalDocument;
use App\Services\MedicalDocuments\MedicalDocumentAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PrivateDocumentDownloadController extends Controller
{
    public function __invoke(Request $request, MedicalDocument $document, string $version, MedicalDocumentAuditService $audit)
    {
        $this->authorize('view', $document);
        abort_unless(in_array($version, ['original', 'issued'], true), 404);
        $path = $version === 'original' ? $document->original_path : $document->issued_path;
        $prefix = $version === 'original' ? 'medical/original/' : 'medical/issued/';
        abort_unless(is_string($path) && str_starts_with($path, $prefix) && ! str_contains($path, '..'), 404);
        abort_unless($path && Storage::disk(config('medical_documents.disk'))->exists($path), 404);
        $audit->record($document, 'downloaded_'.$version, $request->user());

        return Storage::disk(config('medical_documents.disk'))->download($path, $version.'-'.$document->id.'.pdf', [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private, max-age=0', 'Pragma' => 'no-cache', 'Expires' => '0',
        ]);
    }
}
