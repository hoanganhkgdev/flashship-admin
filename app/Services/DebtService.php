<?php

namespace App\Services;

use App\Models\DriverDebt;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DebtService
{
    public function getDebts(User $user, int $page = 1, int $perPage = 3): array
    {
        $totalRemaining = (int) DriverDebt::where('driver_id', $user->id)
            ->where('status', '!=', 'paid')
            ->sum(DB::raw('amount_due - amount_paid'));

        $paginator = DriverDebt::where('driver_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'debts'           => $paginator->map(fn($d) => $this->formatDebt($d))->values()->toArray(),
            'total_remaining' => $totalRemaining,
            'pagination'      => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'has_more'     => $paginator->hasMorePages(),
            ],
        ];
    }

    public function findDebt(User $user, int $id): ?DriverDebt
    {
        return DriverDebt::where('driver_id', $user->id)->find($id);
    }

    public function payDebt(DriverDebt $debt, int $amount): DriverDebt
    {
        DB::transaction(function () use ($debt, $amount) {
            $debt = DriverDebt::lockForUpdate()->find($debt->id);
            $debt->amount_paid += $amount;
            if ($debt->amount_paid >= $debt->amount_due) {
                $debt->status = 'paid';
            }
            $debt->save();
        });

        return $debt->fresh();
    }

    public function initPayment(DriverDebt $debt, User $user): array
    {
        if ($debt->amount_paid >= $debt->amount_due) {
            return ['success' => false, 'message' => 'Công nợ này đã được thanh toán hết.'];
        }

        $type = $this->resolvePaymentChannel($user);
        $payOS = new PayOSService($type);

        $paymentAmount = (int) ($debt->amount_due - $debt->amount_paid);

        // int unique, random để tránh trùng khi tạo đồng thời
        $orderCode = (int) ($debt->id . str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT));

        Payment::create([
            'driver_debt_id' => $debt->id,
            'order_code'     => $orderCode,
            'amount'         => $paymentAmount,
            'status'         => 'pending',
            'channel'        => $type,
        ]);

        $returnUrl  = config('app.url') . '/payment/success';
        $cancelUrl  = config('app.url') . '/payment/cancel';
        $description = $debt->debt_type === 'commission'
            ? 'TT cong no chiet khau'
            : 'TT cong no theo tuan';

        $payment = $payOS->createPaymentLink($orderCode, $paymentAmount, $description, $returnUrl, $cancelUrl);

        Log::info('PayOS payment response', [
            'driver_id'        => $user->id,
            'city'             => $user->city?->name,
            'channel'          => $type,
            'debt_id'          => $debt->id,
            'order_code'       => $orderCode,
            'amount'           => $paymentAmount,
            'payment_response' => $payment,
        ]);

        return [
            'success'     => true,
            'payment'     => $payment,
            'checkoutUrl' => $payment['data']['checkoutUrl'] ?? $payment['checkoutUrl'] ?? null,
        ];
    }

    public function handleWebhook(array $payload, ?string $signature): array
    {
        Log::info('PayOS webhook received', [
            'body' => $payload,
        ]);

        $data             = $payload['data'] ?? [];
        $verifiedChannel  = $this->verifyWebhookSignature($data, $signature);

        if (!$verifiedChannel) {
            Log::warning('PayOS webhook: Invalid signature', [
                'signature' => $signature,
                'data'      => $data,
            ]);
            return ['verified' => false];
        }

        Log::info('PayOS webhook: Signature verified', [
            'channel'   => $verifiedChannel,
            'orderCode' => $data['orderCode'] ?? null,
        ]);

        if (($payload['code'] ?? '') !== '00') {
            Log::info('PayOS webhook: Ignored (code not 00)', ['code' => $payload['code'] ?? null]);
            return ['verified' => true, 'processed' => false];
        }

        $orderCode    = $data['orderCode'] ?? null;
        $orderCodeInt = (int) $orderCode;

        $payment = Payment::where('order_code', $orderCodeInt)
            ->orWhere('order_code', (string) $orderCodeInt)
            ->first();

        if (!$payment) {
            Log::error('PayOS webhook: Payment not found', ['orderCode' => $orderCode]);
            return ['verified' => true, 'processed' => false, 'error' => 'payment_not_found'];
        }

        if ($payment->status === 'paid') {
            Log::info('PayOS webhook: Already processed', ['payment_id' => $payment->id]);
            return ['verified' => true, 'processed' => true, 'idempotent' => true];
        }

        DB::transaction(function () use ($payment) {
            $debt = DriverDebt::lockForUpdate()->find($payment->driver_debt_id);

            if ($debt) {
                $debt->amount_paid = min($debt->amount_paid + $payment->amount, $debt->amount_due);
                $debt->status      = $debt->amount_paid >= $debt->amount_due ? 'paid' : $debt->status;
                $debt->save();

                Log::info('PayOS webhook: Debt updated', [
                    'debt_id'     => $debt->id,
                    'amount_paid' => $debt->amount_paid,
                    'status'      => $debt->status,
                ]);
            }

            $payment->status = 'paid';
            $payment->save();
        });

        return ['verified' => true, 'processed' => true];
    }

    private function resolvePaymentChannel(User $user): string
    {
        $user->loadMissing('city');

        if ($user->city && (
            str_contains(mb_strtolower($user->city->name), 'rạch giá') ||
            str_contains(mb_strtolower($user->city->name), 'rach gia')
        )) {
            return 'payment_rachgia';
        }

        return 'payment_others';
    }

    private function verifyWebhookSignature(array $data, ?string $signature): ?string
    {
        $channels = ['payment_rachgia', 'payment_others', 'payment'];

        foreach ($channels as $channel) {
            if (empty(config("services.payos_{$channel}.client_id")) && $channel !== 'payment') {
                continue;
            }

            $payOS = new PayOSService($channel);
            if ($payOS->verifySignature($data, $signature)) {
                return $channel;
            }
        }

        return null;
    }

    public function formatDebt(DriverDebt $debt): array
    {
        return [
            'id'          => (int) $debt->id,
            'debt_type'   => $debt->debt_type,
            'week_start'  => $debt->week_start,
            'week_end'    => $debt->week_end,
            'date'        => $debt->date,
            'amount_due'  => (int) $debt->amount_due,
            'amount_paid' => (int) $debt->amount_paid,
            'remaining'   => (int) ($debt->amount_due - $debt->amount_paid),
            'status'      => $debt->status,
            'created_at'  => $debt->created_at?->toDateTimeString(),
        ];
    }
}
