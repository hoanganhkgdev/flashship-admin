<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewOrderCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;

    /**
     * Create a new event instance.
     */
    public function __construct($order)
    {
        $this->order = $order;
    }

    /**
     * Get the channels the event should broadcast on.
     * 
     * ✅ Broadcast lên channel riêng theo khu vực (city_id)
     * Tài xế chỉ subscribe channel thành phố của mình → không nhận đơn khu vực khác
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // ✅ Channel riêng theo thành phố (primary)
        if (!empty($this->order->city_id)) {
            $channels[] = new Channel("flashship-city-{$this->order->city_id}");
        }

        // ✅ Giữ channel global để backward compatibility
        // (Flutter đã có filter city_id ở client, sẽ bỏ channel này sau)
        $channels[] = new Channel('flashship-public');

        return $channels;
    }

    /**
     * Get the event name for broadcasting.
     */
    public function broadcastAs(): string
    {
        return 'order.created';
    }
}
