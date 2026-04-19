<?php

namespace App\Services;

use App\Models\AiKnowledge;
use App\Models\City;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * AiTeachingService — AI nhận lệnh dạy từ Admin và tự lưu vào DB
 *
 * Admin nói: "bv có nghĩa là bệnh viện đa khoa tỉnh kiên giang nhe"
 * AI hiểu → lưu shortcut: bv → Bệnh viện Đa Khoa Tỉnh Kiên Giang
 * AI xác nhận: "Em đã ghi nhớ rồi ạ! ✅"
 */
class AiTeachingService
{
    protected string $apiKey;
    protected string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key', env('GEMINI_API_KEY'));
    }

    /**
     * Xử lý tin nhắn dạy từ Admin
     * Trả về: ['saved' => bool, 'type' => string, 'content' => string, 'knowledge' => AiKnowledge|null]
     */
    public function processTeachingMessage(string $adminMessage, ?int $cityId = null): array
    {
        $cityName     = $cityId ? (City::find($cityId)?->name ?? 'Tất cả khu vực') : 'Tất cả khu vực';
        $systemPrompt = $this->buildTeachingSystemPrompt($cityName);

        $response = $this->callGemini([
            ['role' => 'user', 'parts' => [['text' => $adminMessage]]]
        ], $systemPrompt);

        if (!$response) {
            return [
                'saved'   => false,
                'type'    => 'error',
                'content' => '❌ Xin lỗi, em đang gặp sự cố kết nối. Anh thử lại sau nhé!',
                'knowledge' => null,
            ];
        }

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Parse JSON từ AI response
        $parsed = $this->parseAiResponse($text);

        if (!$parsed || $parsed['action'] === 'none') {
            // Chỉ là câu hỏi/chat thường, không có gì cần lưu
            return [
                'saved'     => false,
                'type'      => 'chat',
                'content'   => $parsed['reply'] ?? $text,
                'knowledge' => null,
            ];
        }

        // Lưu vào DB
        $knowledge = $this->saveKnowledge($parsed, $cityId);

        // Xóa cache để kiến thức mới áp dụng ngay (không cần restart worker/supervisor)
        try {
            \Artisan::call('cache:clear');
        } catch (\Throwable) {
            // Fallback: xóa từng key theo từng khu vực
            $cityIds = $cityId ? [$cityId, null] : [null];
            foreach ($cityIds as $cid) {
                $suffix = $cid ? $cid : 'global';
                Cache::forget("ai_shortcuts_city_{$suffix}");
                Cache::forget("ai_knowledge_context_city_{$suffix}");
                Cache::forget("ai_inference_context_city_{$suffix}");
                Cache::forget("ai_rules_for_matching_city_{$suffix}"); // ← Rule pre-match engine
                foreach (['delivery', 'shopping', 'bike', 'motor', 'car', 'topup'] as $type) {
                    Cache::forget("ai_confirm_tpl_{$type}_{$cid}");
                }
            }
            Cache::forget('ai_surcharge_update_shopping');
            Cache::forget('ai_salutation');
        }

        Log::info("AiTeachingService: Đã lưu [{$parsed['action']}] — '{$parsed['input']}' → '{$parsed['output']}'");

        return [
            'saved'     => true,
            'type'      => $parsed['action'],
            'content'   => $parsed['reply'],
            'knowledge' => $knowledge,
            'input'     => $parsed['input'],
            'output'    => $parsed['output'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────

    protected function buildTeachingSystemPrompt(string $cityName = 'Tất cả khu vực'): string
    {
        $cityContext = $cityName !== 'Tất cả khu vực'
            ? "KHU VỰC ĐANG TRAIN: **{$cityName}** — Kiến thức này chỉ áp dụng cho khu vực {$cityName}."
            : "KHU VỰC: Tất cả khu vực — Kiến thức này áp dụng toàn quốc.";

        return <<<PROMPT
Bạn là trợ lý học hỏi của hệ thống AI Flashship — dịch vụ giao hàng nội ô.
{$cityContext}
Admin đang DẠY bạn các kiến thức mới: từ viết tắt, địa danh địa phương, quy tắc xử lý, hoặc ví dụ mẫu.

NHIỆM VỤ:
1. Phân tích tin nhắn admin
2. Xác định đây là loại dạy gì (shortcut / rule / example / none)
3. Trích xuất thông tin cần lưu
4. Trả về JSON CHÍNH XÁC theo format bên dưới

PHÂN LOẠI:

"shortcut" — Khi admin dạy từ viết tắt / địa danh / cụm từ địa phương
  Ví dụ:
  - "bv có nghĩa là bệnh viện đa khoa tỉnh kiên giang nhe"
  - "chợ VTV là chợ Vĩnh Thanh Vân"
  - "ĐH KG = Đại học Kiên Giang"
  - "bn là bao nhiêu"
  - "cổng sau là cổng sau chợ Rạch Sỏi"

"rule" — Khi admin dạy quy tắc xử lý / hành vi / tình huống đặc biệt
  Ví dụ:
  - "khi khách nói gấp thì báo thêm 5k phụ phí"
  - "đơn nào có 3 điểm giao thì hỏi lại vì không hỗ trợ"
  - "nếu khách hỏi giờ làm việc thì trả lời 7h-21h"
  - "khi khách chửi thề thì vẫn giữ thái độ lịch sự"
  - "shop Hằng Lê ưu tiên xử lý nhanh hơn"

"example" — Khi admin cho ví dụ về cách xử lý một tin nhắn cụ thể
  Ví dụ:
  - "nếu khách nhắn 'bún đậu mạc cửu' thì đây là đơn shop bún đậu ở đường X"
  - "khi khách nhắn 'order cũ' là khách muốn xem đơn trước đó"

"none" — Câu hỏi thông thường, không phải lệnh dạy
  Ví dụ: "em đã học được bao nhiêu từ khóa?", "xin chào", "em làm được gì?"

FORMAT TRẢ VỀ (LUÔN là JSON hợp lệ, không có text thừa):

{
  "action": "shortcut" | "rule" | "example" | "none",
  "input": "từ/cụm từ/tình huống cần nhận dạng",
  "output": "giá trị đầy đủ cần thay thế / hành động",
  "title": "tên gợi nhớ ngắn gọn",
  "reply": "tin nhắn xác nhận gửi lại cho admin (thân thiện, bằng tiếng Việt)"
}

QUY TẮC CHO REPLY:
- shortcut: "✅ Em đã ghi nhớ rồi ạ! Từ giờ khi khách nhắn **'{input}'** em sẽ hiểu là **'{output}'** nhé! 🎓"
- rule: "✅ Dạ, em đã học quy tắc này rồi ạ! Em sẽ nhớ: {tóm tắt quy tắc} 📋"
- example: "✅ Dạ, em đã ghi nhớ ví dụ này và sẽ xử lý tương tự khi gặp tình huống đó ạ! 📚"
- none: trả lời tự nhiên, hữu ích

VÍ DỤ OUTPUT:
Input: "bv có nghĩa là bệnh viện đa khoa tỉnh kiên giang nhe"
Output:
{
  "action": "shortcut",
  "input": "bv",
  "output": "Bệnh viện Đa Khoa Tỉnh Kiên Giang",
  "title": "Viết tắt: bv",
  "reply": "✅ Em đã ghi nhớ rồi ạ! Từ giờ khi khách nhắn **'bv'** em sẽ hiểu là **'Bệnh viện Đa Khoa Tỉnh Kiên Giang'** nhé! 🎓"
}
PROMPT;
    }

    protected function callGemini(array $contents, string $systemPrompt): ?array
    {
        if (!$this->apiKey) return null;

        try {
            $response = Http::timeout(20)->post("{$this->apiUrl}?key={$this->apiKey}", [
                'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents'           => $contents,
                'generationConfig'   => [
                    'temperature'       => 0.1,
                    'max_output_tokens' => 500,
                    'response_mime_type' => 'application/json',
                ],
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('AiTeachingService Gemini Error: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('AiTeachingService Exception: ' . $e->getMessage());
        }

        return null;
    }

    protected function parseAiResponse(string $text): ?array
    {
        // Xóa markdown code block nếu có
        $text = preg_replace('/```json\s*/i', '', $text);
        $text = preg_replace('/```\s*/i', '', $text);
        $text = trim($text);

        $data = json_decode($text, true);
        if (!$data || !isset($data['action'])) {
            Log::warning('AiTeachingService: Không parse được response: ' . $text);
            return null;
        }

        return $data;
    }

    protected function saveKnowledge(array $parsed, ?int $cityId): AiKnowledge
    {
        $outputData = match ($parsed['action']) {
            'shortcut' => $parsed['output'],
            'rule'     => $parsed['output'],
            'example'  => [
                'ai_response' => $parsed['output'],
                'context'     => $parsed['input'],
            ],
            default => $parsed['output'],
        };

        return AiKnowledge::create([
            'title'      => $parsed['title'] ?? mb_substr($parsed['input'], 0, 50),
            'type'       => $parsed['action'],
            'input_text' => $parsed['input'],
            'output_data'=> $outputData,
            'is_active'  => true,
            'city_id'    => $cityId ?: null,
        ]);
    }

    /**
     * Thống kê AI đã học được — lọc theo city nếu có
     */
    public function getLearningSummary(?int $cityId = null): array
    {
        $q = fn(string $type) => AiKnowledge::where('type', $type)
            ->where('is_active', true)
            ->when($cityId, fn($q) => $q->where('city_id', $cityId));

        return [
            'shortcuts' => $q('shortcut')->count(),
            'rules'     => $q('rule')->count(),
            'examples'  => $q('example')->count(),
            'total'     => $q('shortcut')->count() + $q('rule')->count() + $q('example')->count(),
        ];
    }
}
