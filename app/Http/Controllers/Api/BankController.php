<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bank\UpdateBankRequest;
use App\Models\Bank;
use App\Models\BankList;
use Illuminate\Support\Facades\Auth;

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
    public function update(UpdateBankRequest $request)
    {
        $data = $request->validated();

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
