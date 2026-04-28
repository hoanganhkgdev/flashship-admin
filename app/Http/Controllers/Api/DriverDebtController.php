<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Debt\PayDebtRequest;
use App\Services\DebtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DriverDebtController extends Controller
{
    public function __construct(private DebtService $debtService) {}

    public function index(Request $request): JsonResponse
    {
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 3);

        $data = $this->debtService->getDebts(Auth::user(), $page, $perPage);

        return response()->json(['success' => true, ...$data]);
    }

    public function show(int $id): JsonResponse
    {
        $debt = $this->debtService->findDebt(Auth::user(), $id);

        if (!$debt) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy công nợ'], 404);
        }

        return response()->json([
            'success' => true,
            'debt'    => $this->debtService->formatDebt($debt),
        ]);
    }

    public function pay(PayDebtRequest $request, int $id): JsonResponse
    {
        $debt = $this->debtService->findDebt(Auth::user(), $id);

        if (!$debt) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy công nợ'], 404);
        }

        $updated = $this->debtService->payDebt($debt, (int) $request->validated('amount'));

        return response()->json([
            'success' => true,
            'message' => 'Đã nộp tiền thành công',
            'debt'    => $updated,
        ]);
    }

    public function payWithWallet(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Tính năng thanh toán bằng ví đã bị tắt. Vui lòng thanh toán qua PayOS hoặc liên hệ quản trị viên.',
        ], 403);
    }

    public function payInit(int $id): JsonResponse
    {
        $user = Auth::user();
        $debt = $this->debtService->findDebt($user, $id);

        if (!$debt) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy công nợ'], 404);
        }

        $result = $this->debtService->initPayment($debt, $user);

        if (!$result['success']) {
            return response()->json($result);
        }

        return response()->json([
            'success'     => true,
            'payment'     => $result['payment'],
            'checkoutUrl' => $result['checkoutUrl'],
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $payload   = $request->all();
        $signature = $request->header('x-signature') ?? ($payload['signature'] ?? null);

        $this->debtService->handleWebhook($payload, $signature);

        return response()->json(['success' => true]);
    }
}
