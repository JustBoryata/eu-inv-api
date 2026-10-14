<?php

namespace Database\Factories;

use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organisation>
 */
class OrganisationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $registrationNumber = fake()->numerify('#########');

        return [
            'name' => fake()->company(),
            'country_code' => 'BG',
            'registration_number' => $registrationNumber,
            'vat_number' => 'BG'.$registrationNumber,
            'address' => fake()->address(),
        ];
    }
}
