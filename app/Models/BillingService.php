<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class BillingService extends Model
{
    use HasUuid;

    protected $fillable = ['code', 'name', 'description', 'default_price', 'tax_type', 'is_active'];

    protected function casts(): array
    {
        return ['default_price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function profiles()
    {
        return $this->hasMany(BillingProfile::class);
    }
}
