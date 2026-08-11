<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentAuditLog;
use App\Models\InstitutionalAsset;
use App\Services\MedicalDocuments\InstitutionalAssetService;
use App\Services\MedicalDocuments\PdfEncryptionService;
use App\Services\MedicalDocuments\PdfToolAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Process\Process;

class InstitutionalAssetController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Signature', ['assets' => InstitutionalAsset::query()->latest()->get()->map(fn (InstitutionalAsset $asset) => [
            'id' => $asset->id, 'kind' => $asset->kind, 'sha256' => $asset->sha256, 'is_active' => $asset->is_active,
            'preview_url' => route('admin.settings.signature.preview', $asset), 'created_at' => $asset->created_at?->toIso8601String(),
        ])]);
    }

    public function store(Request $request, InstitutionalAssetService $assets): RedirectResponse
    {
        $data = $request->validate(['kind' => ['required', 'in:signature,stamp,'.InstitutionalAssetService::SIGNATURE_STAMP_COMBINED], 'asset' => ['required', 'file', 'max:4096', 'mimetypes:image/png,image/webp'], 'active' => ['nullable', 'boolean']]);
        $asset = $assets->store($request->file('asset'), $data['kind'], $request->user(), $request->boolean('active', true));
        $this->audit($asset, strtoupper($data['kind']).'_UPLOADED', $request);

        return back()->with('status', strtoupper($data['kind']).'_UPLOADED: activo institucional guardado.');
    }

    public function preview(InstitutionalAsset $asset)
    {
        $path = Storage::disk(config('medical_documents.disk'))->path($asset->getRawOriginal('path'));
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Cache-Control' => 'private, no-store']);
    }

    public function activate(Request $request, InstitutionalAsset $asset, InstitutionalAssetService $assets): RedirectResponse
    {
        $assets->activate($asset);
        $this->audit($asset, strtoupper($asset->kind).'_REPLACED', $request);

        return back()->with('status', strtoupper($asset->kind).'_REPLACED: activo institucional actualizado.');
    }

    public function destroy(Request $request, InstitutionalAsset $asset): RedirectResponse
    {
        abort_if($asset->is_active, 422, 'Deactivate or replace the active asset first.');
        Storage::disk(config('medical_documents.disk'))->delete($asset->getRawOriginal('path'));
        $this->audit($asset, strtoupper($asset->kind).'_DELETED', $request);
        $asset->delete();

        return back()->with('status', 'Activo institucional eliminado.');
    }

    public function extract(Request $request, InstitutionalAssetService $assets, PdfEncryptionService $encryption, PdfToolAvailabilityService $tools): RedirectResponse
    {
        $data = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf', 'max:15360'], 'kind' => ['required', 'in:signature,stamp,'.InstitutionalAssetService::SIGNATURE_STAMP_COMBINED],
            'x' => ['required', 'integer', 'min:0'], 'y' => ['required', 'integer', 'min:0'], 'width' => ['required', 'integer', 'min:20', 'max:5000'], 'height' => ['required', 'integer', 'min:20', 'max:5000'],
            'confirmed_authorized' => ['accepted'],
        ]);
        $directory = storage_path('app/tmp/signature-extract-'.Str::uuid());
        mkdir($directory, 0700, true);
        try {
            $input = $request->file('document')->getRealPath();
            $pdf = $directory.'/source.pdf';
            copy($input, $pdf);
            $renderInput = $pdf;
            if (str_contains((string) file_get_contents($pdf), '/Encrypt')) {
                $renderInput = $directory.'/decrypted.pdf';
                $encryption->decrypt($pdf, $renderInput);
            }
            $binary = $tools->path('pdftoppm') ?? abort(422, 'Local PDF rendering is unavailable.');
            $prefix = $directory.'/page';
            $process = new Process([$binary, '-f', '1', '-l', '1', '-png', '-r', '300', $renderInput, $prefix]);
            $process->setTimeout(config('medical_documents.process_timeout'))->run();
            abort_unless($process->isSuccessful(), 422, 'Unable to render the authorized PDF locally.');
            $page = $prefix.'-1.png';
            $source = imagecreatefrompng($page);
            abort_unless($source, 422, 'Unable to decode rendered PDF page.');
            abort_if(imagesx($source) < $data['x'] + $data['width'] || imagesy($source) < $data['y'] + $data['height'], 422, 'The selected crop is outside the page.');
            $crop = imagecrop($source, ['x' => $data['x'], 'y' => $data['y'], 'width' => $data['width'], 'height' => $data['height']]);
            imagedestroy($source);
            abort_unless($crop, 422, 'Unable to crop the selected area.');
            $croppedPath = $directory.'/crop.png';
            imagepng($crop, $croppedPath);
            imagedestroy($crop);
            $uploaded = new UploadedFile($croppedPath, $data['kind'].'.png', 'image/png', null, true);
            $asset = $assets->store($uploaded, $data['kind'], $request->user());
            $this->audit($asset, strtoupper($data['kind']).'_UPLOADED', $request, ['source' => 'authorized_pdf_extraction']);

            return back()->with('status', strtoupper($data['kind']).'_UPLOADED: extracción local autorizada guardada.');
        } finally {
            foreach (glob($directory.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);
        }
    }

    public function importCombined(Request $request, InstitutionalAssetService $assets): RedirectResponse
    {
        $source = base_path('docs/SantaAna_Firma_Sello/firma_sello_combinado_transparente.png');
        abort_unless(is_file($source), 404, 'The authorized combined asset source is unavailable.');
        $file = new UploadedFile($source, 'firma_sello_combinado_transparente.png', 'image/png', null, true);
        $asset = $assets->store($file, InstitutionalAssetService::SIGNATURE_STAMP_COMBINED, $request->user());
        $this->audit($asset, 'SIGNATURE_STAMP_COMBINED_IMPORTED', $request, ['source' => 'docs/SantaAna_Firma_Sello']);

        return redirect()->route('admin.settings.signature.index')->with('status', 'Firma y sello importados correctamente.');
    }

    private function audit(InstitutionalAsset $asset, string $action, Request $request, array $metadata = []): void
    {
        DocumentAuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'metadata' => $metadata + ['asset_id' => $asset->id, 'asset_sha256' => $asset->sha256]]);
    }
}
