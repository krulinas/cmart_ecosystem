<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function unverified()
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Canonical role states (Phase 1.3C). Do not add states for the legacy
     * manager/uum/staff roles — tests should use these canonical states.
     */
    public function community(string $vendorStatus = 'none')
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'community',
            'vendor_status' => $vendorStatus,
        ]);
    }

    public function organizer()
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'organizer',
            'vendor_status' => 'none',
        ]);
    }

    public function cmartManagement()
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'cmart_management',
            'vendor_status' => 'none',
        ]);
    }

    public function superAdmin()
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'super_admin',
            'vendor_status' => 'none',
        ]);
    }
}
