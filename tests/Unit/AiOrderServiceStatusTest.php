<?php

namespace Tests\Unit;

use Tests\TestCase;
use ReflectionClass;
use App\Services\AiOrderService;

/**
 * Test cho isMissingField() và determineOrderStatus()
 *
 * Chạy: php artisan test --filter AiOrderServiceStatusTest
 */
class AiOrderServiceStatusTest extends TestCase
{
    private object $service;
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();

        // Resolve qua Laravel container (có đủ config, binding)
        $this->service = app(AiOrderService::class);
        $this->ref = new ReflectionClass($this->service);
    }

    /** Gọi protected method dễ dàng */
    private function invoke(string $method, mixed ...$args): mixed
    {
        $m = $this->ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke($this->service, ...$args);
    }

    // =========================================================================
    // isMissingField()
    // =========================================================================

    /** @test */
    public function isMissingField_returns_true_for_null(): void
    {
        $this->assertTrue($this->invoke('isMissingField', null));
    }

    /** @test */
    public function isMissingField_returns_true_for_empty_string(): void
    {
        $this->assertTrue($this->invoke('isMissingField', ''));
    }

    /** @test */
    public function isMissingField_returns_true_for_placeholder_exact(): void
    {
        $this->assertTrue($this->invoke('isMissingField', 'Sẽ cung cấp sau'));
    }

    /** @test */
    public function isMissingField_returns_true_for_placeholder_uppercase(): void
    {
        $this->assertTrue($this->invoke('isMissingField', 'SẼ CUNG CẤP SAU'));
    }

    /** @test */
    public function isMissingField_returns_true_for_placeholder_mixed_case(): void
    {
        $this->assertTrue($this->invoke('isMissingField', 'Sẽ Cung Cấp Sau'));
    }

    /** @test */
    public function isMissingField_returns_false_for_real_phone(): void
    {
        $this->assertFalse($this->invoke('isMissingField', '0901234567'));
    }

    /** @test */
    public function isMissingField_returns_false_for_real_address(): void
    {
        $this->assertFalse($this->invoke('isMissingField', '123 Nguyễn Trãi, Rạch Giá'));
    }

    // =========================================================================
    // determineOrderStatus() — type: delivery
    // =========================================================================

    /** @test */
    public function delivery_with_full_info_returns_pending(): void
    {
        $status = $this->invoke('determineOrderStatus', 'delivery', [
            'delivery_address' => '123 Lê Lợi, Rạch Giá',
            'delivery_phone'   => '0901234567',
        ]);
        $this->assertSame('pending', $status);
    }

    /** @test */
    public function delivery_missing_address_returns_draft(): void
    {
        $status = $this->invoke('determineOrderStatus', 'delivery', [
            'delivery_address' => '',
            'delivery_phone'   => '0901234567',
        ]);
        $this->assertSame('draft', $status);
    }

    /** @test */
    public function delivery_missing_phone_returns_draft(): void
    {
        $status = $this->invoke('determineOrderStatus', 'delivery', [
            'delivery_address' => '123 Lê Lợi, Rạch Giá',
            'delivery_phone'   => '',
        ]);
        $this->assertSame('draft', $status);
    }

    /** @test */
    public function delivery_with_placeholder_address_returns_draft(): void
    {
        // ← Đây là bug đã fix: trước đây trả về pending
        $status = $this->invoke('determineOrderStatus', 'delivery', [
            'delivery_address' => 'Sẽ cung cấp sau',
            'delivery_phone'   => '0901234567',
        ]);
        $this->assertSame('draft', $status);
    }

    /** @test */
    public function delivery_with_placeholder_phone_returns_draft(): void
    {
        // ← Đây là bug đã fix: trước đây trả về pending
        $status = $this->invoke('determineOrderStatus', 'delivery', [
            'delivery_address' => '123 Lê Lợi, Rạch Giá',
            'delivery_phone'   => 'Sẽ cung cấp sau',
        ]);
        $this->assertSame('draft', $status);
    }

    /** @test */
    public function delivery_with_both_placeholders_returns_draft(): void
    {
        $status = $this->invoke('determineOrderStatus', 'delivery', [
            'delivery_address' => 'Sẽ cung cấp sau',
            'delivery_phone'   => 'Sẽ cung cấp sau',
        ]);
        $this->assertSame('draft', $status);
    }

    /** @test */
    public function delivery_with_null_address_returns_draft(): void
    {
        $status = $this->invoke('determineOrderStatus', 'delivery', [
            'delivery_phone' => '0901234567',
        ]);
        $this->assertSame('draft', $status);
    }

    // =========================================================================
    // determineOrderStatus() — type: shopping
    // =========================================================================

    /** @test */
    public function shopping_with_full_info_returns_pending(): void
    {
        $status = $this->invoke('determineOrderStatus', 'shopping', [
            'items'            => 'Trà sữa, bánh mì',
            'delivery_address' => '456 Trần Phú, Rạch Giá',
        ]);
        $this->assertSame('pending', $status);
    }

    /** @test */
    public function shopping_missing_items_returns_draft(): void
    {
        $status = $this->invoke('determineOrderStatus', 'shopping', [
            'items'            => '',
            'delivery_address' => '456 Trần Phú',
        ]);
        $this->assertSame('draft', $status);
    }

    /** @test */
    public function shopping_with_placeholder_address_returns_draft(): void
    {
        $status = $this->invoke('determineOrderStatus', 'shopping', [
            'items'            => 'Trà sữa',
            'delivery_address' => 'Sẽ cung cấp sau',
        ]);
        $this->assertSame('draft', $status);
    }

    // =========================================================================
    // determineOrderStatus() — type: bike / motor / car
    // =========================================================================

    /** @test */
    public function bike_with_pickup_address_returns_pending(): void
    {
        $status = $this->invoke('determineOrderStatus', 'bike', [
            'pickup_address'   => '789 Nguyễn Huệ',
            'delivery_address' => '321 Lý Thường Kiệt',
            'delivery_phone'   => '0912345678',
        ]);
        $this->assertSame('pending', $status);
    }

    /** @test */
    public function bike_missing_pickup_address_returns_draft(): void
    {
        $status = $this->invoke('determineOrderStatus', 'bike', [
            'pickup_address'   => '',
            'delivery_address' => '321 Lý Thường Kiệt',
        ]);
        $this->assertSame('draft', $status);
    }

    /** @test */
    public function bike_with_placeholder_pickup_returns_draft(): void
    {
        $status = $this->invoke('determineOrderStatus', 'bike', [
            'pickup_address'   => 'Sẽ cung cấp sau',
            'delivery_address' => '321 Lý Thường Kiệt',
        ]);
        $this->assertSame('draft', $status);
    }

    /** @test */
    public function motor_missing_pickup_returns_draft(): void
    {
        $status = $this->invoke('determineOrderStatus', 'motor', []);
        $this->assertSame('draft', $status);
    }

    /** @test */
    public function car_missing_pickup_returns_draft(): void
    {
        $status = $this->invoke('determineOrderStatus', 'car', [
            'pickup_address' => 'Sẽ cung cấp sau',
        ]);
        $this->assertSame('draft', $status);
    }
}
