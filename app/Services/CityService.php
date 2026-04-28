<?php

namespace App\Services;

use App\Models\City;
use Illuminate\Support\Collection;

class CityService
{
    /**
     * Danh sách khu vực cho Flutter app.
     * Chỉ trả khu vực đang hoạt động, kèm type gói hiện tại.
     */
    public function getForApp(): Collection
    {
        return City::select('id', 'name')
            ->active()
            ->with(['activePlan:id,city_id,type'])
            ->orderBy('name')
            ->get()
            ->map(fn($city) => [
                'id'   => $city->id,
                'name' => $city->name,
                'type' => $city->activePlan?->type ?? 'chiết khấu',
            ]);
    }

    /**
     * Danh sách id + name cho dropdown trong admin panel.
     */
    public function getForSelect(): Collection
    {
        return City::select('id', 'name')->orderBy('name')->get();
    }
}
