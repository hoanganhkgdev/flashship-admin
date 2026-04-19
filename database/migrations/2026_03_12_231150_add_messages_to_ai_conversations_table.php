<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            // Lưu toàn bộ lịch sử hội thoại dạng [{role: user|model, content: "..."}]
            $table->json('messages')->nullable()->after('context_data');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('messages');
        });
    }
};
