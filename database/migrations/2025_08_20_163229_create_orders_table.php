<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Người tạo đơn (admin / client)
            $table->unsignedBigInteger('client_id')->nullable();
            $table->foreign('client_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Điểm lấy & giao (chứa cả address, phone, lat, lng, contact_name)
            $table->json('pickup_point')->nullable();
            $table->json('delivery_point')->nullable();

            // Thành phố
            $table->unsignedBigInteger('city_id')->nullable();
            $table->foreign('city_id')
                ->references('id')
                ->on('cities')
                ->onDelete('set null');

            // Tài xế nhận đơn
            $table->unsignedBigInteger('delivery_man_id')->nullable();
            $table->foreign('delivery_man_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Trạng thái
            $table->enum('status', ['pending', 'assigned', 'delivered', 'cancelled'])
                ->default('pending');

            // Phí ship
            $table->double('shipping_fee')->default(0);

            // Ngày giờ thực tế
            $table->dateTime('pickup_datetime')->nullable();
            $table->dateTime('delivery_datetime')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
