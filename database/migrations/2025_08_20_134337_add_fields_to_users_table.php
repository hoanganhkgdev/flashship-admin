<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Thông tin bổ sung
            $table->string('username')->unique()->nullable()->after('email');
            $table->string('phone', 20)->nullable()->after('username');
            $table->string('address')->nullable()->after('phone');

            // Vị trí
            $table->unsignedBigInteger('city_id')->nullable()->after('address');
            $table->decimal('latitude', 10, 7)->nullable()->after('city_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');

            // Quản lý user
            $table->enum('user_type', ['admin', 'subadmin', 'driver'])->default('driver')->after('longitude');
            $table->boolean('status')->default(true)->after('user_type');
            $table->uuid('uid')->unique()->nullable()->after('status');
            $table->string('profile_photo_path', 2048)->nullable()->after('uid');
            $table->timestamp('last_login_at')->nullable()->after('profile_photo_path');

            // Khác
            $table->string('player_id')->nullable()->after('last_login_at'); // OneSignal
            $table->timestamp('last_notification_seen')->nullable()->after('player_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'phone',
                'address',
                'city_id',
                'latitude',
                'longitude',
                'user_type',
                'status',
                'uid',
                'profile_photo_path',
                'last_login_at',
                'player_id',
                'last_notification_seen',
            ]);
        });
    }
};

