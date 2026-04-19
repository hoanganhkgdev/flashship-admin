<?php

namespace App\Http\Controllers\AdminApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $field = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where($field, $request->login)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Sai thông tin đăng nhập'], 401);
        }

        if (method_exists($user, 'hasRole')) {
            if (!$user->hasRole(['admin', 'manager'])) {
                return response()->json(['message' => 'Không có quyền'], 403);
            }
            $role = $user->getRoleNames()->first();
        } else {
            $role = $user->role; // fallback nếu dùng cột role
        }

        $token = $user->createToken('admin_app_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $role,
                'city_id' => $user->city_id,
            ]
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Đã đăng xuất']);
    }
}