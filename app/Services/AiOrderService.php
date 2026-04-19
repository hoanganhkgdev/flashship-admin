<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\AiConversation;
use App\Models\AiEscalation;
use App\Models\Shop;
use App\Models\City;
use App\Models\Order;
use App\Jobs\NotifyManagerEscalation;

class AiOrderService
{
    protected string $apiKey;
    protected string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    protected PricingService $pricingService;

    public function __construct(PricingService $pricingService)
    {
        $this->apiKey = (string) config('services.gemini.api_key', env('GEMINI_API_KEY'));
        $this->pricingService = $pricingService;
    }

    /**
     * Cách xưng hô với khách — admin có thể dạy AI đổi via AiKnowledge
     * Rule: input_text = 'salutation' hoặc 'cách gọi khách' hoặc 'xưng hô'
     * output_data = 'bạn' | 'quý khách' | 'anh/chị' | v.v.
     */
    protected function salutation(): string
    {
        return Cache::remember('ai_salutation', 3600, function () {
            $rule = \App\Models\AiKnowledge::where('type', 'rule')
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('input_text', 'like', '%salutation%')
                        ->orWhere('input_text', 'like', '%cách gọi khách%')
                        ->orWhere('input_text', 'like', '%xưng hô%')
                        ->orWhere('input_text', 'like', '%cách xưng%')
                        ->orWhere('title', 'like', '%xưng hô%')
                        ->orWhere('title', 'like', '%cách gọi%');
                })
                ->first();

            if ($rule) {
                $val = is_array($rule->output_data) ? ($rule->output_data[0] ?? '') : (string) $rule->output_data;
                $val = trim(strip_tags($val));
                if ($val)
                    return $val;
            }

            return 'bạn'; // Mặc định thân thiện hơn "anh/chị"
        });
    }


    // =========================================================================
    // MAIN: Agentic Loop với Function Calling
    // =========================================================================

    // Phiên hội thoại tự động kết thúc sau X phút không hoạt động
    const SESSION_TIMEOUT_MINUTES = 30;

    // Giữ tối đa N tin nhắn gần nhất để tránh context quá dài/cũ
    const MAX_HISTORY_MESSAGES = 20;

    /**
     * Điểm vào chính — Nhận tin nhắn, chạy agentic loop, trả kết quả
     */
    public function parseWithContext(string $userMessage, string $senderId, ?Shop $shop = null, ?int $cityId = null): array
    {
        // 1. Lấy hội thoại cũ
        $conversation = AiConversation::where('sender_id', $senderId)->first();

        // 1a. Kiểm tra Session Timeout
        if ($conversation && $conversation->last_interacted_at) {
            $idleMinutes = now()->diffInMinutes($conversation->last_interacted_at);
            if ($idleMinutes >= self::SESSION_TIMEOUT_MINUTES) {
                Log::info("Session timeout [{$senderId}]: {$idleMinutes} phút không hoạt động → Bắt đầu phiên mới.");
                $conversation->delete();
                $conversation = null;
            }
        }

        // Lệnh reset session — CHỈ khi nhắn đúng từ "reset"
        if (mb_strtolower(trim($userMessage)) === 'reset') {
            if ($conversation)
                $conversation->delete();
            return ['type' => 'text', 'content' => 'Dạ, em đã xóa thông tin đơn hàng cũ. Anh/chị cần ship gì nhắn em nhé!'];
        }

        // 2. Áp dụng shortcuts (lọc theo khu vực)
        $processedMessage = $this->applyShortcuts($userMessage, $cityId);

        // 3. Xây dựng lịch sử hội thoại (giới hạn N tin gần nhất)
        $rawHistory = $conversation ? $conversation->getGeminiHistory() : [];
        $trimmedHistory = array_slice($rawHistory, -self::MAX_HISTORY_MESSAGES);
        $geminiHistory = $trimmedHistory;

        // 3a. Kiểm tra tất cả đơn đang chờ địa chỉ (trong vòng 60 phút)
        $pendingOrders = $this->findPendingAddressOrders($shop, $senderId);
        $pendingCount = $pendingOrders->count();

        if ($pendingCount > 0) {
            $s = $this->salutation();
            $lowerMsg = mb_strtolower($processedMessage);

            $newOrderKeywords = [
                'mua hộ',
                'mua dùm',
                'mua giúp',
                'kêu ship',
                'đặt đơn',
                'ship mới',
                'xe ôm',
                'xe hơi',
                'chở người',
                'lái xe',
                'nạp tiền',
                'topup',
                'đơn mới',
                'đơn nữa',
                'thêm đơn',
                'cuốc nữa',
            ];
            $isNewOrder = false;
            foreach ($newOrderKeywords as $kw) {
                if (str_contains($lowerMsg, $kw)) {
                    $isNewOrder = true;
                    break;
                }
            }

            // Tóm tắt đơn nợ
            $pendingList = $pendingOrders->map(
                fn($o) =>
                "Đơn #{$o->id}" . ($o->order_note ? " ({$o->order_note})" : " — {$o->service_type}") . " lúc {$o->created_at->format('H:i')}"
            )->join('; ');

            if ($pendingCount === 1) {
                $o = $pendingOrders->first();
                if ($isNewOrder) {
                    $note = "[HỆ THỐNG] Đơn #{$o->id} đang chờ địa chỉ giao (tạo lúc {$o->created_at->format('H:i')}). Tin nhắn này là ĐƠN MỚI → gọi create_order. Sau đó nhắc {$s} cung cấp địa chỉ cho đơn #{$o->id}.";
                } else {
                    $note = "[HỆ THỐNG] Đơn #{$o->id}" . ($o->order_note ? " ({$o->order_note})" : '') . " đang chờ địa chỉ giao (pickup: {$o->pickup_address}, tạo lúc {$o->created_at->format('H:i')}). Nếu tin nhắn là địa chỉ/SĐT → gọi update_order_address(identifier: \"" . ($o->order_note ?: '') . "\"). Nếu mập mờ là đơn mới hay cập nhật → BẮT BUỘC hỏi xác nhận trước.";
                }
            } else {
                // Nhiều đơn nợ
                if ($isNewOrder) {
                    $note = "[HỆ THỐNG] {$pendingCount} đơn đang chờ địa chỉ: {$pendingList}. Tin nhắn này là ĐƠN MỚI → gọi create_order. Sau đó nhắc {$s} cung cấp địa chỉ cho các đơn trên.";
                } else {
                    $note = "[HỆ THỐNG] {$s} có {$pendingCount} đơn đang chờ địa chỉ: {$pendingList}. Nếu tin nhắn là địa chỉ/SĐT → PHẢI hỏi đây là cho đơn nào rồi mới gọi update_order_address(identifier: tên món). TUYỆT ĐỐI không tự đoán khi nhiều đơn nợ.";
                }
            }

            array_unshift($geminiHistory, ['role' => 'model', 'parts' => [['text' => $note]]]);
            array_unshift($geminiHistory, ['role' => 'user', 'parts' => [['text' => '[pending context]']]]);
        }

        $geminiHistory[] = ['role' => 'user', 'parts' => [['text' => $processedMessage]]];

        // 3b. Pre-match Admin Rules → inject ADMIN OVERRIDE trực tiếp vào history
        // Đây là cơ chế đáng tin cậy nhất để AI tuân theo rule admin:
        // Thay vì chỉ nhúng vào system prompt (AI có thể bỏ qua), ta inject vào
        // conversation history ngay trước tin nhắn khách — AI PHẢI đọc và follow.
        $this->matchAndInjectRules($processedMessage, $geminiHistory, $cityId);

        // 4. Chạy Agentic Loop
        $systemInstruction = $this->getSystemInstruction($shop, $cityId);
        $tools = $this->getToolDeclarations();

        $result = $this->runAgentLoop($geminiHistory, $systemInstruction, $tools, $shop, $senderId, $cityId);

        // 5. Lưu lịch sử (chỉ khi chưa bị xóa bởi Tool)
        if (AiConversation::where('sender_id', $senderId)->exists() || !isset($result['order'])) {
            $conversation = AiConversation::updateOrCreate(
                ['sender_id' => $senderId],
                ['last_interacted_at' => now()]
            );
            $conversation->appendMessage('user', $processedMessage);
            $conversation->appendMessage('model', $result['content'] ?? '');
            $conversation->save();
        }

        return $result;
    }

    /**
     * Chức năng bóc tách đơn hàng thủ công (cho CMS/Tổng đài)
     * Nhận text copy-paste, trả về array fields để điền vào form.
     */
    public function parseOrder(string $text, ?int $cityId = null): ?array
    {
        // ⚡️ ƯU TIÊN 1: Thử bóc tách cực nhanh bằng Regex nếu đúng mẫu truyền thống
        $fastResult = $this->tryFastRegexParse($text);
        if ($fastResult && !($fastResult['is_incomplete'] ?? false)) {
            Log::info("⚡️ FAST PARSE SUCCESS: " . json_encode($fastResult));
            return $fastResult;
        }

        // 🤖 ƯU TIÊN 2: Dùng AI nếu Regex không bóc tách được (văn bản tự do, hướng dẫn đường đi phức tạp...)
        $salutation = $this->salutation();
        $cityName = $this->getCityName($cityId) ?: 'Kiên Giang';

        $systemInstruction = "BẠN LÀ CHUYÊN VIÊN TRÍCH XUẤT DỮ LIỆU CỦA FLASHSHIP.
Nhiệm vụ: Đọc văn bản (copy từ tin nhắn) và trích xuất thông tin đơn hàng sang định dạng JSON.
Khu vực hiện tại: {$cityName}.
Cách xưng hô với khách: {$salutation}.

ĐỊNH DẠNG JSON TRẢ VỀ (BẮT BUỘC):
{
  \"service_type\": \"delivery\" | \"shopping\" | \"topup\" | \"bike\" | \"motor\" | \"car\",
  \"pickup_address\": \"Địa chỉ lấy hàng. (Với đơn nạp tiền/topup: để trống hoặc 'Tại shop')\",
  \"pickup_phone\": \"SĐT người gửi\",
  \"sender_name\": \"Tên người gửi (nếu có)\",
  \"delivery_address\": \"Địa chỉ giao hàng. (Với đơn nạp tiền/topup: Tên ngân hàng/Ví như Momo, ZaloPay, MBBank...)\",
  \"delivery_phone\": \"SĐT người nhận. (Với đơn nạp tiền/topup: SỐ TÀI KHOẢN hoặc SĐT NHẬN TIỀN)\",
  \"receiver_name\": \"Tên người nhận (nếu có)\",
  \"items\": \"Nội dung hàng hóa / Số tiền nạp / Ghi chú đơn\",
  \"shipping_fee\": 0, (Chỉ điền nếu khách nhắc đến giá ship cụ thể)
  \"is_incomplete\": true | false (true nếu thiếu địa chỉ hoặc SĐT cốt lõi)
}

NHẬN DẠNG LOẠI DỊCH VỤ:
- \"bike\" (Xe ôm): Khi có từ khóa \"xe ôm\", \"đón\", \"rước\", \"điểm rước\", \"chở\", \"đưa đón\", \"tiền xe\", \"cước xe\". Với đơn xe ôm: pickup_address = điểm đón/rước, delivery_address = điểm đến, delivery_phone = SĐT khách.
- \"delivery\" (Giao hàng): Mặc định nếu có địa chỉ lấy + giao.
- \"shopping\" (Mua hộ): Có từ \"mua hộ\", \"ghé mua\", \"đặt mua\".
- \"topup\" (Nạp tiền): Có từ \"nạp tiền\", \"chuyển tiền\", \"momo\", \"zalopay\".
- \"motor\" / \"car\" (Lái hộ xe máy/ô tô): Có từ \"lái hộ\", \"thuê xe\", \"lái xe\".

LƯU Ý:
1. Nếu là đơn NẠP TIỀN (topup): Phải trích xuất được số tiền và Số tài khoản/SĐT nhận tiền. SĐT/STK nhận tiền ưu tiên để vào delivery_phone.
2. Với đơn mua hộ (shopping), pickup_address là nơi mua, delivery_address là nơi nhận.
3. Nếu địa chỉ mơ hồ (vd: 'về shop', 'ghé em'), hãy để nguyên văn.
4. Không thêm bất kỳ văn bản nào ngoài JSON. Không dùng markdown code block.
5. Nếu tin nhắn có nhiều đơn, hãy ưu tiên trích xuất đơn rõ ràng mới hoặc gộp items nếu hợp lý.
6. \"Tiền xe\", \"tiền ship\", \"phí xe\" → điền vào shipping_fee (chỉ số, không có đơn vị).";


        $contents = [['role' => 'user', 'parts' => [['text' => $text]]]];

        // Sử dụng callGeminiApi với tools rỗng và cấu hình không dùng tool
        $response = $this->callGeminiApi($contents, $systemInstruction, [], 'NONE');

        if (!$response)
            return null;

        $jsonStr = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$jsonStr)
            return null;

        // Clean JSON - Tìm khối JSON đầu tiên bắt đầu bằng { và kết thúc bằng }
        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $jsonStr, $matches)) {
            $jsonStr = $matches[0];
        }
        $data = json_decode($jsonStr, true);

        if (!$data) {
            Log::error("parseOrder: Failed to decode JSON from Gemini: " . $jsonStr);
            // Fallback: Nếu không bóc được JSON, trả về structure rỗng với items là chính text đó
            return [
                'service_type' => 'delivery',
                'items' => $text,
                'is_incomplete' => true
            ];
        }

        // 2. Normalize & Clean Data
        $data = $this->cleanAndValidateData($data, null, $cityId);

        // 3. Tính phí dự kiến nếu AI chưa trích xuất được hoặc bằng 0
        if (empty($data['shipping_fee']) || $data['shipping_fee'] == 0) {
            [$fee, $distance] = $this->calculateShippingFee($data['service_type'] ?? 'delivery', $data, $cityId, $cityName);
            $data['shipping_fee'] = $fee;
        }

        return $data;
    }

    /**
     * Vòng lặp Agent: Gọi Gemini → Thực thi Tool → Gọi lại Gemini → Trả kết quả
     * Tối đa 3 vòng để tránh loop vô tận
     */
    protected function runAgentLoop(array $geminiHistory, string $systemInstruction, array $tools, ?Shop $shop, string $senderId, ?int $cityId, int $maxRounds = 15): array
    {
        // Collector cho đơn tổng: AI có thể gọi create_order nhiều lần
        $createdOrders = [];
        $lastOrderMsg = '';

        for ($round = 0; $round < $maxRounds; $round++) {
            $apiResponse = $this->callGeminiApi($geminiHistory, $systemInstruction, $tools);

            if (!$apiResponse) {
                return ['type' => 'text', 'content' => 'Xin lỗi, em đang gặp sự cố nhỏ. Anh/chị thử lại sau nhé!'];
            }

            $candidate = $apiResponse['candidates'][0]['content'] ?? null;
            if (!$candidate) {
                return ['type' => 'text', 'content' => "Xin lỗi, em chưa hiểu ý {$this->salutation()}. {$this->salutation()} thử diễn đạt lại được không ạ?"];
            }

            $parts = $candidate['parts'] ?? [];

            // Kiểm tra xem có Function Call không
            $functionCallPart = null;
            $textPart = null;

            foreach ($parts as $part) {
                if (isset($part['functionCall'])) {
                    $functionCallPart = $part['functionCall'];
                }
                if (isset($part['text'])) {
                    $textPart = $part['text'];
                }
            }

            // Nếu AI gọi Tool
            if ($functionCallPart) {
                $functionName = $functionCallPart['name'];
                $functionArgs = $functionCallPart['args'] ?? [];

                Log::info("AI Agent: Gọi Tool [{$functionName}] (round {$round})", $functionArgs);

                // Thêm model response (function call) vào history
                $geminiHistory[] = ['role' => 'model', 'parts' => [['functionCall' => $functionCallPart]]];

                // Thực thi Tool
                $toolResult = $this->executeFunction($functionName, $functionArgs, $shop, $senderId, $cityId);

                Log::info("AI Agent: Kết quả Tool [{$functionName}]", $toolResult);

                // Thêm Function Response vào history
                $geminiHistory[] = [
                    'role' => 'user',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name' => $functionName,
                                'response' => $toolResult,
                            ]
                        ]
                    ],
                ];

                // ── create_order: KHÔNG thoát ngay — thu thập để xử lý đơn tổng ──
                if ($functionName === 'create_order' && isset($toolResult['__direct_return'])) {
                    $ret = $toolResult['__direct_return'];
                    if (isset($ret['order'])) {
                        $createdOrders[] = $ret['order'];
                        $lastOrderMsg = $ret['content'] ?? '';
                        Log::info("AI Agent: Thu thập đơn #{$ret['order']->id} (tổng: " . count($createdOrders) . " đơn)");
                    }
                    // Không return — tiếp tục để AI có thể tạo thêm đơn
                    continue;
                }

                // Các tool khác có __direct_return → thoát ngay như cũ
                if (isset($toolResult['__direct_return'])) {
                    // Nếu đang có đơn đã tạo trước đó, broadcast chúng trước
                    if (!empty($createdOrders)) {
                        $this->broadcastCreatedOrders($createdOrders);
                    }
                    $ret = $toolResult['__direct_return'];
                    $ret['tool_called'] = $functionName;
                    return $ret;
                }

                // Tiếp tục vòng lặp để AI viết câu trả lời cuối
                continue;
            }

            // AI trả lời text thông thường → kết thúc loop
            if ($textPart) {
                // Nếu có đơn đã thu thập (đơn tổng) → broadcast + trả về đơn cuối
                if (!empty($createdOrders)) {
                    $this->broadcastCreatedOrders($createdOrders);
                    $lastOrder = end($createdOrders);
                    Log::info("AI Agent: Đơn tổng — đã tạo " . count($createdOrders) . " đơn, broadcast xong.");
                    return [
                        'type' => 'order_confirmed',
                        'order' => $lastOrder,
                        'content' => $textPart,  // Dùng text AI viết (tóm tắt tất cả đơn)
                        'tool_called' => 'create_order',
                    ];
                }
                return ['type' => 'text', 'content' => $textPart, 'tool_called' => null];
            }

            break;
        }

        // Hết vòng lặp — nếu còn đơn chưa broadcast
        if (!empty($createdOrders)) {
            $this->broadcastCreatedOrders($createdOrders);
            $lastOrder = end($createdOrders);
            return [
                'type' => 'order_confirmed',
                'order' => $lastOrder,
                'content' => $lastOrderMsg,
                'tool_called' => 'create_order',
            ];
        }

        $s = $this->salutation();
        return ['type' => 'text', 'content' => "Dạ, {$s} cần em hỗ trợ gì ạ?"];
    }

    /**
     * Broadcast tất cả đơn vừa tạo (dùng cho đơn tổng nhiều địa chỉ)
     */
    protected function broadcastCreatedOrders(array $orders): void
    {
        foreach ($orders as $order) {
            try {
                $fresh = $order->fresh();
                // ✅ Ghi vào RTDB cho app mới
                if ($fresh->status === 'pending') {
                    \App\Services\FirebaseRTDBService::publishOrder($fresh);
                }
                Log::info("broadcastCreatedOrders: ✅ Đơn #{$order->id}");
            } catch (\Throwable $e) {
                Log::error("broadcastCreatedOrders: ❌ Đơn #{$order->id}: " . $e->getMessage());
            }
        }
    }

    // =========================================================================
    // TOOLS (Functions AI có thể gọi)
    // =========================================================================

    /**
     * Định nghĩa danh sách Tool cho Gemini
     */
    protected function getToolDeclarations(): array
    {
        return [
            [
                'function_declarations' => [
                    [
                        'name' => 'create_order',
                        'description' => 'TẠO ĐƠN MỚI — Chỉ gọi khi ĐÃ XÁC ĐỊNH ĐƯỢC địa điểm (Pick/Drop) và có SĐT khách.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'service_type' => ['type' => 'STRING', 'description' => "'delivery' (giao hàng), 'shopping' (mua hộ), 'bike' (xe ôm), 'motor' (lái hộ xe), 'car' (lái hộ ô tô), 'topup' (nạp tiền)"],
                                'pickup_address' => ['type' => 'STRING', 'description' => 'ĐỊA CHỈ LẤY (A). Với Shop: Mặc định là địa chỉ của Shop. Với khách lẻ: Là nơi tài xế đến lấy đồ.'],
                                'pickup_phone' => ['type' => 'STRING', 'description' => 'SĐT NGƯỜI GỬI. Với Shop: SĐT Shop. Với khách lẻ: SĐT người ở điểm A.'],
                                'delivery_address' => ['type' => 'STRING', 'description' => "ĐỊA CHỈ GIAO (B). Nếu khách nhắn 'về shop' thì truyền chính xác chuỗi 'về shop'." ],
                                'delivery_phone' => ['type' => 'STRING', 'description' => 'SĐT NGƯỜI NHẬN (B). BẮT BUỘC có 10 chữ số.'],
                                'items' => ['type' => 'STRING', 'description' => 'Tên hàng hóa/Số tiền nạp/Ghi chú cần thiết cho tài xế.'],
                                'receiver_name' => ['type' => 'STRING', 'description' => 'Tên người nhận (nếu có).'],
                                'scheduled_at' => ['type' => 'STRING', 'description' => 'Hẹn giờ giao (Y-m-d H:i:s). Chỉ điền nếu khách yêu cầu giờ cụ thể.'],
                            ],
                            'required' => ['service_type', 'pickup_address', 'pickup_phone', 'delivery_address', 'delivery_phone'],
                        ],
                    ],
                    [
                        'name' => 'update_order_address',
                        'description' => 'CẬP NHẬT ĐỊA CHỈ/SĐT — Dùng khi khách nhắn bổ sung thông tin cho ĐƠN ĐANG CHỜ địa chỉ.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'delivery_address' => ['type' => 'STRING', 'description' => 'Địa chỉ giao mới.'],
                                'delivery_phone' => ['type' => 'STRING', 'description' => 'SĐT người nhận mới.'],
                                'identifier' => ['type' => 'STRING', 'description' => 'Tên món hàng/ID để tìm đúng đơn đang chờ khi khách có nhiều đơn nợ.'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'cancel_order',
                        'description' => 'HỦY ĐƠN — Khi khách yêu cầu không giao nữa.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'identifier' => ['type' => 'STRING', 'description' => 'Tên hàng (#123, bún đậu...) để tìm đơn muốn hủy.'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_order_status',
                        'description' => 'XEM TRẠNG THÁI — Khi khách hỏi "đơn tới đâu rồi", "có ai nhận chưa".',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'identifier' => ['type' => 'STRING', 'description' => 'Tên hàng (#123, bún đậu...) để tìm đúng đơn.'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'calculate_fee',
                        'description' => 'TÍNH TIỀN THỬ — Khi khách chỉ hỏi giá, hỏi ship bao nhiêu, KHÔNG phải lệnh chốt đơn.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'pickup_address' => ['type' => 'STRING', 'description' => 'Điểm đi (vd: quán bún cá).'],
                                'delivery_address' => ['type' => 'STRING', 'description' => 'Điểm đến (vd: 123 Nguyễn Trung Trực).'],
                                'service_type' => ['type' => 'STRING', 'description' => 'Loại dịch vụ (delivery/shopping/bike...).'],
                                'amount' => ['type' => 'STRING', 'description' => 'Số tiền nạp (chỉ dùng cho topup).'],
                            ],
                            'required' => ['service_type'],
                        ],
                    ],
                    [
                        'name' => 'update_order_items',
                        'description' => 'SỬA GHI CHÚ/THÊM HÀNG — Khi khách muốn mua thêm đồ hoặc đổi tên món hàng.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'items' => ['type' => 'STRING', 'description' => 'Nội dung hàng mới.'],
                                'mode' => ['type' => 'STRING', 'description' => "'append' (thêm vào sau), 'replace' (thay thế toàn bộ)"],
                            ],
                            'required' => ['items'],
                        ],
                    ],
                    [
                        'name' => 'escalate_to_manager',
                        'description' => 'Chuyển vấn đề khó cho quản lý xử lý khi: khách khiếu nại, đề nghị hoàn tiền, tài xế làm vỡ đồ, mất hàng, tranh chấp khó, hoặc khách yêu cầu gặp quản lý trực tiếp.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'reason' => ['type' => 'STRING', 'description' => 'Mô tả ngắn vấn đề của khách'],
                                'urgency' => ['type' => 'STRING', 'description' => "Mức độ: 'low' (thắc mắc thường), 'medium' (phàn nàn), 'high' (thiệt hại/yêu cầu gấp)"],
                                'summary' => ['type' => 'STRING', 'description' => 'Tóm tắt nội dung hội thoại và vấn đề cần xử lý'],
                            ],
                            'required' => ['reason', 'urgency', 'summary'],
                        ],
                    ],

                    [
                        'name' => 'answer_faq',
                        'description' => 'Trả lời câu hỏi về chính sách, giờ hoạt động, phạm vi dịch vụ, quy trình — những câu hỏi KHÔNG cần đặt đơn. Dùng khi khách hỏi về dịch vụ nói chung chứ không phải đặt đơn cụ thể.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'question_topic' => ['type' => 'STRING', 'description' => 'Chủ đề câu hỏi. VD: giờ hoạt động, phạm vi giao, chính sách hoàn tiền, loại hàng được ship'],
                            ],
                            'required' => ['question_topic'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Thực thi Tool mà AI đã chọn
     */
    protected function executeFunction(string $name, array $args, ?Shop $shop, string $senderId, ?int $cityId): array
    {
        return match ($name) {
            'create_order' => $this->toolCreateOrder($args, $shop, $senderId, $cityId),
            'update_order_address' => $this->toolUpdateOrderAddress($args, $shop, $senderId, $cityId),
            'update_order_items' => $this->toolUpdateOrderItems($args, $shop, $senderId),
            'cancel_order' => $this->toolCancelOrder($args, $shop, $senderId),
            'get_order_status' => $this->toolGetOrderStatus($args, $shop, $senderId),
            'calculate_fee' => $this->toolCalculateFee($args, $shop, $cityId),
            'escalate_to_manager' => $this->toolEscalateToManager($args, $shop, $senderId),

            'answer_faq' => $this->toolAnswerFaq($args, $shop, $cityId),
            default => ['__direct_return' => ['type' => 'text', 'content' => "Dạ, {$this->salutation()} cần em hỗ trợ gì ạ?"]],
        };
    }

    // =========================================================================
    // TOOL IMPLEMENTATIONS
    // =========================================================================

    protected function toolCreateOrder(array $args, ?Shop $shop, string $senderId, ?int $cityId): array
    {
        // Validate & clean data
        $data = $this->cleanAndValidateData($args, $shop, $cityId);

        // ⚠️ QUAN TRỌNG: create_order LUÔN tạo đơn MỚI hoàn toàn.
        // Việc cập nhật đơn đang nợ địa chỉ chỉ thông qua update_order_address.
        // Logic cũ tự động merge vào đơn nợ đã bị xóa vì gây nhầm lẫn:
        // shop nhắn đơn mới → hệ thống lại điền vào đơn cũ đang chờ → sai hoàn toàn.

        // Tạo đơn mới
        // Kiểm tra đơn "đem về shop" hoặc "hàng cồng kềnh" → phí chưa xác định
        $isShopPickup = !empty($data['is_shop_pickup']);
        $isBulky = !empty($data['is_bulky']);

        if ($isShopPickup || $isBulky) {
            $shippingFee = 0;
            $distance = 0;
            Log::info("toolCreateOrder: Đơn đặc biệt (ShopPickup: $isShopPickup, Bulky: $isBulky) → phí chưa xác định");
        } else {
            [$shippingFee, $distance] = $this->calculateShippingFee($data['service_type'] ?? 'delivery', $data, $cityId, $this->getCityName($cityId));
        }

        $note = !empty($data['items']) ? (is_array($data['items']) ? implode(', ', $data['items']) : $data['items']) : null;
        // Không thêm ghi chú phí vào order_note — phí được hiển thị riêng trong message

        $order = Order::create([
            'service_type' => $data['service_type'] ?? 'delivery',
            'pickup_address' => $data['pickup_address'] ?? '',
            'pickup_phone' => $data['pickup_phone'] ?? '',
            'sender_name' => $data['sender_name'] ?? '',
            'delivery_address' => $data['delivery_address'] ?? '',
            'delivery_phone' => $data['delivery_phone'] ?? '',
            'receiver_name' => $data['receiver_name'] ?? '',
            'shipping_fee' => $shippingFee,
            'distance' => $distance,
            'order_note' => $note,
            'status' => $this->determineOrderStatus($data['service_type'] ?? 'delivery', $data),
            'city_id' => $cityId,
            'shop_id' => $shop?->id,
            'sender_platform_id' => $senderId,
            'platform' => 'zalo',
            'is_ai_created' => true,
            'is_freeship' => false,
            'scheduled_at' => !empty($args['scheduled_at']) ? \Carbon\Carbon::parse($args['scheduled_at']) : null,
        ]);

        // ✔️ Đơn hoàn chỉnh → xóa session
        // ⏳ Đơn nợ địa chỉ → lưu context tối thiểu để AI nhớ lần sau
        $this->finalizeSession($order, $senderId);

        $message = $this->buildConfirmedMessage($order);
        if (!$shop)
            $message .= "\n\n💡 *Nhắn 'Menu' để đặt thêm đơn mới nhé!*";

        return ['__direct_return' => ['type' => 'order_confirmed', 'order' => $order, 'content' => $message]];
    }

    /**
     * Quản lý session sau khi tạo/cập nhật đơn:
     * - Đơn hoàn chỉnh → XÓA session (khởi đầu sạch cho lần sau)
     * - Đơn nợ địa chỉ → Tạo context tối thiểu nhớ đơn
     */
    protected function finalizeSession(Order $order, string $senderId): void
    {
        // Xóa session cũ
        AiConversation::where('sender_id', $senderId)->delete();

        $isPendingAddress = $this->isMissingField($order->delivery_address)
            || $this->isMissingField($order->delivery_phone);

        // Tạo minimal context sau khi tạo đơn.
        // ⚠️ KHÔNG lưu địa chỉ/SĐT vào context để tránh AI tái sử dụng
        // thông tin cũ cho đơn mới khi khách nhắn tin mơ hồ.
        // Chỉ lưu order ID + service_type: đủ để AI hủy/kiểm tra trạng thái.
        $newConv = AiConversation::create([
            'sender_id' => $senderId,
            'last_interacted_at' => now(),
        ]);

        if ($isPendingAddress) {
            // Đơn nợ địa chỉ → giữ context để AI biết cần update địa chỉ
            $newConv->appendMessage('user', "[Đơn #{$order->id} đã tạo lúc " . now()->format('H:i') . "] Dịch vụ: {$order->service_type}. Đang chờ địa chỉ giao và SĐT người nhận.");
            $newConv->appendMessage('model', "Dạ, em đã tạo đơn #{$order->id} thành công. Khi {$this->salutation()} có địa chỉ giao và SĐT người nhận, nhắn em để cập nhật nhé!");
            Log::info("Session: Đơn #{$order->id} nợ địa chỉ → Minimal context cho sender {$senderId}");
        } else {
            // Đơn hoàn chỉnh → chỉ lưu ID & service_type, KHÔNG lưu địa chỉ/SĐT
            $newConv->appendMessage('user', "[Đơn #{$order->id} vừa tạo lúc " . now()->format('H:i') . "] Dịch vụ: {$order->service_type}.");
            $newConv->appendMessage('model', "Dạ, đơn #{$order->id} đã tạo thành công. Tài xế sẽ liên hệ sớm nhé!");
            Log::info("Session: Đơn #{$order->id} hoàn chỉnh → Minimal context (no address) cho sender {$senderId}");
        }

        $newConv->save();
    }

    protected function toolUpdateOrderAddress(array $args, ?Shop $shop, string $senderId, ?int $cityId): array
    {
        $keyword = trim($args['identifier'] ?? '');
        $addr = trim($args['delivery_address'] ?? '');
        $phone = trim($args['delivery_phone'] ?? '');

        // 1. Tìm các đơn nợ — lọc theo keyword nếu có
        $allPending = $this->findPendingAddressOrders($shop, $senderId, $keyword ?: null);

        if ($allPending->isEmpty()) {
            return ['success' => false, 'message' => 'Không tìm thấy đơn hàng nào đang chờ địa chỉ' . ($keyword ? " khớp với \"{$keyword}\"" : '') . '.'];
        }

        // 2. Nếu vẫn còn nhiều đơn nợ → không tự ý cập nhật, yêu cầu xác nhận
        if ($allPending->count() > 1) {
            $s = $this->salutation();
            $list = $allPending->map(
                fn($o) =>
                "- Đơn #{$o->id}" . ($o->order_note ? " ({$o->order_note})" : " — dịch vụ {$o->service_type}") . ", tạo lúc {$o->created_at->format('H:i')}"
            )->join("\n");

            return [
                '__direct_return' => [
                    'type' => 'text',
                    'content' => "Dạ em thấy {$s} đang có {$allPending->count()} đơn chờ địa chỉ:\n{$list}\n\nĐịa chỉ giao này thuộc đơn nào vậy ạ? (Nhắn tên món để em cập nhật đúng nhé!)",
                ]
            ];
        }

        // 3. Chỉ còn 1 đơn → cập nhật
        $pendingOrder = $allPending->first();

        // Chuẩn hóa địa chỉ
        if ($addr && $cityId) {
            $cityName = $this->getCityName($cityId);
            if ($cityName && !str_contains(mb_strtolower($addr), mb_strtolower($cityName))) {
                $suffix = $cityName;
                $addr = rtrim($addr, ', ') . ', ' . $suffix;
            }
        }

        if ($addr)
            $pendingOrder->delivery_address = $addr;
        if ($phone)
            $pendingOrder->delivery_phone = $phone;

        // Kiểm tra còn thiếu gì không
        $stillMissingAddr = !$addr && (!$pendingOrder->delivery_address || str_contains(mb_strtolower($pendingOrder->delivery_address ?? ''), 'sẽ cung cấp sau'));
        $stillMissingPhone = !$phone && (!$pendingOrder->delivery_phone || str_contains(mb_strtolower($pendingOrder->delivery_phone ?? ''), 'sẽ cung cấp sau'));

        // Tính lại phí nếu có đủ địa chỉ
        if (
            $pendingOrder->pickup_address && $pendingOrder->delivery_address
            && !str_contains(mb_strtolower($pendingOrder->delivery_address), 'sẽ cung cấp sau')
        ) {
            $distance = $this->pricingService->getDistance($pendingOrder->pickup_address, $pendingOrder->delivery_address);
            if ($distance > 0) {
                $pendingOrder->distance = $distance;
                $pendingOrder->shipping_fee = $this->pricingService->calculate(
                    $pendingOrder->service_type,
                    $pendingOrder->city_id,
                    $distance
                );
                Log::info("toolUpdateOrderAddress: Tính lại phí đơn #{$pendingOrder->id}: {$pendingOrder->shipping_fee}đ ({$distance}km)");
            }
        }

        $pendingOrder->save();

        // Nếu vẫn còn thiếu → lưu lại và hỏi thêm (KHÔNG finalize)
        if ($stillMissingAddr || $stillMissingPhone) {
            $s = $this->salutation();
            $missing = $stillMissingAddr && $stillMissingPhone
                ? 'địa chỉ giao và SĐT người nhận'
                : ($stillMissingAddr ? 'địa chỉ giao' : 'SĐT người nhận');

            $partial = $addr ? "SĐT: {$phone} đã ghi nhận." : "Địa chỉ: {$addr} đã ghi nhận.";

            return [
                '__direct_return' => [
                    'type' => 'text',
                    'content' => "Dạ em đã lưu thông tin cho đơn #{$pendingOrder->id}. Vui lòng cho em biết thêm {$missing} để em hoàn tất đơn nhé!",
                ]
            ];
        }

        // Đầy đủ → finalize
        $this->finalizeSession($pendingOrder, $senderId);
        $message = "✅ Đã cập nhật địa chỉ giao cho đơn #{$pendingOrder->id}!\n\n" . $this->buildConfirmedMessage($pendingOrder);
        return ['__direct_return' => ['type' => 'order_confirmed', 'order' => $pendingOrder, 'content' => $message]];

    }


    protected function toolGetOrderStatus(array $args, ?Shop $shop, string $senderId): array
    {
        $identifier = trim($args['identifier'] ?? '');
        $orders = $this->findTargetOrders($shop, $senderId, $identifier);

        if ($orders->isEmpty()) {
            return ['__direct_return' => ['type' => 'text', 'content' => 'Dạ, hiện tại em chưa thấy đơn hàng nào của mình trên hệ thống ạ. Anh/chị cần ship gì cứ nhắn em nhé!']];
        }

        // Nếu có nhiều đơn và không có identifier → Liệt kê
        if ($orders->count() > 1 && empty($identifier)) {
            $list = $orders->map(fn($o) => "- Đơn #{$o->id} (" . ($o->order_note ?: "dịch vụ {$o->service_type}") . ")")->join("\n");
            return ['__direct_return' => ['type' => 'text', 'content' => "Dạ em thấy {$this->salutation()} có nhiều đơn gần đây, anh/chị cần kiểm tra đơn nào ạ?\n\n{$list}"]];
        }

        $order = $orders->first();
        $statusLabel = match ($order->status) {
            'pending' => '⏳ Đang chờ tài xế nhận đơn',
            'assigned' => '🛵 Tài xế đã nhận, đang trên đường lấy hàng',
            'delivering' => '🚀 Đang giao hàng đến người nhận',
            'completed' => '✅ Đã giao hàng thành công',
            'cancelled' => '❌ Đơn đã bị hủy',
            'draft' => '📝 Đơn đang chờ bổ sung thông tin',
            default => "🔄 Trạng thái: {$order->status}",
        };

        $content = "📦 Đơn #{$order->id}\nNội dung: " . ($order->order_note ?: "Dịch vụ {$order->service_type}") . "\n{$statusLabel}";

        if ($order->delivery_address && !str_contains(mb_strtolower($order->delivery_address), 'sẽ cung cấp sau')) {
            $content .= "\n📍 Giao đến: {$order->delivery_address}";
        }

        if (in_array($order->status, ['assigned', 'delivering']) && $order->delivery_man_id && ($driver = $order->driver)) {
            $content .= "\n👤 Tài xế: {$driver->name}\n📞 SĐT: {$driver->phone}";
        }

        return ['__direct_return' => ['type' => 'text', 'content' => $content]];
    }

    protected function toolCancelOrder(array $args, ?Shop $shop, string $senderId): array
    {
        $identifier = trim($args['identifier'] ?? '');
        $orders = $this->findTargetOrders($shop, $senderId, $identifier);

        if ($orders->isEmpty()) {
            return ['__direct_return' => ['type' => 'text', 'content' => "Dạ em không tìm thấy đơn hàng nào của {$this->salutation()} để hủy ạ."]];
        }

        if ($orders->count() > 1 && empty($identifier)) {
            $list = $orders->map(fn($o) => "- Đơn #{$o->id} (" . ($o->order_note ?: "dịch vụ {$o->service_type}") . ")")->join("\n");
            return ['__direct_return' => ['type' => 'text', 'content' => "Dạ em thấy {$this->salutation()} đang có nhiều đơn, anh/chị muốn hủy đơn nào ạ?\n\n{$list}"]];
        }

        $order = $orders->first();
        if (in_array($order->status, ['completed', 'cancelled'])) {
            return ['__direct_return' => ['type' => 'text', 'content' => "Dạ đơn #{$order->id} đã ở trạng thái " . ($order->status === 'completed' ? 'hoàn thành' : 'đã hủy') . " rồi ạ."]];
        }

        $order->update(['status' => 'cancelled']);
        return ['__direct_return' => ['type' => 'text', 'content' => "✅ Dạ em đã hủy đơn #{$order->id} cho mình rồi nhé!"]];
    }

    protected function toolCalculateFee(array $args, ?Shop $shop, ?int $cityId): array
    {
        if (!$cityId) {
            return ['__direct_return' => ['type' => 'text', 'content' => 'Dạ, em không xác định được khu vực để tính phí ship ạ.']];
        }

        $type = $args['service_type'] ?? 'delivery';

        // ✉️ Nạp tiền: tính theo số tiền, không theo km
        if ($type === 'topup') {
            $amountStr = trim($args['amount'] ?? $args['items'] ?? '');
            $amount = $this->extractAmountFromItems($amountStr);
            if ($amount <= 0) {
                return ['__direct_return' => ['type' => 'text', 'content' => "Dạ, {$this->salutation()} muốn nạp bao nhiêu tiền để em tính phí ưu đãi chính xác ạ?"]];
            }
            $fee = $this->pricingService->calculate('topup', $cityId, 0, $amount);
            $content = 'Dạ, phí nạp tiền ' . number_format($amount) . 'đ là: ' . number_format($fee) . 'đ ạ. 🚀';
            return ['__direct_return' => ['type' => 'text', 'content' => $content]];
        }

        $deliveryStr = trim($args['delivery_address'] ?? '');
        $pickupArgStr = trim($args['pickup_address'] ?? '');

        // =========================================================
        // Xác định điểm lấy hàng (pickup) theo dịch vụ & loại khách
        // =========================================================
        if ($type === 'shopping') {
            // 🛒 Mua hộ:
            //   pickup  = tiệm mua (bắt buộc từ args)
            //   delivery = Shop (nếu shop & không có delivery_address) hoặc từ args
            if (!$pickupArgStr) {
                return ['__direct_return' => ['type' => 'text', 'content' => "Dạ, {$this->salutation()} mua ở tiệm nào / địa chỉ nào để em tính phí chính xác ạ?"]];
            }
            $pickupStr = $pickupArgStr;

            // Nếu chưa có delivery_address hoặc khách nói "về shop" → dùng địa chỉ Shop
            if (!$deliveryStr && $shop) {
                $deliveryStr = $shop->address;
            } elseif (!$deliveryStr) {
                return ['__direct_return' => ['type' => 'text', 'content' => "Dạ, {$this->salutation()} cho em xin địa chỉ giao cụ thể để em báo phí chính xác nhé!"]];
            }
        } else {
            // 🚚 delivery / bike / motor / car:
            //   pickup = Shop (nếu shop) hoặc từ args
            //   delivery = từ args (bắt buộc)
            if (!$deliveryStr) {
                return ['__direct_return' => ['type' => 'text', 'content' => "Dạ, {$this->salutation()} cho em xin địa chỉ giao cụ thể để em báo phí ship chính xác nhé!"]];
            }
            if ($shop) {
                $pickupStr = $pickupArgStr ?: $shop->address;
            } else {
                $pickupStr = $pickupArgStr;
                if (!$pickupStr) {
                    return ['__direct_return' => ['type' => 'text', 'content' => "Dạ, {$this->salutation()} cho em xin địa chỉ lấy hàng (điểm đi) để em tính phí chính xác nhé!"]];
                }
            }
        }

        // Chuẩn hóa địa chỉ (thêm tên thành phố nếu cần, bỏ qua nếu đã có tỉnh)
        $cityName = $this->getCityName($cityId);
        if ($cityName) {
            $suffix = $cityName;
            $pickupStr = $this->normalizeAddress($pickupStr, $cityName, $suffix);
            $deliveryStr = $this->normalizeAddress($deliveryStr, $cityName, $suffix);
        }

        Log::info("toolCalculateFee [{$type}]: Pickup={$pickupStr} → Delivery={$deliveryStr}");

        $distance = $this->pricingService->getDistance($pickupStr, $deliveryStr);
        if ($distance > 0) {
            $fee = $this->pricingService->calculate($type, $cityId, $distance);

            // Label hiển thị theo dịch vụ
            if ($type === 'shopping') {
                $fromLabel = $args['pickup_address'] ?? $pickupStr;
                $toLabel = ($shop && str_contains($deliveryStr, $shop->address ?? '')) ? 'Shop' : ($args['delivery_address'] ?? $deliveryStr);
                $content = "Dạ, phí mua hộ từ {$fromLabel} về {$toLabel} là: " . number_format($fee) . "đ (khoảng " . number_format($distance, 1) . "km) ạ. 🚀";
            } else {
                $fromLabel = ($shop && $pickupStr === $shop->address) ? 'Shop' : ($args['pickup_address'] ?? $pickupStr);
                $toLabel = $args['delivery_address'] ?? $deliveryStr;
                $content = "Dạ, phí ship từ {$fromLabel} đến {$toLabel} là: " . number_format($fee) . "đ (khoảng " . number_format($distance, 1) . "km) ạ. 🚀";
            }

            return ['__direct_return' => ['type' => 'text', 'content' => $content]];
        }

        return ['__direct_return' => ['type' => 'text', 'content' => 'Dạ, em không tính được phí ship cho địa chỉ này ạ. Anh/chị có thể cung cấp địa chỉ cụ thể hơn không ạ?']];
    }

    protected function toolUpdateOrderItems(array $args, ?Shop $shop, string $senderId): array
    {
        $newItems = trim($args['items'] ?? '');
        $mode = $args['mode'] ?? 'append';

        if (!$newItems) {
            return ['__direct_return' => ['type' => 'text', 'content' => "Dạ, {$this->salutation()} muốn thêm/cập nhật món hàng nào ạ?"]];
        }

        $lastOrder = $this->getLastOrder($shop, $senderId);

        if (!$lastOrder) {
            return ['__direct_return' => ['type' => 'text', 'content' => 'Dạ, em không tìm thấy đơn hàng nào để cập nhật ạ.']];
        }

        // Chỉ cho phép cập nhật đơn chưa hoàn thành / chưa giao xong
        if (in_array($lastOrder->status, ['completed', 'cancelled'])) {
            return [
                '__direct_return' => [
                    'type' => 'text',
                    'content' => "Dạ, đơn #{$lastOrder->id} đã {$lastOrder->status} nên không thể cập nhật thêm hàng được ạ. Anh/chị muốn đặt đơn mới không?",
                ]
            ];
        }

        // Cập nhật note
        $oldNote = $lastOrder->order_note ?? '';
        $isFirstItem = empty($oldNote); // lần đầu thêm vs lần sau

        if ($mode === 'replace' || $isFirstItem) {
            $lastOrder->order_note = $newItems;
        } else {
            $lastOrder->order_note = $oldNote . ', ' . $newItems;
        }

        // ─── Phụ phí khi thêm hàng vào đơn shopping ───────────────────────
        // Chỉ áp dụng khi: đơn shopping + KHÔNG phải lần thêm đầu tiên
        $surchargeAdded = 0;
        if ($lastOrder->service_type === 'shopping' && !$isFirstItem) {
            // Mặc định 5,000đ — admin có thể override qua AiKnowledge rule
            // Rule: input_text LIKE '%surcharge_per_update_shopping%', output = số tiền (vd: "0" để tắt, "10000" để đổi)
            $surchargeAmount = 5000; // default

            $surchargeRule = Cache::remember('ai_surcharge_update_shopping', 3600, function () {
                return \App\Models\AiKnowledge::where('type', 'rule')
                    ->where('is_active', true)
                    ->where('input_text', 'like', '%surcharge_per_update_shopping%')
                    ->first();
            });

            if ($surchargeRule) {
                // Có rule → override giá trị mặc định
                $val = is_array($surchargeRule->output_data)
                    ? ($surchargeRule->output_data['amount'] ?? 5000)
                    : (int) filter_var($surchargeRule->output_data, FILTER_SANITIZE_NUMBER_INT);
                $surchargeAmount = max(0, (int) $val);
            }

            if ($surchargeAmount > 0) {
                $lastOrder->shipping_fee = ($lastOrder->shipping_fee ?? 0) + $surchargeAmount;
                $surchargeAdded = $surchargeAmount;
                Log::info("toolUpdateOrderItems: Cộng phụ phí +{$surchargeAmount}đ cho đơn shopping #{$lastOrder->id}");
            }
        }
        // ──────────────────────────────────────────────────────────────────────

        $lastOrder->save();

        Log::info("toolUpdateOrderItems: Đơn #{$lastOrder->id} → note=[{$lastOrder->order_note}]");

        $surchargeMsg = $surchargeAdded > 0
            ? "\n💰 Phụ phí thêm điểm mua: +" . number_format($surchargeAdded) . "đ (tổng phí: " . number_format($lastOrder->shipping_fee) . "đ)"
            : '';

        return [
            '__direct_return' => [
                'type' => 'text',
                'content' => "✅ Dạ, em đã cập nhật đơn #{$lastOrder->id} thêm: {$newItems} rồi ạ! Tài xế sẽ lấy luôn nhé 🛵{$surchargeMsg}",
            ]
        ];
    }

    /**
     * Tool: record_inference
     * AI tự gọi sau khi suy luận thành công từ câu địa phương/mơ hồ → Lưu pattern vào AiKnowledge
     * Đây là cơ chế "tự học" cốt lõi — AI không cần admin dạy shortcut thủ công
     */

    /**
     * Tool: answer_faq
     * Tra cứu AiKnowledge type=faq để trả lời câu hỏi ngoài phạm vi đặt đơn
     * Nếu không có FAQ → trả về câu trả lời mặc định thông minh
     */
    protected function toolAnswerFaq(array $args, ?Shop $shop, ?int $cityId): array
    {
        $topic = trim($args['question_topic'] ?? '');
        $s = $this->salutation();

        // Tìm FAQ phù hợp trong AiKnowledge
        $faq = null;
        if ($topic) {
            $faq = \App\Models\AiKnowledge::where('type', 'faq')
                ->where('is_active', true)
                ->where(function ($q) use ($topic, $cityId) {
                    $q->whereNull('city_id');
                    if ($cityId)
                        $q->orWhere('city_id', $cityId);
                })
                ->where(function ($q) use ($topic) {
                    $topicWords = explode(' ', mb_strtolower($topic));
                    foreach ($topicWords as $word) {
                        if (mb_strlen($word) >= 3) {
                            $q->orWhere('input_text', 'like', "%{$word}%")
                                ->orWhere('title', 'like', "%{$word}%");
                        }
                    }
                })
                ->first();
        }

        if ($faq) {
            $answer = is_array($faq->output_data)
                ? ($faq->output_data['answer'] ?? implode('\n', $faq->output_data))
                : (string) $faq->output_data;
            Log::info("answer_faq: Tìm thấy FAQ #{$faq->id} cho topic '{$topic}'");
            return ['__direct_return' => ['type' => 'text', 'content' => $answer]];
        }

        // Không có FAQ → Trả lời thông minh mặc định theo topic
        $lowerTopic = mb_strtolower($topic);
        $shopName = $shop?->name ?? 'Flashship';

        $defaultAnswer = match (true) {
            str_contains($lowerTopic, 'giờ') || str_contains($lowerTopic, 'hoạt động') || str_contains($lowerTopic, 'mở cửa')
            => "Dạ, {$shopName} hoạt động từ sáng đến tối hàng ngày {$s} ơi! {$s} cần ship gì cứ nhắn em nhé 🚀",

            str_contains($lowerTopic, 'liên tỉnh') || str_contains($lowerTopic, 'xa') || str_contains($lowerTopic, 'ngoài tỉnh')
            => "Dạ, hiện tại em chỉ giao nội ô thôi {$s} ơi, không giao liên tỉnh được ạ. {$s} cần ship nội ô thì em hỗ trợ ngay!",

            str_contains($lowerTopic, 'hoàn tiền') || str_contains($lowerTopic, 'đền bù') || str_contains($lowerTopic, 'mất hàng')
            => "Dạ, vấn đề hoàn tiền/đền bù cần quản lý xem xét trực tiếp {$s} ơi. Em chuyển thông tin lên cho bộ phận phụ trách ngay nhé!",

            str_contains($lowerTopic, 'phí') || str_contains($lowerTopic, 'giá') || str_contains($lowerTopic, 'bảng giá')
            => "Dạ, phí ship tính theo khoảng cách {$s} ơi. {$s} cho em biết địa chỉ lấy + giao là em tính phí chính xác ngay ạ!",

            str_contains($lowerTopic, 'tài xế') || str_contains($lowerTopic, 'shipper')
            => "Dạ, khi đơn được nhận, thông tin tài xế sẽ hiển thị cho {$s} ngay ạ. {$s} cần đặt đơn không?",

            default
            => "Dạ, câu hỏi của {$s} em sẽ chuyển đến bộ phận hỗ trợ để trả lời chính xác nhất ạ! {$s} cần đặt đơn ship thì nhắn em nhé 😊",
        };

        Log::info("answer_faq: Không có FAQ cho '{$topic}' → Dùng default answer");
        return ['__direct_return' => ['type' => 'text', 'content' => $defaultAnswer]];
    }

    protected function toolEscalateToManager(array $args, ?Shop $shop, string $senderId): array
    {
        $reason = $args['reason'] ?? 'Không rõ lý do (Shop phàn nàn/cần manager)';
        $urgency = in_array($args['urgency'] ?? '', ['low', 'medium', 'high']) ? $args['urgency'] : 'medium';
        $summary = $args['summary'] ?? '';

        // 1. Tìm đơn hàng khả nghi trong tin nhắn (ví dụ: Shop bảo đơn bún đậu hư)
        $identifier = $this->extractIdentifierFromText($reason . ' ' . $summary);
        $orders = $this->findTargetOrders($shop, $senderId, $identifier);
        $targetOrder = $orders->count() === 1 ? $orders->first() : $this->getLastOrder($shop, $senderId);

        // 2. Lưu escalation vào DB để quản lý theo dõi
        $escalation = AiEscalation::create([
            'sender_id' => $senderId,
            'platform' => 'zalo',
            'source_type' => $targetOrder ? Order::class : ($shop ? Shop::class : null),
            'source_id' => $targetOrder?->id ?? $shop?->id,
            'reason' => $reason,
            'urgency' => $urgency,
            'conversation_summary' => $summary,
            'status' => 'open',
        ]);

        Log::warning("AI Escalation #{$escalation->id}: [{$urgency}] {$reason} | Sender: {$senderId}");

        // 3. Thông báo manager qua FCM (chạy async)
        NotifyManagerEscalation::dispatch($escalation->id);

        // 4. Phản hồi khách hàng theo mức độ urgency
        $customerMessage = match ($urgency) {
            'high' => "Dạ, em hiểu tình huống của {$this->salutation()} rất cấp bách! Em đã ngay lập tức ghi nhận và chuyển trực tiếp cho quản lý cấp cao để xử lý trong thời gian sớm nhất ạ. Quản lý sẽ liên hệ lại {$this->salutation()} qua Zalo này nhé! 🙏",
            'medium' => "Dạ, em đã ghi nhận vấn đề của {$this->salutation()} và chuyển cho bộ phận hỗ trợ để xem xét và giải quyết ạ. {$this->salutation()} vui lòng chờ em trong ít phút, bộ phận phụ trách sẽ liên hệ lại sớm nhé! 🙏",
            default => "Dạ, em đã ghi nhận thông tin và chuyển cho team chăm sóc khách hàng của Flashship ạ. {$this->salutation()} có thắc mắc gì thêm cứ nhắn em nhé! 😊",
        };

        return ['__direct_return' => ['type' => 'text', 'content' => $customerMessage]];
    }

    // =========================================================================
    // GEMINI API
    // =========================================================================

    protected function callGeminiApi(array $contents, string $systemInstruction, array $tools = [], string $toolMode = 'AUTO'): ?array
    {
        if (!$this->apiKey) {
            Log::error('AI Agent: Missing GEMINI_API_KEY');
            return null;
        }

        try {
            $payload = [
                'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.35,
                    'max_output_tokens' => 1024,
                ],
            ];

            if (!empty($tools)) {
                $payload['tools'] = $tools;
                $payload['tool_config'] = ['function_calling_config' => ['mode' => $toolMode]];
            } elseif ($toolMode === 'NONE') {
                // Đảm bảo không gọi tool khi mode là NONE dù có tools hay không
                $payload['tool_config'] = ['function_calling_config' => ['mode' => 'NONE']];
            }

            $response = Http::timeout(25)->post("{$this->apiUrl}?key={$this->apiKey}", $payload);

            if ($response->successful()) {
                $result = $response->json();
                Log::info('Gemini Raw Response:', ['finish_reason' => $result['candidates'][0]['finishReason'] ?? 'N/A']);
                return $result;
            }

            Log::error('Gemini API Error: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('Gemini Exception: ' . $e->getMessage());
        }

        return null;
    }

    // =========================================================================
    // ORDER HELPERS (dùng bởi ChatInteractionService nếu cần)
    // =========================================================================

    public function updateOrderFromData(Order $order, array $data, ?int $cityId = null, ?Shop $shop = null): Order
    {
        if (!empty($data['delivery_address']) && !str_contains(mb_strtolower($data['delivery_address']), 'sẽ cung cấp sau')) {
            $order->delivery_address = $data['delivery_address'];
        }
        if (!empty($data['delivery_phone']) && !str_contains(mb_strtolower($data['delivery_phone']), 'sẽ cung cấp sau')) {
            $order->delivery_phone = $data['delivery_phone'];
        }
        if (!empty($data['items'])) {
            $order->order_note = is_array($data['items']) ? implode(', ', $data['items']) : $data['items'];
        }

        if ($order->pickup_address && $order->delivery_address && !str_contains($order->delivery_address, 'Sẽ cung cấp sau')) {
            $distance = $this->pricingService->getDistance($order->pickup_address, $order->delivery_address);
            if ($distance > 0) {
                $order->distance = $distance;
                $order->shipping_fee = $this->pricingService->calculate($order->service_type, $order->city_id, $distance);
            }
        }

        $order->save();
        return $order;
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Engine Pre-match Admin Rules → Inject ADMIN OVERRIDE vào conversation history
     *
     * Khi admin train rule "lấy hàng → tạo đơn cửa hàng (delivery)", method này:
     * 1. Lấy tất cả rules từ DB (theo city)
     * 2. Tokenize input_text của mỗi rule thành các từ khóa riêng biệt
     * 3. Kiểm tra xem tin nhắn có chứa từ khóa nào không
     * 4. Nếu match → inject ADMIN OVERRIDE trực tiếp vào conversation history
     *    ngay TRƯỚC tin nhắn khách (AI thấy trong history = không thể bỏ qua)
     *
     * Đây mạnh hơn system prompt vì:
     * - System prompt → Gemini xử lý như "hướng dẫn nền" → có thể bị override bởi training data
     * - History injection → Gemini xử lý như "context thực tế" → ưu tiên cao hơn nhiều
     */
    protected function matchAndInjectRules(string $message, array &$geminiHistory, ?int $cityId): void
    {
        $cacheKey = 'ai_rules_for_matching_city_' . ($cityId ?? 'global');
        $rules = Cache::remember($cacheKey, 600, function () use ($cityId) {
            return \App\Models\AiKnowledge::where('type', 'rule')
                ->where('is_active', true)
                ->where(function ($q) use ($cityId) {
                    $q->whereNull('city_id');
                    if ($cityId)
                        $q->orWhere('city_id', $cityId);
                })
                ->get(['input_text', 'output_data', 'title']);
        });

        if ($rules->isEmpty())
            return;

        $lowerMsg = mb_strtolower($message);
        $matchedRules = [];

        foreach ($rules as $rule) {
            if (!$rule->input_text)
                continue;

            // Bỏ qua các rule system nội bộ (không phải hành vi tin nhắn)
            $skipPatterns = ['salutation', 'cách gọi', 'xưng hô', 'surcharge_per_update', 'mẫu xác nhận', 'mẫu lên đơn', 'soạn đơn', 'confirm', 'template'];
            $isSystemRule = false;
            foreach ($skipPatterns as $sp) {
                if (str_contains(mb_strtolower($rule->input_text), $sp)) {
                    $isSystemRule = true;
                    break;
                }
            }
            if ($isSystemRule)
                continue;

            // Tokenize input_text thành keywords để match linh hoạt hơn
            // Ví dụ: "shop kêu lấy hàng" → kiểm tra từng từ quan trọng
            $inputLower = mb_strtolower($rule->input_text);

            // Strategy 1: Exact phrase match (ưu tiên nhất)
            $exactMatch = str_contains($lowerMsg, $inputLower);

            // Strategy 2: Smart token match — handle 3 patterns admin hay dùng:
            //   [placeholder] như [địa điểm], [tên shop] → bỏ qua khi match (wildcard)
            //   từ/từ như đồ/hàng, lấy/nhận → OR (chỉ cần 1 trong 2 có mặt)
            //   từ thường → AND (phải có mặt trong message)
            $tokenMatch = false;
            if (!$exactMatch) {
                // Bước 1: Xóa [placeholder] — chúng là wildcard, không cần match literal
                $strippedInput = preg_replace('/\[[^\]]*\]/u', ' ', $inputLower);

                // Bước 2: Tách thành các "token group" — mỗi group là OR (phân cách bởi /)
                // Ví dụ: "đồ/hàng" → group ["đồ", "hàng"] (match nếu có ít nhất 1)
                $rawTokens = preg_split('/[\s,;]+/u', trim($strippedInput));
                $tokenGroups = [];
                foreach ($rawTokens as $raw) {
                    $raw = trim($raw);
                    if ($raw === '')
                        continue;
                    if (str_contains($raw, '/')) {
                        // OR group: tách theo /, filter bỏ từ ngắn < 2 chars
                        $orParts = array_filter(
                            explode('/', $raw),
                            fn($p) => mb_strlen(trim($p)) >= 2
                        );
                        if (!empty($orParts))
                            $tokenGroups[] = array_values($orParts);
                    } else {
                        // Single token: chỉ lấy nếu đủ dài (>= 3 chars)
                        if (mb_strlen($raw) >= 3)
                            $tokenGroups[] = [$raw];
                    }
                }

                if (!empty($tokenGroups)) {
                    $allGroupsMatch = true;
                    foreach ($tokenGroups as $group) {
                        $anyInGroup = false;
                        foreach ($group as $term) {
                            if (str_contains($lowerMsg, trim($term))) {
                                $anyInGroup = true;
                                break;
                            }
                        }
                        if (!$anyInGroup) {
                            $allGroupsMatch = false;
                            break;
                        }
                    }
                    $tokenMatch = $allGroupsMatch;
                }
            }

            if ($exactMatch || $tokenMatch) {
                $matchedRules[] = $rule;
                Log::info("matchAndInjectRules: Rule match! Input=[{$rule->input_text}] matched in message=[{$message}] (exact=" . ($exactMatch ? 'yes' : 'no') . ")");
            }
        }

        if (empty($matchedRules))
            return;

        // Sắp xếp: Rule CỤ THỂ HƠN (input_text dài hơn) → ưu tiên cao hơn
        // Khi "ghé bến xe lấy hàng về shop" khớp cả 2 rule:
        //   - "lấy hàng" (ngắn = tổng quát)
        //   - "ghé [nơi] lấy hàng đem về shop" (dài = cụ thể)
        // Rule cụ thể sẽ được inject CUỐI CÙNG (gần tin nhắn nhất) → AI đọc sau = ưu tiên hơn
        usort($matchedRules, fn($a, $b) => mb_strlen($a->input_text) <=> mb_strlen($b->input_text));
        // → rule ngắn inject trước (context nền), rule dài inject sau (ghi đè nếu mâu thuẫn)

        // Inject từng rule thành một cặp [user/model] riêng biệt
        // Rule ngắn (tổng quát) inject trước, rule dài (cụ thể) inject sau → gần tin nhắn nhất
        // AI đọc context theo thứ tự → rule cuối cùng (cụ thể nhất) sẽ được ưu tiên nhất
        $lastUserMsg = array_pop($geminiHistory);

        foreach ($matchedRules as $i => $rule) {
            $action = is_array($rule->output_data)
                ? implode('; ', array_map(fn($k, $v) => is_int($k) ? $v : "{$k}: {$v}", array_keys($rule->output_data), $rule->output_data))
                : (string) $rule->output_data;

            $isLast = ($i === count($matchedRules) - 1);
            $priorityNote = $isLast
                ? "\n→ Đây là QUY TẮC CỤ THỂ NHẤT — ƯU TIÊN TUYỆT ĐỐI, ghi đè mọi quy tắc trước."
                : "\n→ Quy tắc nền (có thể bị ghi đè bởi quy tắc cụ thể hơn bên dưới).";

            $overrideText = "[ADMIN RULE " . ($i + 1) . "/" . count($matchedRules) . " — BẮT BUỘC]\n"
                . "Khi nhắn \"" . $rule->input_text . "\": " . $action
                . $priorityNote;

            $geminiHistory[] = ['role' => 'user', 'parts' => [['text' => '[admin rule ' . ($i + 1) . ']']]];
            $geminiHistory[] = ['role' => 'model', 'parts' => [['text' => $overrideText]]];
        }

        $geminiHistory[] = $lastUserMsg; // Tin nhắn thực của khách — sau tất cả override

        Log::info("matchAndInjectRules: Injected " . count($matchedRules) . " rule override(s) for message=[{$message}]");
    }

    protected function applyShortcuts(string $text, ?int $cityId = null): string
    {
        $cacheKey = 'ai_shortcuts_city_' . ($cityId ?? 'global');
        $shortcuts = Cache::remember($cacheKey, 3600, function () use ($cityId) {
            return \App\Models\AiKnowledge::where('type', 'shortcut')
                ->where('is_active', true)
                ->where(function ($q) use ($cityId) {
                    $q->whereNull('city_id'); // global
                    if ($cityId) {
                        $q->orWhere('city_id', $cityId); // hoặc đúng khu vực
                    }
                })
                ->orderByRaw('LENGTH(input_text) DESC') // Ưu tiên shortcut dài hơn trước (tránh partial match)
                ->get();
        });

        foreach ($shortcuts as $shortcut) {
            if (!$shortcut->input_text)
                continue;
            $replacement = is_array($shortcut->output_data) ? implode(', ', $shortcut->output_data) : (string) $shortcut->output_data;
            $input = $shortcut->input_text;

            // Dùng word-boundary regex để tránh thay thế sai ngữ cảnh.
            // Ví dụ: "bv" không bị thay khi nằm trong "bvt" hay "number bv123".
            // \b không hoạt động tốt với tiếng Việt (unicode) → dùng lookahead/lookbehind whitespace/punctuation
            $safeInput = preg_quote($input, '/');
            // Chỉ thay khi input nằm ở word-boundary: đầu, cuối, hoặc bên cạnh space/punctuation
            $pattern = '/(?<![\p{L}\p{N}])' . $safeInput . '(?![\p{L}\p{N}])/iu';
            $newText = preg_replace($pattern, $replacement, $text);

            // Nếu regex không match (pattern phức tạp) → fallback safe str_ireplace
            if ($newText === null) {
                $text = str_ireplace($input, $replacement, $text);
            } else {
                $text = $newText;
            }
        }
        return $text;
    }

    /**
     * Load inference patterns AI đã tự học → inject vào system prompt
     * Giúp AI nhận ra ngay những pattern đã từng xử lý thành công trước đó
     */

    /**
     * Load rules + examples từ AiKnowledge DB theo city → inject vào system prompt
     */
    protected function loadKnowledgeContext(?int $cityId = null): string
    {
        $cacheKey = 'ai_knowledge_context_city_' . ($cityId ?? 'global');
        return Cache::remember($cacheKey, 600, function () use ($cityId) {
            // Load rules
            $rules = \App\Models\AiKnowledge::where('type', 'rule')
                ->where('is_active', true)
                ->where(function ($q) use ($cityId) {
                    $q->whereNull('city_id');
                    if ($cityId)
                        $q->orWhere('city_id', $cityId);
                })
                ->get(['input_text', 'output_data']);

            // Load examples
            $examples = \App\Models\AiKnowledge::where('type', 'example')
                ->where('is_active', true)
                ->where(function ($q) use ($cityId) {
                    $q->whereNull('city_id');
                    if ($cityId)
                        $q->orWhere('city_id', $cityId);
                })
                ->get(['input_text', 'output_data']);

            $context = '';

            if ($rules->isNotEmpty()) {
                $context .= "\n\nQUY TẮC ĐẶC BIỆT (Admin đã cấu hình — ưu tiên TUYỆT ĐỐI, áp dụng ngay):";
                foreach ($rules as $rule) {
                    $action = is_array($rule->output_data)
                        ? implode('; ', array_map(fn($k, $v) => is_int($k) ? $v : "{$k}: {$v}", array_keys($rule->output_data), $rule->output_data))
                        : (string) $rule->output_data;
                    // Nếu output là chuỗi ngắn (< 60 ký tự) → hiển thị dạng quy tắc
                    // Nếu dài hơn → đây là mô tả hành động cụ thể → giữ nguyên
                    $context .= "\n- QUY TẮC: Khi [{$rule->input_text}] → HÀNH ĐỘNG: {$action}";
                }
            }

            if ($examples->isNotEmpty()) {
                $context .= "\n\nVÍ DỤ MẪU THỰC TẾ (Admin đã cấu hình — học theo chính xác):";
                foreach ($examples as $ex) {
                    $out = is_array($ex->output_data)
                        ? ($ex->output_data['ai_response'] ?? json_encode($ex->output_data, JSON_UNESCAPED_UNICODE))
                        : (string) $ex->output_data;
                    $context .= "\n- Khi khách nhắn: \"{$ex->input_text}\"\n  → AI phải: {$out}";
                }
            }

            return $context;
        });
    }

    protected function getCityName(?int $cityId): string
    {
        if (!$cityId)
            return '';
        $city = Cache::remember("city_{$cityId}", 3600, fn() => City::find($cityId));
        return $city ? $city->name : '';
    }

    protected function detectCity(string $text): ?int
    {
        $text = mb_strtolower($text);
        $cities = Cache::remember('all_cities', 3600, fn() => City::all());
        foreach ($cities as $city) {
            if (str_contains($text, mb_strtolower($city->name)))
                return $city->id;
            if ($city->code && str_contains($text, mb_strtolower($city->code)))
                return $city->id;
        }
        return null;
    }

    /**
     * Kiểm tra một trường có bị thiếu không:
     * - Chuỗi rỗng / null
     * - Placeholder "Sẽ cung cấp sau" (AI điền khi chưa có thông tin)
     */
    protected function isMissingField(mixed $value): bool
    {
        if (empty($value))
            return true;
        return str_contains(mb_strtolower((string) $value), 'sẽ cung cấp sau');
    }

    protected function determineOrderStatus(string $type, array $data): string
    {
        // 🗓️ Đơn hẹn giờ -> luôn là 'scheduled'
        if (!empty($data['scheduled_at'])) {
            return 'scheduled';
        }

        // ✅ KHÔNG dùng 'draft' nữa để tránh nổ chuông 2 lần trên App tài xế. 
        // Đơn tạo mới luôn là 'pending' để chỉ kích hoạt 1 sự kiện duy nhất.
        return 'pending';
    }

    protected function calculateShippingFee(string $type, array $data, ?int $cityId, string $cityName): array
    {
        if (!$cityId)
            return [0, 0];

        // Nạp tiền: tính theo số tiền, không theo km
        if ($type === 'topup') {
            $amount = $this->extractAmountFromItems($data['items'] ?? '');
            if ($amount > 0) {
                $fee = $this->pricingService->calculate('topup', $cityId, 0, $amount);
                return [$fee, 0];
            }
            return [0, 0];
        }

        $pickupStr = $data['pickup_address'] ?? '';
        $deliveryStr = $data['delivery_address'] ?? '';

        if ($pickupStr && $deliveryStr && !str_contains(mb_strtolower($deliveryStr), 'sẽ cung cấp sau')) {
            $fullSuffix = $cityName;
            if ($cityName) {
                $pickupStr = $this->normalizeAddress($pickupStr, $cityName, $fullSuffix);
                $deliveryStr = $this->normalizeAddress($deliveryStr, $cityName, $fullSuffix);
            }
            $distance = $this->pricingService->getDistance($pickupStr, $deliveryStr);
            if ($distance > 0) {
                return [$this->pricingService->calculate($type, $cityId, $distance), $distance];
            }
        }

        return [0, 0];
    }

    /**
     * Parse số tiền từ chuỗi items.
     * VD: "Nạp tiền 100k" → 100000, "Nạp 1.5tr" → 1500000
     */
    protected function extractAmountFromItems(string $items): float
    {
        $text = mb_strtolower($items);
        // Match số tiền: 100k, 1.5tr, 200,000, 500000...
        if (preg_match('/([\.\d,]+)\s*(tr|triệu|m)/u', $text, $m)) {
            return (float) str_replace(',', '', $m[1]) * 1_000_000;
        }
        if (preg_match('/([\.\d,]+)\s*(k|nghìn|ngàn)/u', $text, $m)) {
            return (float) str_replace(',', '', $m[1]) * 1_000;
        }
        if (preg_match('/([\d,\.]+)/', $text, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }
        return 0;
    }

    /**
     * Chuẩn hóa địa chỉ: chỉ thêm city suffix khi địa chỉ chưa có tên tỉnh/thành phố nào.
     * Check cả DB + danh sách tỉnh thành VN để phát hiện địa chỉ liên tỉnh.
     */
    protected function normalizeAddress(string $address, string $localCityName, string $suffixToAdd): string
    {
        $addrLower = mb_strtolower(trim($address));

        // Bỏ qua nếu là placeholder
        if (str_contains($addrLower, 'sẽ cung cấp sau'))
            return $address;

        // Đã có tên thành phố local rồi
        if (str_contains($addrLower, mb_strtolower($localCityName)))
            return $address;

        // Kiểm tra DB cities (các khu vực Flashship phục vụ)
        $dbCities = Cache::remember('all_city_names', 3600, fn() => City::pluck('name')->toArray());
        foreach ($dbCities as $city) {
            if ($city !== $localCityName && str_contains($addrLower, mb_strtolower($city))) {
                return $address;
            }
        }

        // Kiểm tra danh sách tỉnh thành & thành phố lớn Việt Nam
        foreach (self::VIETNAM_PLACES as $place) {
            if (str_contains($addrLower, mb_strtolower($place))) {
                return $address; // Địa chỉ liên tỉnh → không thêm suffix
            }
        }

        // Không nhận ra tỉnh/thành nào → thêm suffix địa phương
        return rtrim($address, ', ') . ', ' . $suffixToAdd;
    }

    /**
     * Danh sách tỉnh thành & thành phố lớn Việt Nam dùng để phát hiện địa chỉ liên tỉnh.
     */
    const VIETNAM_PLACES = [
        // 63 tỉnh/thành phố
        'hà nội',
        'hồ chí minh',
        'tp.hcm',
        'tp hcm',
        'sài gòn',
        'đà nẵng',
        'hải phòng',
        'cần thơ',
        'an giang',
        'bà rịa',
        'vũng tàu',
        'bắc giang',
        'bắc kạn',
        'bạc liêu',
        'bắc ninh',
        'bến tre',
        'bình định',
        'bình dương',
        'bình phước',
        'bình thuận',
        'cà mau',
        'cao bằng',
        'đắk lắk',
        'đắk nông',
        'điện biên',
        'đồng nai',
        'đồng tháp',
        'gia lai',
        'hà giang',
        'hà nam',
        'hà tĩnh',
        'hải dương',
        'hậu giang',
        'hòa bình',
        'hưng yên',
        'khánh hòa',
        'kiên giang',
        'kon tum',
        'lai châu',
        'lâm đồng',
        'lạng sơn',
        'lào cai',
        'long an',
        'nam định',
        'nghệ an',
        'ninh bình',
        'ninh thuận',
        'phú thọ',
        'phú yên',
        'quảng bình',
        'quảng nam',
        'quảng ngãi',
        'quảng ninh',
        'quảng trị',
        'sóc trăng',
        'sơn la',
        'tây ninh',
        'thái bình',
        'thái nguyên',
        'thanh hóa',
        'thừa thiên huế',
        'huế',
        'tiền giang',
        'trà vinh',
        'tuyên quang',
        'vĩnh long',
        'vĩnh phúc',
        'yên bái',
        // Thành phố lớn trong tỉnh
        'long xuyên',
        'châu đốc',
        'cao lãnh',
        'sa đéc',
        'mỹ tho',
        'bến tre city',
        'quy nhơn',
        'tuy hòa',
        'nha trang',
        'đà lạt',
        'buôn ma thuột',
        'pleiku',
        'kon tum city',
        'tam kỳ',
        'hội an',
        'quảng ngãi city',
        'phan thiết',
        'phan rang',
        'biên hòa',
        'vũng tàu',
        'thủ dầu một',
        'mỹ tho',
        'tân an',
        'vị thanh',
        'ngã bảy',
        'rạch giá',
        'hà tiên',
        'bạc liêu city',
        'cà mau city',
        'sóc trăng city',
        'trà vinh city',
        'vinh',
        'hà tĩnh city',
        'đồng hới',
        'đông hà',
        'uông bí',
        'hạ long',
    ];

    protected function cleanAndValidateData(array $data, ?Shop $shop = null, ?int $cityId = null): array
    {
        // 🔹 Không chuẩn hóa địa chỉ ở đây — việc thêm tên thành phố chỉ
        //    cần dùng nội bộ trong calculateShippingFee() để gọi Google Maps.
        //    Nếu normalize ở đây, địa chỉ hiển thị lên form sẽ bị thêm "Rạch Giá".

        if ($shop) {
            // Gán tên shop cho đơn (luôn cần)
            if (empty($data['sender_name'])) {
                $data['sender_name'] = $shop->name;
            }

            // ── Minimal fallback — CHỈ điền khi AI không cung cấp ──────────
            // Toàn bộ business logic (service_type, pickup/delivery address)
            // do AI quyết định dựa trên rule admin train.
            // PHP chỉ đảm bảo các trường tối thiểu không bị trống.

            $serviceType = $data['service_type'] ?? 'delivery';

            // Pickup phone: nếu chưa có → dùng SĐT shop làm fallback
            if (empty($data['pickup_phone'])) {
                $data['pickup_phone'] = $shop->phone;
            }

            // Pickup address: chỉ fallback về shop nếu AI không cung cấp
            // (AI đã quyết định pickup theo rule admin — không override)
            if (empty($data['pickup_address'])) {
                $data['pickup_address'] = $shop->address;
            }

            // Delivery về shop: nếu AI nói "giao về shop / về shop" → resolve thành địa chỉ thực
            $deliveryRaw = $data['delivery_address'] ?? '';
            $deliveryLower = mb_strtolower(trim($deliveryRaw));
            $isShopKeywords = [
                'shop',
                'về shop',
                'giao về shop',
                'shop address',
                'về cho em',
                'về cho shop',
                'đem về',
                'đem về cho em',
                'giúp shop',
                'hộ shop',
                'cho shop',
                'lấy giúp shop',
                'lấy hộ shop',
            ];

            $isDirectMatch = in_array($deliveryLower, $isShopKeywords);
            $isContainMatch = false;
            foreach ($isShopKeywords as $kw) {
                if (str_contains($deliveryLower, $kw)) {
                    $isContainMatch = true;
                    break;
                }
            }

            if ($isDirectMatch || $isContainMatch) {

                // Đơn "đem về shop": điểm đến là Shop, điểm lấy là bên ngoài (bến xe, v.v.)
                $data['delivery_address'] = $shop->address;

                // Nếu AI đã cung cấp SĐT (thường gán nhầm vào delivery_phone)
                // → hủy chuyển sang pickup_phone (SĐT người ở điểm lấy)
                $suppliedPhone = trim($data['delivery_phone'] ?? '');
                $isPendingPhone = in_array(mb_strtolower($suppliedPhone), ['', 'sẽ cung cấp sau']);

                if (!$isPendingPhone) {
                    // Hoán đổi: SĐT người gửi → pickup_phone
                    $data['pickup_phone'] = $suppliedPhone;
                    // SĐT Shop → delivery_phone (người nhận là Shop)
                    $data['delivery_phone'] = $shop->phone;
                    Log::info("cleanAndValidateData: Hoán đổi SĐT — pickup_phone={$suppliedPhone}, delivery_phone(shop)={$shop->phone}");
                } else {
                    // Không có SĐT nào → điền SĐT Shop cho delivery
                    $data['delivery_phone'] = $shop->phone;
                }

                // Đánh dấu đơn đem về shop → miễn phí ship
                $data['is_shop_pickup'] = true;
                Log::info('cleanAndValidateData: Đơn đem về shop → is_shop_pickup=true');
            }

            // --- Tình huống 6: Detect hàng cồng kềnh ---
            $itemText = mb_strtolower(is_array($data['items'] ?? '') ? implode(' ', $data['items']) : ($data['items'] ?? ''));
            $bulkyKeywords = ['bao', 'thùng', 'kiện', 'bó', 'cồng kềnh', 'quá khổ', 'nặng', 'to'];
            $isBulky = false;
            foreach ($bulkyKeywords as $kw) {
                if (str_contains($itemText, $kw)) {
                    $isBulky = true;
                    break;
                }
            }
            if ($isBulky) {
                $data['is_bulky'] = true;
                Log::info('cleanAndValidateData: Phát hiện hàng cồng kềnh → is_bulky=true');
            }

            Log::debug("cleanAndValidateData: shop=[{$shop->name}] type=[{$serviceType}] pickup=[{$data['pickup_address']}] delivery=[{$data['delivery_address']}]");
        }


        return $data;
    }

    protected function extractIdentifierFromText(string $text): ?string
    {
        // 1. Tìm pattern #123 hoặc [số đơn]
        if (preg_match('/#(\d+)/', $text, $matches)) {
            return $matches[1];
        }

        // 2. Nếu không có mã, AI sẽ cố gắng lọc ra tên hàng (vd: "đơn bún đậu")
        // Chúng ta tạm dùng chính text hoặc substring ngắn để findTargetOrders xử lý
        return null;
    }

    /**
     * Tìm các đơn hàng khả nghi của Shop/Sender trong 60 phút gần nhất.
     * Identifier có thể là:
     * - Mã đơn (VD: #123, 123)
     * - Tên món hàng (VD: bún đậu, trà sữa)
     */
    protected function findTargetOrders(?Shop $shop, string $senderId, ?string $identifier = null): \Illuminate\Support\Collection
    {
        $query = Order::where(function ($q) use ($shop, $senderId) {
            if ($shop) {
                $q->where('shop_id', $shop->id);
            } else {
                $q->where('sender_platform_id', $senderId)->where('platform', 'zalo');
            }
        })
            ->where('created_at', '>=', now()->subMinutes(60))
            ->latest();

        if ($identifier) {
            // Lọc theo Mã đơn
            $cleanId = ltrim($identifier, '#');
            if (is_numeric($cleanId)) {
                $orderById = (clone $query)->where('id', $cleanId)->first();
                if ($orderById)
                    return collect([$orderById]);
            }

            // Lọc theo nội dung hàng hóa
            $query->where('order_note', 'like', "%{$identifier}%");
        }

        return $query->get();
    }

    protected function getLastOrder(?Shop $shop, string $senderId): ?Order
    {
        return Order::where(function ($q) use ($shop, $senderId) {
            if ($shop) {
                $q->where('shop_id', $shop->id);
            } else {
                $q->where('sender_platform_id', $senderId)->where('platform', 'zalo');
            }
        })->latest()->first();
    }

    /**
     * Tìm TẤT CẢ đơn đang nợ địa chỉ của Shop/Sender trong 60 phút gần nhất.
     * Hỗ trợ lọc theo keyword (tên món hàng) để phân biệt khi có nhiều đơn nợ.
     */
    protected function findPendingAddressOrders(?Shop $shop, string $senderId, ?string $keyword = null): \Illuminate\Support\Collection
    {
        return Order::where(function ($q) use ($shop, $senderId) {
            if ($shop) {
                $q->where('shop_id', $shop->id);
            } else {
                $q->where('sender_platform_id', $senderId)->where('platform', 'zalo');
            }
        })
            ->where(function ($q) {
                $q->where('delivery_address', 'like', '%Sẽ cung cấp sau%')
                    ->orWhere('delivery_phone', 'like', '%Sẽ cung cấp sau%')
                    ->orWhere('delivery_address', '')
                    ->orWhereNull('delivery_address');
            })
            ->where('created_at', '>=', now()->subMinutes(60))
            ->whereIn('status', ['pending', 'assigned', 'delivering'])
            ->when($keyword, fn($q) => $q->where('order_note', 'like', "%{$keyword}%"))
            ->latest()
            ->get();
    }

    /** Backward-compat wrapper — trả về đơn nợ gần nhất (hoặc null) */
    protected function findPendingAddressOrder(?Shop $shop, string $senderId, ?string $serviceType = null): ?Order
    {
        return $this->findPendingAddressOrders($shop, $senderId)
            ->when($serviceType, fn($c) => $c->where('service_type', $serviceType))
            ->first();
    }

    protected function buildConfirmedMessage(Order $order): string
    {
        $deliveryAddr = mb_strtolower($order->delivery_address ?? '');
        $isAddressPending = empty($deliveryAddr) || str_contains($deliveryAddr, 'sẽ cung cấp sau');

        // Phát hiện đơn "đem về shop": phí=0, không freeship, và giao về đúng địa chỉ shop
        $shopAddr = $order->shop?->address ?? '';
        $isShopPickup = $order->shipping_fee == 0
            && !$order->is_freeship
            && $shopAddr
            && $order->delivery_address === $shopAddr;

        // Phát hiện hàng cồng kềnh (Tình huống 6)
        $isBulky = $order->shipping_fee == 0 && str_contains(mb_strtolower($order->order_note ?? ''), 'cồng kềnh'); // AI thường nhét item vào note

        $feeFormatted = match (true) {
            $order->shipping_fee > 0 && !$isAddressPending => number_format($order->shipping_fee) . 'đ',
            $isShopPickup => 'Tài xế báo sau (tuỳ khối lượng)',
            $isBulky => 'Điều phối dự kiến (báo sau)',
            default => 'Đang tính...',
        };

        $scheduled = $order->scheduled_at ? "\n⏰ HẸN GIỜ: " . $order->scheduled_at->format('H:i d/m/Y') : "";

        // 1. Tạo Footer thông minh sớm
        $footer = "\n\n🚀 Tài xế sẽ sớm liên hệ để xử lý đơn!";
        $isPendingInfo = $isAddressPending || empty($order->delivery_phone) || str_contains($order->delivery_phone ?? '', 'sẽ cung cấp sau');

        if ($isPendingInfo && !$isShopPickup) {
            $missing = [];
            if ($isAddressPending)
                $missing[] = 'địa chỉ giao';
            if (empty($order->delivery_phone) || str_contains($order->delivery_phone ?? '', 'sẽ cung cấp sau')) {
                $missing[] = 'SĐT người nhận';
            }
            $missingStr = implode(' và ', $missing);
            $footer = "\n\n⚠️ Đang chờ {$missingStr}. Khi nào có mình nhắn em cập nhật ngay cho đơn #{$order->id} nhé!";
        }

        // 2. Ưu tiên mẫu Admin (Vẫn nối thêm footer nếu thiếu info)
        $customMsg = $this->applyConfirmTemplate($order, $feeFormatted);
        if ($customMsg !== null) {
            return $customMsg . ($isPendingInfo ? $footer : "\n\n🚀 Tài xế sẽ sớm liên hệ!");
        }
        // ────────────────────────────────────────────────────────────────────

        $pickup = $order->pickup_address ?: 'Chưa cập nhật';
        $pickupPh = $order->pickup_phone ?: 'N/A';
        $delivery = $order->delivery_address ?: 'Chưa cập nhật';
        $delivPh = $order->delivery_phone ?: 'N/A';
        $items = $order->order_note ?: 'Chưa cập nhật';
        $isShop = (bool) $order->shop_id;

        return match ($order->service_type) {

            // ── Mua hộ / Đơn lấy về shop ────────────────────────────────────
            'shopping' => (function () use ($order, $pickup, $delivery, $delivPh, $pickupPh, $items, $feeFormatted, $footer, $scheduled) {
                    $shopAddr = $order->shop?->address ?? '';
                    $isReturnToShop = $shopAddr && (
                    $order->delivery_address === $shopAddr ||
                    str_contains(mb_strtolower($order->delivery_address ?? ''), 'shop')
                    );

                    $label = $isReturnToShop ? '📦 FLASHSHIP - ĐƠN CỬA HÀNG (LẤY VỀ)' : '🛒 FLASHSHIP - ĐƠN MUA HỘ';
                    $line = "━━━━━━━━━━━━━━━━━━━";

                    return implode("\n", array_filter([
                    $label,
                    "🆔 MÃ ĐƠN: #{$order->id} {$scheduled}",
                    $line,
                    "🏁 LẤY: {$pickup}",
                    "📞 {$pickupPh}",
                    "📍 GIAO: {$delivery}",
                    "📞 {$delivPh}",
                    $items !== 'Chưa cập nhật' ? "📝 NỘI DUNG: {$items}" : null,
                    $line,
                    "💰 PHÍ SHIP: {$feeFormatted}",
                    ])) . $footer;
                })(),

            // ── Nạp tiền ────────────────────────────────────────────────────
            'topup' => $this->buildTopupMessage($order, $feeFormatted) . $footer,

            // ── Xe ôm ───────────────────────────────────────────────────────
            'bike' => implode("\n", [
                '🛵 Đơn xe ôm',
                "🏁 Đón: {$pickup}",
                "📍 Đến: {$delivery}",
                "📞 {$delivPh}",
                "💰 Phí: {$feeFormatted}",
            ]) . $footer,

            // ── Lái hộ xe ──────────────────────────────────────────────────
            'motor', 'car' => implode("\n", [
                '🚗 Đơn lái hộ',
                "🏁 Đón: {$pickup}",
                "📍 Đến: {$delivery}",
                "📞 {$delivPh}",
                "💰 Phí: {$feeFormatted}",
            ]) . $footer,

            // ── Giao hàng (delivery) — mặc định ────────────────────────────
            default => (function () use ($order, $isShop, $pickup, $pickupPh, $delivery, $delivPh, $items, $feeFormatted, $footer, $scheduled) {
                    $label = $isShop ? '📦 FLASHSHIP - ĐƠN CỬA HÀNG' : '🚀 FLASHSHIP - ĐƠN GIAO HÀNG';
                    $line = "━━━━━━━━━━━━━━━━━━━";

                    return implode("\n", array_filter([
                    $label,
                    "🆔 MÃ ĐƠN: #{$order->id} {$scheduled}",
                    $line,
                    "🏁 LẤY: {$pickup}",
                    "☎️ {$pickupPh}",
                    "📍 GIAO: {$delivery}",
                    "📞 {$delivPh}",
                    $items !== 'Chưa cập nhật' ? "📝 NỘI DUNG: {$items}" : null,
                    $line,
                    "💰 PHÍ SHIP: {$feeFormatted}",
                    ])) . $footer;
                })(),
        };
    }

    /**
     * Kiểm tra AiKnowledge có mẫu xác nhận do admin cấu hình không.
     * Hỗ trợ các biến: [số đơn] [tên hàng] [địa chỉ] [SĐT] [phí] [điểm lấy] [SĐT lấy]
     * Nếu tìm thấy → trả về chuỗi đã điền biến. Không có → trả về null.
     */
    protected function applyConfirmTemplate(Order $order, string $feeFormatted): ?string
    {
        $serviceType = $order->service_type;

        // Tìm template theo thứ tự ưu tiên:
        // 1. Rule cho đúng service_type (vd: "mẫu xác nhận shopping")
        // 2. Rule chung cho tất cả đơn (vd: "mẫu xác nhận đơn giao hàng xong")
        $template = Cache::remember("ai_confirm_tpl_{$serviceType}_{$order->city_id}", 600, function () use ($serviceType) {
            return \App\Models\AiKnowledge::where('type', 'rule')
                ->where('is_active', true)
                ->where(function ($q) use ($serviceType) {
                    // Tìm theo output có chứa biến template
                    $q->where('output_data', 'like', '%[số đơn]%')
                        ->orWhere('output_data', 'like', '%[tên hàng]%')
                        ->orWhere('output_data', 'like', '%[phí]%')
                        ->orWhere('output_data', 'like', '%[địa chỉ]%')
                        // Hoặc tìm theo input mô tả ý định
                        ->orWhere('input_text', 'like', '%mẫu xác nhận%')
                        ->orWhere('input_text', 'like', '%mẫu lên đơn%')
                        ->orWhere('input_text', 'like', '%soạn đơn%')
                        ->orWhere('input_text', 'like', '%xác nhận đơn%')
                        ->orWhere('input_text', 'like', '%confirm%')
                        ->orWhere('title', 'like', '%mẫu%')
                        ->orWhere('title', 'like', '%template%');
                })
                // Ưu tiên rule cho đúng service_type
                ->orderByRaw("CASE WHEN input_text LIKE '%{$serviceType}%' THEN 0 ELSE 1 END")
                ->first();
        });

        if (!$template)
            return null;

        // Lấy nội dung template
        $tpl = is_array($template->output_data)
            ? implode("\n", $template->output_data)
            : (string) $template->output_data;

        if (empty(trim($tpl)))
            return null;

        // Strip các prefix thừa mà admin hay gõ vào form
        $stripPrefixes = [
            'Soạn theo mẫu:',
            'Soạn theo mau:',
            'Template:',
            'Mẫu:',
            'Soạn:',
            'Định dạng:',
            'Format:',
        ];
        foreach ($stripPrefixes as $prefix) {
            if (stripos(trim($tpl), $prefix) === 0) {
                $tpl = trim(mb_substr(trim($tpl), mb_strlen($prefix)));
                break;
            }
        }

        // Thay thế biến
        $vars = [
            '[số đơn]' => $order->id,
            '[id]' => $order->id,
            '[tên hàng]' => $order->order_note ?: 'Chưa có',
            '[hàng]' => $order->order_note ?: 'Chưa có',
            '[nội dung]' => $order->order_note ?: 'Chưa có',

            // Địa chỉ giao (nhận)
            '[địa chỉ]' => $order->delivery_address ?: 'Sẽ cập nhật',
            '[giao]' => $order->delivery_address ?: 'Sẽ cập nhật',
            '[delivery_address]' => $order->delivery_address ?: 'Sẽ cập nhật',
            '[địa chỉ nhận]' => $order->delivery_address ?: 'Sẽ cập nhật',

            // SĐT người nhận
            '[SĐT]' => $order->delivery_phone ?: 'N/A',
            '[sdt]' => $order->delivery_phone ?: 'N/A',
            '[delivery_phone]' => $order->delivery_phone ?: 'N/A',
            '[sdt nhận]' => $order->delivery_phone ?: 'N/A',
            '[sđt khách]' => $order->delivery_phone ?: 'N/A',

            // Phí
            '[phí]' => $feeFormatted,
            '[số tiền]' => $feeFormatted,
            '[fee]' => $feeFormatted,
            '[phí ship]' => $feeFormatted,

            // Địa chỉ lấy (gửi)
            '[điểm lấy]' => $order->pickup_address ?: 'N/A',
            '[lấy]' => $order->pickup_address ?: 'N/A',
            '[pickup_address]' => $order->pickup_address ?: 'N/A',
            '[địa chỉ gửi]' => $order->pickup_address ?: 'N/A',

            // SĐT người gửi
            '[SĐT lấy]' => $order->pickup_phone ?: 'N/A',
            '[pickup_phone]' => $order->pickup_phone ?: 'N/A',
            '[sdt gửi]' => $order->pickup_phone ?: 'N/A',

            '[dịch vụ]' => $order->service_type,
        ];

        $result = str_ireplace(array_keys($vars), array_values($vars), $tpl);

        // Xóa ** nếu admin vô tình gõ vào mẫu
        $result = str_replace(['**', '__'], '', $result);

        Log::info("buildConfirmedMessage: Dùng custom template từ AiKnowledge #{$template->id} cho đơn #{$order->id}");
        return $result;
    }


    protected function buildTopupMessage(Order $order, string $feeFormatted = 'Đang tính...'): string
    {
        $note = $order->order_note ?? '';
        $address = $order->delivery_address ?? '';
        $phone = $order->delivery_phone ?? 'N/A';

        return "💳 Đơn nạp tiền"
            . "\n📍 Địa chỉ khách: " . ($address ?: 'Chưa cập nhật')
            . "\n📞 SĐT liên hệ: {$phone}"
            . ($note ? "\n📋 Nội dung: {$note}" : '')
            . "\n💰 Phí dịch vụ: {$feeFormatted}";
    }

    protected function getSystemInstruction(?Shop $shop = null, ?int $cityId = null): string
    {
        $cityName = $this->getCityName($cityId) ?: 'Kiên Giang';
        $cityContext = "KHU VỰC: {$cityName}.";
        $knowledgeContext = $this->loadKnowledgeContext($cityId);
        $salutation = $this->salutation();

        // ✅ Inject thời gian thực — AI dùng để tính đúng 'hôm nay', 'ngày mai'
        $now = now()->timezone('Asia/Ho_Chi_Minh');
        $nowContext = "THỜI GIAN HIỆN TẠI: {$now->format('H:i, d/m/Y')} (giờ Việt Nam).";
        $todayDate = $now->toDateString();
        $tomorrowDate = $now->copy()->addDay()->toDateString();

        $shopContext = $shop
            ? "KHÁCH HÀNG: CỬA HÀNG\n- Tên shop: {$shop->name}\n- SĐT shop: {$shop->phone}\n- Địa chỉ shop: {$shop->address}\n→ Khi AI gọi create_order, hệ thống tự điền thông tin thiếu dựa trên quy tắc admin."
            : "KHÁCH HÀNG: KHÁCH LẺ (không phải shop)\n→ Cần thu thập đủ: pickup_address, pickup_phone, delivery_address, delivery_phone trước khi tạo đơn.";

        return <<<PROMPT
BẠN LÀ AI TỔNG ĐÀI FLASHSHIP — hỗ trợ đặt đơn giao hàng nội ô chuyên nghiệp.
{$cityContext}
{$nowContext}
{$shopContext}

CÁC LOẠI DỊCH VỤ: delivery (giao hàng) | shopping (mua hộ) | bike (xe ôm) | motor/car (lái hộ xe) | topup (nạp tiền)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 QUY TẮC ADMIN — NGUỒN SỰ THẬT DUY NHẤT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$knowledgeContext}

⚠️ Khi tin nhắn khớp quy tắc Admin ở trên:
   → Làm ĐÚNG theo quy tắc, bỏ qua mọi suy luận mặc định.
   → Admin là người dạy — AI chỉ thực thi.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 NGUYÊN TẮC XỬ LÝ
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

1. ĐỌC NGỮ CẢNH → SUY LUẬN → GỌI TOOL (không hỏi thừa):
   - Đọc toàn bộ lịch sử hội thoại trước khi phản hồi.
   - Hiểu ngôn ngữ tự nhiên, tiếng địa phương, viết tắt, typo.
   - Nếu đủ thông tin → gọi tool ngay, không hỏi thêm.
   - Nếu thiếu → hỏi DUY NHẤT 1 thông tin còn thiếu, thật cụ thể.
   - Thời gian hẹn (Scheduled): Nếu shop hẹn giờ (vd: "15h chiều", "8h sáng mai"), phải tính CHÍNH XÁC theo giờ hiện tại được cung cấp:
     + "hôm nay" / "chiều nay" / "sáng nay" = ngày {$todayDate}
     + "ngày mai" / "sáng mai" / "chiều mai" = ngày {$tomorrowDate}
     + format trả về: Y-m-d H:i:s (vd: "8h sáng mai" → "{$tomorrowDate} 08:00:00")
   - Nếu không chắc → tạo đơn với "Sẽ cung cấp sau", không từ chối.
   🚫 Không được dùng: "em chưa hiểu", "bạn nói lại", "em không rõ".

2. CHỌN ĐÚNG TOOL:
   - "Bao nhiêu", "phí ship", "tính giá" OR chỉ có địa chỉ (KHÔNG SĐT / KHÔNG yêu cầu chốt) → calculate_fee
   - "Lên đơn", "chốt đơn", "qua lấy", "địa chỉ + SĐT", "gọi [số]" → create_order.
   - Đang có đơn chờ địa chỉ + tin nhắn là địa chỉ/SĐT → update_order_address
   - "thêm [đồ]" / "bổ sung" → update_order_items
   - "hủy đơn" → cancel_order
   - "đơn đâu" / "kiểm tra" → get_order_status
   - Hỏi chính sách / giờ / quy trình → answer_faq
   - Khiếu nại / mất hàng / hoàn tiền → escalate_to_manager

3. PHONG CÁCH:
   - Tiếng Việt, ngắn gọn, thân thiện, xưng "em", gọi khách bằng "{$salutation}".
   - KHÔNG dùng ** hoặc __ (Zalo không render markdown).
   - KHÔNG nói "Em sẽ tạo..." rồi dừng — gọi tool ngay.

4. LOGIC ĐẶC THÙ CHO ĐỐI TÁC SHOP:
   - Mặc định: Điểm Lấy (Pickup) là địa chỉ của Shop. Điểm Giao (Delivery) là địa chỉ khách.
   - NGOẠI LỆ "ĐƠN TỔNG": CHỈ áp dụng khi Shop gửi TỪ 2 ĐỊA CHỈ GIAO TRỞ LÊN trong 1 tin nhắn.
       + PHẢI gọi tool create_order riêng cho MỖI địa chỉ (vd: 10 địa chỉ → 10 lệnh create_order).
       + Note cho từng đơn phải bắt đầu bằng "[ĐƠN TỔNG - NHÓM 1 SHIPPER]" (hoặc số shipper khách yêu cầu).
       + Điều này giúp dispatcher gộp đơn cho shipper đi giao thuận tiện.
       + QUAN TRỌNG: Nếu chỉ có 1 địa chỉ + 1 SĐT → ĐÂY LÀ ĐƠN ĐƠN LẺ BÌNH THƯỜNG, KHÔNG gắn prefix [ĐƠN TỔNG] vào note.
   - NGOẠI LỆ "ĐEM VỀ SHOP": Nếu tin nhắn có BẤT KỲ cụm nào sau đây:
     "đem về shop", "về shop", "về cho shop", "về cho em", "giúp shop", "hộ shop", "cho shop", "lấy về shop", "lấy giúp shop", "lấy hộ shop":
       → LUÔN truyền delivery_address = "về shop" vào Tool create_order
       + Điểm Lấy (Pickup): Địa điểm khách nhắc (vd: bến xe, cửa hàng khác).
       + SĐT Lấy (Pickup Phone): SỐ ĐIỆN THOẠI TRONG TIN NHẮN (người ở điểm lấy).
       + Điểm Giao (Delivery): Phải là "về shop" để hệ thống tự resolve.
       + SĐT Giao (Delivery Phone): Không cần nhắn — hệ thống sẽ tự điền SĐT Shop.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 CÁC TOOLS VÀ THAM SỐ
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

create_order(
  service_type,      // loại dịch vụ: delivery|shopping|bike|motor|car|topup
  pickup_address,    // điểm LẤY hàng / đón khách
  pickup_phone,      // SĐT điểm lấy — nếu không có: "Sẽ cung cấp sau"
  delivery_address,  // điểm GIAO / điểm đến — nếu không có: "Sẽ cung cấp sau"
                     // "về shop" / "giao về shop" → hệ thống tự resolve thành địa chỉ shop
  delivery_phone,    // SĐT người nhận — nếu không có: "Sẽ cung cấp sau"
  items,             // tên hàng (không bắt buộc với shop)
  receiver_name      // tên người nhận (không bắt buộc)
)

update_order_address(delivery_address, delivery_phone)
update_order_items(items, mode)  // mode: "append" (mặc định) | "replace"
cancel_order()
get_order_status()
calculate_fee(pickup_address, delivery_address, service_type)
escalate_to_manager(reason, urgency, summary)
answer_faq()

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 NHẬN DẠNG NGÔN NGỮ ĐỊA PHƯƠNG
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

- "gé/ghé/gé qua" = đến/ghé | "ky/kêu" = gọi/đặt | "bển" = bên đó
- "nha c"/"nhe"/"nè chị"/"nha" cuối câu = câu đệm → bỏ qua khi parse địa chỉ
- "12prm"/"3g20"/"chiều" = giờ → không phải địa chỉ
- "vị" = suất/phần | "đặng" = để/cho | "mi/mầy" = bạn/anh
- Tên địa danh ngắn ("hoàng thụ", "hyundai", "số 01") → giữ nguyên làm địa chỉ
- Số 10-11 chữ số bắt đầu bằng 0 = SĐT → số khác = số nhà/địa chỉ
PROMPT;
    }
    /**
     * Bóc tách siêu tốc bằng Regex (Không gọi AI)
     * Thiết kế riêng cho mẫu đơn truyền thống của Tổng đài.
     */
    private function tryFastRegexParse(string $text): ?array
    {
        // 1. CHUẨN HÓA: Thay thế các ký tự đặc biệt/biểu tượng để dễ Regex
        $cleanText = str_replace(['☎️', '📞', 'SĐT:', 'Sđt:', 'Số đt:', 'Số điện thoại:'], 'PHONE_LABEL', $text);

        // 2. KHỞI TẠO DATA MẶC ĐỊNH
        $data = [
            'service_type' => 'delivery',
            'pickup_address' => '',
            'pickup_phone' => '',
            'sender_name' => '',
            'delivery_address' => '',
            'delivery_phone' => '',
            'receiver_name' => '',
            'items' => '',
            'shipping_fee' => 0,
            'is_incomplete' => true,
        ];

        // 3. BÓC TÁCH KHỐI LẤY (A)
        // Lấy đoạn văn giữa Điểm lấy đơn và Điểm giao đơn
        if (preg_match('/(?:Điểm lấy đơn|Nơi lấy|Lấy tại|Pick up):?\s*(.*?)(?=\s*(?:Điểm giao đơn|Nơi giao|Giao tại|Delivery|Phí ship|$))/si', $cleanText, $matchA)) {
            $blockA = trim($matchA[1]);
            if (preg_match('/PHONE_LABEL\s*(\d{7,11})/', $blockA, $phoneA)) {
                $data['pickup_phone'] = $phoneA[1];
                $data['pickup_address'] = trim(str_replace($phoneA[0], '', $blockA));
            } else {
                $data['pickup_address'] = $blockA;
            }
        }

        // 4. BÓC TÁCH KHỐI GIAO (B)
        if (preg_match('/(?:Điểm giao đơn|Nơi giao|Giao tại|Delivery):?\s*(.*?)(?=\s*(?:Phí ship|Ghi chú|Note|Tiền ship|$))/si', $cleanText, $matchB)) {
            $blockB = trim($matchB[1]);
            if (preg_match('/PHONE_LABEL\s*(\d{7,11})/', $blockB, $phoneB)) {
                $data['delivery_phone'] = $phoneB[1];
                $data['delivery_address'] = trim(str_replace($phoneB[0], '', $blockB));
            } else {
                $data['delivery_address'] = $blockB;
            }
        }

        // 5. BÓC TÁCH PHÍ SHIP
        if (preg_match('/(?:Phí ship|Tiền ship|Ship|Fee):?\s*(\d+k?|\d+\.\d+k?)/i', $cleanText, $matchFee)) {
            $feeStr = strtolower($matchFee[1]);
            if (str_contains($feeStr, 'k')) {
                $data['shipping_fee'] = (int) str_replace('k', '', $feeStr) * 1000;
            } else {
                $data['shipping_fee'] = (int) $feeStr;
            }
        }

        // 6. KIỂM TRA ĐỘ HOÀN THIỆN
        if (!empty($data['pickup_address']) && !empty($data['delivery_address'])) {
            $data['is_incomplete'] = false;
        }

        if (empty($data['pickup_address']) && empty($data['delivery_address'])) {
            return null;
        }

        return $data;
    }
}
