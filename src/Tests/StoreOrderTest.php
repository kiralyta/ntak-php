<?php

namespace Kiralyta\Ntak\Tests;

use Carbon\Carbon;
use Kiralyta\Ntak\Enums\NTAKAmount;
use Kiralyta\Ntak\Enums\NTAKCategory;
use Kiralyta\Ntak\Enums\NTAKOrderType;
use Kiralyta\Ntak\Enums\NTAKPaymentType;
use Kiralyta\Ntak\Enums\NTAKSubcategory;
use Kiralyta\Ntak\Enums\NTAKVat;
use Kiralyta\Ntak\Models\NTAKOrder;
use Kiralyta\Ntak\Models\NTAKOrderItem;
use Kiralyta\Ntak\NTAK;
use Kiralyta\Ntak\NTAKClient;
use Kiralyta\Ntak\TestCase;

class StoreOrderTest extends TestCase
{
    /**
     * test_store_order
     *
     * @return void
     */
    public function test_store_order(): void
    {
        $orderItems = [
            new NTAKOrderItem(
                name: 'Absolut vodka',
                category: NTAKCategory::ALKOHOLOSITAL,
                subcategory: NTAKSubcategory::PARLAT,
                vat: NTAKVat::C_27,
                price: 1000,
                amountType: NTAKAmount::LITER,
                amount: 0.04,
                quantity: 2,
                when: Carbon::now()
            ),
            new NTAKOrderItem(
                name: 'Túró rudi',
                category: NTAKCategory::ETEL,
                subcategory: NTAKSubcategory::SNACK,
                vat: NTAKVat::C_27,
                price: 1000,
                amountType: NTAKAmount::DARAB,
                amount: 1,
                quantity: 2,
                when: Carbon::now()
            )
        ];

        NTAK::message(
            new NTAKClient(
                $this->taxNumber,
                $this->regNumber,
                $this->softwareRegNumber,
                $this->version,
                $this->certPath,
                $this->keyPath,
                true
            ),
            Carbon::now()
        )->handleOrder(
            new NTAKOrder(
                orderType: NTAKOrderType::NORMAL,
                orderId: random_int(1000, 210204),
                orderItems: $orderItems,
                start: Carbon::now()->addMinutes(-3),
                end: Carbon::now()->addMinutes(-1),
                total: 1954,
                paymentType: NTAKPaymentType::KESZPENZHUF
            )
        );

        $this->assertTrue(true);
    }
}
