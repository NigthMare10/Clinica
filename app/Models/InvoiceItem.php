<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\TaxCategory;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = ['position', 'service_code', 'medical_document_id', 'description', 'quantity', 'unit_price', 'discount', 'tax_category', 'tax_rate', 'net_amount', 'tax_amount', 'total_amount'];

    protected function casts(): array
    {
        return ['tax_category' => TaxCategory::class];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $item) => $item->assertInvoiceIsDraft());
        static::updating(fn (self $item) => $item->assertInvoiceIsDraft());
        static::deleting(fn (self $item) => $item->assertInvoiceIsDraft());
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    private function assertInvoiceIsDraft(): void
    {
        $status = $this->invoice()->value('status');
        if (($status instanceof InvoiceStatus ? $status->value : $status) !== InvoiceStatus::DRAFT->value) {
            throw new \DomainException('Invoice items are immutable after issue.');
        }
    }
}
