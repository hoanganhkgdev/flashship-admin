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
            // Drop foreign key first
            $table->dropForeign(['client_id']);

            // Drop columns
            $table->dropColumn([
                'client_id',
                'pickup_point',
                'delivery_point',
                'topup_amount',
                'pickup_datetime',
                'delivery_datetime'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable();
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');

            $table->json('pickup_point')->nullable();
            $table->json('delivery_point')->nullable();
            $table->double('topup_amount')->default(0);
            $table->dateTime('pickup_datetime')->nullable();
            $table->dateTime('delivery_datetime')->nullable();
        });
    }
};
