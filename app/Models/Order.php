<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasCityScope;

class Order extends Model
{
    use HasCityScope;

    protected static function booted()
    {
        // static::observe(\App\Observers\OrderObserver::class); // Đã có trong AppServiceProvider

        static::creating(function ($order) {
            // MẶC ĐỊNH LÀ PENDING (KHÔNG dùng 'draft' để tránh nổ chuông 2 lần trên App)
            if (empty($order->status) || $order->status === 'draft') {
                $order->status = 'pending';
            }
            $order->bonus_fee = $order->bonus_fee ?? 0;
            $order->shipping_fee = $order->shipping_fee ?? 0;
            $order->is_freeship = $order->is_freeship ?? false;
        });

        static::saving(function ($order) {
            $order->bonus_fee = $order->bonus_fee ?? 0;
            $order->shipping_fee = $order->shipping_fee ?? 0;
            $order->is_freeship = $order->is_freeship ?? false;
        });
        static::created(function ($order) {
            \App\Services\FirebaseRTDBService::publishOrderEvent($order);
        });

        static::updated(function ($order) {
            \App\Services\FirebaseRTDBService::publishOrderEvent($order);
        });
    }

    protected $fillable = [
        'pickup_address',
        'pickup_phone',
        'sender_name',
        'delivery_address',
        'delivery_phone',
        'receiver_name',
        'city_id',
        'delivery_man_id',
        'status',
        'shipping_fee',
        'service_type',
        'order_note',
        'is_ai_created',
        'is_freeship',
        'bonus_fee',
        'distance',
        'shop_id',
        'completed_at',
        'delivered_at',
        'scheduled_at',
        'created_by',
        'sender_platform_id',
        'platform',
    ];


    protected $casts = [
        'delivery_man_id' => 'integer',
        'city_id' => 'integer',
        'is_ai_created' => 'boolean',
        'is_freeship' => 'boolean',
        'completed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'delivery_man_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    protected $appends = ['city_name'];

    public function getCityNameAttribute()
    {
        return $this->city?->name ?? 'N/A';
    }

    public function histories()
    {
        return $this->hasMany(OrderHistory::class)->latest();
    }
}
