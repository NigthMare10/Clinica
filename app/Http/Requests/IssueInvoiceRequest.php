<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['fiscal_authorization_id' => ['nullable', 'uuid', 'exists:fiscal_authorizations,id']];
    }
}
