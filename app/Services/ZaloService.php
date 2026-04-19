<?php

namespace App\Services;

use App\Models\ZaloAccount; // Added this line
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZaloService
{
    protected ?string $accessToken;
    protected ?ZaloAccount $account;

    public function __construct(?ZaloAccount $account = null)
    {
        $this->account = $account;
        if ($account) {
            $this->accessToken = $account->access_token;
        } else {
            $this->accessToken = (string) config('services.zalo.access_token');
        }
    }

    /**
     * Gửi tin nhắn văn bản tới người dùng Zalo
     */
    public function sendTextMessage(string $userId, string $text): bool
    {
        if (empty($this->accessToken)) {
            Log::error('ZaloService: Missing Access Token');
            return false;
        }

        // Ưu tiên gửi qua CS (Customer Service) trước
        $result = $this->callZaloApi('cs', $userId, $text);

        // Nếu lỗi -233 (Message type not support) hoặc -230 (User not follow/interact)
        // Ta thử gửi qua Transaction (Dành cho OA đã lên gói)
        if (!$result || (isset($result['error']) && in_array($result['error'], [-233, -230]))) {
            Log::info("ZaloService: Lỗi {$result['error']}, thử gửi lại qua kênh Transaction...");
            $result = $this->callZaloApi('transaction', $userId, $text);
        }

        if (isset($result['error']) && $result['error'] != 0) {
            // Xử lý token hết hạn
            if (($result['error'] == -216 || $result['error'] == 401) && $this->account) {
                if ($this->refreshAccessToken($this->account)) {
                    return $this->sendTextMessage($userId, $text);
                }
            }
            Log::error('Zalo API Final Error: ' . json_encode($result));
            return false;
        }

        return isset($result['error']) && $result['error'] == 0;
    }

    public function sendListMessage(string $userId, string $title, array $options): bool
    {
        if (empty($this->accessToken)) {
            Log::error('ZaloService: Missing Access Token');
            return false;
        }

        $elements = [];
        foreach ($options as $key => $label) {
            $elements[] = [
                'title' => $label,
                'subtitle' => "Bấm để chọn dịch vụ",
                'action' => [
                    'type' => 'oa.query.show',
                    'payload' => (string)($key + 1)
                ]
            ];
            // Zalo List limit is typically 5, so we use the first 5 and then maybe a general button
            if (count($elements) >= 5) break; 
        }

        $payload = [
            'recipient' => ['user_id' => $userId],
            'message' => [
                'attachment' => [
                    'type' => 'template',
                    'payload' => [
                        'template_type' => 'list',
                        'elements' => $elements
                    ]
                ]
            ]
        ];

        return $this->callRawApi('cs', $payload, $userId);
    }

    private function callRawApi(string $type, array $payload, string $userId): bool
    {
        try {
            $endpoint = "https://openapi.zalo.me/v3.0/oa/message/{$type}";
            $response = Http::withHeaders(['access_token' => $this->accessToken])->post($endpoint, $payload);
            $result = $response->json();

            if (isset($result['error']) && $result['error'] != 0) {
                // Retry with Transaction if needed (similar to sendTextMessage)
                if (in_array($result['error'], [-233, -230]) && $type === 'cs') {
                    return $this->callRawApi('transaction', $payload, $userId);
                }
                Log::error("Zalo API Raw Error [{$type}]: " . json_encode($result));
                return false;
            }
            return true;
        } catch (\Exception $e) {
            Log::error("Zalo API Raw Exception: " . $e->getMessage());
            return false;
        }
    }

    private function callZaloApi(string $type, string $userId, string $text): ?array
    {
        try {
            $endpoint = "https://openapi.zalo.me/v3.0/oa/message/{$type}";
            $response = Http::withHeaders([
                'access_token' => $this->accessToken,
            ])->post($endpoint, [
                        'recipient' => ['user_id' => $userId],
                        'message' => ['text' => $text],
                    ]);

            if ($response->successful()) {
                return $response->json();
            }
            Log::error("Zalo API HTTP Error [{$type}]: " . $response->body());
        } catch (\Exception $e) {
            Log::error("Zalo API Exception [{$type}]: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Làm mới Access Token bằng Refresh Token
     */
    public function refreshAccessToken(ZaloAccount $account): bool
    {
        $appId = config('services.zalo.app_id');
        $secretKey = config('services.zalo.app_secret');

        if (empty($account->refresh_token)) {
            Log::error("ZaloService: Refresh token missing for OA {$account->oa_id}");
            return false;
        }

        try {
            $response = Http::asForm()->withHeaders([
                'secret_key' => $secretKey,
            ])->post('https://oauth.zaloapp.com/v4/oa/access_token', [
                        'refresh_token' => $account->refresh_token,
                        'app_id' => $appId,
                        'grant_type' => 'refresh_token',
                    ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['access_token'])) {
                    $account->update([
                        'access_token' => $data['access_token'],
                        'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
                        'token_expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 90000)),
                    ]);
                    $this->accessToken = $data['access_token'];
                    Log::info("ZaloService: Token refreshed successfully for OA {$account->oa_id}");
                    return true;
                }
            }
            Log::error('Zalo Refresh Token Error: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('ZaloService Refresh Exception: ' . $e->getMessage());
        }

        return false;
    }
}
