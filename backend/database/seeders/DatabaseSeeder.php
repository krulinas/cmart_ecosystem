<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Space;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Demo accounts for Carboot@CMart RBAC.
        User::updateOrCreate(['email' => 'vendor@cmart.com'], [
            'name' => 'Inas (Test Vendor)',
            'password' => bcrypt('password123'),
            'phone_number' => '0123456789',
            'role' => 'community',
            'vendor_status' => 'approved',
        ]);

        User::updateOrCreate(['email' => 'admin@cmart.com'], [
            'name' => 'CMart Admin',
            'password' => bcrypt('password123'),
            'phone_number' => '0111111111',
            'role' => 'cmart_admin',
            'vendor_status' => 'none',
        ]);

        User::updateOrCreate(['email' => 'staff@cmart.com'], [
            'name' => 'CMart Staff',
            'password' => bcrypt('password123'),
            'phone_number' => '0122222222',
            'role' => 'cmart_staff',
            'vendor_status' => 'none',
        ]);

        // Create default rentable spaces.
        Space::updateOrCreate(['space_size' => 'Standard (1 Parking Lot)'], [
            'space_size' => 'Standard (1 Parking Lot)',
            'price' => 30.00,
            'status' => 'Available'
        ]);

        Space::updateOrCreate(['space_size' => 'Large (2 Parking Lots)'], [
            'space_size' => 'Large (2 Parking Lots)',
            'price' => 50.00,
            'status' => 'Available'
        ]);
    }
}