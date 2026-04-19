<?php

namespace App\Services;

use App\Models\DriverWallet;
use Illuminate\Support\Facades\DB;

class DriverWalletService
{
    public static function adjust($driverId, $amount, $type = 'credit', $desc = null, $ref = null)
    {
        return DB::transaction(function () use ($driverId, $amount, $type, $desc, $ref) {
            $wallet = DriverWallet::firstOrCreate(['driver_id' => $driverId]);

            // 🔹 Kiểm tra trùng lặp nếu có reference
            if ($ref && $wallet->transactions()->where('reference', $ref)->exists()) {
                return $wallet->transactions()->where('reference', $ref)->first();
            }

            if ($type === 'debit' && $wallet->balance < $amount) {
                throw new \Exception("Số dư không đủ");
            }

            $wallet->balance += $type === 'credit' ? $amount : -$amount;
            $wallet->save();

            return $wallet->transactions()->create([
                'type' => $type,
                'amount' => $amount,
                'description' => $desc,
                'reference' => $ref,
            ]);
        });
    }

}
