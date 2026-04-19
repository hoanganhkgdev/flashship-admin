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
        'zalo_id',
        'last_notification_seen',
        'is_online',
        'shift_id',
        'has_car_license',
        'plan_id',
        'name_updated_at',
        'custom_commission_rate',
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
            'name_updated_at' => 'datetime', // 🔹 Cast name_updated_at thành datetime
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
        // Cache 2 phút/user — tránh query DB mọi request
        return Cache::remember("user_shift_{$this->id}", 120, function () {
            // 🔹 Nếu là tài xế đối tác (chiết khấu riêng) HOẶC gói % (commission) HOẶC không gán gói
            // -> Cho phép Online bất kỳ lúc nào
            if ($this->custom_commission_rate !== null || !$this->plan || $this->plan->type === 'commission') {
                return true;
            }

            // 🚫 Nếu là gói tuần (weekly) nhưng chưa gán ca → Không được phép Online
            if ($this->shifts->isEmpty()) {
                return false;
            }

            // 🕒 Kiểm tra: có ca nào đang trong giờ làm việc không?
            foreach ($this->shifts as $shift) {
                if ($shift->isNowInShift()) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Xóa cache ca làm việc của tài xế — gọi khi admin đổi ca
     */
    public function clearShiftCache(): void
    {
        Cache::forget("user_shift_{$this->id}");
    }

    public function getHasCarLicenseAttribute()
    {
        return $this->driverLicenses()
            ->where('status', 'approved')
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

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
