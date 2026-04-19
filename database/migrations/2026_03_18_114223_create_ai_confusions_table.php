<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_confusions', function (Blueprint $table) {
            $table->id();
            $table->string('sender_id');                    // Zalo user ID
            $table->string('platform')->default('zalo');
            $table->text('confused_message');               // Câu gốc AI không hiểu
            $table->text('clarified_message')->nullable();  // Câu khách giải thích lại
            $table->string('resolved_action')->nullable();  // Tool AI gọi khi hiểu (create_order, etc.)
            $table->json('resolved_args')->nullable();      // Args của tool (service_type, pickup, etc.)
            $table->boolean('is_learned')->default(false);  // Đã lưu vào AiKnowledge chưa
            $table->unsignedBigInteger('ai_knowledge_id')->nullable(); // Link đến AiKnowledge được tạo
            $table->integer('city_id')->nullable();
            $table->timestamps();

            $table->index(['sender_id', 'is_learned']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_confusions');
    }
};
