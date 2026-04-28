<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Page;
use App\Models\Setting;
use App\Models\SupportConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function page(string $slug): JsonResponse
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        return response()->json([
            'title'   => $page->title,
            'content' => $page->content,
        ]);
    }

    public function appVersion(): JsonResponse
    {
        return response()->json([
            'latest_version' => Setting::where('key', 'app_version')->value('value') ?? '1.0.0',
            'force_update'   => filter_var(
                Setting::where('key', 'force_update')->value('value'),
                FILTER_VALIDATE_BOOLEAN
            ),
            'store_url' => [
                'android' => Setting::where('key', 'store_url_android')->value('value'),
                'ios'     => Setting::where('key', 'store_url_ios')->value('value'),
            ],
        ]);
    }

    public function banners(): JsonResponse
    {
        $banners = Banner::where('is_active', true)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($b) => [
                'id'        => $b->id,
                'title'     => $b->title,
                'image_url' => asset('storage/' . $b->image),
            ]);

        return response()->json($banners);
    }

    public function supportConfigs(Request $request): JsonResponse
    {
        $cityId = $request->query('city_id');

        $user = auth('sanctum')->user();
        if ($user) {
            $cityId = $user->city_id;
        }

        $configs = SupportConfig::where('is_active', true)
            ->where(function ($query) use ($cityId) {
                $query->whereNull('city_id');
                if ($cityId) {
                    $query->orWhere('city_id', $cityId);
                }
            })
            ->orderBy('priority', 'asc')
            ->get()
            ->map(fn($s) => [
                'id'        => $s->id,
                'title'     => $s->title,
                'subtitle'  => $s->subtitle,
                'icon'      => $s->icon,
                'type'      => $s->type,
                'value'     => $s->value,
                'color'     => $s->color,
                'city_id'   => $s->city_id,
                'priority'  => $s->priority,
                'is_active' => $s->is_active,
            ]);

        return response()->json($configs);
    }
}
