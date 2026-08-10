<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\SitePage;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $specialties = [
            ['Medicina General', 'Atencion integral para diagnostico, prevencion y seguimiento de la salud familiar.', '/images/photography/female-doctor-consultation-1280.webp'],
            ['Odontologia', 'Cuidado preventivo, diagnostico y tratamiento para una sonrisa saludable.', '/images/photography/dentistry-1280.webp'],
            ['Ginecologia', 'Atencion respetuosa y especializada para la salud integral de la mujer.', '/images/photography/gynecology-1280.webp'],
            ['Traumatologia', 'Evaluacion y recuperacion de lesiones del sistema musculoesqueletico.', '/images/photography/traumatology-1280.webp'],
            ['Pediatria', 'Acompanamiento cercano al crecimiento y bienestar de ninos y adolescentes.', '/images/photography/pediatrics-1280.webp'],
            ['Medicina Interna', 'Abordaje integral de condiciones medicas complejas en personas adultas.', '/images/photography/internal-medicine-1280.webp'],
            ['Cardiologia', 'Prevencion, diagnostico y control especializado de la salud cardiovascular.', '/images/photography/internal-medicine-1280.webp'],
            ['Dermatologia', 'Diagnostico y tratamiento de condiciones de piel, cabello y unas.', '/images/photography/female-doctor-consultation-1280.webp'],
            ['Gastroenterologia', 'Cuidado especializado del sistema digestivo con enfoque preventivo.', '/images/photography/internal-medicine-1280.webp'],
            ['Oftalmologia', 'Evaluacion visual y atencion especializada para proteger su vision.', '/images/photography/female-doctor-consultation-1280.webp'],
            ['Otorrinolaringologia', 'Atencion de oido, nariz y garganta para pacientes de todas las edades.', '/images/photography/female-doctor-consultation-1280.webp'],
            ['Urologia', 'Diagnostico y seguimiento confidencial de la salud del sistema urinario.', '/images/photography/female-doctor-consultation-1280.webp'],
            ['Psicologia', 'Acompanamiento profesional para el bienestar emocional y la salud mental.', '/images/photography/document-review-1280.webp'],
            ['Nutricion', 'Planes personalizados para construir habitos sostenibles y saludables.', '/images/photography/document-review-1280.webp'],
            ['Fisioterapia', 'Rehabilitacion funcional para recuperar movimiento, fuerza y autonomia.', '/images/photography/traumatology-1280.webp'],
        ];

        foreach ($specialties as $order => [$name, $description, $image]) {
            Specialty::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'short_description' => $description, 'description' => $description, 'image_path' => $image,
                    'common_reasons' => ['Consulta preventiva', 'Evaluacion de sintomas', 'Seguimiento clinico'],
                    'services' => ['Consulta especializada', 'Control y seguimiento', 'Orientacion preventiva'],
                    'is_active' => true, 'is_public' => true, 'sort_order' => $order],
            );
        }

        Doctor::query()->update(['is_public' => false]);
        $provider = config('institution.provider');
        $doctor = Doctor::updateOrCreate(
            ['credential_type' => $provider['credential_type'], 'credential_number' => $provider['credential_number']],
            ['first_name' => $provider['first_name'], 'last_name' => $provider['last_name'], 'professional_name' => $provider['name'],
                'biography' => 'Profesional emisora institucional de Clínica Médica Santa Ana.',
                'photo_path' => '/images/doctors/portrait-placeholder.webp', 'is_active' => true, 'is_public' => false],
        );
        $specialty = Specialty::where('name', 'Medicina General')->firstOrFail();
        $doctor->specialties()->sync([$specialty->id => ['is_primary' => true]]);

        $adminEmail = config('institution.admin.email');
        $adminPassword = config('institution.admin.password');
        if ($adminEmail && $adminPassword) {
            User::updateOrCreate(
                ['email' => $adminEmail],
                ['name' => config('institution.admin.name'), 'password' => Hash::make($adminPassword),
                    'role' => UserRole::SUPER_ADMIN, 'is_active' => true, 'email_verified_at' => now()],
            );
        }

        foreach ([
            ['DNI', '0801-1990-10001', 'Ana', 'Martinez', '1990-04-12', 36, 'Femenino', 'ana.demo@example.com', '9999-1001'],
            ['DNI', '0501-1985-10002', 'Luis', 'Gonzalez', '1985-09-21', 40, 'Masculino', 'luis.demo@example.com', '9999-1002'],
            ['DNI', '0101-2016-10003', 'Camila', 'Rivera', '2016-02-08', 10, 'Femenino', null, '9999-1003'],
            ['DNI', '0601-1978-10004', 'Marta', 'Flores', '1978-11-30', 47, 'Femenino', 'marta.demo@example.com', '9999-1004'],
            ['DNI', '1401-2001-10005', 'Daniel', 'Reyes', '2001-06-15', 25, 'Masculino', 'daniel.demo@example.com', '9999-1005'],
        ] as [$type, $number, $firstName, $lastName, $birthDate, $age, $sex, $email, $phone]) {
            Patient::updateOrCreate(['document_type' => $type, 'document_number' => $number], [
                'first_name' => $firstName, 'last_name' => $lastName, 'birth_date' => $birthDate, 'age' => $age,
                'sex' => $sex, 'email' => $email, 'phone' => $phone,
            ]);
        }

        SitePage::updateOrCreate(['slug' => 'clinica'], [
            'title' => '14 años cuidando la salud de Honduras',
            'content' => '<h2>Atención médica accesible, confiable y humana</h2><p>Clínica Médica Santa Ana es una red de atención médica con 14 años de experiencia, enfocada en brindar servicios de salud accesibles, confiables y humanos para pacientes en todo el país.</p><p>A lo largo de su crecimiento, ha consolidado una red de 18 clínicas con presencia estratégica en los 18 departamentos de Honduras.</p>',
            'meta_title' => 'Nuestra clinica | Clinica Medica Santa Ana',
            'meta_description' => 'Conozca nuestra historia, valores y cobertura nacional.',
            'is_published' => true,
        ]);

        SitePage::updateOrCreate(['slug' => 'contacto'], [
            'title' => 'Conversemos sobre tu salud',
            'content' => '<h3>Orientación y atención</h3><p>Nuestro equipo puede orientarte sobre especialidades y la cobertura referencial más cercana.</p><p><strong>Atención 24/7:</strong> emergencias 24 horas, todos los días.</p>',
            'meta_title' => 'Contacto | Clínica Médica Santa Ana',
            'meta_description' => 'Canales de orientación de Clínica Médica Santa Ana.',
            'is_published' => true,
        ]);
    }
}
