<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = ['document_type', 'document_number', 'first_name', 'last_name', 'birth_date', 'age', 'sex', 'email', 'phone', 'address'];

    protected $hidden = ['document_number', 'email', 'phone', 'address'];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function documents()
    {
        return $this->hasMany(MedicalDocument::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function clinics()
    {
        return $this->belongsToMany(Clinic::class, 'patient_clinic')->withPivot(['medical_record_number', 'first_seen_at', 'last_seen_at'])->withTimestamps();
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(UserRole::SUPER_ADMIN)) {
            return $query;
        }

        $clinicIds = $user->accessibleClinicIds();

        return $query->where(fn (Builder $builder) => $builder->whereDoesntHave('clinics')
            ->orWhereHas('clinics', fn (Builder $clinics) => $clinics->whereIn('clinics.id', $clinicIds)));
    }
}
