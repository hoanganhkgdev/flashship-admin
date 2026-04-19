<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tên tỉnh/thành phố
            $table->string('code')->nullable(); // Ví dụ: HCM, HN
            $table->tinyInteger('status')->default(1); // 1 = hoạt động, 0 = tạm ngưng
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cities');
    }
};
