<?php

namespace Kiralyta\Ntak\Tests;

use Carbon\Carbon;
use Kiralyta\Ntak\Enums\DayType;
use Kiralyta\Ntak\NTAK;
use Kiralyta\Ntak\NTAKClient;
use PHPUnit\Framework\TestCase;

class CloseDayTest extends TestCase
{
    /**
     * test_close_day
     *
     * @return void
     */
    public function test_close_day(): void
    {
        NTAK::message(
            new NTAKClient('3453234-32-4', 'RMX43', 'TabTenderYohh', '1.4.17'),
            Carbon::now()
        )->closeDay(
            Carbon::now()->addHours(-8),
            Carbon::now()->addMinutes(-2),
            DayType::NORMAL_NAP
        );

        $this->assertTrue(true);
    }
}
