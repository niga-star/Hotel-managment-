<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Check-in and Check-out management upgrade.
     */
    public function up(): void
    {
        // 1. Add Check-in / Check-out operational fields to bookings table
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'key_card_number')) {
                $table->string('key_card_number', 64)->nullable()->after('qr_verification_hash');
            }
            if (!Schema::hasColumn('bookings', 'early_check_in_approved')) {
                $table->boolean('early_check_in_approved')->default(false)->after('actual_check_in_at');
            }
            if (!Schema::hasColumn('bookings', 'early_check_in_fee_etb')) {
                $table->decimal('early_check_in_fee_etb', 10, 2)->default(0)->after('early_check_in_approved');
            }
            if (!Schema::hasColumn('bookings', 'late_check_out_approved')) {
                $table->boolean('late_check_out_approved')->default(false)->after('actual_check_out_at');
            }
            if (!Schema::hasColumn('bookings', 'late_check_out_fee_etb')) {
                $table->decimal('late_check_out_fee_etb', 10, 2)->default(0)->after('late_check_out_approved');
            }
            if (!Schema::hasColumn('bookings', 'check_in_notes')) {
                $table->text('check_in_notes')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('bookings', 'check_out_notes')) {
                $table->text('check_out_notes')->nullable()->after('check_in_notes');
            }
        });

        // 2. Create booking_service_charges table for incidental / hotel service charges
        if (!Schema::hasTable('booking_service_charges')) {
            Schema::create('booking_service_charges', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('booking_id', 64);
                $table->enum('service_type', [
                    'restaurant',
                    'minibar',
                    'room_service',
                    'laundry',
                    'spa',
                    'late_checkout',
                    'early_checkin',
                    'damage',
                    'other'
                ])->default('other');
                $table->string('description', 255);
                $table->decimal('amount_etb', 12, 2);
                $table->decimal('amount_usd', 10, 2)->default(0);
                $table->integer('quantity')->default(1);
                $table->string('added_by', 64)->nullable();
                $table->timestamps();

                $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
                $table->index('booking_id', 'idx_service_charges_booking_id');
                $table->index('service_type', 'idx_service_charges_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_service_charges');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'key_card_number',
                'early_check_in_approved',
                'early_check_in_fee_etb',
                'late_check_out_approved',
                'late_check_out_fee_etb',
                'check_in_notes',
                'check_out_notes',
            ]);
        });
    }
};
