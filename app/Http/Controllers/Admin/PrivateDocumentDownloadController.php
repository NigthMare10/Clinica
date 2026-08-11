<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MedicalDocument;
use App\Services\MedicalDocuments\MedicalDocumentAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $document->loadMissing('patient');
        $kind = $document->certificate_kind === 'INCAPACIDAD' ? 'Incapacidad_Medica' : 'Constancia_Medica';
        $patient = $this->filenamePart(trim(($document->patient?->first_name ?? 'Paciente').' '.($document->patient?->last_name ?? 'No_Identificado')));
        $date = ($document->consultation_date ?? $document->created_at)->format('Y-m-d');

        return Storage::disk(config('medical_documents.disk'))->download($path, $kind.'_'.$patient.'_'.$date.'.pdf', [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private, max-age=0', 'Pragma' => 'no-cache', 'Expires' => '0',
        ]);
    }

    private function filenamePart(string $value): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', Str::ascii($value)), '_') ?: 'Paciente';
    }
}
