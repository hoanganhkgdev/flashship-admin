<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Index cho bảng orders — dùng nhiều trong query lọc đơn
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status');                          // WHERE status = 'pending'
            $table->index('created_at');                     // ORDER BY created_at, thống kê theo ngày
            $table->index(['status', 'city_id']);            // WHERE status = 'pending' AND city_id = ?
            $table->index(['status', 'delivery_man_id']);    // WHERE status = 'assigned' AND delivery_man_id = ?
        });

        // Index cho bảng users — dùng nhiều khi tìm tài xế online
        Schema::table('users', function (Blueprint $table) {
            $table->index('city_id');                        // WHERE city_id = ?
            $table->index('is_online');                      // WHERE is_online = true
            $table->index(['city_id', 'is_online', 'status']); // Lọc tài xế đang online theo vùng
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status', 'city_id']);
            $table->dropIndex(['status', 'delivery_man_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['city_id']);
            $table->dropIndex(['is_online']);
            $table->dropIndex(['city_id', 'is_online', 'status']);
        });
    }
};
