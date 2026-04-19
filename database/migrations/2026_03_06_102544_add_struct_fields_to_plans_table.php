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
        Schema::table('plans', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('weekly')->comment('weekly, commission');
            $table->decimal('weekly_fee_full', 15, 2)->nullable();
            $table->decimal('weekly_fee_part', 15, 2)->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropColumn(['city_id', 'type', 'weekly_fee_full', 'weekly_fee_part', 'commission_rate']);
        });
    }
};
