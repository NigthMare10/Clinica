<?php

namespace Database\Seeders;

use App\Models\Clinic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ClinicSeeder extends Seeder
{
    public function run(): void
    {
        $clinics = [
            ['Atlantida', 'La Ceiba', 15.7835, -86.7918],
            ['Colon', 'Trujillo', 15.9167, -85.9542],
            ['Comayagua', 'Comayagua', 14.4519, -87.6375],
            ['Copan', 'Santa Rosa de Copan', 14.7667, -88.7792],
            ['Cortes', 'San Pedro Sula', 15.5007, -88.0334],
            ['Choluteca', 'Choluteca', 13.3003, -87.1908],
            ['El Paraiso', 'Yuscaran', 13.9439, -86.8527],
            ['Francisco Morazan', 'Tegucigalpa', 14.0723, -87.1921],
            ['Gracias a Dios', 'Puerto Lempira', 15.2667, -83.7722],
            ['Intibuca', 'La Esperanza', 14.3111, -88.1806],
            ['Islas de la Bahia', 'Roatan', 16.3167, -86.5167],
            ['La Paz', 'La Paz', 14.3194, -87.6792],
            ['Lempira', 'Gracias', 14.5903, -88.5819],
            ['Ocotepeque', 'Nueva Ocotepeque', 14.4333, -89.1833],
            ['Olancho', 'Juticalpa', 14.6667, -86.2194],
            ['Santa Barbara', 'Santa Barbara', 14.9194, -88.2361],
            ['Valle', 'Nacaome', 13.5361, -87.4875],
            ['Yoro', 'Yoro', 15.1333, -87.1333],
        ];

        foreach ($clinics as $order => [$department, $city, $latitude, $longitude]) {
            Clinic::updateOrCreate(
                ['code' => 'HN-'.str_pad((string) ($order + 1), 2, '0', STR_PAD_LEFT)],
                [
                    'slug' => Str::slug($department),
                    'name' => 'Santa Ana '.$city,
                    'department' => $department,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'address' => $department === 'Francisco Morazan' ? config('institution.address') : 'Ubicacion referencial de cobertura',
                    'phone' => config('institution.phone'),
                    'hours' => ['Atencion' => '24/7', 'Emergencias' => '24 horas, todos los dias'],
                    'status' => 'ACTIVE',
                    'is_public' => true,
                    'sort_order' => $order,
                ],
            );
        }
    }
}
