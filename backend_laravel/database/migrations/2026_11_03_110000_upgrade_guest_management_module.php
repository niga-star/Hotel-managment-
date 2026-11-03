<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations to upgrade Guest Management capabilities.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Personal & Demographic Details
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name', 60)->nullable()->after('full_name');
            }
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name', 60)->nullable()->after('first_name');
            }
            if (!Schema::hasColumn('users', 'gender')) {
                $table->enum('gender', ['male', 'female', 'other', 'unspecified'])->default('unspecified')->after('last_name');
            }
            if (!Schema::hasColumn('users', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('gender');
            }
            if (!Schema::hasColumn('users', 'nationality')) {
                $table->string('nationality', 100)->nullable()->default('Ethiopian')->after('date_of_birth');
            }

            // Contact & Residential Address
            if (!Schema::hasColumn('users', 'address')) {
                $table->string('address', 255)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'city')) {
                $table->string('city', 100)->nullable()->default('Addis Ababa')->after('address');
            }
            if (!Schema::hasColumn('users', 'country')) {
                $table->string('country', 100)->nullable()->default('Ethiopia')->after('city');
            }

            // Identification Information
            if (!Schema::hasColumn('users', 'id_type')) {
                $table->enum('id_type', ['national_id', 'passport', 'driving_license', 'kebele_id', 'resident_id', 'other'])->nullable()->after('country');
            }
            if (!Schema::hasColumn('users', 'id_number')) {
                $table->string('id_number', 100)->nullable()->after('id_type');
            }
            if (!Schema::hasColumn('users', 'id_issue_country')) {
                $table->string('id_issue_country', 100)->nullable()->default('Ethiopia')->after('id_number');
            }
            if (!Schema::hasColumn('users', 'id_expiry_date')) {
                $table->date('id_expiry_date')->nullable()->after('id_issue_country');
            }

            // Hospitality VIP & Operational Status
            if (!Schema::hasColumn('users', 'vip_status')) {
                $table->enum('vip_status', ['standard', 'vip', 'vvip', 'blacklist'])->default('standard')->after('id_expiry_date');
            }
            if (!Schema::hasColumn('users', 'preferences')) {
                $table->json('preferences')->nullable()->after('vip_status');
            }
            if (!Schema::hasColumn('users', 'notes')) {
                $table->text('notes')->nullable()->after('preferences');
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('notes');
            }

            // Emergency Contact Details
            if (!Schema::hasColumn('users', 'emergency_contact_name')) {
                $table->string('emergency_contact_name', 120)->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('users', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone', 50)->nullable()->after('emergency_contact_name');
            }
        });

        // Add indexes for efficient searching and duplicate checking
        Schema::table('users', function (Blueprint $table) {
            $table->index('id_number', 'idx_users_id_number');
            $table->index('vip_status', 'idx_users_vip_status');
            $table->index('is_active', 'idx_users_is_active');
        });

        // Backfill first_name and last_name from existing full_name
        $users = DB::table('users')->get();
        foreach ($users as $u) {
            $parts = explode(' ', trim($u->full_name), 2);
            $firstName = $parts[0] ?? $u->full_name;
            $lastName = $parts[1] ?? '';
            DB::table('users')->where('id', $u->id)->update([
                'first_name' => $u->first_name ?: $firstName,
                'last_name' => $u->last_name ?: $lastName,
                'is_active' => 1,
                'nationality' => $u->nationality ?: 'Ethiopian',
                'country' => $u->country ?: 'Ethiopia',
                'vip_status' => $u->vip_status ?: 'standard',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_id_number');
            $table->dropIndex('idx_users_vip_status');
            $table->dropIndex('idx_users_is_active');

            $table->dropColumn([
                'first_name',
                'last_name',
                'gender',
                'date_of_birth',
                'nationality',
                'address',
                'city',
                'country',
                'id_type',
                'id_number',
                'id_issue_country',
                'id_expiry_date',
                'vip_status',
                'preferences',
                'notes',
                'is_active',
                'emergency_contact_name',
                'emergency_contact_phone',
            ]);
        });
    }
};
