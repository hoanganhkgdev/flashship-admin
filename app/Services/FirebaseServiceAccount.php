<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shared Firebase credential manager.
 * Both FirebaseRTDBService and FcmHelper delegate token fetching here.
 * Tokens are cached per scope for 50 minutes (Firebase tokens expire after 60 min).
 */
class FirebaseServiceAccount
{
    const SCOPE_DATABASE  = 'https://www.googleapis.com/auth/firebase.database https://www.googleapis.com/auth/userinfo.email';
    const SCOPE_MESSAGING = 'https://www.googleapis.com/auth/firebase.messaging';

    public static function getAccessToken(string $scope): ?string
    {
        return Cache::remember('firebase_token_' . md5($scope), 50 * 60, function () use ($scope) {
            try {
                $path = config('services.firebase.service_account_path');

                if (!file_exists($path)) {
                    Log::error('❌ Firebase: firebase-service-account.json không tồn tại');
                    return null;
                }

                $sa = json_decode(file_get_contents($path), true);
                if (empty($sa['client_email']) || empty($sa['private_key'])) {
                    Log::error('❌ Firebase: service account JSON thiếu trường bắt buộc');
                    return null;
                }

                $now    = time();
                $header = self::base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
                $claims = self::base64url(json_encode([
                    'iss'   => $sa['client_email'],
                    'scope' => $scope,
                    'aud'   => 'https://oauth2.googleapis.com/token',
                    'iat'   => $now,
                    'exp'   => $now + 3600,
                ]));

                $signingInput = "{$header}.{$claims}";
                openssl_sign($signingInput, $signature, openssl_pkey_get_private($sa['private_key']), OPENSSL_ALGO_SHA256);

                $jwt = "{$signingInput}." . self::base64url($signature);

                $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ]);

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                Log::error('❌ Firebase: Không lấy được access token: ' . $response->body());
                return null;
            } catch (\Throwable $e) {
                Log::error('❌ Firebase: getAccessToken exception: ' . $e->getMessage());
                return null;
            }
        });
    }

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
