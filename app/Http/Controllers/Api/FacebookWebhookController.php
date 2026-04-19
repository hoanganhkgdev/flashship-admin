<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\FacebookAccount;

class FacebookWebhookController extends Controller
{
    /**
     * Xác thực Webhook từ Facebook
     */
    public function verify(Request $request)
    {
        $mode = $request->input('hub_mode');
        $token = $request->input('hub_verify_token');
        $challenge = $request->input('hub_challenge');

        if ($mode === 'subscribe') {
            // Kiểm tra xem verify_token này có thuộc về Page nào đang quản lý không
            $account = FacebookAccount::where('verify_token', $token)->first();

            if ($account || $token === config('services.facebook.verify_token')) {
                Log::info('Facebook Webhook: Verified successfully');
                return response($challenge, 200);
            }
        }

        return response('Forbidden', 403);
    }

    /**
     * Tiếp nhận và xử lý webhook từ Facebook Messenger
     */
    public function handle(Request $request)
    {
        $payload = $request->all();

        // Facebook webhook thường gửi "entry" array
        if (!isset($payload['object']) || $payload['object'] !== 'page') {
            return response()->json(['status' => 'ignored'], 200);
        }

        Log::info('Facebook Webhook: Nhận yêu cầu mới', ['payload' => $payload]);

        // Sử dụng Laravel Job để xử lý nhanh và tin cậy hơn
        \App\Jobs\ProcessFacebookMessage::dispatch($payload);

        return response()->json(['status' => 'success'], 200);
    }
}
