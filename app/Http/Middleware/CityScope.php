<?php

namespace App\Http\Middleware;
use Illuminate\Support\Facades\Log;

use Closure;
use Illuminate\Http\Request;

class CityScope
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->filled('city_id')) {
            // Gắn city_id vào global scope (tuỳ bạn)
            Log::info('🌆 CityScope middleware => ' . $request->city_id);
            app()->instance('current_city_id', (int) $request->city_id);
        }
        return $next($request);
    }
}