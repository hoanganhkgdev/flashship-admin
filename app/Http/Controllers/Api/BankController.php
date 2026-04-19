<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\BankList;

class BankController extends Controller
{
    /**
     * Lấy thông tin ngân hàng của user đang đăng nhập
     */
    public function show()
    {
        $bank = Auth::user()->bank;

        return response()->json([
            'success' => true,
            'data'    => $bank,
        ]);
    }

    /**
     * Cập nhật hoặc tạo mới bank info cho user
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'bank_code'    => 'nullable|string|max:20',
            'bank_name'    => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:50',
            'bank_owner'   => 'nullable|string|max:255',
        ]);

        $bank = Bank::updateOrCreate(
            ['user_id' => Auth::id()],
            $data
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu thông tin ngân hàng',
            'data'    => $bank,
        ]);
    }

    public function bankLists()
    {
        return response()->json([
            'success' => true,
            'data' => BankList::all(['code', 'name']),
        ]);
    }
}
