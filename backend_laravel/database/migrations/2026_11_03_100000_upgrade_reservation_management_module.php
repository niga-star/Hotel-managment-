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
        // 1. Expand booking_status enum in bookings to include 'no_show' and 'pending'
        DB::statement("ALTER TABLE bookings MODIFY COLUMN booking_status ENUM('pending','pending_payment','pending_verification','confirmed','checked_in','checked_out','rejected','cancelled','expired','no_show') NOT NULL DEFAULT 'confirmed'");

        // 2. Add rooms_count and cancellation tracking columns to bookings
        if (!Schema::hasColumn('bookings', 'rooms_count')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->integer('rooms_count')->default(1)->after('children_count');
            });
        }

        if (!Schema::hasColumn('bookings', 'cancellation_reason')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->text('cancellation_reason')->nullable()->after('checked_out_by');
                $table->dateTime('cancelled_at')->nullable()->after('cancellation_reason');
                $table->string('cancelled_by', 64)->nullable()->after('cancelled_at');
            });
        }

        // 3. Create booking_rooms table for full multiple-room reservation support
        if (!Schema::hasTable('booking_rooms')) {
            Schema::create('booking_rooms', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('booking_id', 64);
                $table->string('room_id', 64);
                $table->string('room_number', 20)->nullable();
                $table->integer('adults_count')->default(1);
                $table->integer('children_count')->default(0);
                $table->decimal('nightly_rate_etb', 12, 2)->default(0.00);
                $table->decimal('nightly_rate_usd', 10, 2)->default(0.00);
                $table->decimal('total_price_etb', 12, 2)->default(0.00);
                $table->decimal('total_price_usd', 10, 2)->default(0.00);
                $table->enum('status', ['reserved', 'checked_in', 'checked_out', 'cancelled'])->default('reserved');
                $table->string('notes', 255)->nullable();
                $table->timestamps();

                $table->foreign('booking_id', 'fk_br_booking')
                    ->references('id')->on('bookings')
                    ->onDelete('cascade');

                $table->foreign('room_id', 'fk_br_room')
                    ->references('id')->on('rooms')
                    ->onDelete('restrict');

                $table->index(['room_id', 'status'], 'idx_br_room_status');
            });
        }

        // 4. Backfill booking_rooms from existing bookings if empty
        $existingBookingsCount = DB::table('bookings')->count();
        $existingBookingRoomsCount = DB::table('booking_rooms')->count();

        if ($existingBookingsCount > 0 && $existingBookingRoomsCount === 0) {
            $bookings = DB::table('bookings')
                ->leftJoin('rooms', 'bookings.room_id', '=', 'rooms.id')
                ->select(
                    'bookings.id as booking_id',
                    'bookings.room_id',
                    'rooms.room_number',
                    'bookings.adults_count',
                    'bookings.children_count',
                    'bookings.nightly_rate_etb',
                    'bookings.nightly_rate_usd',
                    'bookings.total_price_etb',
                    'bookings.total_price_usd',
                    'bookings.booking_status',
                    'bookings.created_at'
                )
                ->get();

            $batch = [];
            foreach ($bookings as $b) {
                $roomStatus = match ($b->booking_status) {
                    'checked_in' => 'checked_in',
                    'checked_out' => 'checked_out',
                    'cancelled', 'rejected' => 'cancelled',
                    default => 'reserved',
                };

                $batch[] = [
                    'id' => 'br-' . $b->booking_id,
                    'booking_id' => $b->booking_id,
                    'room_id' => $b->room_id,
                    'room_number' => $b->room_number,
                    'adults_count' => $b->adults_count ?: 1,
                    'children_count' => $b->children_count ?: 0,
                    'nightly_rate_etb' => $b->nightly_rate_etb ?: 0.00,
                    'nightly_rate_usd' => $b->nightly_rate_usd ?: 0.00,
                    'total_price_etb' => $b->total_price_etb ?: 0.00,
                    'total_price_usd' => $b->total_price_usd ?: 0.00,
                    'status' => $roomStatus,
                    'notes' => 'Primary room',
                    'created_at' => $b->created_at ?: now(),
                    'updated_at' => now(),
                ];

                if (count($batch) >= 100) {
                    DB::table('booking_rooms')->insert($batch);
                    $batch = [];
                }
            }
            if (!empty($batch)) {
                DB::table('booking_rooms')->insert($batch);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_rooms');

        if (Schema::hasColumn('bookings', 'cancellation_reason')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn(['cancellation_reason', 'cancelled_at', 'cancelled_by']);
            });
        }

        if (Schema::hasColumn('bookings', 'rooms_count')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('rooms_count');
            });
        }
    }
};
