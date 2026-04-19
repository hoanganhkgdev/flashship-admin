<?php

namespace App\Jobs;

use App\Helpers\FcmHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendFcmNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tokens;
    protected $title;
    protected $body;
    protected $data;
    protected $collapseId;

    /**
     * Create a new job instance.
     */
    public function __construct(array $tokens, string $title, string $body, array $data = [], ?string $collapseId = null)
    {
        $this->tokens = $tokens;
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
        $this->collapseId = $collapseId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->tokens)) {
            return;
        }

        Log::info("Job SendFcmNotification: Bắt đầu gửi cho " . count($this->tokens) . " tokens. CollapseId: {$this->collapseId}");
        
        FcmHelper::sendToMultiple($this->tokens, $this->title, $this->body, $this->data, $this->collapseId);
    }
}
