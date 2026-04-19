<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\DriverDebt;
use App\Services\PayOSService;

class DriverDebtController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // 🔹 Lấy page và per_page từ request (mặc định: page=1, per_page=3)
        $page = (int) ($request->input('page', 1));
        $perPage = (int) ($request->input('per_page', 3));

        // 🔹 Sắp xếp theo mới nhất lên trên (created_at desc hoặc id desc)
        $query = DriverDebt::where('driver_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        // 🔹 Lấy tổng số công nợ và tổng còn nợ (1 query, không phụ thuộc pagination)
        $total = $query->count();
        $totalRemaining = (int) DriverDebt::where('driver_id', $user->id)
            ->where('status', '!=', 'paid')
            ->sum(DB::raw('amount_due - amount_paid'));

        // 🔹 Paginate
        $debts = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(fn($d) => [
                'id' => (int) $d->id,
                'debt_type' => $d->debt_type,
                'week_start' => $d->week_start,
                'week_end' => $d->week_end,
                'date' => $d->date,
                'amount_due' => (int) $d->amount_due,
                'amount_paid' => (int) $d->amount_paid,
                'remaining' => (int) ($d->amount_due - $d->amount_paid),
                'status' => $d->status,
                'created_at' => $d->created_at?->toDateTimeString(),
            ]);

        return response()->json([
            'success' => true,
            'debts' => $debts,
            'total_remaining' => $totalRemaining,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total,
            ],
        ]);
    }

    public function show($id)
    {
        $user = Auth::user();
        $debt = DriverDebt::where('driver_id', $user->id)->find($id);

        if (!$debt) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy công nợ'], 404);
        }

        return response()->json([
            'success' => true,
            'debt' => [
                'id'         => (int) $debt->id,
                'debt_type'  => $debt->debt_type,
                'week_start' => $debt->week_start,
                'week_end'   => $debt->week_end,
                'date'       => $debt->date,
                'amount_due' => (int) $debt->amount_due,
                'amount_paid'=> (int) $debt->amount_paid,
                'remaining'  => (int) ($debt->amount_due - $debt->amount_paid),
                'status'     => $debt->status,
                'created_at' => $debt->created_at?->toDateTimeString(),
            ],
        ]);
    }

    public function pay(Request $request, $id)
    {
        $user = $request->user();

        $debt = \App\Models\DriverDebt::where('driver_id', $user->id)->find($id);

        if (!$debt) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy công nợ',
            ], 404);
        }

        $amount = (int) $request->input('amount', 0);

        if ($amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Số tiền nộp không hợp lệ',
            ], 400);
        }

        DB::transaction(function () use ($debt, $amount) {
            // Reload with lock to prevent race condition from concurrent requests
            $debt = DriverDebt::lockForUpdate()->find($debt->id);
            $debt->amount_paid += $amount;
            if ($debt->amount_paid >= $debt->amount_due) {
                $debt->status = 'paid';
            }
            $debt->save();
        });

        $debt->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Đã nộp tiền thành công',
            'debt' => $debt,
        ]);
    }

    /**
     * Thanh toán công nợ bằng Ví tài xế - ĐÃ TẮT
     */
    public function payWithWallet(Request $request, $id)
    {
        return response()->json([
            'success' => false,
            'message' => 'Tính năng thanh toán bằng ví đã bị tắt. Vui lòng thanh toán qua PayOS hoặc liên hệ quản trị viên.',
        ], 403);
    }


    public function payInit($id)
    {
        $user = Auth::user();
        $debt = DriverDebt::where('driver_id', $user->id)->findOrFail($id);

        // 🔹 Xác định kênh thanh toán dựa trên khu vực của tài xế
        $type = 'payment_others';
        
        // Nạp city nếu chưa có
        $user->load('city');
        if ($user->city && (str_contains(mb_strtolower($user->city->name), 'rạch giá') || str_contains(mb_strtolower($user->city->name), 'rach gia'))) {
            $type = 'payment_rachgia';
        }

        $payOS = new PayOSService($type);

        // Kiểm tra nếu đã thanh toán hết
        if ($debt->amount_paid >= $debt->amount_due) {
            return response()->json([
                'success' => false,
                'message' => 'Công nợ này đã được thanh toán hết.',
            ]);
        }

        // Thanh toán phần còn lại — tránh thu thừa nếu admin đã nhập một phần trước đó
        $paymentAmount = (int) ($debt->amount_due - $debt->amount_paid);

        // orderCode = int unique, dùng random_int để tránh trùng khi tạo đồng thời
        $orderCode = (int) ($debt->id . str_pad(random_int(0, 999999), 6, "0", STR_PAD_LEFT));

        // 🔹 Lưu mapping payment với số tiền còn lại cần thanh toán
        \App\Models\Payment::create([
            'driver_debt_id' => $debt->id,
            'order_code' => $orderCode,
            'amount' => $paymentAmount,
            'status' => 'pending',
            'channel' => $type, // Lưu lại channel để dùng cho webhook nếu cần
        ]);

        $returnUrl = config('app.url') . '/payment/success';
        $cancelUrl = config('app.url') . '/payment/cancel';

        // 🔹 Webhook URL đã được cấu hình trong PayOS dashboard
        // PayOS sẽ tự động gọi webhook URL đã cấu hình trong dashboard

        $description = $debt->debt_type === 'commission'
            ? "TT cong no chiet khau"
            : "TT cong no theo tuan";

        $payment = $payOS->createPaymentLink(
            $orderCode,
            $paymentAmount,
            $description,
            $returnUrl,
            $cancelUrl
        );

        // 🔹 Log để debug
        \Log::info('PayOS payment response', [
            'driver_id' => $user->id,
            'city' => $user->city?->name,
            'channel' => $type,
            'debt_id' => $debt->id,
            'order_code' => $orderCode,
            'amount' => $paymentAmount,
            'payment_response' => $payment,
        ]);

        // 🔹 Đảm bảo trả về đúng structure cho frontend
        return response()->json([
            'success' => true,
            'payment' => $payment,
            'checkoutUrl' => $payment['data']['checkoutUrl'] ?? $payment['checkoutUrl'] ?? null,
        ]);
    }

    public function webhook(Request $request)
    {
        // 🔹 Log toàn bộ webhook request để debug
        \Log::info('PayOS webhook received', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
            'ip' => $request->ip(),
        ]);

        $payload = $request->all();
        $signature = $request->header('x-signature') ?? ($payload['signature'] ?? null);
        $data = $payload['data'] ?? [];

        // 🔐 Verify chữ ký bằng cách thử qua các kênh cấu hình
        $verifiedChannel = null;
        $channels = ['payment_rachgia', 'payment_others', 'payment'];
        
        foreach ($channels as $channel) {
            $payOS = new PayOSService($channel);
            // Nếu channel này không có config (clientId trống) thì bỏ qua
            if (empty(config("services.payos_{$channel}.client_id")) && $channel !== 'payment') {
                continue;
            }
            
            if ($payOS->verifySignature($data, $signature)) {
                $verifiedChannel = $channel;
                break;
            }
        }

        if (!$verifiedChannel) {
            \Log::warning('PayOS webhook: Invalid signature - bỏ qua xử lý', [
                'signature' => $signature,
                'data'      => $data,
                'ip'        => $request->ip(),
            ]);

            return response()->json(['success' => true, 'message' => 'Received but invalid signature']);
        }

        \Log::info('PayOS webhook: Signature verified', [
            'channel' => $verifiedChannel,
            'code' => $payload['code'] ?? null,
            'orderCode' => $data['orderCode'] ?? null,
        ]);

        // ✅ Chỉ xử lý khi thanh toán thành công
        if (($payload['code'] ?? '') === '00') {
            $orderCode = $data['orderCode'] ?? null;
            $amount = (int) ($data['amount'] ?? 0);

            \Log::info('PayOS webhook: Processing successful payment', [
                'orderCode' => $orderCode,
                'orderCode_type' => gettype($orderCode),
                'amount' => $amount,
                'data' => $data,
            ]);

            // 🔹 Đảm bảo orderCode là số nguyên (PayOS có thể trả về string hoặc int)
            $orderCodeInt = (int) $orderCode;

            // 🔹 Tìm payment bằng cả string và int để đảm bảo tìm thấy
            $payment = \App\Models\Payment::where('order_code', $orderCodeInt)
                ->orWhere('order_code', (string) $orderCodeInt)
                ->first();

            if ($payment) {
                // Idempotency: bỏ qua nếu đã xử lý rồi
                if ($payment->status === 'paid') {
                    \Log::info('PayOS webhook: Payment already processed, skipping', [
                        'payment_id' => $payment->id,
                        'orderCode'  => $orderCode,
                    ]);
                    return response()->json(['success' => true]);
                }

                DB::transaction(function () use ($payment) {
                    $debt = DriverDebt::lockForUpdate()->find($payment->driver_debt_id);

                    if ($debt) {
                        // Dùng amount từ payment record (số tiền thực tế được tạo lúc payInit)
                        $debt->amount_paid = min($debt->amount_paid + $payment->amount, $debt->amount_due);
                        $debt->status = $debt->amount_paid >= $debt->amount_due ? 'paid' : $debt->status;
                        $debt->save();

                        \Log::info('PayOS webhook: Debt updated', [
                            'debt_id'     => $debt->id,
                            'amount_paid' => $debt->amount_paid,
                            'status'      => $debt->status,
                        ]);
                    } else {
                        \Log::warning('PayOS webhook: Debt not found', [
                            'driver_debt_id' => $payment->driver_debt_id,
                        ]);
                    }

                    $payment->status = 'paid';
                    $payment->save();

                    \Log::info('PayOS webhook: Payment updated', [
                        'payment_id' => $payment->id,
                        'status'     => $payment->status,
                    ]);
                });
            } else {
                \Log::error('PayOS webhook: Payment not found for orderCode', [
                    'orderCode' => $orderCode,
                    'orderCode_type' => gettype($orderCode),
                    'orderCode_int' => $orderCodeInt,
                    'data' => $data,
                    'payload' => $payload,
                ]);

                // 🔹 Thử tìm lại với các format khác để debug
                $allPayments = \App\Models\Payment::where('status', 'pending')
                    ->orderBy('created_at', 'desc')
                    ->take(5)
                    ->get(['id', 'order_code', 'driver_debt_id', 'amount', 'status', 'created_at']);

                \Log::info('PayOS webhook: Recent pending payments for debugging', [
                    'recent_payments' => $allPayments->toArray(),
                ]);
            }
        } else {
            \Log::info('PayOS webhook: Ignored (code not 00)', [
                'code' => $payload['code'] ?? null,
                'desc' => $payload['desc'] ?? null,
            ]);
        }

        return response()->json(['success' => true]);
    }
}
