<?php

namespace App\Models;

use App\Enums\TaxCategory;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class BillingProfile extends Model
{
    use HasUuid;

    protected $fillable = [
        'clinic_id', 'certificate_kind', 'billing_service_id', 'default_quantity', 'price_override',
        'tax_category', 'default_payment_method', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_quantity' => 'decimal:3',
            'price_override' => 'decimal:2',
            'tax_category' => TaxCategory::class,
            'is_active' => 'boolean',
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function service()
    {
        return $this->belongsTo(BillingService::class, 'billing_service_id');
    }

    public function unitPrice(): string
    {
        return $this->price_override ?? $this->service->default_price;
    }
}
