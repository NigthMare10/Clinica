<?php

namespace App\Http\Requests;

use App\Enums\TaxCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertBillingProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole('SUPER_ADMIN', 'ADMINISTRATOR') ?? false;
    }

    public function rules(): array
    {
        return [
            'clinic_id' => ['required', 'uuid', 'exists:clinics,id'],
            'kind' => ['required', Rule::in(['CONSTANCIA', 'INCAPACIDAD'])],
            'service_code' => ['required', 'string', 'max:60'],
            'service_name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'tax_category' => ['required', Rule::enum(TaxCategory::class)],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999.999'],
            'default_payment_method' => ['required', Rule::in(['EFECTIVO', 'TARJETA', 'TRANSFERENCIA', 'MIXTO', 'OTRO'])],
        ];
    }
}
