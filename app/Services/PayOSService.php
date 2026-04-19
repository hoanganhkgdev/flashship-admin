<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayOSService
{
    protected $clientId;
    protected $apiKey;
    protected $checksumKey;
    protected $endpoint;

    /**
     * @param string $type 'payment', 'payout', 'payment_rachgia', 'payment_others'
     */
    public function __construct(string $type = 'payment')
    {
        // Thử lấy config theo type cụ thể, nếu không có thì fallback về payment
        $config = config("services.payos_{$type}");
        
        if (!$config) {
            if ($type === 'payout') {
                $config = config('services.payos_payout') ?: config('services.payos_payment');
            } else {
                $config = config('services.payos_payment');
            }
        }

        $this->clientId    = $config['client_id']    ?? '';
        $this->apiKey      = $config['api_key']      ?? '';
        $this->checksumKey = $config['checksum_key'] ?? '';
        $this->endpoint    = $config['endpoint']     ?? 'https://api-merchant.payos.vn';
    }

    /**
     * Build raw string for signature (fields sorted alphabetically)
     */
    protected function buildRawData(array $data): string
    {
        foreach ($data as $k => $v) {
            if (is_null($v)) {
                $data[$k] = '';
            }
        }
        ksort($data); // sort alphabet
        return urldecode(http_build_query($data));
    }

    /**
     * Tạo link thanh toán (Payment)
     */
    public function createPaymentLink($orderCode, $amount, $description, $returnUrl, $cancelUrl)
    {
        $body = [
            "amount"      => (int) $amount,
            "cancelUrl"   => $cancelUrl,
            "description" => $description,
            "orderCode"   => (int) $orderCode,
            "returnUrl"   => $returnUrl,
        ];

        // 🔐 Ký dữ liệu
        $rawData   = $this->buildRawData($body);
        $signature = hash_hmac('sha256', $rawData, $this->checksumKey);
        $body['signature'] = $signature;

        $response = Http::withHeaders([
            "x-client-id" => $this->clientId,
            "x-api-key"   => $this->apiKey,
            "Content-Type"=> "application/json",
        ])->post($this->endpoint . "/v2/payment-requests", $body);

        return $response->json();
    }

    /**
     * Encode giá trị theo đúng chuẩn PayOS
     */
    private function encodeValue($value): string
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return rawurlencode($value);
    }

    /* Build rawData theo đúng thứ tự field
     */
    private function buildRawDataPayout(array $body): string
    {
        return "amount=" . $this->encodeValue($body['amount'])
            . "&category=" . $this->encodeValue($body['category'])
            . "&description=" . $this->encodeValue($body['description'])
            . "&referenceId=" . $this->encodeValue($body['referenceId'])
            . "&toAccountNumber=" . $this->encodeValue($body['toAccountNumber'])
            . "&toBin=" . $this->encodeValue($body['toBin']);
    }

    public function createPayout(
        string $referenceId,
        int $amount,
        string $toBin,
        string $toAccountNumber,
        string $description = "Thanh toán lương",
        array $category = ["salary"]
    ) {
        $body = [
            "referenceId"     => $referenceId,
            "amount"          => $amount,
            "description"     => $description,
            "toBin"           => $toBin,
            "toAccountNumber" => $toAccountNumber,
            "category"        => $category,
        ];

        $rawData = $this->buildRawDataPayout($body);
        $signature = hash_hmac('sha256', $rawData, $this->checksumKey, false);
        $idempotencyKey = (string) Str::uuid();

        // ⚙️ Log request đầu vào
        Log::info('Payout request initiated', [
            'referenceId' => $referenceId,
            'body' => $body,
            'signature' => $signature,
            'idempotencyKey' => $idempotencyKey,
            'endpoint' => $this->endpoint . "/v1/payouts",
        ]);

        try {
            $response = Http::withHeaders([
                "x-client-id"       => $this->clientId,
                "x-api-key"         => $this->apiKey,
                "x-signature"       => $signature,
                "x-idempotency-key" => $idempotencyKey,
            ])->post($this->endpoint . "/v1/payouts", $body);

            // ⚙️ Log phản hồi từ API
            Log::info('Payout response', [
                'referenceId' => $referenceId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->failed()) {
                Log::warning('Payout API returned failure', [
                    'referenceId' => $referenceId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Payout exception caught', [
                'referenceId' => $referenceId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify chữ ký từ webhook
     */
    public function verifySignature(array $data, ?string $signature): bool
    {
        if (empty($signature)) {
            return false;
        }

        $rawData    = $this->buildRawData($data);
        $calculated = hash_hmac('sha256', $rawData, $this->checksumKey);

        return hash_equals($calculated, $signature);
    }
}
