<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
    $table->enum('shift_type', ['morning', 'evening', 'night', 'full'])
          ->default('full')
          ->comment('morning: 6h-17h, evening: 15h-24h, night: 0h-2h, full: 24h')
          ->change();
});
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('shift_type');
        });
    }

};
