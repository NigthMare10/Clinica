<?php

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingRequest extends FormRequest
{
    public const KEYS = ['verification.require_identity_last4', 'verification.show_patient_name',
        'verification.show_diagnosis', 'privacy.public_doctor_credentials'];

    public function authorize(): bool
    {
        return $this->user()?->can($this->route('setting') ? 'update' : 'create', $this->route('setting') ?: Setting::class) ?? false;
    }

    public function rules(): array
    {
        return ['key' => ['required', Rule::in(self::KEYS), Rule::unique('settings')->ignore($this->route('setting')?->id)],
            'value' => ['required', 'boolean'], 'is_public' => ['required', 'boolean']];
    }
}
