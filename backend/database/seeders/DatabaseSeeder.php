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
use Illuminate\Support\Carbon;

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

        User::updateOrCreate(['email' => 'vendor_b@cmart.com'], [
            'name' => 'Vendor B (E2E Test)',
            'password' => bcrypt('password123'),
            'phone_number' => '0123456790',
            'role' => 'community',
            'vendor_status' => 'approved',
        ]);

        // Legacy demo login retained for continuity; the role is canonical
        // organizer now (manager was the legacy Organizer bridge).
        User::updateOrCreate(['email' => 'admin@cmart.com'], [
            'name' => 'Carboot Organizer (Ops)',
            'password' => bcrypt('password123'),
            'phone_number' => '0111111111',
            'role' => 'organizer',
            'vendor_status' => 'none',
        ]);

        // TEMPORARY (PR2 removes): staff role is kept only because the
        // two-stage booking pipeline (Pending_Staff stage) and its tests/E2E
        // still need a staff-stage actor until the PR2 workflow cutover.
        // PR2 remaps this account to cmart_management.
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
                    'tier' => 1,
                    'position_title' => 'Operations Staff',
                    'department' => 'Operations',
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

        $venueManager = User::where('email', 'venue@cmart.com')->first();
        if ($venueManager) {
            ManagementProfile::updateOrCreate(
                ['user_id' => $venueManager->id],
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

        // Upcoming events use relative dates so E2E booking specs stay bookable after re-seeding.
        $weeklyStart = Carbon::now()->addDays(7)->setTime(8, 0, 0);
        $weeklyEnd = $weeklyStart->copy()->addHours(6);
        $almostFullStart = Carbon::now()->addDays(14)->setTime(8, 0, 0);
        $almostFullEnd = $almostFullStart->copy()->addHours(6);
        $megaStart = Carbon::now()->addDays(21)->setTime(8, 0, 0);
        $megaEnd = $megaStart->copy()->addHours(10);

        $weeklyEvent = CarbootEvent::updateOrCreate(
            ['title' => 'CMart Weekly Carboot'],
            [
                'starts_at' => $weeklyStart,
                'ends_at' => $weeklyEnd,
                'status' => 'Available',
                'description' => 'Standard weekend carboot at CMart Changlun.',
                'max_slots' => 120,
            ]
        );

        CarbootEvent::updateOrCreate(
            ['title' => 'CMart Weekly Carboot (Almost Full)'],
            [
                'starts_at' => $almostFullStart,
                'ends_at' => $almostFullEnd,
                'status' => 'Almost Full',
                'description' => 'Limited slots remaining for Sunday carboot.',
                'max_slots' => 120,
            ]
        );

        CarbootEvent::updateOrCreate(
            ['title' => 'Changlun Mega Carboot'],
            [
                'starts_at' => $megaStart,
                'ends_at' => $megaEnd,
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

        if ($vendor && $standardSpace && $weeklyEvent) {
            $demoBooking = Booking::updateOrCreate(
                [
                    'user_id' => $vendor->id,
                    'carboot_event_id' => $weeklyEvent->id,
                ],
                [
                    'space_id' => $standardSpace->id,
                    'booking_date' => $weeklyStart->toDateString(),
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

        $this->call(FeedbackSeeder::class);
    }
}