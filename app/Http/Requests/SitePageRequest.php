<?php

namespace App\Http\Requests;

use App\Models\SitePage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SitePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('page') ? 'update' : 'create', $this->route('page') ?: SitePage::class) ?? false;
    }

    public function rules(): array
    {
        return ['slug' => ['required', 'alpha_dash:ascii', 'max:255', Rule::unique('site_pages')->ignore($this->route('page')?->id)],
            'title' => ['required', 'string', 'max:255'], 'content' => ['nullable', 'string', 'max:100000'],
            'meta_title' => ['nullable', 'string', 'max:255'], 'meta_description' => ['nullable', 'string', 'max:1000'],
            'is_published' => ['required', 'boolean']];
    }
}
