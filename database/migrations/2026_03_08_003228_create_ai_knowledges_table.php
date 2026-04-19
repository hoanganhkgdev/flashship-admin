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
        Schema::create('ai_knowledges', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('type'); // example, shortcut, rule
            $table->text('input_text');
            $table->json('output_data')->nullable(); // JSON for examples/rules, or string for shortcuts
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_knowledges');
    }
};
