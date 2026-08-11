<?php

namespace App\Models;

use App\Enums\FiscalAuthorizationStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FiscalAuthorization extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = ['clinic_id', 'created_by', 'cai', 'rtn', 'establishment', 'point_of_issue', 'document_type', 'ncf_type', 'ncf_prefix', 'range_start', 'range_end', 'full_range_start', 'full_range_end', 'source', 'next_number', 'number_padding', 'valid_from', 'valid_until', 'status', 'is_active', 'activated_at'];

    protected function casts(): array
    {
        return ['status' => FiscalAuthorizationStatus::class, 'range_start' => 'integer', 'range_end' => 'integer', 'next_number' => 'integer', 'number_padding' => 'integer', 'valid_from' => 'date', 'valid_until' => 'date', 'is_active' => 'boolean', 'activated_at' => 'datetime', 'exhausted_at' => 'datetime'];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function formatNcf(int $sequence): string
    {
        return $this->ncf_prefix.str_pad((string) $sequence, $this->number_padding, '0', STR_PAD_LEFT);
    }

    public function rangeStartNcf(): string
    {
        return $this->full_range_start ?: $this->formatNcf($this->range_start);
    }

    public function rangeEndNcf(): string
    {
        return $this->full_range_end ?: $this->formatNcf($this->range_end);
    }
}
