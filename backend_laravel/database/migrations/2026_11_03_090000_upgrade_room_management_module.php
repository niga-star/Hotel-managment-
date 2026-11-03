<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Expand rooms status enum to include 'reserved' and 'cleaning'
        DB::statement("ALTER TABLE rooms MODIFY COLUMN status ENUM('available', 'reserved', 'occupied', 'maintenance', 'cleaning') NOT NULL DEFAULT 'available'");

        // 2. Add max_capacity to room_categories if not present
        if (!Schema::hasColumn('room_categories', 'max_capacity')) {
            Schema::table('room_categories', function (Blueprint $table) {
                $table->integer('max_capacity')->default(2)->after('base_price_usd');
            });
        }

        // Add updated_at to room_categories if not present
        if (!Schema::hasColumn('room_categories', 'updated_at')) {
            Schema::table('room_categories', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate()->after('created_at');
            });
        }

        // 3. Add nightly_rate_etb and nightly_rate_usd to bookings table to preserve agreed rates
        if (!Schema::hasColumn('bookings', 'nightly_rate_etb')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->decimal('nightly_rate_etb', 12, 2)->nullable()->after('children_count');
                $table->decimal('nightly_rate_usd', 10, 2)->nullable()->after('nightly_rate_etb');
            });
        }

        // 4. Backfill nightly rates on historical bookings if null
        DB::statement("
            UPDATE bookings b
            JOIN rooms r ON b.room_id = r.id
            SET b.nightly_rate_etb = COALESCE(b.nightly_rate_etb, r.price_etb),
                b.nightly_rate_usd = COALESCE(b.nightly_rate_usd, r.price_usd)
            WHERE b.nightly_rate_etb IS NULL
        ");

        // 5. Seed standard Room Types required for luxury & full-spectrum hotel operations:
        // Single Room, Double Room, Twin Room, Triple Room, Deluxe Room, Suite, Family Room
        $standardCategories = [
            [
                'id' => 'cat-single',
                'name' => 'Single Room',
                'slug' => 'single-room',
                'badge_color' => '#2563EB',
                'description' => 'Cozy, well-appointed private room designed for solo business and leisure travelers.',
                'base_price_etb' => 1100.00,
                'base_price_usd' => 160.00,
                'max_capacity' => 1,
            ],
            [
                'id' => 'cat-double',
                'name' => 'Double Room',
                'slug' => 'double-room',
                'badge_color' => '#059669',
                'description' => 'Comfortable room featuring one plush queen or king-size bed, ideal for couples.',
                'base_price_etb' => 1500.00,
                'base_price_usd' => 210.00,
                'max_capacity' => 2,
            ],
            [
                'id' => 'cat-twin',
                'name' => 'Twin Room',
                'slug' => 'twin-room',
                'badge_color' => '#0D9488',
                'description' => 'Spacious room with two separate single beds, perfect for friends and colleagues.',
                'base_price_etb' => 1600.00,
                'base_price_usd' => 220.00,
                'max_capacity' => 2,
            ],
            [
                'id' => 'cat-triple',
                'name' => 'Triple Room',
                'slug' => 'triple-room',
                'badge_color' => '#7C3AED',
                'description' => 'Versatile accommodation with three individual beds or combo setup for travel groups.',
                'base_price_etb' => 2100.00,
                'base_price_usd' => 290.00,
                'max_capacity' => 3,
            ],
            [
                'id' => 'cat-deluxe',
                'name' => 'Deluxe Room',
                'slug' => 'deluxe',
                'badge_color' => '#D4A329',
                'description' => 'Spacious deluxe rooms with king comfort, private balcony, and scenic resort panoramas.',
                'base_price_etb' => 1800.00,
                'base_price_usd' => 250.00,
                'max_capacity' => 2,
            ],
            [
                'id' => 'cat-suite',
                'name' => 'Suite',
                'slug' => 'suite',
                'badge_color' => '#4F46E5',
                'description' => 'Luxury Ethiopian heritage suite featuring separate lounge, handcrafted timber art, and VIP amenities.',
                'base_price_etb' => 2500.00,
                'base_price_usd' => 390.00,
                'max_capacity' => 4,
            ],
            [
                'id' => 'cat-family',
                'name' => 'Family Room',
                'slug' => 'family-room',
                'badge_color' => '#EA580C',
                'description' => 'Generously sized multi-bed suite tailored for families with children, offering interconnected space.',
                'base_price_etb' => 3000.00,
                'base_price_usd' => 420.00,
                'max_capacity' => 5,
            ],
        ];

        foreach ($standardCategories as $cat) {
            DB::table('room_categories')->updateOrInsert(
                ['id' => $cat['id']],
                [
                    'name' => $cat['name'],
                    'slug' => $cat['slug'],
                    'badge_color' => $cat['badge_color'],
                    'description' => $cat['description'],
                    'base_price_etb' => $cat['base_price_etb'],
                    'base_price_usd' => $cat['base_price_usd'],
                    'max_capacity' => $cat['max_capacity'],
                    'created_at' => now(),
                ]
            );
        }

        // Update Standard category capacity if it exists
        DB::table('room_categories')->where('id', 'cat-standard')->update([
            'max_capacity' => 2
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE rooms MODIFY COLUMN status ENUM('available', 'occupied', 'maintenance') NOT NULL DEFAULT 'available'");
        
        if (Schema::hasColumn('room_categories', 'max_capacity')) {
            Schema::table('room_categories', function (Blueprint $table) {
                $table->dropColumn('max_capacity');
            });
        }

        if (Schema::hasColumn('bookings', 'nightly_rate_etb')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn(['nightly_rate_etb', 'nightly_rate_usd']);
            });
        }
    }
};
