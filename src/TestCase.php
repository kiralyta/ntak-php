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
}
