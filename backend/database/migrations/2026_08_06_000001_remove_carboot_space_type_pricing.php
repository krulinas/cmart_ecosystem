<?php

use App\Models\Space;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collapse legacy Carboot Space Type pricing (Standard RM30 / Large RM50)
 * into a single internal physical-site record.
 *
 * The obsolete `spaces.price` column is zeroed and retained only as unused
 * schema residue so existing FKs remain intact. Booking money is authoritative
 * via carboot_events.site_price × site count (bookings.unit_site_price snapshot).
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaultId = $this->ensureDefaultPhysicalSpaceId();

        if (Schema::hasTable('event_sites')) {
            DB::table('event_sites')
                ->where(function ($query) use ($defaultId) {
                    $query->whereNull('space_id')
                        ->orWhere('space_id', '!=', $defaultId);
                })
                ->update(['space_id' => $defaultId]);
        }

        if (Schema::hasTable('bookings')) {
            DB::table('bookings')
                ->where(function ($query) use ($defaultId) {
                    $query->whereNull('space_id')
                        ->orWhere('space_id', '!=', $defaultId);
                })
                ->update(['space_id' => $defaultId]);
        }

        DB::table('spaces')->where('id', '!=', $defaultId)->delete();

        if (Schema::hasColumn('spaces', 'price')) {
            DB::table('spaces')->update(['price' => 0]);
        }
    }

    public function down(): void
    {
        $default = DB::table('spaces')
            ->where('space_size', Space::PHYSICAL_PARKING_SITE)
            ->first();

        if ($default) {
            DB::table('spaces')->where('id', $default->id)->update([
                'space_size' => 'Standard (1 Parking Lot)',
                'price' => 30.00,
                'updated_at' => now(),
            ]);
        }

        $largeExists = DB::table('spaces')
            ->where('space_size', 'Large (2 Parking Lots)')
            ->exists();

        if (! $largeExists) {
            DB::table('spaces')->insert([
                'space_size' => 'Large (2 Parking Lots)',
                'price' => 50.00,
                'status' => 'Available',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureDefaultPhysicalSpaceId(): int
    {
        $standard = DB::table('spaces')
            ->where('space_size', 'Standard (1 Parking Lot)')
            ->orderBy('id')
            ->first();

        if ($standard) {
            DB::table('spaces')->where('id', $standard->id)->update([
                'space_size' => Space::PHYSICAL_PARKING_SITE,
                'price' => 0,
                'status' => 'Available',
                'updated_at' => now(),
            ]);

            return (int) $standard->id;
        }

        $existing = DB::table('spaces')
            ->where('space_size', Space::PHYSICAL_PARKING_SITE)
            ->orderBy('id')
            ->first();

        if ($existing) {
            DB::table('spaces')->where('id', $existing->id)->update([
                'price' => 0,
                'status' => 'Available',
                'updated_at' => now(),
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table('spaces')->insertGetId([
            'space_size' => Space::PHYSICAL_PARKING_SITE,
            'price' => 0,
            'status' => 'Available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
