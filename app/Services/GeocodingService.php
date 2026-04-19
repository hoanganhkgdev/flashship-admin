<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    protected static function getApiKey() {
        return config('services.google.api_key');
    }

    public static function geocodeFromNote(string $note, string $cityName = '') {
        try {
            $address = self::extractAddress($note);
            if (empty($address)) return null;

            $fullAddress = $address . ($cityName ? ", $cityName" : "");

            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $fullAddress,
                'key' => self::getApiKey(),
                'language' => 'vi'
            ]);

            if ($response->successful() && $response->json('status') === 'OK') {
                return $response->json('results.0.geometry.location');
            }
            return null;
        } catch (\Exception $e) {
            Log::error("❌ Geocoding Error: " . $e->getMessage());
            return null;
        }
    }

    private static function extractAddress(string $note) {
        if (preg_match('/(?:ĐC|Địa chỉ|Đ\/c)[:\-\s]+([^,;\n\t]+(?:,[^,;\n\t]+)*)/i', $note, $matches)) {
            return trim($matches[1]);
        }
        $lines = explode("\n", $note);
        return count($lines) > 0 ? trim($lines[0]) : $note;
    }
}
