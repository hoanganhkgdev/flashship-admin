<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\License\UploadLicenseRequest;
use App\Models\DriverLicense;

class DriverLicenseController extends Controller
{
    public function upload(UploadLicenseRequest $request)
    {
        $user = $request->user();

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
