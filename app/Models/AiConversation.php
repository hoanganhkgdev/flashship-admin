<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    protected $fillable = [
        'sender_id',
        'platform',
        'context_data',   // Vẫn giữ để lưu data đơn hàng đang thu thập
        'messages',       // Lịch sử hội thoại full [{role, content}]
        'last_interacted_at',
    ];

    protected $casts = [
        'context_data' => 'array',
        'messages' => 'array',
        'last_interacted_at' => 'datetime',
    ];

    /**
     * Thêm một tin nhắn mới vào lịch sử hội thoại
     */
    public function appendMessage(string $role, string $content): void
    {
        $messages = $this->messages ?? [];
        $messages[] = ['role' => $role, 'content' => $content];

        // Giới hạn tối đa 20 lượt hỏi đáp (40 messages) để tránh quá dài
        if (count($messages) > 40) {
            $messages = array_slice($messages, -40);
        }

        $this->messages = $messages;
    }

    /**
     * Lấy lịch sử hội thoại theo định dạng Gemini API
     * [{role: "user"|"model", parts: [{text: "..."}]}]
     */
    public function getGeminiHistory(): array
    {
        $history = [];
        foreach ($this->messages ?? [] as $msg) {
            $history[] = [
                'role' => $msg['role'],
                'parts' => [['text' => $msg['content']]],
            ];
        }
        return $history;
    }
}
