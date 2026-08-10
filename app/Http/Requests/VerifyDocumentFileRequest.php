<?php

namespace App\Http\Requests;

use App\Services\MedicalDocuments\SecurePdfValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class VerifyDocumentFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['document' => ['required', 'file', 'max:'.config('medical_documents.max_upload_kb'), 'mimetypes:application/pdf']];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $file = $this->file('document');
            if ($file?->isValid() && ! app(SecurePdfValidator::class)->valid($file->getRealPath(), $file->getClientOriginalName(), config('medical_documents.max_upload_kb') * 1024)) {
                $validator->errors()->add('document', 'The file must be a genuine PDF document.');
            }
        }];
    }
}
