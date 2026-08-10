<?php

namespace App\Http\Requests;

use App\Enums\MedicalDocumentType;
use App\Models\PdfTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PdfTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('template') ? 'update' : 'create', $this->route('template') ?: PdfTemplate::class) ?? false;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'document_type' => ['nullable', Rule::enum(MedicalDocumentType::class)],
            'page_size' => ['nullable', Rule::in(['A4', 'LETTER', 'LEGAL'])], 'qr_page' => ['required', 'integer', 'min:1', 'max:999'],
            'qr_x' => ['required', 'numeric', 'min:0', 'max:1000'], 'qr_y' => ['required', 'numeric', 'min:0', 'max:1000'],
            'qr_width' => ['required', 'numeric', 'gt:0', 'max:500'], 'qr_height' => ['required', 'numeric', 'gt:0', 'max:500'],
            'coordinates' => ['nullable', 'array', 'max:100'], 'coordinates.*' => ['array'], 'is_active' => ['required', 'boolean']];
    }
}
