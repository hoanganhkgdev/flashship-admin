<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_debt_id');
            $table->bigInteger('order_code')->unique();
            $table->integer('amount');
            $table->string('status')->default('pending'); // pending | paid | failed
            $table->timestamps();

            $table->foreign('driver_debt_id')->references('id')->on('driver_debts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
