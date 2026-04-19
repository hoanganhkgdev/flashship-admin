<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('driver_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('balance', 15, 2)->default(0); // số dư
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('driver_wallets');
    }
};
