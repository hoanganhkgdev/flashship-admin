<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\PricingRule;
use Illuminate\Database\Seeder;

class PricingRulesSeeder extends Seeder
{
    public function run(): void
    {
        $canTho = City::firstOrCreate(['name' => 'Cần Thơ'], ['code' => 'CT']);

        // 1. Giao hàng shop (Bậc thang Km)
        $deliveryPrices = [
            [0, 3, 13000],
            [3.1, 4, 15000],
            [4.1, 5, 18000],
            [5.1, 6, 20000],
            [6.1, 7, 23000],
            [7.1, 8, 25000],
            [8.1, 9, 28000],
            [9.1, 10, 30000],
        ];

        foreach ($deliveryPrices as $p) {
            PricingRule::create([
                'city_id' => $canTho->id,
                'service_type' => 'delivery',
                'min_distance' => $p[0],
                'max_distance' => $p[1],
                'base_price' => $p[2],
            ]);
        }

        // 2. Xe ôm
        PricingRule::create(['city_id' => $canTho->id, 'service_type' => 'bike', 'min_distance' => 0, 'max_distance' => 2, 'base_price' => 15000]);
        PricingRule::create(['city_id' => $canTho->id, 'service_type' => 'bike', 'min_distance' => 2, 'max_distance' => 15, 'price_per_km' => 5000]);
        PricingRule::create(['city_id' => $canTho->id, 'service_type' => 'bike', 'min_distance' => 15, 'price_per_km' => 6000]);

        // 3. Nạp/Rút
        $topupPrices = [
            [0, 5000000, 20000],
            [5000000, 10000000, 30000],
            [10000000, 15000000, 40000],
            [15000000, 20000000, 50000],
            [20000000, 25000000, 60000],
            [25000000, null, 61000], // +1k/1tr sẽ xử lý trong Service
        ];
        foreach ($topupPrices as $p) {
            PricingRule::create([
                'city_id' => $canTho->id,
                'service_type' => 'topup',
                'min_amount' => $p[0],
                'max_amount' => $p[1],
                'base_price' => $p[2],
            ]);
        }

        // 4. Lái hộ xe máy
        PricingRule::create([
            'city_id' => $canTho->id,
            'service_type' => 'motor',
            'min_distance' => 0,
            'price_per_km' => 6000,
            'extra_fee' => 60000,
        ]);

        // 5. Lái hộ Oto
        PricingRule::create([
            'city_id' => $canTho->id,
            'service_type' => 'car',
            'min_distance' => 0,
            'price_per_km' => 8000,
            'extra_fee' => 80000,
        ]);
    }
}
