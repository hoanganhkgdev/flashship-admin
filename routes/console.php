<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('driver:generate-debts')
    ->weeklyOn(1, '13:00')
    ->timezone('Asia/Ho_Chi_Minh');

// 17:00 kết thúc ca sáng
Schedule::command('shift:end morning')
    ->dailyAt('17:00')
    ->timezone('Asia/Ho_Chi_Minh');

// 23:59 kết thúc ca tối
Schedule::command('shift:end evening')
    ->dailyAt('23:59')
    ->timezone('Asia/Ho_Chi_Minh');

// Nhắc thanh toán công nợ tuần vào 20h Chủ nhật (1 tiếng trước hạn)
Schedule::command('driver:notify-weekly')
    ->sundays()
    ->at('20:00')
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping();

// Chạy mỗi Chủ nhật lúc 21h (theo giờ VN) - chuyển công nợ tuần sang quá hạn
Schedule::command('debt:mark-overdue')
    ->sundays()
    ->at('21:00')
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping();

// Nhắc thanh toán công nợ chiết khấu vào 6h sáng hôm sau
Schedule::command('driver:notify-commission')
    ->dailyAt('06:00')
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping();

// Đánh dấu công nợ chiết khấu quá hạn vào 12h trưa hôm sau (Có tự trừ ví)
Schedule::command('debt:commission-overdue-daily')
    ->dailyAt('12:00')
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping();

// Tính công nợ chiết khấu theo ngày vào 12h khuya (tính cho ngày hôm trước)
Schedule::command('debt:calculate-daily-commission')
    ->dailyAt('00:00')
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping();

// Release đơn hẹn giờ → pending (30 phút trước scheduled_at)
Schedule::command('orders:release-scheduled')
    ->everyMinute()
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping();

// Quét và tự động Offline tài xế hết ca làm việc
Schedule::command('driver:check-shifts')
    ->everyMinute()
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping();

