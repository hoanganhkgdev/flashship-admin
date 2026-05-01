<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_histories', function (Blueprint $table) {
            $this->dropForeignIfExists('order_histories', 'order_histories_user_id_foreign');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('order_histories', function (Blueprint $table) {
            $this->dropForeignIfExists('order_histories', 'order_histories_user_id_foreign');
            $table->foreign('user_id')
                ->references('id')
                ->on('users');
        });
    }

    private function dropForeignIfExists(string $tableName, string $foreignKey): void
    {
        $foreignKeys = collect(Schema::getForeignKeys($tableName))
            ->pluck('name')
            ->toArray();

        if (in_array($foreignKey, $foreignKeys)) {
            Schema::table($tableName, function (Blueprint $table) use ($foreignKey) {
                $table->dropForeign($foreignKey);
            });
        }
    }
};
