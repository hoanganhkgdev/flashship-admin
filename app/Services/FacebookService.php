<?php

namespace App\Services;

use App\Models\FacebookAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookService
{
    protected ?string $accessToken;

    public function __construct(?FacebookAccount $account = null)
    {
        if ($account) {
            $this->accessToken = $account->access_token;
        } else {
            $this->accessToken = (string) config('services.facebook.access_token', env('FACEBOOK_ACCESS_TOKEN'));
        }
    }

    /**
     * Gửi tin nhắn văn bản tới người dùng Messenger
     */
    public function sendTextMessage(string $recipientId, string $text): bool
    {
        if (empty($this->accessToken)) {
            Log::error('FacebookService: Missing Access Token');
            return false;
        }

        try {
            $response = Http::post("https://graph.facebook.com/v19.0/me/messages?access_token={$this->accessToken}", [
                'recipient' => [
                    'id' => $recipientId,
                ],
                'message' => [
                    'text' => $text,
                ],
            ]);

            if ($response->successful()) {
                return true;
            } else {
                Log::error('Facebook API Error: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('FacebookService Exception: ' . $e->getMessage());
        }

        return false;
    }
}
