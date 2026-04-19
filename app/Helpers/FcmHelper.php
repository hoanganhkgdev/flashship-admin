<?php
/**
 * FIREBASE CLOUD MESSAGING (FCM) HELPER
 * 
 * Sử dụng FCM HTTP v1 API với Service Account (OAuth 2.0)
 * 
 * HƯỚNG DẪN CẤU HÌNH:
 * 1. Vào Firebase Console → Project Settings → Service Accounts
 * 2. Click "Generate new private key" → tải file JSON
 * 3. Lưu file vào: storage/app/firebase-service-account.json
 *    (hoặc đường dẫn tùy chỉnh qua FIREBASE_CREDENTIALS_PATH trong .env)
 * 4. Thêm vào .env: FIREBASE_PROJECT_ID=your-project-id
 */

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FcmHelper
{
    /**
     * Gửi notification đến một hoặc nhiều FCM token
     *
     * @param array  $fcmTokens  Danh sách FCM token của tài xế
     * @param string $title      Tiêu đề notification
     * @param string $body       Nội dung notification
     * @param array  $data       Data payload (key-value, all values phải là string)
     */
    /**
     * Gửi notification đến danh sách User (Eloquent models)
     */
    public static function sendToUsers($users, string $title, string $body, array $data = [], ?string $collapseId = null): array
    {
        $tokens = [];
        foreach ($users as $user) {
            if (!empty($user->fcm_token)) {
                $tokens[] = $user->fcm_token;
            }
        }

        return self::sendToMultiple($tokens, $title, $body, $data, $collapseId);
    }

    /**
     * Gửi notification đến một hoặc nhiều FCM token
     */
    public static function sendToMultiple(array $fcmTokens, string $title, string $body, array $data = [], ?string $collapseId = null): array
    {
        $fcmTokens = array_values(array_filter($fcmTokens, fn($t) => !empty(trim((string) $t))));

        if (empty($fcmTokens)) {
            Log::warning("FCM: sendToMultiple bỏ qua vì không có token hợp lệ.");
            return ['success' => 0, 'failure' => 0];
        }

        $results = ['success' => 0, 'failure' => 0];

        foreach ($fcmTokens as $token) {
            $result = self::sendSingle($token, $title, $body, $data, $collapseId);
            $result['success'] ? $results['success']++ : $results['failure']++;
        }

        Log::info("✅ FCM: Đã gửi xong. Success: {$results['success']}, Failure: {$results['failure']} | Title: {$title}");

        return $results;
    }

    /**
     * Gửi notification đến một FCM token
     */
    public static function sendSingle(string $fcmToken, string $title, string $body, array $data = [], ?string $collapseId = null): array
    {
        try {
            $projectId   = (string) config('services.firebase.project_id');
            $accessToken = self::getAccessToken();

            if (empty($projectId) || !$accessToken) {
                Log::error("FCM Error: Thiếu cấu hình FIREBASE_PROJECT_ID hoặc không lấy được token.");
                return ['success' => false, 'error' => 'Config error'];
            }

            $payload = self::buildPayload($fcmToken, $title, $body, $data, $collapseId);

            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type'  => 'application/json',
                ])
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            if ($response->successful()) {
                return ['success' => true, 'message_id' => $response->json('name')];
            }

            $errorBody = $response->json();
            $errorCode = $errorBody['error']['status'] ?? 'UNKNOWN';

            if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'])) {
                Log::warning("FCM: Token không hợp lệ -> xóa khỏi DB: {$fcmToken} | Response Body: " . json_encode($errorBody));
                self::markTokenInvalid($fcmToken);
            } else {
                Log::error("FCM API Error ({$errorCode})", ['body' => $errorBody, 'token' => substr($fcmToken, 0, 15) . '...']);
            }

            return ['success' => false, 'error' => $errorCode];
        } catch (\Exception $e) {
            Log::error("FCM Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cấu trúc payload chuẩn FCM v1 (Android & iOS)
     */
    private static function buildPayload(string $token, string $title, string $body, array $data, ?string $collapseId): array
    {
        // FCM v1 yêu cầu tất cả data values phải là STRING
        $stringData = array_map('strval', $data);

        // Luôn truyền title & body vào data để Flutter background handler có thể hiện thông báo
        $stringData['title'] = $title;
        $stringData['body']  = $body;

        // 'notification' block top-level: FCM dùng để hiện banner trên cả Android và iOS.
        // - Android background/killed: FCM tự hiện, không cần background handler
        // - iOS background/killed: APNS alert hiện ngay lập tức
        // - Foreground: Flutter chặn qua setForegroundNotificationPresentationOptions + onMessage ignore
        $payload = [
            'message' => [
                'token'        => $token,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'data'    => $stringData,
                'android' => [
                    'priority'     => 'HIGH',
                    'notification' => [
                        'sound'      => 'default',
                        'channel_id' => 'order_channel',
                    ],
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority'  => '10',
                        'apns-push-type' => 'alert',
                    ],
                    'payload' => [
                        'aps' => [
                            'sound'             => 'default',
                            'content-available' => 1,
                        ],
                    ],
                ],
            ],
        ];

        if ($collapseId) {
            $payload['message']['android']['collapse_key'] = (string) $collapseId;
            $payload['message']['apns']['headers']['apns-collapse-id'] = (string) $collapseId;
        }

        return $payload;
    }

    /**
     * Lấy OAuth 2.0 Access Token từ Service Account
     * Cache 55 phút (token hết hạn sau 60 phút)
     */
    private static function getAccessToken(): ?string
    {
        return Cache::remember('fcm_access_token', 55 * 60, function () {
            $credentialsPath = storage_path('app/firebase-service-account.json');

            if (!file_exists($credentialsPath)) {
                Log::error("FCM: Không tìm thấy file service account tại: {$credentialsPath}");
                return null;
            }

            $credentials = json_decode(file_get_contents($credentialsPath), true);
            if (empty($credentials)) {
                Log::error("FCM: Không thể đọc service account JSON");
                return null;
            }

            try {
                // Tạo JWT
                $header  = self::base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
                $now     = time();
                $payload = self::base64UrlEncode(json_encode([
                    'iss'   => $credentials['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud'   => 'https://oauth2.googleapis.com/token',
                    'iat'   => $now,
                    'exp'   => $now + 3600,
                ]));

                $signingInput = "{$header}.{$payload}";
                $privateKey   = openssl_pkey_get_private($credentials['private_key']);

                openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
                $jwt = "{$signingInput}." . self::base64UrlEncode($signature);

                // Đổi JWT lấy Access Token
                $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ]);

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                Log::error("FCM: Lấy access token thất bại: " . $response->body());
                return null;
            } catch (\Exception $e) {
                Log::error("FCM: Lỗi tạo JWT: " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Đánh dấu token không hợp lệ → xóa khỏi DB
     */
    private static function markTokenInvalid(string $token): void
    {
        try {
            \App\Models\User::where('fcm_token', $token)->update(['fcm_token' => null]);
            Log::info("FCM: Đã xóa token không hợp lệ khỏi DB");
        } catch (\Exception $e) {
            Log::error("FCM: Lỗi xóa token: " . $e->getMessage());
        }
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
