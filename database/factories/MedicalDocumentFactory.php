<?php

namespace Database\Factories;

use App\Enums\MedicalDocumentStatus;
use App\Enums\MedicalDocumentType;
use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MedicalDocumentFactory extends Factory
{
    protected $model = MedicalDocument::class;

    public function definition(): array
    {
        return ['type' => MedicalDocumentType::MEDICAL_CERTIFICATE, 'status' => MedicalDocumentStatus::DRAFT, 'uploaded_by' => User::factory(), 'original_filename' => 'certificate.pdf', 'original_path' => 'medical/original/'.fake()->uuid().'.pdf', 'original_sha256' => str_repeat('a', 64)];
    }
}
