<?php

namespace App\Channels;

use App\Helpers\FcmHelper;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $token = $notifiable->fcm_token ?? null;
        if (empty($token)) return;

        $message = $notification->toFcm($notifiable);

        FcmHelper::sendSingle(
            $token,
            $message['title'],
            $message['body'],
            $message['data'] ?? [],
            $message['collapse_id'] ?? null,
        );
    }
}
