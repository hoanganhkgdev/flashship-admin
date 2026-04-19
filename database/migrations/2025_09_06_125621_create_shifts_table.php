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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // mã ca: morning, evening, night
            $table->string('name');           // tên ca: Ca sáng, Ca tối
            $table->time('start_time');       // giờ bắt đầu
            $table->time('end_time');         // giờ kết thúc
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->constrained('shifts');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });
        Schema::dropIfExists('shifts');
    }

};
