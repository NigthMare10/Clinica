<?php

namespace App\Support;

use App\Models\Doctor;

class InstitutionalMedicalProvider
{
    public function details(): array
    {
        return config('institution.provider');
    }

    public function doctor(): Doctor
    {
        $provider = $this->details();

        return Doctor::query()->updateOrCreate(
            ['credential_type' => $provider['credential_type'], 'credential_number' => $provider['credential_number']],
            [
                'first_name' => $provider['first_name'],
                'last_name' => $provider['last_name'],
                'professional_name' => $provider['name'],
                'is_active' => true,
                'is_public' => false,
            ],
        );
    }
}
