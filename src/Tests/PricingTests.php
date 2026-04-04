<?php

namespace Kiralyta\Ntak\Tests;

use Carbon\Carbon;
use Kiralyta\Ntak\Enums\NTAKOrderType;
use Kiralyta\Ntak\Enums\NTAKPaymentType;
use Kiralyta\Ntak\Enums\NTAKSubcategory;
use Kiralyta\Ntak\Enums\NTAKVat;
use Kiralyta\Ntak\Enums\NTAKCategory;
use Kiralyta\Ntak\Enums\NTAKAmount;
use Kiralyta\Ntak\Models\NTAKOrder;
use Kiralyta\Ntak\Models\NTAKPayment;
use Kiralyta\Ntak\NTAK;
use Ramsey\Uuid\Uuid;
use Kiralyta\Ntak\Models\NTAKOrderItem;
use PHPUnit\Framework\TestCase as FrameworkTestCase;

class PricingTests extends FrameworkTestCase
{
    // don't forget: each line item (order item prices, discount items per VAT, service fees per VAT, DRS items) must be rounded to whole number, which can cause unexpected cases!
    // 1
    public function test_one_kaja(): void
    {
        $items = [
            $this->productKaja(1)
        ];

        $finalAmount = 1000;
        $discount = 0;
        $serviceFee = 0;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(0, $discountItems);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(0, $serviceFeeItems);
    }

    // 2
    public function test_one_kaja_one_pia(): void
    {
        $items = [
            $this->productKaja(1),
            $this->productPia(1),
        ];

        $finalAmount = 1397; // 1000 + 397 = 1397
        $discount = 0;
        $serviceFee = 0;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(0, $discountItems);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(0, $serviceFeeItems);
    }

    // 3
    public function test_two_brios_three_hell(): void
    {
        $items = [
            $this->productBrios(2),
            $this->productHell(3),
        ];

        $finalAmount = 3004; // 2 * 902 + 3 * 350 + 3 * 50 = 3004
        $discount = 0;
        $serviceFee = 0;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(0, $discountItems);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(0, $serviceFeeItems);

        $byName = [];
        foreach ($builtOrderItems as $b) {
            $name = $b['megnevezes'] ?? '';
            $byName[$name] = ($byName[$name] ?? 0) + ($b['tetelOsszesito'] ?? 0);
        }

        $this->assertEquals(1804, $byName['briós']);
        $this->assertEquals(1050, $byName['hell']);
        $this->assertEquals(150, $byName['DRS']);
    }

    // 4
    public function test_one_kaja_one_pia_with_discount(): void
    {
        $items = [
            $this->productKaja(1),
            $this->productPia(1),
        ];

        $finalAmount = 1327; // (1000 + 397) * 0.95 = 1327.15 ~ 1327
        $discount = 5;
        $serviceFee = 0;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(1, $discountItems);

        $this->assertEquals(-70, $discountItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $discountItems[0]['afaKategoria']);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(0, $serviceFeeItems);
    }

    // 5
    public function test_two_brios_with_discount(): void
    {
        $items = [
            $this->productBrios(2),
            $this->productHell(3)
        ];

        $finalAmount = 2554; // 5%: 902 * 2 * 0.85 = 1533.4 ~ 1533; 27%: 350 * 3 * 0.85 = 892.5 ~ 893; 0%: 3 * 50 * 0.85 = 127.5 ~ 128 ==> 1533 + 893 + 128 = 2554
        $discount = 15;
        $serviceFee = 0;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(3, $discountItems);

        $this->assertEquals(-271, $discountItems[0]['tetelOsszesito']);
        $this->assertEquals('A_5', $discountItems[0]['afaKategoria']);

        $this->assertEquals(-157, $discountItems[1]['tetelOsszesito']);
        $this->assertEquals('C_27', $discountItems[1]['afaKategoria']);

        $this->assertEquals(-22, $discountItems[2]['tetelOsszesito']);
        $this->assertEquals('E_0', $discountItems[2]['afaKategoria']);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(0, $serviceFeeItems);
    }

    // 6
    public function test_one_hell_with_service_fee(): void
    {
        $items = [
            $this->productHell(1),
        ];

        $finalAmount = 446; // 350 * 1.13 + 50 = 445.5 ~ 446
        $discount = 0;
        $serviceFee = 13;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(0, $discountItems);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(1, $serviceFeeItems);

        $this->assertEquals(46, $serviceFeeItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItems[0]['afaKategoria']);
    }

    // 7
    public function test_four_hell_with_service_fee(): void
    {
        $items = [
            $this->productHell(4),
        ];

        $finalAmount = 1782; // 4 * 350 * 1.13 + 4 * 50 = 1782
        $discount = 0;
        $serviceFee = 13;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(0, $discountItems);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(1, $serviceFeeItems);

        $this->assertEquals(182, $serviceFeeItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItems[0]['afaKategoria']);
    }

    // 8
    public function test_one_kaja_one_pia_one_hell_with_service_fee(): void
    {
        $items = [
            $this->productKaja(1),
            $this->productPia(1),
            $this->productHell(1),
        ];

        $finalAmount = 2024; // (1000 + 397 + 350) * 1.13 + 50 = 2024.11 ~ 2024
        $discount = 0;
        $serviceFee = 13;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(0, $discountItems);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(1, $serviceFeeItems);

        $this->assertEquals(227, $serviceFeeItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItems[0]['afaKategoria']);
    }

    // 9
    public function test_two_kaja_two_brios_with_service_fee(): void
    {
        $items = [
            $this->productKaja(2),
            $this->productBrios(2),
        ];

        $finalAmount = 4299; // (2 * 1000 + 2 * 902) * 1.13 = 4298.52 ~ 4299
        $discount = 0;
        $serviceFee = 13;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(0, $discountItems);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(2, $serviceFeeItems);

        $this->assertEquals(260, $serviceFeeItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItems[0]['afaKategoria']);

        $this->assertEquals(235, $serviceFeeItems[1]['tetelOsszesito']);
        $this->assertEquals('A_5', $serviceFeeItems[1]['afaKategoria']);
    }

    // 10
    public function test_one_kaja_one_pia_with_service_fee_with_discount(): void
    {
        $items = [
            $this->productKaja(1),
            $this->productPia(1),
        ];

        $finalAmount = 1420; // service fee: (1000 + 397) * 0.9 * 0.13 = 163.449 ~ 163; final amount: (1000 + 397) * 0.9 + 163 = 1420.3 ~ 1420
        $discount = 10;
        $serviceFee = 13;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(1, $discountItems);

        $this->assertEquals(-140, $discountItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $discountItems[0]['afaKategoria']);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(1, $serviceFeeItems);

        $this->assertEquals(163, $serviceFeeItems[0]['tetelOsszesito']); // service fee of discounted base price
        $this->assertEquals('C_27', $serviceFeeItems[0]['afaKategoria']);
    }

    // 11
    public function test_six_kaja_six_pia_with_service_fee_with_discount(): void
    {
        $items = [
            $this->productKaja(6),
            $this->productPia(6),
        ];

        $finalAmount = 8525; // service fee: (6 * 1000 + 6 * 397) * 0.9 * 0.13 = 980.694 ~ 981; final amount: (6 * 1000 + 6 * 397) * 0.9 + 981 = 8524.8 ~ 8525
        $discount = 10;
        $serviceFee = 13;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(1, $discountItems);

        $this->assertEquals(-838, $discountItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $discountItems[0]['afaKategoria']);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(1, $serviceFeeItems);

        $this->assertEquals(981, $serviceFeeItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItems[0]['afaKategoria']);
    }

    // 12
    public function test_one_hell_with_service_fee_with_discount(): void
    {
        $items = [
            $this->productHell(1)
        ];

        $finalAmount = 380; // service fee: 350 * 0.85 * 0.13 = 38.675 ~ 39; drs: 50 * 0.85 = 42.5 ~ 43; final amount: 350 * 0.85 + 39 + 43 = 379.5 ~ 380
        $discount = 15;
        $serviceFee = 13;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(2, $discountItems);

        $this->assertEquals(-52, $discountItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $discountItems[0]['afaKategoria']);

        $this->assertEquals(-7, $discountItems[1]['tetelOsszesito']);
        $this->assertEquals('E_0', $discountItems[1]['afaKategoria']);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(1, $serviceFeeItems);

        $this->assertEquals(39, $serviceFeeItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItems[0]['afaKategoria']);
    }

    // 13
    public function test_seven_hell_with_service_fee(): void
    {
        $items = [
            $this->productHell(7)
        ];

        $finalAmount = 3119; // service fee: 7 * 350 * 0.13 = 318.5 ~ 319; final amount: 7 * 400 + 319 = 3119
        $discount = 0;
        $serviceFee = 13;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(0, $discountItems);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(1, $serviceFeeItems);

        $this->assertEquals(319, $serviceFeeItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItems[0]['afaKategoria']);
    }

    // 14
    public function test_seven_hell_with_service_fee_with_discount(): void
    {
        $items = [
            $this->productHell(7) // service fee: 7 * 350 * 0.85 * 0.13 = 270.725 ~ 271; drs: 7 * 50 * 0.85 = 297.5 ~ 298; final amount: 7 * 350 * 0.85 + 271 + 298 = 2651.5 ~ 2652
        ];

        $finalAmount = 2652;
        $discount = 15;
        $serviceFee = 13;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(2, $discountItems);

        $this->assertEquals(-367, $discountItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $discountItems[0]['afaKategoria']);

        $this->assertEquals(-52, $discountItems[1]['tetelOsszesito']);
        $this->assertEquals('E_0', $discountItems[1]['afaKategoria']);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(1, $serviceFeeItems);

        $this->assertEquals(271, $serviceFeeItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItems[0]['afaKategoria']);
    }

    // 15
    public function test_one_kaja_one_pia_one_brios_one_hell_with_service_fee_with_discount(): void
    {
        $items = [
            $this->productKaja(1),
            $this->productPia(1),
            $this->productBrios(1),
            $this->productHell(1),
        ];

        // service fee: 27%: (1000 + 397 + 350) * 0.8 * 0.13 = 181.688 ~ 182; 5%: 902 * 0.8 * 0.13 = 93.808 ~ 94
        // final amount: round(1000 * 0.8) + round(397 * 0.8) + round(902 * 0.8) + round(400 * 0.8) + 182 + 94 = 800 + 317.6 + 721.6 + 320 + 182 + 94 = 2436
        $finalAmount = 2436;
        $discount = 20;
        $serviceFee = 13;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(3, $discountItems);

        $this->assertEquals(-349, $discountItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $discountItems[0]['afaKategoria']);

        $this->assertEquals(-180, $discountItems[1]['tetelOsszesito']);
        $this->assertEquals('A_5', $discountItems[1]['afaKategoria']);

        $this->assertEquals(-10, $discountItems[2]['tetelOsszesito']);
        $this->assertEquals('E_0', $discountItems[2]['afaKategoria']);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(2, $serviceFeeItems);

        $this->assertEquals(182, $serviceFeeItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItems[0]['afaKategoria']);

        $this->assertEquals(94, $serviceFeeItems[1]['tetelOsszesito']);
        $this->assertEquals('A_5', $serviceFeeItems[1]['afaKategoria']);
    }

    // 16
    public function test_six_kaja_six_brios_six_pia_with_service_fee(): void
    {
        $items = [
            $this->productKaja(6),
            $this->productBrios(6),
            $this->productPia(6),
        ];

        // service fee: 27%: (6 * 1000 + 6 * 397) * 0.13 = 1089.66 ~ 1090; 5%: 6 * 902 * 0.13 = 703.56 ~ 704
        // final amount: 6 * 1000 + 6 * 902 + 6 * 397 + 1090 + 704 = 15588
        $finalAmount = 15588;
        $discount = 0;
        $serviceFee = 13;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(0, $discountItems);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(2, $serviceFeeItems);

        $this->assertEquals(1090, $serviceFeeItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItems[0]['afaKategoria']);

        $this->assertEquals(704, $serviceFeeItems[1]['tetelOsszesito']);
        $this->assertEquals('A_5', $serviceFeeItems[1]['afaKategoria']);
    }

    // 17
    public function test_one_jegy_one_kaja(): void
    {
        $items = [
            $this->productJegy(1),
            $this->productKaja(1)
        ];

        $finalAmount = 2500; // 1500 + 1000 = 2500
        $discount = 0;
        $serviceFee = 0;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(0, $discountItems);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(0, $serviceFeeItems);
    }

    // 18
    public function test_one_jegy_one_kaja_with_service_fee(): void
    {
        $items = [
            $this->productJegy(1), // no service fee for jegy
            $this->productKaja(1)
        ];

        $finalAmount = 2630; // kaja should have 130 Ft service fee, but jegy shouldn't (bypass service fee), so: 1000 * 1.13 + 1500 = 2630
        $discount = 0;
        $serviceFee = 13;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(0, $discountItems);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(1, $serviceFeeItems);

        $this->assertEquals(130, $serviceFeeItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItems[0]['afaKategoria']);
    }

    // 19
    public function test_one_jegy_one_kaja_with_service_fee_with_discount(): void
    {
        $items = [
            $this->productJegy(1), // no service fee for jegy
            $this->productKaja(1)
        ];

        $finalAmount = 2367; // 1000 * 0.9 * 1.13 + 1500 * 0.9 = 2367
        $discount = 10;
        $serviceFee = 13;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(1, $discountItems);

        $this->assertEquals(-250, $discountItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $discountItems[0]['afaKategoria']);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(1, $serviceFeeItems);

        $this->assertEquals(117, $serviceFeeItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItems[0]['afaKategoria']);
    }

    // 20
    public function test_two_kaja_two_brios_two_hell_two_jegy_with_service_fee(): void
    {
        $items = [
            $this->productKaja(2),
            $this->productBrios(2),
            $this->productHell(2),
            $this->productJegy(2) // no service fee for jegy
        ];

        // service fee: 27%: (2 * 1000 + 2 * 350) * 0.13 = 351; 5%: 2 * 902 * 0.13 = 234.52 ~ 235
        // final amount: 2 * 1000 + 2 * 902 + 2 * 350 + 2 * 1500 + 2 * 50 + 351 + 235 = 8190
        $finalAmount = 8190; 
        $discount = 0;
        $serviceFee = 13;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(0, $discountItems);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(2, $serviceFeeItems);

        $this->assertEquals(351, $serviceFeeItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItems[0]['afaKategoria']);

        $this->assertEquals(235, $serviceFeeItems[1]['tetelOsszesito']);
        $this->assertEquals('A_5', $serviceFeeItems[1]['afaKategoria']);
    }

    // 21
    public function test_one_vacsi_one_uzsi_with_service_fee_with_discount(): void
    {
        $items = [
            $this->productVacsi(1),
            $this->productUzsi(1)
        ];

        // service fee: (3490 + 4490) * 0.13 * 0.85 = 881.79 ~ 882
        // final amount: round(3490 * 0.85) + round(4490 * 0.85) + 882 = round(2966.5) + round(3816.5) + 882 = 2967 + 3817 + 882 = 7666
        $finalAmount = 7666; 
        $discount = 15;
        $serviceFee = 13;
        $order = $this->createOrder($finalAmount, $items, $discount, $serviceFee);

        $this->log($order);
        $builtOrderItems = $order->buildOrderItems();

        $sumOfOrderItems = array_sum(array_column($builtOrderItems, 'tetelOsszesito'));
        $this->assertEquals($finalAmount, $sumOfOrderItems);

        $discountItems = $this->getDiscountItems($builtOrderItems);
        $this->assertCount(1, $discountItems);

        $this->assertEquals(-1196, $discountItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $discountItems[0]['afaKategoria']);

        $serviceFeeItems = $this->getServiceFeeItems($builtOrderItems);
        $this->assertCount(1, $serviceFeeItems);

        $this->assertEquals(882, $serviceFeeItems[0]['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItems[0]['afaKategoria']);
    }

    private function getDiscountItems(array $orderItems): array
    {
        return $this->getFilteredItems($orderItems, NTAKSubcategory::KEDVEZMENY);
    }

    private function getServiceFeeItems(array $orderItems): array
    {
        return $this->getFilteredItems($orderItems, NTAKSubcategory::SZERVIZDIJ);
    }

    private function getFilteredItems(array $orderItems, NTAKSubcategory $ntakSubCategory): array
    {
        $filtered = array_filter($orderItems, function($item) use ($ntakSubCategory) {
            return $item['alkategoria'] === $ntakSubCategory->name;
        });

        return array_values($filtered);
    }

    /**
     * Reusable product for tests: kaja (1000 Ft, 27% VAT)
     */
    private function productKaja(int $quantity): NTAKOrderItem
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
     * Reusable product for tests: pia (397 Ft, 27% VAT, no DRS)
     */
    private function productPia(int $quantity): NTAKOrderItem
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
     * Reusable product for tests: briós (902 Ft, 5% VAT)
     */
    private function productBrios(int $quantity): NTAKOrderItem
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
     * Reusable product for tests: hell (400 Ft, 27% VAT, with DRS included)
     */
    private function productHell(int $quantity): NTAKOrderItem
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
     * Reusable product for tests: jegy (1500 Ft, 27% VAT, with bypass service fee option, so service fee will not be added for this product)
     */
    private function productJegy(int $quantity): NTAKOrderItem
    {
        return new NTAKOrderItem(
            name: 'jegy',
            category: NTAKCategory::ETEL,
            subcategory: NTAKSubcategory::FOETEL,
            vat: NTAKVat::C_27,
            price: 1500,
            amountType: NTAKAmount::DARAB,
            amount: 1,
            quantity: $quantity,
            when: Carbon::now(),
            isDrs: false,
            bypassServiceFee: true
        );
    }

    private function productVacsi(int $quantity): NTAKOrderItem
    {
        return new NTAKOrderItem(
            name: 'vacsi',
            category: NTAKCategory::ETEL,
            subcategory: NTAKSubcategory::FOETEL,
            vat: NTAKVat::C_27,
            price: 3490,
            amountType: NTAKAmount::DARAB,
            amount: 1,
            quantity: $quantity,
            when: Carbon::now()
        );
    }

    private function productUzsi(int $quantity): NTAKOrderItem
    {
        return new NTAKOrderItem(
            name: 'uzsi',
            category: NTAKCategory::ETEL,
            subcategory: NTAKSubcategory::FOETEL,
            vat: NTAKVat::C_27,
            price: 4490,
            amountType: NTAKAmount::DARAB,
            amount: 1,
            quantity: $quantity,
            when: Carbon::now()
        );
    }

    private function createOrder(int $finalAmount, array $items, int $discount, int $serviceFee): NTAKOrder
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

    private function log($order): void
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
