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
use Kiralyta\Ntak\Models\NTAKPayment;
use Kiralyta\Ntak\NTAK;
use Kiralyta\Ntak\NTAKClient;
use Kiralyta\Ntak\TestCase;
use Ramsey\Uuid\Uuid;

class StoreOrderTest extends TestCase
{
    /**
     * test_store_order
     *
     * @return void
     */
    public function test_store_order(): void
    {
        $when = Carbon::now()->addMinutes(-1);

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
                when: $when
            ),
            new NTAKOrderItem(
                name: 'Túró rudi',
                category: NTAKCategory::ETEL,
                subcategory: NTAKSubcategory::SNACK,
                vat: NTAKVat::C_27,
                price: 1001,
                amountType: NTAKAmount::DARAB,
                amount: 1,
                quantity: 2,
                when: $when
            )
        ];

        $response = NTAK::message(
            $client = new NTAKClient(
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
                orderId: Uuid::uuid4(),
                orderItems: $orderItems,
                start: $when->copy()->addMinutes(-7),
                end: $when,
                payments: [
                    new NTAKPayment(
                        NTAKPaymentType::KESZPENZHUF,
                        4002
                    )
                ]
            )
        );

        $this->assertIsString($response);
        $this->assertIsArray($client->lastRequest());
        $this->assertIsArray($client->lastResponse());
    }
}
