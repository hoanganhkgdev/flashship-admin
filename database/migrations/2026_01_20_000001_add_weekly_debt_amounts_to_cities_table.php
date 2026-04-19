<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->unsignedBigInteger('weekly_debt_full_amount')
                ->nullable()
                ->after('status')
                ->comment('Mức công nợ tuần cho tài xế ca full (VND)');

            $table->unsignedBigInteger('weekly_debt_part_amount')
                ->nullable()
                ->after('weekly_debt_full_amount')
                ->comment('Mức công nợ tuần cho tài xế ca không full (VND)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropColumn(['weekly_debt_full_amount', 'weekly_debt_part_amount']);
        });
    }
};

