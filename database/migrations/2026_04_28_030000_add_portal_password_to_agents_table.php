<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agents') && !Schema::hasColumn('agents', 'portal_password')) {
            Schema::table('agents', function (Blueprint $table) {
                $table->string('portal_password')->nullable()->after('referral_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('agents') && Schema::hasColumn('agents', 'portal_password')) {
            Schema::table('agents', function (Blueprint $table) {
                $table->dropColumn('portal_password');
            });
        }
    }
};
