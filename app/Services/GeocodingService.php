<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    protected static function apiKey(): string
    {
        return (string) (config('services.google.maps_key') ?? '');
    }

    /**
     * Places Autocomplete — trả về danh sách gợi ý địa chỉ Việt Nam.
     * Return: ['Địa chỉ đầy đủ' => 'Địa chỉ đầy đủ', ...]
     */
    public static function autocomplete(string $input): array
    {
        if (empty($input)) return [];

        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
                'input'      => $input,
                'key'        => static::apiKey(),
                'language'   => 'vi',
                'components' => 'country:vn',
            ]);

            return collect($response->json('predictions') ?? [])
                ->mapWithKeys(fn($p) => [$p['description'] => $p['description']])
                ->toArray();
        } catch (\Exception $e) {
            Log::error('GeocodingService::autocomplete: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Geocode một địa chỉ thành toạ độ {lat, lng}.
     * Return: ['lat' => float, 'lng' => float] hoặc null nếu không tìm thấy.
     */
    public static function geocodeAddress(string $address): ?array
    {
        if (empty($address)) return null;

        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key'     => static::apiKey(),
            ]);

            if ($response->json('status') === 'OK') {
                return $response->json('results.0.geometry.location');
            }

            return null;
        } catch (\Exception $e) {
            Log::error('GeocodingService::geocodeAddress: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Geocode từ ghi chú đơn hàng (extract địa chỉ trước, rồi geocode).
     */
    public static function geocodeFromNote(string $note, string $cityName = ''): ?array
    {
        $address = self::extractAddress($note);
        if (empty($address)) return null;

        return self::geocodeAddress($address . ($cityName ? ", $cityName" : ''));
    }

    private static function extractAddress(string $note): string
    {
        if (preg_match('/(?:ĐC|Địa chỉ|Đ\/c)[:\-\s]+([^,;\n\t]+(?:,[^,;\n\t]+)*)/i', $note, $matches)) {
            return trim($matches[1]);
        }

        $lines = explode("\n", $note);
        return count($lines) > 0 ? trim($lines[0]) : $note;
    }
}
