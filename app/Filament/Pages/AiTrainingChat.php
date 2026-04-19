<?php

namespace App\Filament\Pages;

use App\Models\AiKnowledge;
use App\Models\AiConversation;
use App\Models\City;
use App\Models\Shop;
use App\Services\AiOrderService;
use App\Services\AiTeachingService;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AiTrainingChat extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Chat dạy AI';
    protected static ?string $navigationGroup = 'CẤU HÌNH AI & OA';
    protected static ?int    $navigationSort  = 2;
    protected static string  $view            = 'filament.pages.ai-training-chat';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    // ── Tab ──────────────────────────────────────────────────────────────────
    public string $activeTab = 'teach'; // 'teach' | 'simulate'

    // ── Tab Dạy AI ───────────────────────────────────────────────────────────
    public array  $teachMessages  = [];
    public string $teachInput     = '';
    public int    $selectedCityId = 0;

    // ── Tab Giả lập ──────────────────────────────────────────────────────────
    public array  $simMessages    = [];
    public string $simInput       = '';
    public string $senderMode     = 'shop';
    public int    $selectedShopId = 0;
    public int    $simCityId      = 0;
    public string $lastToolCalled = '';

    protected string $fakeSenderId = '';

    // ─────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->fakeSenderId   = 'admin_sim_' . auth()->id();
        
        // Tự động chọn khu vực theo "switch area" (session)
        $sessionCityId        = session('current_city_id', 0);
        $this->selectedCityId = $sessionCityId ?: (City::first()?->id ?? 0);
        $this->simCityId      = $sessionCityId ?: (City::first()?->id ?? 0);

        $this->teachMessages = [
            [
                'role'    => 'ai',
                'content' => "Chào Quản trị viên! Tôi là **Flashy AI** — bộ não tự động hóa của Flashship. 🧠✨\n\nBạn có thể huấn luyện tôi xử lý đơn hàng thông minh bằng cách nhắn tin trực tiếp:\n1️⃣ **Định nghĩa từ viết tắt**: \"bv là bệnh viện\", \"p16 là Phường 16\"\n2️⃣ **Xác định địa danh**: \"chợ VTV là chợ Vĩnh Thanh Vân\"\n3️⃣ **Thiết lập quy tắc**: \"ưu tiên xử lý nhanh khi khách nói gấp\"\n\nNhững gì bạn dạy sẽ được lưu vào **Kho kiến thức** và áp dụng ngay lập tức cho các cuộc hội thoại thực tế trên Zalo OA!",
                'time'    => now()->format('H:i'),
            ]
        ];

        $this->simMessages = [
            [
                'role'    => 'system',
                'content' => '🏗️ **Hệ thống giả lập sẵn sàng.** Bạn có thể nhập vai Khách lẻ hoặc Shop để kiểm tra khả năng phản hồi của AI sau khi đã được huấn luyện.',
                'time'    => now()->format('H:i'),
            ]
        ];
    }

    // ── Computed ─────────────────────────────────────────────────────────────

    public function getShopsProperty()
    {
        return Shop::orderBy('name')->get(['id', 'name', 'phone']);
    }

    public function getCitiesProperty()
    {
        return City::orderBy('name')->get(['id', 'name']);
    }

    public function getLearningSummaryProperty(): array
    {
        return app(AiTeachingService::class)->getLearningSummary($this->selectedCityId ?: null);
    }

    public function getRecentKnowledgeProperty()
    {
        return AiKnowledge::when($this->selectedCityId, fn($q) => $q->where('city_id', $this->selectedCityId))
            ->latest()->take(5)->get(['type', 'title', 'input_text', 'created_at']);
    }

    // ── Tab Dạy AI — Send ─────────────────────────────────────────────────────

    public function sendTeachMessage(): void
    {
        $text = trim($this->teachInput);
        if (!$text) return;

        // Hiện tin nhắn admin
        $this->teachMessages[] = [
            'role'    => 'admin',
            'content' => $text,
            'time'    => now()->format('H:i'),
        ];
        $this->teachInput = '';

        try {
            $service = app(AiTeachingService::class);
            $result  = $service->processTeachingMessage($text, $this->selectedCityId ?: null);

            $this->teachMessages[] = [
                'role'    => 'ai',
                'content' => $result['content'],
                'time'    => now()->format('H:i'),
                'saved'   => $result['saved'],
                'type'    => $result['type'] ?? 'chat',
                'input'   => $result['input'] ?? null,
                'output'  => $result['output'] ?? null,
            ];

            // Toast nếu lưu thành công
            if ($result['saved']) {
                Notification::make()
                    ->title('AI đã học xong! ✅')
                    ->body('Đã lưu vào Kho kiến thức — áp dụng ngay cho Zalo.')
                    ->success()
                    ->duration(3000)
                    ->send();
            }

        } catch (\Throwable $e) {
            $this->teachMessages[] = [
                'role'    => 'ai',
                'content' => '❌ Lỗi: ' . $e->getMessage(),
                'time'    => now()->format('H:i'),
                'saved'   => false,
            ];
            Log::error('AiTrainingChat Teach Error: ' . $e->getMessage());
        }
    }

    // ── Tab Giả lập — Send ───────────────────────────────────────────────────

    public function sendSimMessage(): void
    {
        $text = trim($this->simInput);
        if (!$text) return;

        $this->simMessages[] = [
            'role'    => 'user',
            'content' => $text,
            'time'    => now()->format('H:i'),
        ];
        $this->simInput       = '';
        $this->lastToolCalled = '';

        try {
            $shop    = ($this->senderMode === 'shop' && $this->selectedShopId > 0)
                ? Shop::find($this->selectedShopId)
                : null;
            $cityId  = $this->simCityId ?: null;

            $aiService = app(AiOrderService::class);
            $result    = $aiService->parseWithContext($text, $this->fakeSenderId, $shop, $cityId);

            $this->extractLastTool();

            $this->simMessages[] = [
                'role'    => 'assistant',
                'content' => $result['content'] ?? '',
                'type'    => $result['type'] ?? 'text',
                'time'    => now()->format('H:i'),
                'tool'    => $this->lastToolCalled,
            ];

        } catch (\Throwable $e) {
            $this->simMessages[] = [
                'role'    => 'error',
                'content' => '❌ ' . $e->getMessage(),
                'time'    => now()->format('H:i'),
            ];
        }
    }

    public function clearSimSession(): void
    {
        AiConversation::where('sender_id', $this->fakeSenderId)->delete();
        $this->simMessages = [[
            'role'    => 'system',
            'content' => '🔄 Session đã xóa. Bắt đầu hội thoại mới.',
            'time'    => now()->format('H:i'),
        ]];
        $this->lastToolCalled = '';
        Notification::make()->title('Đã xóa session')->success()->send();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function extractLastTool(): void
    {
        try {
            $lines = array_slice(file(storage_path('logs/laravel.log')), -80);
            foreach (array_reverse($lines) as $line) {
                if (str_contains($line, 'AI Agent: Gọi Tool')) {
                    preg_match('/Gọi Tool \[([^\]]+)\]/', $line, $m);
                    if ($m[1] ?? null) { $this->lastToolCalled = $m[1]; break; }
                }
            }
        } catch (\Throwable) {}
    }

    public function getTitle(): string { return '🤖 Chat dạy AI'; }

    public static function getNavigationBadge(): ?string        { return 'NEW'; }
    public static function getNavigationBadgeColor(): ?string   { return 'success'; }
}
