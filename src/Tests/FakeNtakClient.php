<?php

namespace Kiralyta\Ntak\Tests;

use Kiralyta\Ntak\NTAKClient;
use Carbon\Carbon;

class FakeNTAKClient extends NTAKClient
{
    public function message(array $message, Carbon $when, string $uri): array
    {
        // capture what would have been sent
        $this->lastRequest = $message;

        // fake response
        $this->lastResponse = [
            'header' => [
                'requestId' => 'FAKE-' . uniqid(),
                'timestamp' => Carbon::now()->toIso8601String(),
            ],
            'status' => 'SUCCESS',
            'feldolgozasAzonosito' => 'fake-ntak-process-' . bin2hex(random_bytes(8))
        ];

        return $this->lastResponse;
    }

    protected function requestHeaders(array $message): array
    {
        // avoid using cert
        return [
            'x-jws-signature' => 'fake-jws-signature-content',
            'x-certificate'   => 'fake-base64-encoded-cert'
        ];
    }
}
