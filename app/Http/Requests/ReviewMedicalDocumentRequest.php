<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewMedicalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('document')) ?? false;
    }

    public function rules(): array
    {
        return ['fields' => ['required', 'array:patient_name,patient_document,birth_date,age,age_at_consultation,sex,issue_date,consultation_date,consultation_time,start_date,end_date,leave_start_date,leave_end_date,days,leave_days,symptoms,medical_reason,diagnosis,treatment,recommendations,observations,doctor_name,doctor_credential,clinic_name,clinic_address'],
            'fields.*' => ['nullable', 'string', 'max:2000'], 'approve' => ['sometimes', 'boolean'],
            'doctor_id' => ['nullable', 'uuid', 'exists:doctors,id'], 'patient_id' => ['nullable', 'uuid', 'exists:patients,id']];
    }
}
