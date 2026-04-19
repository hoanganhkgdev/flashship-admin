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
        // Sử dụng raw query để modify enum trong MySQL
        \DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'assigned', 'cancelled', 'completed', 'delivered_pending', 'draft') DEFAULT 'draft'");
    }

    public function down(): void
    {
        \DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'assigned', 'cancelled', 'completed', 'delivered_pending') DEFAULT 'pending'");
    }
};
