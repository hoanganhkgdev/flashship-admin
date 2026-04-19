<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // order_histories.user_id đã được fix (SET NULL) từ lần chạy trước

        // Xóa orphaned records trước khi add foreign key
        DB::statement('DELETE FROM user_login_logs WHERE user_id NOT IN (SELECT id FROM users)');
        DB::statement('DELETE FROM driver_commission_refs WHERE driver_id NOT IN (SELECT id FROM users)');

        // Fix: user_login_logs.user_id không có foreign key → orphaned data khi xóa user
        Schema::table('user_login_logs', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        // Fix: driver_commission_refs.driver_id không có foreign key → orphaned data khi xóa tài xế
        Schema::table('driver_commission_refs', function (Blueprint $table) {
            $table->foreign('driver_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('user_login_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('driver_commission_refs', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
        });
    }
};
