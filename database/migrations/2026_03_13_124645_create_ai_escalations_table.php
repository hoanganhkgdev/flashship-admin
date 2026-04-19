<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_escalations', function (Blueprint $table) {
            $table->id();
            $table->string('sender_id');               // Zalo ID / platform ID của khách
            $table->string('platform')->default('zalo');
            $table->nullableMorphs('source');          // Shop hoặc Order nếu có liên quan
            $table->string('reason');                  // Lý do leo thang
            $table->enum('urgency', ['low', 'medium', 'high'])->default('medium');
            $table->text('conversation_summary');      // Tóm tắt hội thoại
            $table->enum('status', ['open', 'in_progress', 'resolved'])->default('open');
            $table->unsignedBigInteger('assigned_to')->nullable(); // Admin/dispatcher nhận xử lý
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->index(['sender_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_escalations');
    }
};
