<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class SitePage extends Model
{
    use HasUuid;

    protected $fillable = ['slug', 'title', 'content', 'meta_title', 'meta_description', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
