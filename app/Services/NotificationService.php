<?php

namespace App\Services;

use App\Jobs\SendFcmNotificationJob;
use App\Models\Order;
use App\Models\User;
use App\Models\WithdrawRequest;
use Illuminate\Support\Facades\Log;

/**
 * Service quản lý logic nghiệp vụ cho thông báo Push (FCM).
 * Centralize tất cả nội dung tin nhắn và cấu hình collapseId tại đây.
 */
class NotificationService
{
    /**
     * Thông báo cho các tài xế có đơn hàng mới
     */
    public static function notifyNewOrder(Order $order, $drivers)
    {
        $title = "🚚 Có đơn hàng mới!";
        $body = "Đơn #{$order->id} đang chờ bạn nhận. Bấm để xem chi tiết.";

        $data = [
            'type' => 'new_order',
            'order_id' => (string) $order->id,
            'status' => 'pending'
        ];

        self::dispatchToUsers($drivers, $title, $body, $data, "order_{$order->id}");
    }

    /**
     * Thông báo cập nhật thông tin đơn hàng (địa chỉ, phí...)
     */
    public static function notifyOrderUpdated(Order $order, $drivers)
    {
        $title = "🔄 Đơn hàng #{$order->id} đã cập nhật";
        $body = "Thông tin đơn hàng đã thay đổi. Bấm để xem bản mới nhất.";

        $data = [
            'type' => 'order_updated',
            'order_id' => (string) $order->id,
            'status' => 'pending'
        ];

        // Dùng cùng collapseId để đè thông báo cũ
        self::dispatchToUsers($drivers, $title, $body, $data, "order_{$order->id}");
    }

    /**
     * Thông báo khi đơn hàng được gán cho tài xế (Manual Assign)
     */
    public static function notifyOrderAssigned(Order $order, User $driver)
    {
        $title = "🛵 Bạn có đơn hàng mới được gán!";
        $body = "Đơn #{$order->id} đã được gán cho bạn. Hãy bắt đầu ngay!";

        $data = [
            'type' => 'order_assigned',
            'order_id' => (string) $order->id,
            'status' => 'assigned'
        ];

        // 1. Gửi Push (FCM)
        self::dispatchToUsers([$driver], $title, $body, $data, "order_assigned_{$order->id}");

        // 2. Lưu vào Lịch sử thông báo trên App (Database)
        $driver->notify(new \App\Notifications\DriverAppNotification($title, $body, 'order'));
    }

    /**
     * Thông báo trạng thái yêu cầu rút tiền
     */
    public static function notifyWithdrawStatus(WithdrawRequest $request, string $status)
    {
        $title = $status === 'approved' ? "✅ Rút tiền thành công" : "❌ Rút tiền bị từ chối";
        $amount = number_format($request->amount) . 'đ';
        $body = $status === 'approved'
            ? "Yêu cầu rút {$amount} của bạn đã được duyệt."
            : "Yêu cầu rút {$amount} của bạn không được phê duyệt: " . ($request->admin_note ?? 'Vui lòng liên hệ hỗ trợ.');

        $data = [
            'type' => 'withdraw_update',
            'request_id' => (string) $request->id,
            'status' => $status
        ];

        // 1. Gửi Push (FCM)
        self::dispatchToUsers([$request->driver], $title, $body, $data, "withdraw_{$request->id}");

        // 2. Lưu vào Lịch sử thông báo trên App (Database)
        $type = $status === 'approved' ? 'success' : 'error';
        $request->driver->notify(new \App\Notifications\DriverAppNotification($title, $body, $type));
    }

    /**
     * Thông báo nhắc công nợ cho tài xế
     */
    public static function notifyDebtReminder(User $driver, $amount, $isOverdue = false)
    {
        $title = $isOverdue ? "⚠️ Nhắc nợ QUÁ HẠN" : "🔔 Thông báo công nợ";
        $amountStr = number_format($amount) . 'đ';
        $body = $isOverdue
            ? "Tài khoản của bạn đang có khoản nợ {$amountStr} đã quá hạn. Vui lòng thanh toán để tránh bị khóa app."
            : "Bạn có khoản công nợ {$amountStr} cần thanh toán trong tuần này. Trân trọng!";

        $data = [
            'type' => 'debt_reminder',
            'is_overdue' => $isOverdue
        ];

        // 1. Gửi Push (FCM)
        self::dispatchToUsers([$driver], $title, $body, $data, "debt_" . time());

        // 2. Lưu vào Lịch sử thông báo trên App (Database)
        $driver->notify(new \App\Notifications\DriverAppNotification($title, $body, 'warning'));
    }

    /**
     * Thông báo cho Manager khi AI cần can thiệp (Escalation)
     */
    public static function notifyEscalation($escalation, User $manager, string $urgencyEmoji = '⚠️')
    {
        $title = "{$urgencyEmoji} Escalation #{$escalation->id}: Cần hỗ trợ khách hàng!";
        $body = "Khách [{$escalation->sender_id}]: {$escalation->reason}";

        $data = [
            'type' => 'escalation',
            'escalation_id' => (string) $escalation->id,
            'urgency' => $escalation->urgency,
        ];

        self::dispatchToUsers([$manager], $title, $body, $data, "escalation_{$escalation->id}");
    }


    /**
     * Helper để gửi thông báo trực tiếp (không qua queue)
     */
    private static function dispatchToUsers($users, string $title, string $body, array $data = [], ?string $collapseId = null)
    {
        $tokens = [];
        foreach ($users as $user) {
            if (!empty($user->fcm_token)) {
                $tokens[] = $user->fcm_token;
            }
        }

        if (!empty($tokens)) {
            SendFcmNotificationJob::dispatchSync($tokens, $title, $body, $data, $collapseId);
        }
    }
}
