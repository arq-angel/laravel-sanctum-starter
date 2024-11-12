<?php

namespace Database\Seeders;

use App\Models\User;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => fake()->phoneNumber(),
            'password' => bcrypt('password'),
            'date_of_birth' => fake()->dateTimeBetween(endDate: '-15 years'),
            'address' => fake()->streetAddress(),
            'suburb' => fake()->city(),
            'state' => fake()->randomElement(['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'NT', 'ACT',]),
            'post_code' => fake()->postCode(),
            'country' => 'Australia',
            'is_active' => true,
            'is_verified' => true,
        ]);

        User::factory(10)->create();

    }
}
