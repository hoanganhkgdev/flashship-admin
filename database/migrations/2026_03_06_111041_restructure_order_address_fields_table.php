<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('pickup_address')->nullable()->after('order_note');
            $table->string('pickup_phone')->nullable()->after('pickup_address');
            $table->string('sender_name')->nullable()->after('pickup_phone');

            $table->string('delivery_address')->nullable()->after('sender_name');
            $table->string('delivery_phone')->nullable()->after('delivery_address');
            $table->string('receiver_name')->nullable()->after('delivery_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_address',
                'pickup_phone',
                'sender_name',
                'delivery_address',
                'delivery_phone',
                'receiver_name'
            ]);
        });
    }
};
