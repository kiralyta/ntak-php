<?php

namespace Kiralyta\Ntak\Tests;

use Carbon\Carbon;
use Kiralyta\Ntak\Enums\DayType;
use Kiralyta\Ntak\Enums\OrderType;
use Kiralyta\Ntak\Enums\PaymentType;
use Kiralyta\Ntak\Models\NTAKOrder;
use Kiralyta\Ntak\NTAK;
use Kiralyta\Ntak\NTAKClient;
use PHPUnit\Framework\TestCase;

class StoreOrderTest extends TestCase
{
    /**
     * test_store_order
     *
     * @return void
     */
    public function test_store_order(): void
    {
        NTAK::message(
            new NTAKClient('3453234-32-4', 'RMX43', 'TabTenderYohh', '1.4.17'),
            Carbon::now()
        )->storeOrder(
            new NTAKOrder(
                orderType: OrderType::NORMAL,
                orderId: random_int(1000, 210204),
                start: Carbon::now()->addMinutes(-3),
                end: Carbon::now()->addMinutes(-1),
                total: 1954,
                paymentType: PaymentType::KESZPENZHUF
            )
        );

        $this->assertTrue(true);
    }
}
