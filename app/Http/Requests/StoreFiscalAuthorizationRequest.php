<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFiscalAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole('SUPER_ADMIN', 'ADMINISTRATOR') ?? false;
    }

    public function rules(): array
    {
        return ['clinic_id' => ['required', 'uuid', 'exists:clinics,id'], 'cai' => ['required', 'string', 'max:100'], 'rtn' => ['required', 'string', 'max:30'], 'establishment' => ['required', 'string', 'max:20'], 'point_of_issue' => ['required', 'string', 'max:20'], 'document_type' => ['required', 'string', 'max:30'], 'ncf_prefix' => ['required', 'string', 'max:30'], 'range_start' => ['required', 'integer', 'min:1'], 'range_end' => ['required', 'integer', 'min:1'], 'number_padding' => ['nullable', 'integer', 'between:1,20'], 'valid_from' => ['required', 'date'], 'valid_until' => ['required', 'date', 'after_or_equal:valid_from'], 'is_active' => ['sometimes', 'boolean']];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ((int) $this->input('range_end') < (int) $this->input('range_start')) {
                $validator->errors()->add('range_end', 'The NCF range end must not precede the start.');
            }
        }];
    }
}
