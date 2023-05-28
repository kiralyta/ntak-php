<?php

namespace Kiralyta\Ntak\Tests;

use Carbon\Carbon;
use Kiralyta\Ntak\Enums\NTAKDayType;
use Kiralyta\Ntak\NTAK;
use Kiralyta\Ntak\NTAKClient;
use Kiralyta\Ntak\TestCase;

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
        )->closeDay(
            Carbon::now()->addHours(-8),
            Carbon::now()->addMinutes(-2),
            NTAKDayType::NORMAL_NAP
        );

        $this->assertTrue(true);
    }
}
