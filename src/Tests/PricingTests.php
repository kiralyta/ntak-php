<?php

namespace Kiralyta\Ntak\Tests;

use Carbon\Carbon;
use Kiralyta\Ntak\Enums\NTAKOrderType;
use Kiralyta\Ntak\Enums\NTAKPaymentType;
use Kiralyta\Ntak\Enums\NTAKSubcategory;
use Kiralyta\Ntak\Models\NTAKOrder;
use Kiralyta\Ntak\Models\NTAKPayment;
use Kiralyta\Ntak\NTAK;
use Ramsey\Uuid\Uuid;
use Kiralyta\Ntak\TestCase;
use Kiralyta\Ntak\Models\NTAKOrderItem;

class PricingTests extends TestCase
{
    // 3
    public function test_two_brios_three_hell_no_discount(): void
    {
        $items = [
            $this->productBrios(2),
            $this->productHell(3),
        ];

        $discount = 0;
        $serviceFee = 0;
        $order = $this->createOrder($items, $discount, $serviceFee);

        $this->assertEquals(3004, $order->total());
        $this->assertEquals(3004, $order->totalWithDiscount());

        $built = $order->buildOrderItems();

        $byName = [];
        foreach ($built as $b) {
            $name = $b['megnevezes'] ?? '';
            $byName[$name] = ($byName[$name] ?? 0) + ($b['tetelOsszesito'] ?? 0);
        }

        $this->assertEquals(1804, $byName['briós']);
        $this->assertEquals(1050, $byName['hell']);
        $this->assertEquals(150, $byName['DRS']);
    }

    // 4
    public function test_one_kaja_one_pia_with_five_percent_discount(): void
    {
        $items = [
            $this->productKaja(1),
            $this->productPia(1),
        ];

        $discount = 5;
        $serviceFee = 0;
        $order = $this->createOrder($items, $discount, $serviceFee);

        $this->assertEquals(1397, $order->total());
        $this->assertEquals(1327, $order->totalWithDiscount());

        $built = $order->buildOrderItems();

        $discountItems = array_filter($built, fn($item) => ($item['megnevezes'] ?? '') === 'Kedvezmény');
        $this->assertNotEmpty($discountItems, 'No discount item found in built order items');

        $discount = array_values($discountItems)[0];
        $this->assertEquals(-70, $discount['tetelOsszesito']);
    }

    // 5
    public function test_two_brios_with_15_percent_discount(): void
    {
        $items = [
            $this->productBrios(2),
            $this->productHell(3)
        ];

        $discount = 15;
        $serviceFee = 0;
        $order = $this->createOrder($items, $discount, $serviceFee);

        $this->assertEquals(3004, $order->total());
        $this->assertEquals(2554, $order->totalWithDiscount());

        $built = $order->buildOrderItems();

        $discountItems = array_filter($built, fn($item) => ($item['megnevezes'] ?? '') === 'Kedvezmény');
        $this->assertNotEmpty($discountItems, 'No discount item found');

        $discount1 = array_values($discountItems)[0];
        $this->assertEquals(-271, $discount1['tetelOsszesito']);
        $this->assertEquals('A_5', $discount1['afaKategoria']);

        $discount2 = array_values($discountItems)[1];
        $this->assertEquals(-157, $discount2['tetelOsszesito']);
        $this->assertEquals('C_27', $discount2['afaKategoria']);

        $discount3 = array_values($discountItems)[2];
        $this->assertEquals(-22, $discount3['tetelOsszesito']);
        $this->assertEquals('E_0', $discount3['afaKategoria']);
    }

    // 6
    public function test_one_hell_with_13_percent_service_fee(): void
    {
        $items = [
            $this->productHell(1),
        ];

        $discount = 0;
        $serviceFee = 13;
        $order = $this->createOrder($items, $discount, $serviceFee);
        $this->assertEquals(446, $order->total());
        $this->assertEquals(446, $order->totalWithDiscount());

        $built = $order->buildOrderItems();

        $serviceFeeItems = array_filter($built, fn($item) => ($item['megnevezes'] ?? '') === 'Szervízdíj');
        $this->assertNotEmpty($serviceFeeItems, 'No service fee item found');

        $serviceFeeItem = array_values($serviceFeeItems)[0];
        $this->assertEquals(46, $serviceFeeItem['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItem['afaKategoria']);
    }

    // 7
    public function test_four_hells_with_13_percent_service_fee(): void
    {
        $items = [
            $this->productHell(4),
        ];

        $discount = 0;
        $serviceFee = 13;
        $order = $this->createOrder($items, $discount, $serviceFee);
        $this->assertEquals(1782, $order->total());
        $this->assertEquals(1782, $order->totalWithDiscount());

        $built = $order->buildOrderItems();

        $serviceFeeItems = array_filter($built, fn($item) => ($item['megnevezes'] ?? '') === 'Szervízdíj');
        $this->assertNotEmpty($serviceFeeItems, 'No service fee item found');

        $serviceFeeItem = array_values($serviceFeeItems)[0];
        $this->assertEquals(182, $serviceFeeItem['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItem['afaKategoria']);
    }

    // 8
    public function test_one_kaja_one_pia_one_hell_with_service_fee(): void
    {
        $items = [
            $this->productKaja(1),
            $this->productPia(1),
            $this->productHell(1),
        ];

        $discount = 0;
        $serviceFee = 13;
        $order = $this->createOrder($items, $discount, $serviceFee);
        $this->assertEquals(2024, $order->total());
        $this->assertEquals(2024, $order->totalWithDiscount());

        $built = $order->buildOrderItems();

        $serviceFeeItems = array_filter($built, fn($item) => ($item['megnevezes'] ?? '') === 'Szervízdíj');
        $this->assertNotEmpty($serviceFeeItems, 'No service fee item found');

        $serviceFeeItem = array_values($serviceFeeItems)[0];
        $this->assertEquals(227, $serviceFeeItem['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItem['afaKategoria']);
    }

    // 9
    public function test_two_kaja_two_brios_with_service_fee(): void
    {
        $items = [
            $this->productKaja(2),
            $this->productBrios(2),
        ];

        $discount = 0;
        $serviceFee = 13;
        $order = $this->createOrder($items, $discount, $serviceFee);
        $this->assertEquals(4299, $order->total());
        $this->assertEquals(4299, $order->totalWithDiscount());

        $built = $order->buildOrderItems();

        $serviceFeeItems = array_filter($built, fn($item) => ($item['megnevezes'] ?? '') === 'Szervízdíj');
        $this->assertNotEmpty($serviceFeeItems, 'No service fee item found');

        $serviceFeeItem1 = array_values($serviceFeeItems)[0];
        $this->assertEquals(260, $serviceFeeItem1['tetelOsszesito']);
        $this->assertEquals('C_27', $serviceFeeItem1['afaKategoria']);

        $serviceFeeItem2 = array_values($serviceFeeItems)[1];
        $this->assertEquals(235, $serviceFeeItem2['tetelOsszesito']);
        $this->assertEquals('A_5', $serviceFeeItem2['afaKategoria']);
    }

    // 10
    public function test_one_kaja_one_pia_with_service_fee_with_discount(): void
    {
        $items = [
            $this->productKaja(1),
            $this->productPia(1),
        ];

        $discount = 10;
        $serviceFee = 13;
        $order = $this->createOrder($items, $discount, $serviceFee);
        $this->assertEquals(1579, $order->total());
        $this->assertEquals(1420, $order->totalWithDiscount());

        $built = $order->buildOrderItems();

        $serviceFeeItems = array_filter($built, fn($item) => ($item['megnevezes'] ?? '') === 'Szervízdíj');
        $this->assertNotEmpty($serviceFeeItems, 'No service fee item found');

        $serviceFeeItem = array_values($serviceFeeItems)[0];
        $this->assertEquals(163, $serviceFeeItem['tetelOsszesito']); // service fee of discounted base price
        $this->assertEquals('C_27', $serviceFeeItem['afaKategoria']);

        $discountItems = array_filter($built, fn($item) => ($item['megnevezes'] ?? '') === 'Kedvezmény');
        $this->assertNotEmpty($discountItems, 'No discount item found');

        $discount = array_values($discountItems)[0];
        $this->assertEquals(-140, $discount['tetelOsszesito']);
        $this->assertEquals('C_27', $discount['afaKategoria']);
    }

    // 11
    public function test_six_kaja_six_pia_with_service_fee_with_discount(): void
    {
        $items = [
            $this->productKaja(6),
            $this->productPia(6),
        ];

        $discount = 10;
        $serviceFee = 13;
        $order = $this->createOrder($items, $discount, $serviceFee);
        $this->assertEquals(9472, $order->total());
        $this->assertEquals(8525, $order->totalWithDiscount());

        $built = $order->buildOrderItems();

        $serviceFeeItems = array_filter($built, fn($item) => ($item['megnevezes'] ?? '') === 'Szervízdíj');
        $this->assertNotEmpty($serviceFeeItems, 'No service fee item found');

        $serviceFeeItem = array_values($serviceFeeItems)[0];
        $this->assertEquals(981, $serviceFeeItem['tetelOsszesito']); // service fee of discounted base price
        $this->assertEquals('C_27', $serviceFeeItem['afaKategoria']);

        $discountItems = array_filter($built, fn($item) => ($item['megnevezes'] ?? '') === 'Kedvezmény');
        $this->assertNotEmpty($discountItems, 'No discount item found');

        $discount = array_values($discountItems)[0];
        $this->assertEquals(-838, $discount['tetelOsszesito']);
        $this->assertEquals('C_27', $discount['afaKategoria']);
    }

    // 12
    public function test_one_hell_with_service_fee_with_discount(): void
    {
        $items = [
            $this->productHell(1)
        ];

        $discount = 15;
        $serviceFee = 13;
        $order = $this->createOrder($items, $discount, $serviceFee);
        $this->assertEquals(446, $order->total());
        $this->assertEquals(380, $order->totalWithDiscount());

        $built = $order->buildOrderItems();

        $serviceFeeItems = array_filter($built, fn($item) => ($item['megnevezes'] ?? '') === 'Szervízdíj');
        $this->assertNotEmpty($serviceFeeItems, 'No service fee item found');

        $serviceFeeItem = array_values($serviceFeeItems)[0];
        $this->assertEquals(39, $serviceFeeItem['tetelOsszesito']); // service fee of discounted base price
        $this->assertEquals('C_27', $serviceFeeItem['afaKategoria']);

        $discountItems = array_filter($built, fn($item) => ($item['megnevezes'] ?? '') === 'Kedvezmény');
        $this->assertNotEmpty($discountItems, 'No discount item found');

        $discount1 = array_values($discountItems)[0];
        $this->assertEquals(-52, $discount1['tetelOsszesito']);
        $this->assertEquals('C_27', $discount1['afaKategoria']);

        $discount2 = array_values($discountItems)[1];
        $this->assertEquals(-7, $discount2['tetelOsszesito']);
        $this->assertEquals('E_0', $discount2['afaKategoria']);
    }

}
