<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = ['clinic_id', 'fiscal_authorization_id', 'patient_id', 'medical_document_id', 'replacement_for_invoice_id', 'service_date', 'service_time', 'medical_document_code', 'medical_document_type', 'service_professional', 'recipient_name', 'recipient_tax_id', 'payment_method', 'paid_total', 'balance', 'order_number', 'invoice_control_number', 'created_by'];

    protected $attributes = ['status' => 'DRAFT'];

    protected $hidden = ['qr_token_hash', 'recipient_tax_id', 'issued_path'];

    protected function casts(): array
    {
        return ['status' => InvoiceStatus::class, 'service_date' => 'date', 'service_time' => 'datetime:H:i', 'issued_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $invoice): void {
            $original = InvoiceStatus::tryFrom((string) $invoice->getRawOriginal('status'));
            if ($original === InvoiceStatus::DRAFT && $invoice->isDirty('status') && $invoice->status !== InvoiceStatus::ISSUED) {
                throw new \DomainException('A draft invoice can only transition to issued.');
            }
            if (! in_array($original, [InvoiceStatus::ISSUED, InvoiceStatus::VOID], true)) {
                return;
            }

            $lifecycle = ['status', 'voided_at', 'voided_by', 'void_reason', 'updated_at'];
            if (array_diff(array_keys($invoice->getDirty()), $lifecycle) !== []) {
                throw new \DomainException('Issued and void invoices are immutable.');
            }
            if ($original === InvoiceStatus::VOID && $invoice->isDirty('status')) {
                throw new \DomainException('A void invoice cannot transition.');
            }
            if ($original === InvoiceStatus::ISSUED && $invoice->isDirty('status') && $invoice->status !== InvoiceStatus::VOID) {
                throw new \DomainException('An issued invoice can only be voided.');
            }
        });

        static::deleting(function (self $invoice): void {
            if (in_array($invoice->status, [InvoiceStatus::ISSUED, InvoiceStatus::VOID], true)) {
                throw new \DomainException('Issued and void invoices cannot be deleted.');
            }
        });
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $user->hasAnyRole(UserRole::SUPER_ADMIN) ? $query : $query->whereIn('clinic_id', $user->accessibleClinicIds());
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function authorization()
    {
        return $this->belongsTo(FiscalAuthorization::class, 'fiscal_authorization_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function medicalDocument()
    {
        return $this->belongsTo(MedicalDocument::class);
    }

    public function replacementForInvoice()
    {
        return $this->belongsTo(self::class, 'replacement_for_invoice_id');
    }

    public function replacements()
    {
        return $this->hasMany(self::class, 'replacement_for_invoice_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    public function audits()
    {
        return $this->hasMany(InvoiceAudit::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
