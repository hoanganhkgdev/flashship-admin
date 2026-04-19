<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('sender_id')->index(); // ID của người dùng Zalo/FB
            $table->string('platform')->default('zalo');
            $table->json('context_data')->nullable(); // Lưu trữ thông tin đơn hàng đang thu thập
            $table->timestamp('last_interacted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};
