<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('bank_code', 20)->nullable(); // Mã ngân hàng (nếu cần)
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_owner')->nullable();

            $table->timestamps();
        });

        // Xoá cột cũ trong bảng users
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bank_code', 'bank_name', 'bank_account', 'bank_owner']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('bank_code', 20)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_owner')->nullable();
        });

        Schema::dropIfExists('banks');
    }
};
