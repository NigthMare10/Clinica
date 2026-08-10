<?php

namespace App\Http\Requests;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('patient') ? 'update' : 'create', $this->route('patient') ?: Patient::class) ?? false;
    }

    public function rules(): array
    {
        return ['document_type' => ['nullable', 'string', 'max:30'], 'document_number' => ['nullable', 'string', 'max:100',
            Rule::unique('patients')->where(fn ($q) => $q->where('document_type', $this->input('document_type')))->ignore($this->route('patient')?->id)],
            'first_name' => ['required', 'string', 'max:255'], 'last_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'sex' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:1000']];
    }
}
