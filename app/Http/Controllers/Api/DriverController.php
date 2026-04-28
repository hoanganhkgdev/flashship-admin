<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\ChangePasswordRequest;
use App\Http\Requests\Driver\UpdateFcmTokenRequest;
use App\Http\Requests\Driver\UpdateLocationRequest;
use App\Http\Requests\Driver\UpdateProfileRequest;
use App\Models\User;
use App\Services\DriverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DriverController extends Controller
{
    public function __construct(private DriverService $driverService) {}

    public function profile(Request $request): JsonResponse
    {
        $data = $this->driverService->getProfile($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Thông tin tài khoản',
            'data'    => ['user' => $data],
        ]);
    }

    public function toggleNightShift(Request $request): JsonResponse
    {
        $result = $this->driverService->toggleNightShift($request->user());

        return response()->json($result);
    }

    public function toggleOnline(Request $request): JsonResponse
    {
        $result = $this->driverService->toggleOnline($request->user());
        $status = $result['status'];
        unset($result['status']);

        return response()->json($result, $status);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('profile-photos', 'public');
        }

        $result = $this->driverService->updateProfile($request->user(), $request->validated(), $avatarPath);
        $status = $result['status'];
        unset($result['status']);

        return response()->json($result, $status);
    }

    public function updateFcmToken(UpdateFcmTokenRequest $request): JsonResponse
    {

        $this->driverService->updateFcmToken($request->user(), $request->fcm_token);

        return response()->json([
            'success' => true,
            'message' => 'FCM Token đã được cập nhật thành công',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {

        $result = $this->driverService->changePassword(
            Auth::user(),
            $request->current_password,
            $request->new_password
        );

        $status = $result['status'];
        unset($result['status']);

        return response()->json($result, $status);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $request->user()->delete();

        return response()->json(['success' => true, 'message' => 'Tài khoản đã được xóa']);
    }

    public function updateLocation(UpdateLocationRequest $request): JsonResponse
    {

        $this->driverService->updateLocation(
            $request->user(),
            (float) $request->latitude,
            (float) $request->longitude
        );

        return response()->json(['success' => true, 'message' => 'Cập nhật vị trí thành công']);
    }

    public function locations(): JsonResponse
    {
        $drivers = User::role('driver')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'name', 'latitude', 'longitude', 'last_location_update')
            ->get();

        return response()->json(['success' => true, 'data' => $drivers]);
    }

    public function updateBank(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->validate([
            'bank_code'    => 'required|string|max:50',
            'bank_name'    => 'required|string|max:255',
            'bank_account' => 'required|string|max:50',
            'bank_owner'   => 'required|string|max:255',
        ]);

        $user->update($request->only('bank_code', 'bank_name', 'bank_account', 'bank_owner'));

        return response()->json(['success' => true, 'message' => 'Cập nhật ngân hàng thành công', 'user' => $user]);
    }

    public function stats(Request $request): JsonResponse
    {
        $data = $this->driverService->getStats($request->user());

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $page   = max(1, (int) $request->input('page', 1));
        $result = $this->driverService->getNotifications($request->user(), $page);

        return response()->json(['success' => true, ...$result]);
    }

    public function markNotificationAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if ($id === 'all') {
            $user->unreadNotifications()->update(['read_at' => now()]);
        } else {
            $notification = $user->notifications()->where('id', $id)->first();
            if ($notification) $notification->markAsRead();
        }

        return response()->json(['success' => true]);
    }
}
