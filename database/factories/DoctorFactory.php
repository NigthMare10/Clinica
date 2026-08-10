<?php

namespace Database\Factories;

use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition(): array
    {
        return ['first_name' => fake()->firstName(), 'last_name' => fake()->lastName(), 'credential_type' => 'CMP', 'credential_number' => fake()->unique()->numerify('######'), 'email' => fake()->unique()->safeEmail(), 'is_active' => true, 'is_public' => true];
    }
}
