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
            ['Atlántida', 'La Ceiba', 15.7835, -86.7918],
            ['Colón', 'Trujillo', 15.9167, -85.9542],
            ['Comayagua', 'Comayagua', 14.4519, -87.6375],
            ['Copán', 'Santa Rosa de Copán', 14.7667, -88.7792],
            ['Cortés', 'San Pedro Sula', 15.5007, -88.0334],
            ['Choluteca', 'Choluteca', 13.3003, -87.1908],
            ['El Paraíso', 'Yuscarán', 13.9439, -86.8527],
            ['Francisco Morazán', 'Tegucigalpa', 14.0723, -87.1921],
            ['Gracias a Dios', 'Puerto Lempira', 15.2667, -83.7722],
            ['Intibucá', 'La Esperanza', 14.3111, -88.1806],
            ['Islas de la Bahía', 'Roatán', 16.3167, -86.5167],
            ['La Paz', 'La Paz', 14.3194, -87.6792],
            ['Lempira', 'Gracias', 14.5903, -88.5819],
            ['Ocotepeque', 'Nueva Ocotepeque', 14.4333, -89.1833],
            ['Olancho', 'Juticalpa', 14.6667, -86.2194],
            ['Santa Bárbara', 'Santa Bárbara', 14.9194, -88.2361],
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
                    'address' => match ($department) {
                        'Francisco Morazán' => config('institution.address'),
                        'Cortés' => 'Plaza Geo Sur, 13 Calle S.O. Barrio Paz Barahona. San Pedro Sula, Honduras',
                        default => 'Cobertura departamental en '.$department.', Honduras',
                    },
                    'phone' => config('institution.phone'),
                    'hours' => ['Atención' => '24/7', 'Emergencias' => '24 horas, todos los días'],
                    'status' => 'ACTIVE',
                    'is_public' => true,
                    'sort_order' => $order,
                ],
            );
        }
    }
}
