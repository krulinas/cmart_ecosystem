<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\Invoice;
use App\Models\NewsPost;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Opt-in demo business content for local/E2E development only.
 *
 * Never invoked by DatabaseSeeder. Run explicitly:
 *   php artisan db:seed --class=DemoContentSeeder
 *
 * Refuses to run in production / UAT / staging environments.
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->isUnsafeEnvironment()) {
            throw new RuntimeException(
                'DemoContentSeeder refused: demo events, news, bookings, and feedback must not run in production, UAT, or staging.'
            );
        }

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
                'site_price' => CarbootEvent::DEFAULT_SITE_PRICE,
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
                'site_price' => CarbootEvent::DEFAULT_SITE_PRICE,
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
                'site_price' => CarbootEvent::DEFAULT_SITE_PRICE,
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

    private function isUnsafeEnvironment(): bool
    {
        return app()->environment(['production', 'prod', 'uat', 'staging']);
    }
}
