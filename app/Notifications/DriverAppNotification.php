<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Notifications\Notification;

class DriverAppNotification extends Notification
{

    public function __construct(
        private string  $title,
        private string  $message,
        private string  $type = 'info',
        private array   $data = [],
        private ?string $collapseId = null,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (!empty($notifiable->fcm_token)) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title'       => $this->title,
            'body'        => $this->message,
            'data'        => array_merge($this->data, ['type' => $this->type]),
            'collapse_id' => $this->collapseId,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return array_merge($this->data, [
            'title'   => $this->title,
            'message' => $this->message,
            'type'    => $this->type,
        ]);
    }
}
