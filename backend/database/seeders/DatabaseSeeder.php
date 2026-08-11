<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Space;
use App\Models\ManagementProfile;
use RuntimeException;

/**
 * Baseline catalogue / RBAC seed for local development.
 *
 * Does NOT create demo events, news, bookings, or feedback.
 * For opt-in local demo business content only:
 *   php artisan db:seed --class=DemoContentSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run()
    {
        if (app()->environment(['production', 'prod', 'uat', 'staging'])) {
            throw new RuntimeException(
                'DatabaseSeeder refused: do not seed demo accounts or catalogues into production, UAT, or staging via db:seed.'
            );
        }

        // Development RBAC accounts (local/testing/e2e only).
        User::updateOrCreate(['email' => 'vendor@cmart.com'], [
            'name' => 'Inas (Test Vendor)',
            'password' => bcrypt('password123'),
            'phone_number' => '0123456789',
            'role' => 'community',
            'vendor_status' => 'approved',
        ]);

        User::updateOrCreate(['email' => 'vendor_b@cmart.com'], [
            'name' => 'Vendor B (E2E Test)',
            'password' => bcrypt('password123'),
            'phone_number' => '0123456790',
            'role' => 'community',
            'vendor_status' => 'approved',
        ]);

        User::updateOrCreate(['email' => 'admin@cmart.com'], [
            'name' => 'Carboot Organizer (Ops)',
            'password' => bcrypt('password123'),
            'phone_number' => '0111111111',
            'role' => 'organizer',
            'vendor_status' => 'none',
        ]);

        User::updateOrCreate(['email' => 'staff@cmart.com'], [
            'name' => 'CMart Management Demo',
            'password' => bcrypt('password123'),
            'phone_number' => '0122222222',
            'role' => 'cmart_management',
            'vendor_status' => 'none',
        ]);

        User::updateOrCreate(['email' => 'hq@cmart.com'], [
            'name' => 'CMart HQ Admin',
            'password' => bcrypt('password123'),
            'phone_number' => '0133333333',
            'role' => 'super_admin',
            'vendor_status' => 'none',
        ]);

        User::updateOrCreate(['email' => 'organizer@cmart.com'], [
            'name' => 'Carboot Organizer',
            'password' => bcrypt('password123'),
            'phone_number' => '0144444444',
            'role' => 'organizer',
            'vendor_status' => 'none',
        ]);

        User::updateOrCreate(['email' => 'venue@cmart.com'], [
            'name' => 'CMart Venue Manager',
            'password' => bcrypt('password123'),
            'phone_number' => '0155555555',
            'role' => 'cmart_management',
            'vendor_status' => 'none',
        ]);

        $staff = User::where('email', 'staff@cmart.com')->first();
        if ($staff) {
            ManagementProfile::updateOrCreate(
                ['user_id' => $staff->id],
                [
                    'staff_code' => 'CM-STF-001',
                    'tier' => 2,
                    'position_title' => 'CMart Management',
                    'department' => 'Venue & Activities',
                    'branch_name' => 'CMart Main Branch',
                    'is_active' => true,
                ]
            );
        }

        $legacyOrganizer = User::where('email', 'admin@cmart.com')->first();
        if ($legacyOrganizer) {
            ManagementProfile::updateOrCreate(
                ['user_id' => $legacyOrganizer->id],
                [
                    'staff_code' => 'CM-MGR-001',
                    'tier' => 2,
                    'position_title' => 'Carboot Organizer',
                    'department' => 'Carboot Operations',
                    'branch_name' => 'CMart Main Branch',
                    'is_active' => true,
                ]
            );
        }

        $hq = User::where('email', 'hq@cmart.com')->first();
        if ($hq) {
            ManagementProfile::updateOrCreate(
                ['user_id' => $hq->id],
                [
                    'staff_code' => 'CM-HQ-001',
                    'tier' => 3,
                    'position_title' => 'HQ Administrator',
                    'department' => 'Headquarters',
                    'branch_name' => 'HQ',
                    'is_active' => true,
                ]
            );
        }

        $organizer = User::where('email', 'organizer@cmart.com')->first();
        if ($organizer) {
            ManagementProfile::updateOrCreate(
                ['user_id' => $organizer->id],
                [
                    'staff_code' => 'CM-ORG-001',
                    'tier' => 2,
                    'position_title' => 'Carboot Organizer',
                    'department' => 'Carboot Operations',
                    'branch_name' => 'CMart Main Branch',
                    'is_active' => true,
                ]
            );
        }

        $venue = User::where('email', 'venue@cmart.com')->first();
        if ($venue) {
            ManagementProfile::updateOrCreate(
                ['user_id' => $venue->id],
                [
                    'staff_code' => 'CM-VEN-001',
                    'tier' => 2,
                    'position_title' => 'CMart Venue Manager',
                    'department' => 'Venue & Activities',
                    'branch_name' => 'CMart Main Branch',
                    'is_active' => true,
                ]
            );
        }

        Space::updateOrCreate(
            ['space_size' => Space::PHYSICAL_PARKING_SITE],
            [
                'space_size' => Space::PHYSICAL_PARKING_SITE,
                'status' => 'Available',
            ]
        );

        $this->call(VendorCategorySeeder::class);

        if ($this->command) {
            $this->command->warn(
                'Demo events/news/bookings/feedback were NOT seeded. '
                .'For local demo content only: php artisan db:seed --class=DemoContentSeeder'
            );
        }
    }
}
