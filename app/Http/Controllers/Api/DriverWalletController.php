<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\DriverWallet;
use App\Models\WithdrawRequest;
use App\Services\DriverWalletService;
use App\Models\Bank;

class DriverWalletController extends Controller
{
    // 📌 Lấy số dư ví
    public function index(Request $request)
    {
        $driver = $request->user();

        $wallet = DriverWallet::firstOrCreate([
            'driver_id' => $driver->id,
        ]);

        return response()->json([
            'balance' => (float) $wallet->balance,
        ]);
    }

    // 📌 Lịch sử giao dịch
    public function transactions(Request $request)
    {
        $driver = $request->user();

        $wallet = DriverWallet::firstOrCreate([
            'driver_id' => $driver->id,
        ]);

        return response()->json(
            $wallet->transactions()->latest()->paginate(20)
        );
    }

    public function withdraw(Request $request)
    {
        $driver = $request->user();
        $amount = (float) $request->input('amount');

        if ($amount < 100000) {
            return response()->json([
                'message' => 'Số tiền rút tối thiểu là 100.000₫',
            ], 400);
        }

        $bank = Bank::where('user_id', $driver->id)->first();
        if (!$bank) {
            return response()->json([
                'message'      => 'Vui lòng cập nhật thông tin ngân hàng trước khi rút tiền',
                'require_bank' => true,
            ], 400);
        }

        // Ensure wallet row exists before locking
        DriverWallet::firstOrCreate(['driver_id' => $driver->id]);

        try {
            $withdraw = DB::transaction(function () use ($driver, $amount, $bank) {
                // Lock the wallet row so concurrent requests are serialised
                $wallet = DriverWallet::where('driver_id', $driver->id)->lockForUpdate()->first();

                if ($wallet->balance < $amount) {
                    throw new \RuntimeException('INSUFFICIENT_BALANCE');
                }

                $withdraw = WithdrawRequest::create([
                    'driver_id' => $driver->id,
                    'amount'    => $amount,
                    'status'    => 'pending',
                    'note'      => 'Rút về ' . $bank->bank_name . ' - ' . $bank->bank_account,
                ]);

                DriverWalletService::adjust(
                    $driver->id,
                    $amount,
                    'debit',
                    'Yêu cầu rút tiền #' . $withdraw->id,
                    'withdraw_request_' . $withdraw->id
                );

                return $withdraw;
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return response()->json(['message' => 'Số dư không đủ'], 400);
            }
            throw $e;
        }

        return response()->json([
            'message'  => 'Yêu cầu rút tiền đã được gửi',
            'withdraw' => $withdraw,
        ]);
    }


    public function withdrawRequests(Request $request)
    {
        $driver = $request->user();

        $requests = \App\Models\WithdrawRequest::where('driver_id', $driver->id)
            ->latest()
            ->paginate(20);

        return response()->json($requests);
    }

}
