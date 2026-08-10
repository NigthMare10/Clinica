<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuid, Notifiable;

    public function doctor()
    {
        return $this->hasOne(Doctor::class);
    }

    public function clinics()
    {
        return $this->belongsToMany(Clinic::class)->withPivot(['role', 'is_active'])->withTimestamps();
    }

    public function accessibleClinicIds(): array
    {
        return $this->clinics()->wherePivot('is_active', true)->pluck('clinics.id')->all();
    }

    public function hasClinicAccess(?string $clinicId): bool
    {
        return $clinicId === null
            || $this->hasAnyRole(UserRole::SUPER_ADMIN)
            || in_array($clinicId, $this->accessibleClinicIds(), true);
    }

    public function hasAnyRole(UserRole|string ...$roles): bool
    {
        $values = array_map(fn (UserRole|string $role) => $role instanceof UserRole ? $role->value : $role, $roles);

        return $this->is_active && in_array($this->role->value, $values, true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }
}
