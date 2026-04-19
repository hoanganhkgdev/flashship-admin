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
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained('cities')->onDelete('cascade');
            $table->string('service_type'); // delivery, bike, motor, car, topup
            $table->double('min_distance')->default(0);
            $table->double('max_distance')->nullable();
            $table->double('base_price')->default(0);
            $table->double('price_per_km')->default(0);
            $table->double('extra_fee')->default(0);
            $table->double('min_amount')->nullable(); // For topup
            $table->double('max_amount')->nullable(); // For topup
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
