<?php

namespace App\Http\Requests;

use App\Models\Doctor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('doctor') ? 'update' : 'create', $this->route('doctor') ?: Doctor::class) ?? false;
    }

    public function rules(): array
    {
        $doctor = $this->route('doctor');

        return ['user_id' => ['nullable', 'uuid', Rule::exists('users', 'id'), Rule::unique('doctors')->ignore($doctor?->id)],
            'first_name' => ['required', 'string', 'max:255'], 'last_name' => ['required', 'string', 'max:255'],
            'professional_name' => ['nullable', 'string', 'max:255'], 'credential_type' => ['nullable', 'string', 'max:50'],
            'credential_number' => ['nullable', 'string', 'max:100', Rule::unique('doctors')->where(fn ($q) => $q->where('credential_type', $this->input('credential_type')))->ignore($doctor?->id)],
            'email' => ['nullable', 'email:rfc', 'max:255'], 'phone' => ['nullable', 'string', 'max:40'],
            'biography' => ['nullable', 'string', 'max:20000'], 'schedules' => ['nullable', 'array', 'max:50'],
            'specialty_ids' => ['nullable', 'array', 'max:30'], 'specialty_ids.*' => ['uuid', 'distinct', 'exists:specialties,id'],
            'primary_specialty_id' => ['nullable', 'uuid', 'exists:specialties,id'], 'is_active' => ['required', 'boolean'],
            'is_public' => ['required', 'boolean'], 'signature' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'seal' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048']];
    }
}
