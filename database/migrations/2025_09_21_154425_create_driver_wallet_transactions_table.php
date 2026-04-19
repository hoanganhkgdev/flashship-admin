<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('driver_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('driver_wallets')->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit']); // cộng tiền / trừ tiền
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();
            $table->string('reference')->nullable(); // order_id, bonus...
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('driver_wallet_transactions');
    }
};

