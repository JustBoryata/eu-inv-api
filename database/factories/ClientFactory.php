<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $registrationNumber = fake()->numerify('#########');

        return [
            'organisation_id' => Organisation::factory(),
            'name' => fake()->company(),
            'vat_number' => 'BG'.$registrationNumber,
            'registration_number' => $registrationNumber,
            'address' => fake()->address(),
            'country_code' => 'BG',
            'email' => fake()->companyEmail(),
        ];
    }

    /**
     * A private person: no VAT or registration number.
     */
    public function consumer(): static
    {
        return $this->state(fn () => [
            'name' => fake()->name(),
            'vat_number' => null,
            'registration_number' => null,
        ]);
    }
}
