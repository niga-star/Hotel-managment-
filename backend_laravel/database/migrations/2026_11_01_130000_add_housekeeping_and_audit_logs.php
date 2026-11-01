<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('rooms', 'housekeeping_status')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->enum('housekeeping_status', ['inspected', 'clean', 'dirty', 'maintenance'])
                      ->default('inspected')
                      ->after('status');
            });
        }

        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('user_id', 64)->nullable();
                $table->string('user_name', 150)->nullable();
                $table->string('user_role', 50)->nullable();
                $table->string('action', 100);
                $table->string('resource_type', 50);
                $table->string('resource_id', 100)->nullable();
                $table->json('details')->nullable();
                $table->string('ip_address', 50)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rooms', 'housekeeping_status')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropColumn('housekeeping_status');
            });
        }
        Schema::dropIfExists('audit_logs');
    }
};
