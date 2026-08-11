<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CorrectMedicalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('correct', $this->route('document')) ?? false;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:2000']];
    }
}
