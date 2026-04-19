<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('support_configs', function (Blueprint $col) {
            $col->id();
            $col->string('title');
            $col->string('subtitle')->nullable();
            $col->string('icon')->default('help'); // chat, phone, warning, tech, policy, help
            $col->string('type')->default('link'); // call, zalo, link, screen
            $col->string('value'); // Phone number, URL, or Screen route
            $col->string('color')->nullable(); // Hex color or name
            $col->integer('priority')->default(0);
            $col->boolean('is_active')->default(true);
            $col->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('support_configs');
    }
};
