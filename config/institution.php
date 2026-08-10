<?php

return [
    'name' => 'CLÍNICA MÉDICA SANTA ANA',
    'short_name' => 'Clínica Médica Santa Ana',
    'provider' => [
        'name' => 'Dra. Adriana Abelina Pinot Moncada',
        'first_name' => 'Adriana Abelina',
        'last_name' => 'Pinot Moncada',
        'credential_type' => 'Cédula profesional',
        'credential_number' => '1102149468',
    ],
    'admin' => [
        'name' => env('ADMIN_NAME', 'Super Administrator'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],
    'phone' => '+504 9485-5657',
    'phone_uri' => 'tel:+50494855657',
    'whatsapp_uri' => 'https://wa.me/50494855657',
    'address' => "Entrada Principal colonia Torocagua,\nFrente a supermercado La Colonia,\nComayagüela M.D.C.,\nHonduras C.A.",
    'hours' => 'ABIERTO 24/7',
    'availability' => 'Atención disponible 24/7',
    'emergencies' => 'Emergencias 24 horas, todos los días.',
    'timezone' => 'America/Tegucigalpa',
    'document_prefix' => 'CSA',
];
