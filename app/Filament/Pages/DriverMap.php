<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\User;

class DriverMap extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-map';
    protected static ?string $navigationLabel = 'Bản đồ thời gian thực';
    protected static ?string $navigationGroup = 'QUẢN LÝ TÀI XẾ';
    protected static ?int    $navigationSort  = 10;
    protected static ?string $title           = 'Bản đồ tài xế';

    protected static string $view = 'filament.resources.user-resource.pages.driver-map';

    protected function getViewData(): array
    {
        $user = auth()->user();

        // Dispatcher/manager dùng city_id của tài khoản, admin dùng session city switcher
        $cityId = $user->hasRole('admin')
            ? session('current_city_id')
            : $user->city_id;

        $city = \App\Models\City::find($cityId);

        $center = [
            'lat' => (float) ($city?->latitude  ?: 10.038),
            'lng' => (float) ($city?->longitude ?: 105.782),
        ];

        return [
            'googleMapsKey' => config('services.google.maps_key'),
            'center'        => $center,
            'cityName'      => $city?->name ?? null,
            'currentCityId' => $cityId,
            'firebaseConfig' => [
                'apiKey'            => config('services.firebase.api_key'),
                'authDomain'        => config('services.firebase.auth_domain'),
                'databaseURL'       => config('services.firebase.database_url'),
                'projectId'         => config('services.firebase.project_id'),
                'storageBucket'     => config('services.firebase.storage_bucket'),
                'messagingSenderId' => config('services.firebase.messaging_sender_id'),
                'appId'             => config('services.firebase.app_id'),
            ],
        ];
    }
}

