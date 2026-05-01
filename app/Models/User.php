<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Shift;
use App\Models\DriverLicense;
use App\Models\Plan;
use App\Models\Order;
use App\Models\DriverWallet;
use App\Models\DriverDebt;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Facades\Cache;

class User extends Authenticatable implements FilamentUser
{
    use HasRoles;
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'phone',
        'address',
        'city_id',
        'latitude',
        'longitude',
        'status',
        'uid',
        'profile_photo_path',
        'last_login_at',
        'player_id',   // Deprecated - giữ để tương thích ngược
        'fcm_token',   // ✅ Firebase Cloud Messaging token
        'last_notification_seen',
        'is_online',
        'shift_id',
        'has_car_license',
        'plan_id',
        'name_updated_at',
        'custom_commission_rate',
        'delete_requested_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'name_updated_at' => 'datetime',
            'delete_requested_at' => 'datetime',
            'custom_commission_rate' => 'float',
        ];
    }

    /**
     * Cho phép admin, dispatcher, accountant vào Filament panel.
     * Driver bị từ chối ở đây.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Cho phép nếu có role admin/dispatcher/accountant hoặc user_type là admin/subadmin
        return $this->hasAnyRole(['admin', 'dispatcher', 'accountant', 'subadmin', 'sub-admin', 'manager', 'editor']) 
            || in_array($this->user_type, ['admin', 'subadmin']);
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function scopeDrivers($query)
    {
        return $query->role('driver');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'delivery_man_id');
    }

    public function scopeAdmins($query)
    {
        return $query->role(['admin', 'dispatcher', 'accountant']);
    }

    public function shifts()
    {
        return $this->belongsToMany(Shift::class, 'shift_user', 'user_id', 'shift_id');
    }

    public function isInShift(): bool
    {
        $cacheKey = "user_shift_{$this->id}";

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        [$result, $ttl] = $this->_computeIsInShift();
        Cache::put($cacheKey, $result, $ttl);
        return $result;
    }

    /** @return array{bool, int} [inShift, ttlSeconds] */
    private function _computeIsInShift(): array
    {
        // Commission, partner, free, custom rate → no shift restriction
        if (!$this->plan
            || $this->plan->type === Plan::TYPE_COMMISSION
            || $this->plan->type === Plan::TYPE_PARTNER
            || $this->plan->type === Plan::TYPE_FREE
            || $this->custom_commission_rate !== null
        ) {
            return [true, 120];
        }

        // Weekly plan, no shifts assigned → blocked
        if ($this->shifts->isEmpty()) {
            return [false, 30];
        }

        // Find active shift and cache until it ends (max 120s) so expiry is precise
        foreach ($this->shifts as $shift) {
            if ($shift->isNowInShift()) {
                $ttl = max(1, min(120, $shift->secondsUntilEnd()));
                return [true, $ttl];
            }
        }

        return [false, 30];
    }

    /**
     * Xóa cache ca làm việc của tài xế — gọi khi admin đổi ca
     */
    public function clearShiftCache(): void
    {
        Cache::forget("user_shift_{$this->id}");
    }

    public function getHasCarLicenseAttribute(): bool
    {
        return $this->driverLicenses()
            ->where('status', DriverLicense::STATUS_APPROVED)
            ->exists();
    }

    public function driverLicenses()
    {
        return $this->hasMany(DriverLicense::class, 'user_id');
    }

    public function bank()
    {
        return $this->hasOne(Bank::class);
    }

    public function wallet()
    {
        return $this->hasOne(DriverWallet::class, 'driver_id');
    }
    
    public function debts()
    {
        return $this->hasMany(DriverDebt::class, 'driver_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
