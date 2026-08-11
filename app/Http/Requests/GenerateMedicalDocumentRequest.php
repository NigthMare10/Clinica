<?php

namespace App\Http\Requests;

use App\Models\MedicalDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GenerateMedicalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MedicalDocument::class) ?? false;
    }

    public function rules(): array
    {
        $incapacity = $this->route('kind') === 'incapacidad';

        return [
            'patient_id' => ['nullable', 'uuid', 'exists:patients,id'],
            'doctor_id' => ['nullable', 'uuid', 'exists:doctors,id'],
            'patient_name' => ['nullable', 'string', 'max:200'],
            'identity' => ['nullable', 'string', 'max:30'],
            'age_at_consultation' => ['nullable', 'integer', 'min:0', 'max:125'],
            'create_patient' => ['nullable', 'boolean'],
            'clinic_id' => ['required', 'uuid', 'exists:clinics,id'],
            'pdf_template_id' => ['nullable', 'uuid', 'exists:pdf_templates,id'],
            'consultation_date' => ['required', 'date'],
            'consultation_time' => ['nullable', 'date_format:H:i'],
            'symptoms' => ['nullable', 'string', 'max:1500'],
            'medical_reason' => ['nullable', 'string', 'max:6000', Rule::requiredIf(! $this->filled('free_text'))],
            'diagnosis' => ['nullable', 'string', 'max:1500'],
            'recommendations' => ['nullable', 'string', 'max:1500'],
            'free_text' => ['nullable', 'string', 'max:12000'],
            'intent' => ['nullable', Rule::in(['draft', 'issue'])],
            'quick_invoice' => ['nullable', 'boolean', Rule::prohibitedIf($this->input('intent') !== 'issue')],
            'leave_start_date' => [Rule::requiredIf($incapacity), 'nullable', 'date'],
            'leave_end_date' => [Rule::requiredIf($incapacity), 'nullable', 'date', 'after_or_equal:leave_start_date'],
            'leave_days' => [Rule::requiredIf($incapacity), 'nullable', 'integer', 'min:1', 'max:365'],
            'confirm' => ['accepted'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->filled('patient_id') && (! $this->filled('patient_name') || ! $this->filled('identity'))) {
                $validator->errors()->add('patient_id', 'Seleccione un paciente o confirme el nuevo paciente detectado.');
            }
            if ($this->route('kind') !== 'incapacidad' || ! $this->date('leave_start_date') || ! $this->date('leave_end_date')) {
                return;
            }
            $expected = (int) $this->date('leave_start_date')->diffInDays($this->date('leave_end_date')) + 1;
            if ($expected !== $this->integer('leave_days')) {
                $validator->errors()->add('leave_days', "El rango seleccionado corresponde a {$expected} dias.");
            }
        }];
    }
}
