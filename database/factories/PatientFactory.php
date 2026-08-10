<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return ['document_type' => 'DNI', 'document_number' => fake()->unique()->numerify('########'), 'first_name' => fake()->firstName(), 'last_name' => fake()->lastName(), 'birth_date' => fake()->dateTimeBetween('-80 years', '-1 year')];
    }
}
