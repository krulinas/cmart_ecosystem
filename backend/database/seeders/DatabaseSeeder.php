<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Space;
use App\Models\CarbootEvent;
use App\Models\NewsPost;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\ManagementProfile;

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
            'name' => 'CMart Manager',
            'password' => bcrypt('password123'),
            'phone_number' => '0111111111',
            'role' => 'manager',
            'vendor_status' => 'none',
        ]);

        User::updateOrCreate(['email' => 'staff@cmart.com'], [
            'name' => 'CMart Staff',
            'password' => bcrypt('password123'),
            'phone_number' => '0122222222',
            'role' => 'staff',
            'vendor_status' => 'none',
        ]);

        User::updateOrCreate(['email' => 'hq@cmart.com'], [
            'name' => 'CMart HQ Admin',
            'password' => bcrypt('password123'),
            'phone_number' => '0133333333',
            'role' => 'super_admin',
            'vendor_status' => 'none',
        ]);

        $staff = User::where('email', 'staff@cmart.com')->first();
        if ($staff) {
            ManagementProfile::updateOrCreate(
                ['user_id' => $staff->id],
                [
                    'staff_code' => 'CM-STF-001',
                    'tier' => 1,
                    'position_title' => 'Operations Staff',
                    'department' => 'Operations',
                    'branch_name' => 'CMart Main Branch',
                    'is_active' => true,
                ]
            );
        }

        $manager = User::where('email', 'admin@cmart.com')->first();
        if ($manager) {
            ManagementProfile::updateOrCreate(
                ['user_id' => $manager->id],
                [
                    'staff_code' => 'CM-MGR-001',
                    'tier' => 2,
                    'position_title' => 'Branch Manager',
                    'department' => 'Management',
                    'branch_name' => 'CMart Main Branch',
                    'is_active' => true,
                ]
            );
        }

        $hqAdmin = User::where('email', 'hq@cmart.com')->first();
        if ($hqAdmin) {
            ManagementProfile::updateOrCreate(
                ['user_id' => $hqAdmin->id],
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

        CarbootEvent::updateOrCreate(
            ['title' => 'CMart Weekly Carboot'],
            [
                'starts_at' => '2026-05-16 08:00:00',
                'ends_at' => '2026-05-16 14:00:00',
                'status' => 'Available',
                'description' => 'Standard weekend carboot at CMart Changlun.',
                'max_slots' => 120,
            ]
        );

        CarbootEvent::updateOrCreate(
            ['title' => 'CMart Weekly Carboot (Almost Full)'],
            [
                'starts_at' => '2026-05-17 08:00:00',
                'ends_at' => '2026-05-17 14:00:00',
                'status' => 'Almost Full',
                'description' => 'Limited slots remaining for Sunday carboot.',
                'max_slots' => 120,
            ]
        );

        CarbootEvent::updateOrCreate(
            ['title' => 'Changlun Mega Carboot'],
            [
                'starts_at' => '2026-05-23 08:00:00',
                'ends_at' => '2026-05-23 18:00:00',
                'status' => 'Available',
                'description' => 'Extended hours mega carboot event.',
                'max_slots' => 200,
            ]
        );

        $admin = User::where('email', 'admin@cmart.com')->first();

        NewsPost::updateOrCreate(
            ['title' => 'Digital System Introduced with OIB Developers'],
            [
                'excerpt' => 'CMart proudly launches a new booking portal to simplify invoice management...',
                'body' => 'The new Carboot@CMart digital ecosystem is now live for vendors and community members.',
                'category' => 'Announcement',
                'image_url' => 'https://images.unsplash.com/photo-1556761175-5973dc0f32b7?q=80&w=800&auto=format&fit=crop',
                'published_at' => '2026-05-12 09:00:00',
                'is_published' => true,
                'author_id' => $admin?->id,
            ]
        );

        NewsPost::updateOrCreate(
            ['title' => 'Flea Market Vendors Transition to CMart'],
            [
                'excerpt' => 'Over 20 vendors from outside sites have joined our ecosystem...',
                'body' => 'CMart welcomes flea market vendors into the unified booking and approval pipeline.',
                'category' => 'Community',
                'image_url' => 'https://images.unsplash.com/photo-1472851294608-062f18ce0411?q=80&w=800&auto=format&fit=crop',
                'published_at' => '2026-05-10 09:00:00',
                'is_published' => true,
                'author_id' => $admin?->id,
            ]
        );

        NewsPost::updateOrCreate(
            ['title' => 'How to Choose the Right Space Size?'],
            [
                'excerpt' => 'Do you need an M or L sized space? Learn the exact dimensions and pricing...',
                'body' => 'Compare Standard and Large parking-lot spaces before you submit your vendor booking.',
                'category' => 'Vendor Tips',
                'image_url' => 'https://images.unsplash.com/photo-1533900298318-6b8da08a523e?q=80&w=800&auto=format&fit=crop',
                'published_at' => '2026-05-05 09:00:00',
                'is_published' => true,
                'author_id' => $admin?->id,
            ]
        );

        $vendor = User::where('email', 'vendor@cmart.com')->first();
        $standardSpace = Space::where('space_size', 'Standard (1 Parking Lot)')->first();

        if ($vendor && $standardSpace) {
            $demoBooking = Booking::updateOrCreate(
                [
                    'user_id' => $vendor->id,
                    'booking_date' => '2026-05-16',
                ],
                [
                    'space_id' => $standardSpace->id,
                    'product_category' => 'Food & Beverages',
                    'product_details' => 'Ayam Gunting, Ramen',
                    'approval_status' => 'Approved',
                    'revision_comment' => null,
                    'whatsapp_link' => 'https://chat.whatsapp.com/CMART_OFFICIAL_GROUP_INVITE',
                ]
            );

            Invoice::updateOrCreate(
                ['booking_id' => $demoBooking->id],
                [
                    'amount' => $standardSpace->price,
                    'payment_status' => 'Paid',
                ]
            );
        }
    }
}