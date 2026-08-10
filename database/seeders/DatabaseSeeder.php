<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([ClinicSeeder::class, DemoContentSeeder::class]);

        foreach (['verification.require_identity_last4' => false, 'verification.show_patient_name' => true,
            'verification.show_diagnosis' => false, 'verification.show_full_identity' => false,
            'privacy.public_doctor_credentials' => false] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'is_public' => false]);
        }

    }
}
