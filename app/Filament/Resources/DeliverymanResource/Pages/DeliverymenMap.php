<?php

namespace App\Filament\Resources\DeliverymanResource\Pages;

use App\Filament\Resources\DeliverymanResource;
use Filament\Resources\Pages\Page;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class DeliverymenMap extends Page
{
    protected static string $resource = DeliverymanResource::class;

    protected static string $view = 'filament.resources.deliveryman-resource.pages.deliverymen-map';

    protected static ?string $title = 'Vị trí Người Giao Hàng';

    public $drivers = [];

    public function mount()
    {
        $this->drivers = User::role('driver')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->withCount(['orders as active_orders_count' => function ($query) {
                $query->whereNotIn('status', ['completed', 'cancelled', 'draft']);
            }])
            ->get(['id', 'name', 'latitude', 'longitude', 'status', 'profile_photo_path'])
            ->map(function ($driver) {
                $driver->avatar_url = $driver->profile_photo_path
                    ? Storage::url($driver->profile_photo_path)
                    : 'https://cdn-icons-png.flaticon.com/512/149/149071.png'; // ảnh default
                $driver->is_busy = $driver->active_orders_count > 0;
                return $driver;
            });
    }

}
