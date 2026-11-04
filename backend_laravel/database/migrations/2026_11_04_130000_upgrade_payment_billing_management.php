<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Payment and Billing Management.
     */
    public function up(): void
    {
        // 1. Upgrade payments table with financial ledger & receipt fields
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'receipt_number')) {
                $table->string('receipt_number', 64)->nullable()->unique()->after('transaction_id');
            }
            if (!Schema::hasColumn('payments', 'is_advance_deposit')) {
                $table->boolean('is_advance_deposit')->default(false)->after('payment_method');
            }
            if (!Schema::hasColumn('payments', 'refunded_amount_etb')) {
                $table->decimal('refunded_amount_etb', 12, 2)->default(0)->after('amount_usd');
            }
            if (!Schema::hasColumn('payments', 'refund_status')) {
                $table->enum('refund_status', ['none', 'partially_refunded', 'refunded'])->default('none')->after('status');
            }
            if (!Schema::hasColumn('payments', 'parent_payment_id')) {
                $table->string('parent_payment_id', 64)->nullable()->after('refund_status');
            }
            if (!Schema::hasColumn('payments', 'processed_by')) {
                $table->string('processed_by', 64)->nullable()->after('reviewed_by');
            }
            if (!Schema::hasColumn('payments', 'idempotency_key')) {
                $table->string('idempotency_key', 128)->nullable()->index()->after('gateway_reference');
            }
            if (!Schema::hasColumn('payments', 'notes')) {
                $table->text('notes')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('payments', 'created_at')) {
                $table->timestamp('created_at')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('payments', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        // 2. Create refunds table for immutable audit ledger
        if (!Schema::hasTable('refunds')) {
            Schema::create('refunds', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('refund_number', 64)->unique();
                $table->string('payment_id', 64)->index();
                $table->string('booking_id', 64)->index();
                $table->decimal('amount_etb', 12, 2);
                $table->decimal('amount_usd', 12, 2)->default(0);
                $table->text('reason');
                $table->enum('status', ['pending', 'approved', 'completed', 'rejected'])->default('completed');
                $table->string('requested_by', 64)->nullable();
                $table->string('approved_by', 64)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }

        // 3. Create invoices table for persistent unique tax invoices
        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('invoice_number', 64)->unique();
                $table->string('booking_id', 64)->index();
                $table->string('guest_id', 64)->nullable()->index();
                $table->date('issue_date');
                $table->date('due_date')->nullable();
                $table->decimal('subtotal_etb', 12, 2)->default(0);
                $table->decimal('tax_vat_etb', 12, 2)->default(0);
                $table->decimal('tax_service_etb', 12, 2)->default(0);
                $table->decimal('tax_tourism_etb', 12, 2)->default(0);
                $table->decimal('total_amount_etb', 12, 2)->default(0);
                $table->decimal('paid_amount_etb', 12, 2)->default(0);
                $table->decimal('balance_due_etb', 12, 2)->default(0);
                $table->enum('status', ['unpaid', 'partially_paid', 'paid', 'void'])->default('unpaid');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 4. Upgrade booking_service_charges with duplicate charge prevention reference
        if (Schema::hasTable('booking_service_charges')) {
            Schema::table('booking_service_charges', function (Blueprint $table) {
                if (!Schema::hasColumn('booking_service_charges', 'reference_code')) {
                    $table->string('reference_code', 128)->nullable()->index()->after('description');
                }
            });
        }

        // 5. Expand bookings payment_status enum and payments payment_method column
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method VARCHAR(64) NOT NULL DEFAULT 'cash'");
        DB::statement("ALTER TABLE bookings MODIFY COLUMN payment_status ENUM('pending','pending_verification','paid','rejected','refunded','unpaid','partially_paid') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('invoices');

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $columns = ['receipt_number', 'is_advance_deposit', 'refunded_amount_etb', 'refund_status', 'parent_payment_id', 'processed_by', 'idempotency_key', 'notes', 'created_at', 'updated_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('payments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('booking_service_charges')) {
            Schema::table('booking_service_charges', function (Blueprint $table) {
                if (Schema::hasColumn('booking_service_charges', 'reference_code')) {
                    $table->dropColumn('reference_code');
                }
            });
        }
    }
};
