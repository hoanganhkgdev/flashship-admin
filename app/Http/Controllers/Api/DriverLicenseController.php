<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DriverLicense;

class DriverLicenseController extends Controller
{
    public function upload(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'image' => 'required|image|max:2048',
        ]);

        $path = $request->file('image')->store('licenses', 'public');

        $license = DriverLicense::create([
            'user_id' => $user->id,
            'image_path' => $path,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi bằng lái, vui lòng chờ admin duyệt',
            'license' => $license,
        ]);
    }
}
