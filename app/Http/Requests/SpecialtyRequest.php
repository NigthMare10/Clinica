<?php

namespace App\Http\Requests;

use App\Models\Specialty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SpecialtyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('specialty') ? 'update' : 'create', $this->route('specialty') ?: Specialty::class) ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('specialty')?->id;

        return ['name' => ['required', 'string', 'max:255', Rule::unique('specialties')->ignore($id)],
            'slug' => ['required', 'alpha_dash:ascii', 'max:255', Rule::unique('specialties')->ignore($id)],
            'short_description' => ['nullable', 'string', 'max:1000'], 'description' => ['nullable', 'string', 'max:20000'],
            'common_reasons' => ['nullable', 'array', 'max:50'], 'common_reasons.*' => ['string', 'max:255'],
            'services' => ['nullable', 'array', 'max:50'], 'services.*' => ['string', 'max:255'], 'icon' => ['nullable', 'string', 'max:100'],
            'seo_title' => ['nullable', 'string', 'max:255'], 'seo_description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'], 'is_public' => ['required', 'boolean'], 'sort_order' => ['required', 'integer', 'min:0', 'max:65535']];
    }
}
