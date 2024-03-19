<?php

namespace Kiralyta\Ntak\Tests;

use GuzzleHttp\Client;
use Kiralyta\Ntak\Enums\NTAKAmount;
use Kiralyta\Ntak\Enums\NTAKCategory;
use Kiralyta\Ntak\Enums\NTAKDayType;
use Kiralyta\Ntak\Enums\NTAKOrderType;
use Kiralyta\Ntak\Enums\NTAKPaymentType;
use Kiralyta\Ntak\Enums\NTAKSubcategory;
use Kiralyta\Ntak\Enums\NTAKVat;
use Kiralyta\Ntak\Enums\NTAKVerifyStatus;
use Kiralyta\Ntak\TestCase;

class EnumTest extends TestCase
{
    public mixed $jsonData;

    public function setUp(): void {
        $client = new Client([
            'base_uri' => $this->swaggerUrl,
            'cert'     => $this->certPath,
            'ssl_key'  => $this->certPath,
            'verify'   => false,
        ]);

        $swaggerJson = $client->request(
            'GET',
            '/v3/api-docs/rms'
        );

        $this -> jsonData = json_decode($swaggerJson->getBody()->getContents());
    }

    public function tearDown(): void {
        $this -> jsonData = null;
    }

    public function test_ntak_payment_type(): void
    {

        $arrayOfEnum = $this -> jsonData->components->schemas->Fizetes->properties->fizetesiMod->enum;

        self::assertIsArray($arrayOfEnum);
        self::assertEqualsCanonicalizing($arrayOfEnum, NTAKPaymentType::names());

    }

    public function test_ntak_order_type(): void
    {
        $arrayOfEnum = $this -> jsonData->components->schemas->RendelesOsszesitoAdat->properties->rendelesBesorolasa->enum;

        self::assertIsArray($arrayOfEnum);
        self::assertEqualsCanonicalizing($arrayOfEnum, NTAKOrderType::names());

    }

    public function test_ntak_category(): void
    {
        $arrayOfEnum = $this -> jsonData->components->schemas->RendelesiTetel->properties->fokategoria->enum;

        self::assertIsArray($arrayOfEnum);
        self::assertEqualsCanonicalizing($arrayOfEnum, NTAKCategory::names());

    }

    public function test_ntak_sub_category(): void
    {
        $arrayOfEnum = $this -> jsonData->components->schemas->RendelesiTetel->properties->alkategoria->enum;

        self::assertIsArray($arrayOfEnum);
        self::assertEqualsCanonicalizing($arrayOfEnum, NTAKSubcategory::names());

    }

    public function test_ntak_vat(): void
    {
        $arrayOfEnum = $this -> jsonData->components->schemas->RendelesiTetel->properties->afaKategoria->enum;

        self::assertIsArray($arrayOfEnum);
        self::assertEqualsCanonicalizing($arrayOfEnum, NTAKVat::names());

    }

    public function test_ntak_amount(): void
    {
        $arrayOfEnum = $this -> jsonData->components->schemas->RendelesiTetel->properties->mennyisegiEgyseg->enum;

        self::assertIsArray($arrayOfEnum);
        self::assertEqualsCanonicalizing($arrayOfEnum, NTAKAmount::names());

    }

    public function test_ntak_day_type(): void
    {
        $arrayOfEnum = $this -> jsonData->components->schemas->NapiZarasAdat->properties->targynapBesorolasa->enum;

        self::assertIsArray($arrayOfEnum);
        self::assertEqualsCanonicalizing($arrayOfEnum, NTAKDayType::names());

    }

    public function test_ntak_verify_status(): void
    {
        $arrayOfEnum = $this -> jsonData->components->schemas->UzenetValasz->properties->statusz->enum;

        self::assertIsArray($arrayOfEnum);
        self::assertEquals($arrayOfEnum, NTAKVerifyStatus::names());

    }
}
