<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Setting::updateOrCreate(
            ['key' => 'app_version'],
            ['value' => '1.0.5']
        );

        Setting::updateOrCreate(
            ['key' => 'force_update'],
            ['value' => 'true']
        );

        Setting::updateOrCreate(
            ['key' => 'store_url_android'],
            ['value' => 'https://play.google.com/store/apps/details?id=com.example.app']
        );

        Setting::updateOrCreate(
            ['key' => 'store_url_ios'],
            ['value' => 'https://apps.apple.com/app/idYOUR_APP_ID']
        );
    }
}
