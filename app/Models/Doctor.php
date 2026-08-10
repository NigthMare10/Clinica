<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = ['user_id', 'first_name', 'last_name', 'professional_name', 'credential_type', 'credential_number',
        'email', 'phone', 'biography', 'schedules', 'photo_path', 'is_active', 'is_public'];

    protected $hidden = ['signature_path', 'seal_path'];

    protected function casts(): array
    {
        return ['schedules' => 'array', 'is_active' => 'boolean', 'is_public' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function specialties()
    {
        return $this->belongsToMany(Specialty::class)->withPivot('is_primary');
    }

    public function documents()
    {
        return $this->hasMany(MedicalDocument::class);
    }

    public function clinics()
    {
        return $this->belongsToMany(Clinic::class)->withPivot('is_active');
    }
}
