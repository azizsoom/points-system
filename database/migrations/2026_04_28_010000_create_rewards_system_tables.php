<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('staff')->after('password');
                $table->foreignId('branch_id')->nullable()->after('role');
                $table->boolean('active')->default(true)->after('branch_id');
            });
        }

        if (!Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable()->unique();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('agents')) {
            Schema::create('agents', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone')->nullable();
                $table->string('referral_code')->unique();
                $table->string('city')->nullable();
                $table->decimal('commission_rate', 6, 3)->default(0.500);
                $table->string('status')->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_number')->unique();
                $table->foreignId('agent_id')->constrained('agents');
                $table->foreignId('branch_id')->nullable()->constrained('branches');
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->decimal('amount', 12, 2);
                $table->decimal('discount', 12, 2)->default(0);
                $table->decimal('net_amount', 12, 2);
                $table->decimal('reward_rate', 6, 3);
                $table->decimal('reward_amount', 12, 2);
                $table->string('status')->default('active');
                $table->string('attachment_path')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('payouts')) {
            Schema::create('payouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agent_id')->constrained('agents');
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->decimal('amount', 12, 2);
                $table->string('method')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('agents');
        Schema::dropIfExists('branches');
    }
};
