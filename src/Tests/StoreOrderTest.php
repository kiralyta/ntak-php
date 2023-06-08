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
    protected NTAKClient|null $client;

    /**
     * test_store_order
     *
     * @return void
     */
    public function test_store_order(): void
    {
        $when = Carbon::now()->addMinutes(-1);

        $response = $this->ntak()->handleOrder(
            $this->ntakOrder($when, NTAKOrderType::NORMAL)
        );

        $this->assertIsString($response);
        $this->assertIsArray($this->client->lastRequest());
        $this->assertIsArray($this->client->lastResponse());
    }

    public function test_destroy_order(): void
    {
        // Create order
        $when = Carbon::now()->addMinutes(-1);

        $response = $this->ntak()->handleOrder(
            $ntakOrder = $this->ntakOrder($when, NTAKOrderType::NORMAL)
        );

        // Destroy order
        $when = Carbon::now()->addMinutes(-1);

        $response = $this->ntak()->handleOrder(
            $ntakOrder = $this->ntakOrder($when, NTAKOrderType::NORMAL)
        );
    }

    /**
     * ntak
     *
     * @return NTAK
     */
    protected function ntak(): NTAK
    {
        return NTAK::message(
            $this->client = new NTAKClient(
                $this->taxNumber,
                $this->regNumber,
                $this->softwareRegNumber,
                $this->version,
                $this->certPath,
                $this->keyPath,
                true
            ),
            Carbon::now()
        );
    }

    /**
     * orderItems
     *
     * @param  Carbon $when
     * @return array
     */
    protected function orderItems(Carbon $when): array
    {
        return [
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
                vat: NTAKVat::A_5,
                price: 500,
                amountType: NTAKAmount::DARAB,
                amount: 1,
                quantity: 2,
                when: $when
            )
        ];
    }

    /**
     * ntakOrder
     *
     * @param  Carbon        $when
     * @param  NTAKOrderType $orderType
     * @return NTAKOrder
     */
    protected function ntakOrder(Carbon $when, NTAKOrderType $orderType): NTAKOrder
    {
        return new NTAKOrder(
            orderType: $orderType,
            orderId: Uuid::uuid4(),
            orderItems: $this->orderItems($when),
            start: $when->copy()->addMinutes(-7),
            end: $when,
            payments: [
                new NTAKPayment(
                    NTAKPaymentType::KESZPENZHUF,
                    3000 * 0.8 + 3000 * 0.8 * 0.1
                )
            ],
            discount: 20,
            serviceFee: 10
        );
    }
}
