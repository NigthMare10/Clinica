<?php

namespace App\Services\MedicalDocuments;

use App\Models\InstitutionalAsset;
use App\Models\MedicalDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use setasign\Fpdi\Fpdi;

class InstitutionalSignatureStampService
{
    /** @return array<string, string> */
    public function apply(MedicalDocument $document, string $input, string $output): array
    {
        if ($document->source_kind !== 'GENERATED') {
            copy($input, $output);

            return [];
        }
        $assets = InstitutionalAsset::query()->whereIn('kind', ['signature', 'stamp', InstitutionalAssetService::SIGNATURE_STAMP_COMBINED])->where('is_active', true)->get()->keyBy('kind');
        if ($assets->isEmpty()) {
            copy($input, $output);

            return [];
        }
        $pdf = new Fpdi;
        $pages = $pdf->setSourceFile($input);
        if ($pages !== 1) {
            throw new RuntimeException('A generated medical document must remain one page.');
        }
        $template = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($template);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($template);
        $coordinates = $document->template?->coordinates ?? [];
        $defaults = config('medical_documents.institutional_marks');
        // A source document may contain an inseparable signature and seal. Never layer recreated marks over it.
        $kinds = $this->kindsToApply($assets);
        foreach ($kinds as $kind) {
            $asset = $assets->get($kind);
            if (! $asset) {
                continue;
            }
            $position = $coordinates[$kind] ?? $defaults[$kind];
            foreach (['x', 'y', 'width'] as $field) {
                if (! isset($position[$field]) || ! is_numeric($position[$field])) {
                    throw new RuntimeException('Invalid institutional mark coordinates.');
                }
            }
            if ($position['x'] < 0 || $position['y'] < 0 || $position['width'] <= 0 || $size['width'] < $position['x'] + $position['width'] || $position['y'] > $size['height']) {
                throw new RuntimeException('Institutional mark does not fit in the PDF page.');
            }
            $pdf->Image(Storage::disk(config('medical_documents.disk'))->path($asset->getRawOriginal('path')), (float) $position['x'], (float) $position['y'], (float) $position['width']);
        }
        $pdf->Output('F', $output);

        return collect($kinds)
            ->mapWithKeys(function (string $kind) use ($assets): array {
                $asset = $assets->get($kind);

                return $asset ? [$kind => $asset->sha256] : [];
            })
            ->all();
    }

    /** @param Collection<string, InstitutionalAsset> $assets */
    public function kindsToApply(Collection $assets): array
    {
        return $assets->has(InstitutionalAssetService::SIGNATURE_STAMP_COMBINED)
            ? [InstitutionalAssetService::SIGNATURE_STAMP_COMBINED]
            : ['signature', 'stamp'];
    }
}
