<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Specialty extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = ['name', 'slug', 'short_description', 'description', 'common_reasons', 'services', 'image_path',
        'icon', 'seo_title', 'seo_description', 'is_active', 'is_public', 'sort_order'];

    protected function casts(): array
    {
        return ['common_reasons' => 'array', 'services' => 'array', 'is_active' => 'boolean', 'is_public' => 'boolean'];
    }

    public function doctors()
    {
        return $this->belongsToMany(Doctor::class)->withPivot('is_primary');
    }
}
