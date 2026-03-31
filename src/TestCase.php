<?php

namespace Kiralyta\Ntak;

use PHPUnit\Framework\TestCase as FrameworkTestCase;
use Carbon\Carbon;
use Kiralyta\Ntak\Enums\NTAKOrderType;
use Kiralyta\Ntak\Enums\NTAKPaymentType;
use Kiralyta\Ntak\Models\NTAKPayment;
use Kiralyta\Ntak\Models\NTAKOrder;
use Kiralyta\Ntak\Models\NTAKOrderItem;
use Kiralyta\Ntak\Enums\NTAKCategory;
use Kiralyta\Ntak\Enums\NTAKSubcategory;
use Kiralyta\Ntak\Enums\NTAKVat;
use Kiralyta\Ntak\Enums\NTAKAmount;
use Ramsey\Uuid\Uuid;
use Kiralyta\Ntak\Tests\FakeNTAKClient;

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
    protected function productKaja(int $quantity): NTAKOrderItem
    {
        return new NTAKOrderItem(
            name: 'kaja',
            category: NTAKCategory::ETEL,
            subcategory: NTAKSubcategory::FOETEL,
            vat: NTAKVat::C_27,
            price: 1000,
            amountType: NTAKAmount::DARAB,
            amount: 1,
            quantity: $quantity,
            when: Carbon::now()
        );
    }

    /**
     * Reusable product: pia (397 Ft, 27% VAT, 0.04 liter)
     */
    protected function productPia(int $quantity): NTAKOrderItem
    {
        return new NTAKOrderItem(
            name: 'pia',
            category: NTAKCategory::ALKOHOLOSITAL,
            subcategory: NTAKSubcategory::PARLAT,
            vat: NTAKVat::C_27,
            price: 397,
            amountType: NTAKAmount::LITER,
            amount: 0.04,
            quantity: $quantity,
            when: Carbon::now()
        );
    }

    /**
     * Reusable product: briós (902 Ft, 5% VAT, 1 darab)
     */
    protected function productBrios(int $quantity): NTAKOrderItem
    {
        return new NTAKOrderItem(
            name: 'briós',
            category: NTAKCategory::ETEL,
            subcategory: NTAKSubcategory::PEKSUTEMENY,
            vat: NTAKVat::A_5,
            price: 902,
            amountType: NTAKAmount::DARAB,
            amount: 1,
            quantity: $quantity,
            when: Carbon::now()
        );
    }

    /**
     * Reusable product: hell (400 Ft total, 27% VAT) with DRS (separate DRS item expected)
     * The item price passed should include the DRS amount (e.g. 400), and we mark it as DRS so the net product price becomes price - drsAmount.
     */
    protected function productHell(int $quantity): NTAKOrderItem
    {
        return new NTAKOrderItem(
            name: 'hell',
            category: NTAKCategory::ALKMENTESITAL_HELYBEN,
            subcategory: NTAKSubcategory::ROSTOS_UDITO,
            vat: NTAKVat::C_27,
            price: 400,
            amountType: NTAKAmount::DARAB,
            amount: 1,
            quantity: $quantity,
            when: Carbon::now(),
            isDrs: true
        );
    }

    /**
     * Create an NTAKOrder for tests with given parameters.
     *
     * @param int $finalAmount
     * @param array $items
     * @param int $discount
     * @param int $serviceFee
     * @param NTAKOrderType $orderType
     * @return NTAKOrder
     */
    protected function createOrder(int $finalAmount, array $items, int $discount, int $serviceFee): NTAKOrder
    {
        return new NTAKOrder(
            orderType: NTAKOrderType::NORMAL,
            orderId: Uuid::uuid4(),
            orderItems: $items,
            start: Carbon::now()->subMinutes(5),
            end: Carbon::now(),
            payments: [new NTAKPayment(NTAKPaymentType::BANKKARTYA, $finalAmount)],
            discount: $discount,
            serviceFee: $serviceFee
        );
    }

    public function log($order): void
    {
        $orders = [$order];

        $fakeClient = new FakeNTAKClient(
            taxNumber:         'tax_number',
            regNumber:         'NTAK_REGNUMBER',
            softwareRegNumber: 'rms_reg_number',
            version:           'version',
            certPath:          'cert_path',
            testing:           true
        );

        $response = NTAK::message($fakeClient, Carbon::now())->handleOrder(...$orders);

        file_put_contents(
            __DIR__ . '/../debug.log', 
            json_encode($fakeClient->lastRequest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", 
            FILE_APPEND
        );
    }
}
