<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // orders.completed_at — dùng trong CalculateDailyCommission: whereBetween('completed_at', ...)
        // orders.[delivery_man_id, status, completed_at] — composite cho commission query
        Schema::table('orders', function (Blueprint $table) {
            $table->index('completed_at');
            $table->index(['delivery_man_id', 'status', 'completed_at']);
        });

        // driver_debts — chưa có index nào ngoài FK driver_id
        Schema::table('driver_debts', function (Blueprint $table) {
            $table->index('status');                          // WHERE status IN ('pending','overdue')
            $table->index('debt_type');                       // WHERE debt_type = 'commission'/'weekly'
            $table->index('date');                            // WHERE date = ? (commission debts)
            $table->index('week_end');                        // WHERE week_end <= deadline (MarkOverdueDebts)
            $table->index(['driver_id', 'debt_type', 'date']); // unique check trong CalculateDailyCommission
        });

        // withdraw_requests.status — dùng trong getNavigationBadge COUNT mỗi page load
        Schema::table('withdraw_requests', function (Blueprint $table) {
            $table->index('status');
        });

        // driver_wallet_transactions.reference — dùng trong DriverWalletService dedup check
        Schema::table('driver_wallet_transactions', function (Blueprint $table) {
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['completed_at']);
            $table->dropIndex(['delivery_man_id', 'status', 'completed_at']);
        });

        Schema::table('driver_debts', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['debt_type']);
            $table->dropIndex(['date']);
            $table->dropIndex(['week_end']);
            $table->dropIndex(['driver_id', 'debt_type', 'date']);
        });

        Schema::table('withdraw_requests', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('driver_wallet_transactions', function (Blueprint $table) {
            $table->dropIndex(['reference']);
        });
    }
};
