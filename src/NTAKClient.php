<?php

namespace Kiralyta\Ntak;

use Carbon\Carbon;
use GuzzleHttp\Client;

class NTAKClient
{
    protected Client $client;
    protected Carbon $when;
    protected string $url = 'https://rms.ntaktst.hu';

    /**
     * __construct
     *
     * @param  string $taxNumber
     * @param  string $regNumber
     * @param  string $softwareRegNumber
     * @param  string $version
     * @param  string $cert
     * @param  string $key
     * @return void
     */
    public function __construct(
        protected string $taxNumber,
        protected string $regNumber,
        protected string $softwareRegNumber,
        protected string $version,
        protected string $cert,
        protected string $key
    ) {
        $this->client = new Client([
            'base_uri' => $this->url,
            'headers'  => [
                'x-jws-signature' => $key,
                'x-certificate'   => base64_encode($cert),
            ]
        ]);
    }

    /**
     * message
     *
     * @param  array  $message
     * @param  Carbon $when
     * @param  string $uri
     * @return void
     */
    public function message(array $message, Carbon $when, string $uri): void
    {
        $this->when = $when;

        $json = array_merge(
            $this->header(),
            $message
        );

        // Send request with guzzle
        $this->client->request(
            'post',
            $uri,
            compact('json')
        );
    }

    /**
     * header
     *
     * @return array
     */
    protected function header(): array
    {
        return [
            'szolgaltatoAdatok'   => [
                'adoszam'                => $this->taxNumber,
                'vendeglatoUzletRegSzam' => $this->regNumber,
            ],
            'uzenetAdatok'        => [
                'uzenetKuldesIdeje' => $this->when->toIso8601String(),
            ],
            'kuldoRendszerAdatok' => [
                'rmsRendszerNTAKAzonosito' => $this->softwareRegNumber,
                'rmsRendszerVerzioszam'    => $this->version,
            ]
        ];
    }
}
