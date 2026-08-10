<?php

namespace Database\Factories;

use App\Models\Specialty;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SpecialtyFactory extends Factory
{
    protected $model = Specialty::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return ['name' => ucwords($name), 'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999), 'description' => fake()->sentence(), 'is_active' => true, 'is_public' => true];
    }
}
