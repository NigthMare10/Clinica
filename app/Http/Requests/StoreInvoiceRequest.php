<?php

namespace App\Http\Requests;

use App\Enums\TaxCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole('SUPER_ADMIN', 'ADMINISTRATOR', 'DOCUMENT_OPERATOR') ?? false;
    }

    public function rules(): array
    {
        return ['clinic_id' => ['required', 'uuid', 'exists:clinics,id'], 'patient_id' => ['nullable', 'uuid', 'exists:patients,id'], 'medical_document_id' => ['nullable', 'uuid', 'exists:medical_documents,id'], 'recipient_name' => ['nullable', 'string', 'max:255'], 'recipient_tax_id' => ['nullable', 'string', 'max:100'], 'service_date' => ['nullable', 'date'], 'service_time' => ['nullable', 'date_format:H:i'], 'medical_document_code' => ['nullable', 'string', 'max:100'], 'medical_document_type' => ['nullable', 'string', 'max:60'], 'service_professional' => ['nullable', 'string', 'max:255'], 'payment_method' => ['required', Rule::in(['EFECTIVO', 'TARJETA', 'TRANSFERENCIA', 'MIXTO', 'OTRO'])], 'paid_total' => ['nullable', 'numeric', 'min:0'], 'items' => ['required', 'array', 'min:1', 'max:'.config('invoice_pdf.max_items')], 'items.*.service_code' => ['nullable', 'string', 'max:60'], 'items.*.description' => ['required', 'string', 'max:255'], 'items.*.quantity' => ['required', 'numeric', 'gt:0'], 'items.*.unit_price' => ['required', 'numeric', 'min:0'], 'items.*.discount' => ['nullable', 'numeric', 'min:0'], 'items.*.tax_category' => ['required', Rule::enum(TaxCategory::class)]];
    }
}
