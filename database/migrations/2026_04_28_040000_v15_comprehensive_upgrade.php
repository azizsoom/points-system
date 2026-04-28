<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'can_manage_invoices')) $table->boolean('can_manage_invoices')->default(true);
                if (!Schema::hasColumn('users', 'can_approve_invoices')) $table->boolean('can_approve_invoices')->default(false);
                if (!Schema::hasColumn('users', 'can_manage_payouts')) $table->boolean('can_manage_payouts')->default(false);
                if (!Schema::hasColumn('users', 'can_view_reports')) $table->boolean('can_view_reports')->default(false);
            });
        }

        if (Schema::hasTable('agents')) {
            Schema::table('agents', function (Blueprint $table) {
                if (!Schema::hasColumn('agents', 'active')) $table->boolean('active')->default(true);
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('invoices', 'invoice_status')) $table->string('invoice_status')->default('new')->index();
                if (!Schema::hasColumn('invoices', 'tax_amount')) $table->decimal('tax_amount', 12, 2)->default(0);
                if (!Schema::hasColumn('invoices', 'shipping_amount')) $table->decimal('shipping_amount', 12, 2)->default(0);
                if (!Schema::hasColumn('invoices', 'eligible_amount')) $table->decimal('eligible_amount', 12, 2)->default(0);
                if (!Schema::hasColumn('invoices', 'approved_by')) $table->foreignId('approved_by')->nullable();
                if (!Schema::hasColumn('invoices', 'approved_at')) $table->timestamp('approved_at')->nullable();
                if (!Schema::hasColumn('invoices', 'rejected_by')) $table->foreignId('rejected_by')->nullable();
                if (!Schema::hasColumn('invoices', 'rejected_at')) $table->timestamp('rejected_at')->nullable();
                if (!Schema::hasColumn('invoices', 'reject_reason')) $table->text('reject_reason')->nullable();
                if (!Schema::hasColumn('invoices', 'paid_at')) $table->timestamp('paid_at')->nullable();
            });
        }

        if (!Schema::hasTable('payout_requests')) {
            Schema::create('payout_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agent_id')->index();
                $table->decimal('requested_amount', 12, 2);
                $table->decimal('approved_amount', 12, 2)->nullable();
                $table->string('status')->default('pending')->index();
                $table->text('agent_note')->nullable();
                $table->text('manager_note')->nullable();
                $table->foreignId('handled_by')->nullable();
                $table->timestamp('handled_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('audit_logs', 'before_data')) $table->text('before_data')->nullable();
                if (!Schema::hasColumn('audit_logs', 'after_data')) $table->text('after_data')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
        Schema::dropIfExists('system_settings');
    }
};
