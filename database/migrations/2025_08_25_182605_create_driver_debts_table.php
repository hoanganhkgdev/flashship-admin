<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('driver_debts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_id');
            $table->date('week_start');
            $table->date('week_end');
            $table->decimal('amount_due', 10, 0);
            $table->decimal('amount_paid', 10, 0)->default(0);
            $table->enum('status', ['pending', 'paid', 'overdue'])->default('pending');
            $table->timestamps();

            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_debts');
    }
};
