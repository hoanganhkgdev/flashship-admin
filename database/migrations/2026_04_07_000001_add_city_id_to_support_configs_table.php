<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('support_configs', function (Blueprint $table) {
            $table->unsignedBigInteger('city_id')->nullable()->after('id');
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('support_configs', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropColumn('city_id');
        });
    }
};
