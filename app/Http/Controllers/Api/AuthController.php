<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Đăng ký
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'shift_ids' => 'sometimes|array',
            'shift_ids.*' => 'exists:shifts,id',
            'city_id' => 'required|exists:cities,id',
            'profile_photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // 🔹 Upload ảnh
        $path = null;
        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('profiles', 'public');
        }

        // 🔹 Tạo user
        $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'password' => bcrypt($data['password']),
            'status' => 0, // chờ duyệt
            'city_id' => $data['city_id'],
            'profile_photo_path' => $path,
        ]);

        // Gán role driver
        $user->assignRole('driver');

        // 🔹 Gắn ca làm việc vào bảng pivot shift_user (chỉ với gói weekly)
        $plan = \App\Models\Plan::where('city_id', $data['city_id'])
            ->where('is_active', true)
            ->first();
        $user->update(['plan_id' => $plan?->id]);

        if ($plan?->type === 'weekly' && !empty($data['shift_ids'])) {
            $user->shifts()->sync($data['shift_ids']);
        }

        // 🔹 Tạo token đăng nhập
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đăng ký thành công, vui lòng chờ admin duyệt',
            'data' => [
                'user' => $user->load('shifts', 'city'),
                'token' => $token,
                'profile_photo_url' => $path ? asset('storage/' . $path) : null,
            ],
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
            'device_id' => ['nullable', 'string'],
        ]);

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where($field, $data['login'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Tài khoản hoặc mật khẩu không đúng'],
            ]);
        }

        if ($user->status == 0) {
            return response()->json(['success' => false, 'message' => 'Chưa được duyệt'], 403);
        }
        if ($user->status == 2) {
            return response()->json(['success' => false, 'message' => 'Tài khoản bị khóa'], 403);
        }

        $currentDeviceId = $data['device_id'] ?? null;

        // 🔹 CHỈ xử lý tokens của app (api_token_%), KHÔNG động đến token của web admin
        $existingTokens = $user->tokens()->where('name', 'like', 'api_token_%')->get();
        $oldDeviceIds = [];
        $hasCurrentDeviceToken = false;

        foreach ($existingTokens as $token) {
            if (preg_match('/api_token_(.+)/', $token->name, $matches)) {
                $deviceId = $matches[1];
                $oldDeviceIds[] = $deviceId;

                if ($currentDeviceId && $deviceId === $currentDeviceId) {
                    $hasCurrentDeviceToken = true;
                }
            }
        }

        if ($currentDeviceId && !empty($oldDeviceIds) && !$hasCurrentDeviceToken) {
            foreach ($oldDeviceIds as $oldDeviceId) {
                if ($oldDeviceId !== $currentDeviceId) {
                    $user->tokens()->where('name', 'like', "api_token_{$oldDeviceId}%")->delete();
                }
            }
        } elseif ($currentDeviceId && $hasCurrentDeviceToken) {
            $user->tokens()->where('name', 'like', "api_token_{$currentDeviceId}%")->delete();
        } elseif (!$currentDeviceId) {
            $user->tokens()->where('name', 'like', 'api_token_%')->delete();
        }


        $tokenName = $currentDeviceId ? "api_token_{$currentDeviceId}" : 'api_token';
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'data' => [
                'token' => $token,
                'user' => $this->formatUserData($user),
            ],
        ]);
    }

    /**
     * Đăng xuất
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đăng xuất thành công',
            'data' => null,
        ]);
    }

    /**
     * Thông tin người dùng hiện tại
     */
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'message' => 'Thông tin người dùng',
            'data' => $this->formatUserData($user),
        ]);
    }

    /**
     * Format user data with relationships for Flutter App
     */
    private function formatUserData($user)
    {
        $user->loadMissing(['city', 'shifts', 'bank', 'driverLicenses', 'plan']);

        $userData = $user->toArray();
        $userData['city_name'] = $user->city->name ?? '';
        $userData['shifts'] = $user->shifts;
        $userData['plan_type'] = $user->plan?->type; // ✅ Trả plan_type cho Flutter app

        // Bank info
        $userData['bank_name'] = $user->bank->bank_name ?? null;
        $userData['bank_code'] = $user->bank->bank_code ?? null;
        $userData['bank_account'] = $user->bank->account_number ?? null;
        $userData['bank_owner'] = $user->bank->account_name ?? null;

        // License info — dùng relation đã load sẵn, không query thêm
        $license = $user->driverLicenses->sortByDesc('id')->first();
        if ($license) {
            $userData['license_status'] = $license->status;
            $userData['license_image_url'] = $license->image_path ? url('storage/' . $license->image_path) : null;
        }

        // Profile photo processing
        if ($user->profile_photo_path) {
            $userData['profile_photo_url'] = url('storage/' . $user->profile_photo_path);
        }

        return $userData;
    }
}
