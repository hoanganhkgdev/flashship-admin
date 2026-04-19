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
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn(['weekly_debt_full_amount', 'weekly_debt_part_amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->decimal('weekly_debt_full_amount', 15, 2)->default(0);
            $table->decimal('weekly_debt_part_amount', 15, 2)->default(0);
        });
    }
};
