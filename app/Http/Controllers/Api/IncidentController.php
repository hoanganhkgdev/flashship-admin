<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\StoreIncidentRequest;
use App\Models\Incident;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class IncidentController extends Controller
{
    public function store(StoreIncidentRequest $request)
    {

        try {
            $user = Auth::user();
            $imagePath = null;

            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('incidents', 'public');
            }

            $incident = Incident::create([
                'user_id' => $user->id,
                'order_id' => $request->order_id,
                'type' => $request->type,
                'description' => $request->description,
                'image_path' => $imagePath,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'status' => 'pending',
            ]);


            return response()->json([
                'success' => true,
                'message' => 'Báo cáo sự cố thành công',
                'data' => $incident,
            ]);
        } catch (\Exception $e) {
            Log::error("❌ Incident Store Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Lỗi khi lưu báo cáo'], 500);
        }
    }
}
