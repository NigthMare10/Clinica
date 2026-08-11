<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class InstitutionalAsset extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'activated_at' => 'datetime'];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
