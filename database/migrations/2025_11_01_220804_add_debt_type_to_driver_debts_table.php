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
        Schema::table('driver_debts', function (Blueprint $table) {
            $table->enum('debt_type', ['weekly', 'commission'])->default('weekly')->after('driver_id');
            $table->date('date')->nullable()->after('week_end'); // cho công nợ chiết khấu ngày
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_debts', function (Blueprint $table) {
            //
        });
    }
};
