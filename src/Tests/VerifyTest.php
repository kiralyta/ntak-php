<?php

namespace Kiralyta\Ntak\Tests;

use Carbon\Carbon;
use Kiralyta\Ntak\NTAK;
use Kiralyta\Ntak\NTAKClient;
use Kiralyta\Ntak\Responses\NTAKVerifyResponse;
use Kiralyta\Ntak\TestCase;

class VerifyTest extends TestCase
{
    /**
     * test_verify
     *
     * @return void
     */
    public function test_verify(): void
    {
        $response = NTAK::message(
            $client = new NTAKClient(
                $this->taxNumber,
                $this->regNumber,
                $this->softwareRegNumber,
                $this->version,
                $this->certPath,
                true
            ),
            Carbon::now()
        )->verify('cfb3197a-a70d-4ba0-8de1-c1e6306c9fe8');

        $this->assertInstanceOf(NTAKVerifyResponse::class, $response);
        $this->assertTrue($response->successful());
        $this->assertIsArray($client->lastRequest());
        $this->assertIsArray($client->lastResponse());
        $this->assertIsInt($client->lastRequestTime());
    }

    /**
     * test_verify_all
     *
     * @return void
     */
    public function test_verify_all(): void
    {
        $response = NTAK::message(
            $client = new NTAKClient(
                $this->taxNumber,
                $this->regNumber,
                $this->softwareRegNumber,
                $this->version,
                $this->certPath,
                true
            ),
            Carbon::now()
        )->verifyAll(['cfb3197a-a70d-4ba0-8de1-c1e6306c9fe8', '781caae3-3ea3-4258-b7e8-64ad3121bf56']);

        $this->assertIsArray($response);
        $this->assertCount(2, $response);
        $this->assertInstanceOf(NTAKVerifyResponse::class, $response[0]);
        $this->assertInstanceOf(NTAKVerifyResponse::class, $response[1]);
        $this->assertTrue($response[0]->successful());
        $this->assertTrue($response[1]->successful());
        $this->assertIsArray($client->lastRequest());
        $this->assertIsArray($client->lastResponse());
        $this->assertIsInt($client->lastRequestTime());
    }
}
