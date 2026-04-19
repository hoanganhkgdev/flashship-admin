<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Thêm completed_at vào orders
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('created_at');
            }
        });

        // 2) Thêm ref_id vào driver_debts
        Schema::table('driver_debts', function (Blueprint $table) {
            if (!Schema::hasColumn('driver_debts', 'ref_id')) {
                $table->string('ref_id')->nullable()->after('debt_type');
                $table->index('ref_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });

        Schema::table('driver_debts', function (Blueprint $table) {
            if (Schema::hasColumn('driver_debts', 'ref_id')) {
                $table->dropColumn('ref_id');
            }
        });
    }
};
