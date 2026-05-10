<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Space;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // 1. Create a dummy Vendor (User ID 1)
        User::create([
            'name' => 'Inas (Test Vendor)',
            'email' => 'vendor@cmart.com',
            'password' => bcrypt('password123'),
            'phone_number' => '0123456789',
            'role' => 'Vendor'
        ]);

        // 2. Create the Spaces (Space ID 1 & 2)
        Space::create([
            'space_size' => 'Standard (1 Parking Lot)',
            'price' => 30.00,
            'status' => 'Available'
        ]);

        Space::create([
            'space_size' => 'Large (2 Parking Lots)',
            'price' => 50.00,
            'status' => 'Available'
        ]);
    }
}