<?php

namespace App\Services;

use App\Models\City;
use App\Models\PricingRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PricingService
{
    /**
     * Tính toán phí vận chuyển
     * @param string $serviceType 'delivery', 'bike', 'motor', 'car', 'topup'
     * @param int $cityId
     * @param float $distance Quãng đường (Km)
     * @param float $amount Số tiền (Đối với nạp/rút tiền)
     */
    public function calculate(string $serviceType, int $cityId, float $distance = 0, float $amount = 0): float
    {
        // 1. Xử lý nạp rút tiền (Dựa trên số tiền)
        if ($serviceType === 'topup') {
            return $this->roundPrice($this->calculateTopupFee($cityId, $amount));
        }

        // 2. Giao hàng & Mua hộ: dùng chung bảng giá delivery (ship nội ô theo km)
        if (in_array($serviceType, ['delivery', 'shopping'])) {
            return $this->roundPrice($this->calculateDeliveryFee($cityId, $distance));
        }

        // 3. Xe ôm & Lái hộ (bike, motor, car)
        return $this->roundPrice($this->calculateDistanceBasedFee($serviceType, $cityId, $distance));
    }

    /**
     * Làm tròn giá đến 1,000đ gần nhất
     * >= 600 → làm tròn lên: 32,600 → 33,000
     * <= 500 → làm tròn xuống: 32,500 → 32,000
     */
    protected function roundPrice(float $price): float
    {
        return round($price / 1000, 0, PHP_ROUND_HALF_DOWN) * 1000;
    }

    /**
     * Tính phí giao hàng nội ô theo bảng giá bặc thang
     */
    protected function calculateDeliveryFee(int $cityId, float $distance): float
    {
        // Tìm rule khớp với khoảng cách (min <= distance <= max)
        $rule = PricingRule::where('city_id', $cityId)
            ->where('service_type', 'delivery')
            ->where('min_distance', '<=', $distance)
            ->where(function ($query) use ($distance) {
                $query->where('max_distance', '>=', $distance)
                      ->orWhereNull('max_distance');
            })
            ->first();

        // Không tìm thấy rule khớp → đơn vượt mốc cuối cùng (ví dụ > 10km)
        // → Dùng rule cuối cùng (có price_per_km) làm fallback
        if (!$rule) {
            $rule = PricingRule::where('city_id', $cityId)
                ->where('service_type', 'delivery')
                ->whereNotNull('max_distance')
                ->where('price_per_km', '>', 0)
                ->orderByDesc('max_distance')
                ->first();

            if (!$rule) {
                return $distance * 5000; // Fallback tuyệt đối
            }

            // Tính: giá mốc cuối + (km vượt mốc cuối × giá/km)
            // VD: 11km → 25,000 + (11 - 10) × 4,000 = 29,000đ
            $excessKm = max(0, $distance - $rule->max_distance);
            return $rule->base_price + ($excessKm * $rule->price_per_km);
        }

        // Trong range: giá cố định (price_per_km = 0)
        // VD: 3km → 10,000đ; 9km → 25,000đ
        if ($rule->price_per_km == 0) {
            return $rule->base_price;
        }

        // Có price_per_km nhưng vẫn trong range (max_distance chưa vượt)
        // → Cũng tính km vượt max nếu null, hoặc flat nếu max không null
        $excessKm = $rule->max_distance
            ? max(0, $distance - $rule->max_distance)
            : max(0, $distance - $rule->min_distance);
        return $rule->base_price + ($excessKm * $rule->price_per_km);
    }


    /**
     * Tính phí Xe ôm & Lái hộ
     */
    protected function calculateDistanceBasedFee(string $serviceType, int $cityId, float $distance): float
    {
        $rules = PricingRule::where('city_id', $cityId)
            ->where('service_type', $serviceType)
            ->orderBy('min_distance', 'asc')
            ->get();

        if ($rules->isEmpty())
            return 0;

        $totalFee = 0;

        // Phí lái hộ cố định (Extra Fee)
        $extraFee = $rules->first()->extra_fee ?? 0;

        // Tính lũy tiến hoặc theo mốc tùy logic
        // Hiện tại tính theo mốc đơn giản nhất cho dễ quản lý
        foreach ($rules as $rule) {
            if ($distance >= $rule->min_distance) {
                $appliedDistance = $rule->max_distance
                    ? min($distance, $rule->max_distance) - $rule->min_distance
                    : $distance - $rule->min_distance;

                if ($appliedDistance > 0) {
                    $totalFee += $rule->base_price + ($appliedDistance * $rule->price_per_km);
                }
            }
        }

        return $totalFee + $extraFee;
    }

    /**
     * Tính phí Nạp/Rút tiền
     */
    protected function calculateTopupFee(int $cityId, float $amount): float
    {
        $rule = PricingRule::where('city_id', $cityId)
            ->where('service_type', 'topup')
            ->where('min_amount', '<=', $amount)
            ->where(function ($query) use ($amount) {
                $query->where('max_amount', '>=', $amount)
                    ->orWhereNull('max_amount');
            })
            ->first();

        if ($rule) {
            $fee = $rule->base_price;
            // Xử lý "Mỗi 1tr thêm 1k" cho đơn trên 25tr
            if ($rule->max_amount == null && $amount > $rule->min_amount) {
                $extraMillions = floor(($amount - $rule->min_amount) / 1000000);
                $fee += $extraMillions * 1000;
            }
            return $fee;
        }

        return 20000; // Mặc định tối thiểu 20k
    }

    /**
     * Gọi Google Maps lấy quãng đường thực tế (Có Cache)
     */
    public function getDistance(string $origin, string $destination): float
    {
        $apiKey = config('services.google.maps_key');
        
        // 🚀 CACHE: Lưu lại kết quả tìm kiếm để tránh gọi API Google Maps liên tục cho cùng 1 lộ trình
        $cacheKey = 'distance_' . md5($origin . $destination);
        
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function() use ($origin, $destination, $apiKey) {
            Log::info("PricingService: Fetching distance (Directions API) for '{$origin}' to '{$destination}'");

            try {
                // Sử dụng Directions API thay vì Distance Matrix API
                $response = Http::timeout(10)->get("https://maps.googleapis.com/maps/api/directions/json", [
                    'origin' => $origin,
                    'destination' => $destination,
                    'mode' => 'driving',
                    'key' => $apiKey,
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (isset($data['status']) && $data['status'] !== 'OK') {
                        Log::error("Google Directions API Status: " . $data['status'] . " - " . ($data['error_message'] ?? 'No message'));
                        return 0.0;
                    }

                    if (isset($data['routes'][0]['legs'][0]['distance']['value'])) {
                        $distanceInMeters = $data['routes'][0]['legs'][0]['distance']['value'];
                        return round($distanceInMeters / 1000, 1);
                    } else {
                        Log::warning("Google Directions API: No route found.");
                    }
                } else {
                    Log::error("Google Directions HTTP Error: " . $response->status() . " - " . $response->body());
                }
            } catch (\Exception $e) {
                Log::error("PricingService Distance Exception: " . $e->getMessage());
            }

            return 0.0;
        });
    }
}
