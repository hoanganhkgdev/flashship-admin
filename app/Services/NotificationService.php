<?php

namespace App\Services;

use App\Helpers\FcmHelper;
use App\Models\DriverDebt;
use App\Models\Order;
use App\Models\User;
use App\Models\WithdrawRequest;
use App\Notifications\DriverAppNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Central hub cho mọi push notification và database notification.
 * Không class nào được gọi FcmHelper hoặc DriverAppNotification trực tiếp —
 * tất cả đi qua đây để nội dung tin nhắn được quản lý tại một chỗ.
 */
class NotificationService
{
    // =========================================================================
    // ORDER NOTIFICATIONS
    // =========================================================================

    public static function notifyNewOrder(Order $order, $drivers): void
    {
        FcmHelper::sendToUsers(
            $drivers,
            '🚚 Có đơn hàng mới!',
            "Đơn #{$order->id} đang chờ bạn nhận. Bấm để xem chi tiết.",
            ['type' => 'new_order', 'order_id' => (string) $order->id, 'status' => 'pending'],
            "order_{$order->id}",
        );
    }

    public static function notifyOrderUpdated(Order $order, $drivers): void
    {
        FcmHelper::sendToUsers(
            $drivers,
            "🔄 Đơn hàng #{$order->id} đã cập nhật",
            'Thông tin đơn hàng đã thay đổi. Bấm để xem bản mới nhất.',
            ['type' => 'order_updated', 'order_id' => (string) $order->id, 'status' => $order->status],
            "order_{$order->id}",
        );
    }

    public static function notifyOrderAssigned(Order $order, User $driver): void
    {
        $driver->notify(new DriverAppNotification(
            '🛵 Bạn có đơn hàng mới được gán!',
            "Đơn #{$order->id} đã được gán cho bạn. Hãy bắt đầu ngay!",
            'order',
            ['type' => 'order_assigned', 'order_id' => (string) $order->id, 'status' => 'assigned'],
            "order_assigned_{$order->id}",
        ));
    }

    public static function notifyDeliveredPending(Order $order): void
    {
        $cityId = $order->city_id;

        $admins = User::role(['admin', 'dispatcher', 'manager'])
            ->whereNotNull('fcm_token')
            ->where(function ($q) use ($cityId) {
                $q->where('city_id', $cityId)
                    ->orWhereHas('roles', fn($r) => $r->where('name', 'admin'));
            })
            ->get();

        if ($admins->isEmpty()) return;

        FcmHelper::sendToUsers(
            $admins,
            "📋 Đơn #{$order->id} chờ xác nhận",
            'Tài xế đã báo hoàn thành. Vui lòng kiểm tra và duyệt đơn.',
            ['type' => 'delivered_pending', 'order_id' => (string) $order->id, 'city_id' => (string) $cityId],
            "delivered_pending_{$order->id}",
        );

        Log::info("[Notification] FCM → {$admins->count()} admin(s), delivered_pending order #{$order->id}");
    }

    public static function notifyOrderApproved(Order $order, User $driver): void
    {
        $driver->notify(new DriverAppNotification(
            "✅ Đơn #{$order->id} đã được xác nhận",
            'Tổng đài đã duyệt hoàn thành đơn của bạn.',
            'success',
            ['type' => 'order_completed', 'order_id' => (string) $order->id],
            "order_completed_{$order->id}",
        ));
    }

    // =========================================================================
    // WITHDRAW NOTIFICATIONS
    // =========================================================================

    public static function notifyWithdrawStatus(WithdrawRequest $request, string $status): void
    {
        $approved = $status === 'approved';
        $amount   = number_format($request->amount) . 'đ';

        $request->driver->notify(new DriverAppNotification(
            $approved ? '✅ Rút tiền thành công' : '❌ Rút tiền bị từ chối',
            $approved
                ? "Yêu cầu rút {$amount} của bạn đã được duyệt."
                : "Yêu cầu rút {$amount} của bạn không được phê duyệt: " . ($request->admin_note ?? 'Vui lòng liên hệ hỗ trợ.'),
            $approved ? 'success' : 'error',
            ['type' => 'withdraw_update', 'request_id' => (string) $request->id, 'status' => $status],
            "withdraw_{$request->id}",
        ));
    }

    // =========================================================================
    // DEBT / COMMISSION NOTIFICATIONS
    // =========================================================================

    public static function notifyCommissionDebtCreated(
        User $driver,
        DriverDebt $debt,
        float $totalEarning,
        int $orderCount,
        float $rate,
        float $appFee = 0
    ): void {
        $date = Carbon::parse($debt->date)->format('d/m/Y');
        $body = 'Tổng thu nhập: ' . number_format($totalEarning) . "đ ({$orderCount} đơn)\n"
              . 'Chiết khấu ' . $rate . '%: ' . number_format($totalEarning * $rate / 100) . 'đ';

        if ($appFee > 0) {
            $body .= "\nPhí App duy trì: " . number_format($appFee) . 'đ';
        }

        $body .= "\n------------------\nTổng cộng cần nộp: " . number_format($debt->amount_due) . 'đ';

        $driver->notify(new DriverAppNotification(
            "💸 Công nợ chiết khấu ngày {$date}",
            $body,
            'warning',
            ['type' => 'commission_debt_created', 'debt_id' => (string) $debt->id, 'amount_due' => (string) $debt->amount_due, 'date' => (string) $debt->date],
            "commission_debt_{$debt->id}",
        ));
    }

    public static function notifyCommissionDebtOverdue(
        User $driver,
        int $debtCount,
        float $totalOverdue,
        string $datesText
    ): void {
        $driver->notify(new DriverAppNotification(
            '🚫 Công nợ chiết khấu đã quá hạn',
            "Bạn có {$debtCount} công nợ quá hạn (tổng: " . number_format($totalOverdue) . "đ)\n"
            . "Các ngày: {$datesText}\n"
            . 'Vui lòng thanh toán để tiếp tục nhận đơn.',
            'error',
            ['type' => 'commission_debt_overdue', 'count' => (string) $debtCount, 'total_amount' => (string) $totalOverdue],
            "commission_overdue_{$driver->id}",
        ));
    }

    public static function notifyWeeklyDebtCreated(User $driver, DriverDebt $debt): void
    {
        $from = Carbon::parse($debt->week_start)->format('d/m');
        $to   = Carbon::parse($debt->week_end)->format('d/m');

        $driver->notify(new DriverAppNotification(
            '💰 Công nợ tuần mới',
            "Công nợ từ {$from} đến {$to} đã được tạo. Vui lòng thanh toán trước hạn.",
            'warning',
            ['type' => 'driver_debt_created', 'debt_id' => (string) $debt->id, 'amount_due' => (string) $debt->amount_due, 'week_start' => Carbon::parse($debt->week_start)->toDateString(), 'week_end' => Carbon::parse($debt->week_end)->toDateString()],
            "weekly_debt_{$debt->id}",
        ));
    }

    public static function notifyWeeklyDebtOverdue(
        User $driver,
        int $debtCount,
        float $totalOverdue,
        string $weeksText
    ): void {
        $driver->notify(new DriverAppNotification(
            '🚫 Công nợ tuần đã quá hạn',
            "Bạn có {$debtCount} công nợ tuần quá hạn (tổng: " . number_format($totalOverdue) . "đ)\n"
            . "Các tuần: {$weeksText}\n"
            . 'App đã khóa nhận đơn. Vui lòng thanh toán để tiếp tục hoạt động.',
            'error',
            ['type' => 'weekly_debt_overdue', 'count' => (string) $debtCount, 'total_amount' => (string) $totalOverdue],
            "weekly_overdue_{$driver->id}",
        ));
    }

    public static function notifyWeeklyDebtReminder(User $driver, DriverDebt $debt): void
    {
        $from = $debt->week_start->format('d/m');
        $to   = $debt->week_end->format('d/m');

        $driver->notify(new DriverAppNotification(
            '💸 Nhắc thanh toán công nợ tuần',
            'Bạn cần thanh toán ' . number_format($debt->amount_due) . "đ công nợ tuần từ {$from} đến {$to}.\n"
            . 'Vui lòng thanh toán trước 21h tối hôm nay để tránh quá hạn.',
            'warning',
            ['type' => 'weekly_debt_reminder', 'debt_id' => (string) $debt->id, 'amount_due' => (string) $debt->amount_due, 'week_start' => $debt->week_start->toDateString(), 'week_end' => $debt->week_end->toDateString()],
            "weekly_reminder_{$debt->id}",
        ));
    }

    public static function notifyCommissionDebtReminder(User $driver, DriverDebt $debt, Carbon $date): void
    {
        $driver->notify(new DriverAppNotification(
            '💸 Nhắc thanh toán chiết khấu',
            'Ngày ' . $date->format('d/m/Y') . ' bạn cần thanh toán ' . number_format($debt->amount_due) . "đ chiết khấu.\n"
            . 'Vui lòng thanh toán trước 12h trưa hôm nay để tránh bị khóa nhận đơn.',
            'warning',
            ['type' => 'daily_commission_reminder', 'debt_id' => (string) $debt->id, 'amount_due' => (string) $debt->amount_due, 'date' => $date->toDateString()],
            "commission_reminder_{$debt->id}",
        ));
    }

    public static function notifyDebtReminder(User $driver, float $amount, bool $isOverdue = false): void
    {
        $formatted = number_format($amount) . 'đ';

        $driver->notify(new DriverAppNotification(
            $isOverdue ? '⚠️ Nhắc nợ QUÁ HẠN' : '🔔 Thông báo công nợ',
            $isOverdue
                ? "Tài khoản của bạn đang có khoản nợ {$formatted} đã quá hạn. Vui lòng thanh toán để tránh bị khóa app."
                : "Bạn có khoản công nợ {$formatted} cần thanh toán trong tuần này. Trân trọng!",
            'warning',
            ['type' => 'debt_reminder', 'is_overdue' => $isOverdue ? '1' : '0'],
            'debt_' . $driver->id,
        ));
    }
}
