<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIssuedMedicalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('correct', $this->route('document')) ?? false;
    }

    public function rules(): array
    {
        return [
            'current_revision_id' => ['required', 'string'],
            'reason' => ['required', 'in:Error de redacción,Error de datos,Solicitud del paciente,Otro'],
            'source_text' => ['required', 'string', 'max:12000'],
            'fields' => ['required', 'array'],
            'fields.patient_name' => ['required', 'string', 'max:255'],
            'fields.identity' => ['nullable', 'string', 'max:30'],
            'fields.age_at_consultation' => ['nullable', 'integer', 'min:0', 'max:130'],
            'fields.consultation_date' => ['nullable', 'date_format:d/m/Y'],
            'fields.consultation_time' => ['nullable', 'date_format:H:i'],
            'fields.diagnosis' => ['nullable', 'string', 'max:2000'],
            'fields.leave_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'fields.leave_start_date' => ['nullable', 'date_format:d/m/Y'],
            'fields.leave_end_date' => ['nullable', 'date_format:d/m/Y'],
            'fields.recommendations' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
