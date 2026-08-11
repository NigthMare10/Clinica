<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clinic extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'code', 'slug', 'name', 'department', 'latitude', 'longitude', 'address', 'phone', 'hours',
        'status', 'is_public', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'hours' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot(['role', 'is_active'])->withTimestamps();
    }

    public function doctors()
    {
        return $this->belongsToMany(Doctor::class)->withPivot('is_active');
    }

    public function patients()
    {
        return $this->belongsToMany(Patient::class, 'patient_clinic')->withPivot(['medical_record_number', 'first_seen_at', 'last_seen_at'])->withTimestamps();
    }

    public function documents()
    {
        return $this->hasMany(MedicalDocument::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function fiscalAuthorizations()
    {
        return $this->hasMany(FiscalAuthorization::class);
    }
}
