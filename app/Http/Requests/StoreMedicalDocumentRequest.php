<?php

namespace App\Http\Requests;

use App\Enums\MedicalDocumentType;
use App\Models\MedicalDocument;
use App\Services\MedicalDocuments\SecurePdfValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMedicalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MedicalDocument::class) ?? false;
    }

    public function rules(): array
    {
        return ['type' => ['required', Rule::enum(MedicalDocumentType::class)], 'patient_id' => ['nullable', 'uuid', 'exists:patients,id'],
            'doctor_id' => ['nullable', 'uuid', 'exists:doctors,id'], 'pdf_template_id' => ['nullable', 'uuid', 'exists:pdf_templates,id'],
            'document' => ['required', 'file', 'max:'.config('medical_documents.max_upload_kb')]];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $file = $this->file('document');
            if (! $file || ! $file->isValid()) {
                return;
            }
            if (! app(SecurePdfValidator::class)->valid($file->getRealPath(), $file->getClientOriginalName(), config('medical_documents.max_upload_kb') * 1024)) {
                $validator->errors()->add('document', 'The file must be a genuine PDF document.');
            }
        }];
    }
}
