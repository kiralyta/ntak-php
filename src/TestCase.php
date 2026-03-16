<?php

namespace Kiralyta\Ntak;

use PHPUnit\Framework\TestCase as FrameworkTestCase;

class TestCase extends FrameworkTestCase
{
    protected string $taxNumber = '11223344122';
    protected string $regNumber = 'ET23002480';
    protected string $softwareRegNumber = 'TABTENDER';
    protected string $version = '1.4.21';
    protected string $certPath = __DIR__.'/../auth/pem.pem';
    protected string $swaggerUrl = 'https://rms.tesztntak.hu';

    /**
     * Reusable product: kaja (1000 Ft, 27% VAT, 1 darab)
     */
    protected function productKaja(\Carbon\Carbon $when): \Kiralyta\Ntak\Models\NTAKOrderItem
    {
        return new \Kiralyta\Ntak\Models\NTAKOrderItem(
            name: 'kaja',
            category: \Kiralyta\Ntak\Enums\NTAKCategory::ETEL,
            subcategory: \Kiralyta\Ntak\Enums\NTAKSubcategory::FOETEL,
            vat: \Kiralyta\Ntak\Enums\NTAKVat::C_27,
            price: 1000,
            amountType: \Kiralyta\Ntak\Enums\NTAKAmount::DARAB,
            amount: 1,
            quantity: 1,
            when: $when
        );
    }

    /**
     * Reusable product: pia (397 Ft, 27% VAT, 0.04 liter)
     */
    protected function productPia(\Carbon\Carbon $when): \Kiralyta\Ntak\Models\NTAKOrderItem
    {
        return new \Kiralyta\Ntak\Models\NTAKOrderItem(
            name: 'pia',
            category: \Kiralyta\Ntak\Enums\NTAKCategory::ALKOHOLOSITAL,
            subcategory: \Kiralyta\Ntak\Enums\NTAKSubcategory::PARLAT,
            vat: \Kiralyta\Ntak\Enums\NTAKVat::C_27,
            price: 397,
            amountType: \Kiralyta\Ntak\Enums\NTAKAmount::LITER,
            amount: 0.04,
            quantity: 1,
            when: $when
        );
    }

    /**
     * Reusable product: briós (902 Ft, 5% VAT, 1 darab)
     */
    protected function productBrios(\Carbon\Carbon $when, int $quantity = 1): \Kiralyta\Ntak\Models\NTAKOrderItem
    {
        return new \Kiralyta\Ntak\Models\NTAKOrderItem(
            name: 'briós',
            category: \Kiralyta\Ntak\Enums\NTAKCategory::ETEL,
            subcategory: \Kiralyta\Ntak\Enums\NTAKSubcategory::PEKSUTEMENY,
            vat: \Kiralyta\Ntak\Enums\NTAKVat::A_5,
            price: 902,
            amountType: \Kiralyta\Ntak\Enums\NTAKAmount::DARAB,
            amount: 1,
            quantity: $quantity,
            when: $when
        );
    }

    /**
     * Reusable product: hell (400 Ft total, 27% VAT) with DRS (separate DRS item expected)
     * The item price passed should include the DRS amount (e.g. 400), and we mark it as DRS so the net product price becomes price - drsAmount.
     */
    protected function productHell(\Carbon\Carbon $when, int $quantity = 1): \Kiralyta\Ntak\Models\NTAKOrderItem
    {
        return new \Kiralyta\Ntak\Models\NTAKOrderItem(
            name: 'hell',
            category: \Kiralyta\Ntak\Enums\NTAKCategory::ALKMENTESITAL_HELYBEN,
            subcategory: \Kiralyta\Ntak\Enums\NTAKSubcategory::ROSTOS_UDITO,
            vat: \Kiralyta\Ntak\Enums\NTAKVat::C_27,
            price: 400,
            amountType: \Kiralyta\Ntak\Enums\NTAKAmount::DARAB,
            amount: 1,
            quantity: $quantity,
            when: $when,
            isDrs: true
        );
    }

    /**
     * Create an NTAKOrder with common defaults (Bank card payment) for tests.
     *
     * @param array $items
     * @param int $discount
     * @param int $serviceFee
     * @param \Carbon\Carbon|null $start
     * @param \Carbon\Carbon|null $end
     * @param \Kiralyta\Ntak\Enums\NTAKOrderType $orderType
     * @return \Kiralyta\Ntak\Models\NTAKOrder
     */
    protected function createOrder(array $items, int $discount = 0, int $serviceFee = 0, ?\Carbon\Carbon $start = null, ?\Carbon\Carbon $end = null, \Kiralyta\Ntak\Enums\NTAKOrderType $orderType = \Kiralyta\Ntak\Enums\NTAKOrderType::NORMAL): \Kiralyta\Ntak\Models\NTAKOrder
    {
        $end = $end ?: \Carbon\Carbon::now();
        $start = $start ?: $end->copy()->subMinutes(5);

        $productsTotal = array_reduce(
            $items,
            function ($carry, $item) {
                return $carry + $item->roundedSum();
            },
            0
        );

        $drsQuantity = array_reduce(
            $items,
            function ($carry, $item) {
                return $carry + (property_exists($item, 'isDrs') && $item->isDrs ? (int)$item->quantity : 0);
            },
            0
        );

        $productsTotal += $drsQuantity * \Kiralyta\Ntak\NTAK::drsAmount;

        $total = (int) round($productsTotal * (1 - $discount / 100) * (1 + $serviceFee / 100));

        return new \Kiralyta\Ntak\Models\NTAKOrder(
            orderType: $orderType,
            orderId: \Ramsey\Uuid\Uuid::uuid4(),
            orderItems: $items,
            start: $start,
            end: $end,
            payments: [new \Kiralyta\Ntak\Models\NTAKPayment(\Kiralyta\Ntak\Enums\NTAKPaymentType::BANKKARTYA, $total)],
            discount: $discount,
            serviceFee: $serviceFee
        );
    }
}
