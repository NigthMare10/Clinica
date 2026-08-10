<?php

declare(strict_types=1);

$images = [
    'female-doctor-consultation' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0c/Doctor_talking_with_a_patient.jpg/1280px-Doctor_talking_with_a_patient.jpg',
    'home-consultation' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/44/CP25_Leukemia_Patient_Consultation_%289123625%29.jpg/1280px-CP25_Leukemia_Patient_Consultation_%289123625%29.jpg',
    'dentistry' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6c/LAMAT_25_Patient_Care_Kicks_Off_On_Saint_Kitts_and_Nevis_%288936543%29.jpg/1280px-LAMAT_25_Patient_Care_Kicks_Off_On_Saint_Kitts_and_Nevis_%288936543%29.jpg',
    'gynecology' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0b/Ultrasound_examination_of_woman.JPG/1280px-Ultrasound_examination_of_woman.JPG',
    'traumatology' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ae/Ortho_clinic_keeps_Nellis_warfighters_fighting_150116-F-AT963-020.jpg/1280px-Ortho_clinic_keeps_Nellis_warfighters_fighting_150116-F-AT963-020.jpg',
    'pediatrics' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7a/A_Continuing_Promise_2015_physician_examines_a_child.jpg/1280px-A_Continuing_Promise_2015_physician_examines_a_child.jpg',
    'internal-medicine' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7b/Doctor_examines_patient_with_stethoscope_%281%29.jpg/1280px-Doctor_examines_patient_with_stethoscope_%281%29.jpg',
    'clinic-corridor' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e9/20220507Changhua_Railway_Hospital_Interiors-06.jpg/1280px-20220507Changhua_Railway_Hospital_Interiors-06.jpg',
    'consultation-room' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/64/University_Medical_Center%2C_New_Orleans%2C_4_April_2025_-_Exam_room_1.jpg/1280px-University_Medical_Center%2C_New_Orleans%2C_4_April_2025_-_Exam_room_1.jpg',
    'patient-assistance' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/42/USAF_medical_personnel_assist_in_treatment_at_Academic_Hospital_Suriname_%289186827%29.jpg/1280px-USAF_medical_personnel_assist_in_treatment_at_Academic_Hospital_Suriname_%289186827%29.jpg',
    'clinic-exterior' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b0/Banner_University_Medical_Center%2C_McDowell_Road%2C_Coronado%2C_Phoenix%2C_AZ.jpg/1280px-Banner_University_Medical_Center%2C_McDowell_Road%2C_Coronado%2C_Phoenix%2C_AZ.jpg',
    'clinic-reception' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/e7/Ochsner_hospital_atrium_Jan_2018.jpg/1280px-Ochsner_hospital_atrium_Jan_2018.jpg',
    'document-review' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/fd/WIC%2C_Seattle%2C_Washington_%2820241113-USDA-FNS-UNK-0011%29.jpg/1280px-WIC%2C_Seattle%2C_Washington_%2820241113-USDA-FNS-UNK-0011%29.jpg',
    'front-desk-assistance' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/19/WIC%2C_Seattle%2C_Washington_%2820241113-USDA-FNS-UNK-0130%29.jpg/1280px-WIC%2C_Seattle%2C_Washington_%2820241113-USDA-FNS-UNK-0130%29.jpg',
];

$outputDirectory = dirname(__DIR__).'/public/images/photography';
if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0755, true) && ! is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create the photography directory.');
}

foreach ($images as $name => $url) {
    if (is_file("$outputDirectory/$name-640.webp") && is_file("$outputDirectory/$name-1280.webp")) {
        echo "$name (existing)\n";

        continue;
    }
    $temporary = tempnam(sys_get_temp_dir(), 'csa-photo-');
    $command = sprintf(
        'curl.exe -L --retry 3 --retry-delay 3 --fail --silent --show-error -A %s -o %s %s',
        escapeshellarg('SantaAnaClinicAssetBuilder/1.0 (local development)'),
        escapeshellarg((string) $temporary),
        '"'.$url.'"',
    );
    passthru($command, $status);
    $bytes = $status === 0 && is_file((string) $temporary) ? file_get_contents((string) $temporary) : false;
    @unlink((string) $temporary);
    if (! is_string($bytes)) {
        throw new RuntimeException("Unable to download $name.");
    }

    $source = imagecreatefromstring($bytes);
    if (! $source) {
        throw new RuntimeException("Unable to decode $name.");
    }

    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    foreach ([640 => 78, 1280 => 84] as $targetWidth => $quality) {
        $width = min($targetWidth, $sourceWidth);
        $height = (int) round($sourceHeight * ($width / $sourceWidth));
        $target = imagecreatetruecolor($width, $height);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
        if (! imagewebp($target, "$outputDirectory/$name-$targetWidth.webp", $quality)) {
            throw new RuntimeException("Unable to encode $name at $targetWidth pixels.");
        }
        imagedestroy($target);
    }
    imagedestroy($source);
    echo "$name\n";
    usleep(500000);
}
