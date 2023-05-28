<?php

namespace Kiralyta\Ntak;

use Carbon\Carbon;
use Gamegos\JWS\JWS;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Kiralyta\Ntak\Exceptions\NTAKClientException;

class NTAKClient
{
    protected Client $client;
    protected Carbon $when;
    protected string $url = 'https://rms.tesztntak.hu';
    protected array $lastRequest;
    protected array $lastResponse;

    /**
     * __construct
     *
     * @param  string $taxNumber
     * @param  string $regNumber
     * @param  string $softwareRegNumber
     * @param  string $version
     * @param  string $certPath
     * @param  string $keyPath
     * @param  bool   $testing
     * @return void
     */
    public function __construct(
        protected string $taxNumber,
        protected string $regNumber,
        protected string $softwareRegNumber,
        protected string $version,
        protected string $certPath,
        protected string $keyPath,
        protected bool   $testing = false
    ) {
        if (! $testing) {
            $this->url = 'https://rms.ntaktst.hu';
        }

        $this->client = new Client([
            'base_uri' => $this->url,
            'cert'     => $certPath,
            'ssl_key'  => $keyPath,
            'verify'   => false,
        ]);
    }

    /**
     * message
     *
     * @param  array  $message
     * @param  Carbon $when
     * @param  string $uri
     * @return array
     */
    public function message(array $message, Carbon $when, string $uri): array
    {
        $this->when = $when;

        $json = array_merge(
            $this->header(),
            $message
        );

        $headers = $this->requestHeaders($json);

        // Send request with guzzle
        try {
            $this->lastRequest = $json;
            dump($json);

            $response = $this->client->request(
                'post',
                $uri,
                compact('json', 'headers')
            );
        } catch (ClientException $e) {
            throw new NTAKClientException(
                $e->getResponse()->getBody()->getContents()
            );
        }

        return $this->lastResponse = json_decode($response->getBody(), true) ?? [];
    }

    /**
     * requestHeaders
     *
     * @param  array $message
     * @return array
     */
    protected function requestHeaders(array $message): array
    {
        $jws = (new JWS())->encode(
            [
                'alg' => 'RS256',
                'typ' => 'JWS'
            ],
            $message,
            file_get_contents($this->keyPath)
        );

        $tmp = explode('.', $jws);

        return [
            'x-jws-signature' => $tmp[0].'..'.$tmp[2],
            'x-certificate'   => base64_encode(file_get_contents($this->certPath))
        ];
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
                'uzenetKuldesIdeje' => $this->when->timezone('Europe/Budapest')->toIso8601String(),
            ],
            'kuldoRendszerAdatok' => [
                'rmsRendszerNTAKAzonosito' => $this->softwareRegNumber,
                'rmsRendszerVerzioszam'    => $this->version,
            ]
        ];
    }

    /**
     * lastRequest getter
     *
     * @return array|null
     */
    public function lastRequest(): ?array
    {
        return $this->lastRequest;
    }

    /**
     * lastResponse getter
     *
     * @return array
     */
    public function lastResponse(): ?array
    {
        return $this->lastResponse;
    }
}
