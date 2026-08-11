<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\Setting;
use App\Models\SitePage;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ClinicSeeder::class);

        foreach ([
            ['Medicina General', '/images/specialties/medicina-general.webp'],
            ['Odontologia', '/images/photography/dentistry-1280.webp'],
            ['Ginecologia', '/images/specialties/ginecologia.webp'],
            ['Traumatologia', '/images/specialties/traumatologia.webp'],
            ['Pediatria', '/images/specialties/pediatria.webp'],
            ['Medicina Interna', '/images/specialties/medicina-interna.webp'],
            ['Cardiologia', '/images/specialties/cardiologia.webp'],
            ['Dermatologia', '/images/specialties/dermatologia.webp'],
            ['Gastroenterologia', '/images/specialties/gastroenterologia.webp'],
            ['Oftalmologia', '/images/specialties/oftalmologia.webp'],
            ['Otorrinolaringologia', '/images/specialties/otorrinolaringologia.webp'],
            ['Urologia', '/images/photography/internal-medicine-1280.webp'],
            ['Psicologia', '/images/photography/document-review-1280.webp'],
            ['Nutricion', '/images/photography/patient-assistance-1280.webp'],
            ['Fisioterapia', '/images/photography/traumatology-1280.webp'],
        ] as $order => [$name, $imagePath]) {
            Specialty::updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'short_description' => 'Atencion medica especializada con enfoque humano y preventivo.',
                'description' => 'Atencion medica especializada con enfoque humano, seguro y preventivo.',
                'image_path' => $imagePath,
                'common_reasons' => ['Consulta preventiva', 'Evaluacion de sintomas', 'Seguimiento clinico'],
                'services' => ['Consulta especializada', 'Control y seguimiento', 'Orientacion preventiva'],
                'is_active' => true,
                'is_public' => true,
                'sort_order' => $order,
            ]);
        }

        $provider = config('institution.provider');
        Doctor::updateOrCreate(
            ['credential_type' => $provider['credential_type'], 'credential_number' => $provider['credential_number']],
            [
                'first_name' => $provider['first_name'],
                'last_name' => $provider['last_name'],
                'professional_name' => $provider['name'],
                'biography' => 'Profesional emisora institucional de Clinica Medica Santa Ana.',
                'photo_path' => '/images/doctors/portrait-placeholder.webp',
                'is_active' => true,
                'is_public' => false,
            ],
        );

        foreach ([
            'clinica' => ['14 anos cuidando la salud de Honduras', 'Atencion medica accesible, confiable y humana'],
            'contacto' => ['Conversemos sobre tu salud', 'Orientacion y atencion disponible para pacientes y familias.'],
        ] as $slug => [$title, $content]) {
            SitePage::updateOrCreate(['slug' => $slug], [
                'title' => $title,
                'content' => '<p>'.$content.'</p>',
                'meta_title' => $title.' | Clinica Medica Santa Ana',
                'meta_description' => $content,
                'is_published' => true,
            ]);
        }

        foreach ([
            'verification.require_identity_last4' => false,
            'verification.show_patient_name' => true,
            'verification.show_diagnosis' => false,
            'verification.show_full_identity' => false,
            'privacy.public_doctor_credentials' => false,
        ] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'is_public' => false]);
        }

        $email = (string) config('institution.admin.email');
        $password = (string) config('institution.admin.password');
        if ($email === '' || $password === '') {
            $this->command?->warn('ADMIN_EMAIL and ADMIN_PASSWORD are required to create the production administrator.');

            return;
        }

        User::updateOrCreate(['email' => $email], [
            'name' => config('institution.admin.name'),
            'password' => Hash::make($password),
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $superAdminEmail = (string) env('SUPER_ADMIN_EMAIL');
        $superAdminPassword = (string) env('SUPER_ADMIN_PASSWORD');
        if ($superAdminEmail !== '' && $superAdminPassword !== '') {
            User::updateOrCreate(['email' => $superAdminEmail], [
                'name' => 'Cesar',
                'password' => Hash::make($superAdminPassword),
                'role' => UserRole::SUPER_ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }
    }
}
