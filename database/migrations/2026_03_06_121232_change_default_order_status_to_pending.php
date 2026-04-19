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
        // Trả lại mặc định là 'pending' cho các đơn lên thủ công bởi Admin/Dispatcher
        \DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'assigned', 'cancelled', 'completed', 'delivered_pending', 'draft') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'assigned', 'cancelled', 'completed', 'delivered_pending', 'draft') DEFAULT 'draft'");
    }
};
