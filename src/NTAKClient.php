<?php

namespace Kiralyta\Ntak;

use Carbon\Carbon;
use Gamegos\JWS\JWS;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Kiralyta\Ntak\Exceptions\NTAKClientException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

class NTAKClient
{
    protected Client     $client;
    protected Carbon     $when;
    protected string     $url;
    protected array      $lastRequest;
    protected array      $lastResponse;
    protected int        $lastRequestTime; // milliseconds
    protected ?array     $fakeResponse = null;

    protected ?Logger    $logger = null;

    protected static string $prodUrl = 'https://rms.ntaktst.hu';
    protected static string $testUrl = 'https://rms.tesztntak.hu';

    /**
     * __construct
     *
     * @param  string  $taxNumber
     * @param  string  $regNumber
     * @param  string  $softwareRegNumber
     * @param  string  $version
     * @param  string  $certPath
     * @param  bool    $testing
     * @param  bool    $logging
     * @param  string  $logPath
     * @return void
     */
    public function __construct(
        protected string  $taxNumber,
        protected string  $regNumber,
        protected string  $softwareRegNumber,
        protected string  $version,
        protected string  $certPath,
        protected bool    $testing = false,
        protected ?bool   $logging = false,
        protected ?string $logPath = './ntak-log/'
    ) {
        $this->url = $testing
            ? self::$testUrl
            : self::$prodUrl;

        $this->client = new Client([
            'base_uri' => $this->url,
            'cert'     => $certPath,
            'ssl_key'  => $certPath,
            'verify'   => false,
        ]);

        if($logging) {
            $this->logger = new Logger('NTAK');
            $this->logger->pushHandler(new StreamHandler($logPath . Carbon::today()->toDateString() . '.log'));
        }
    }

    /**
     * fakeResponse
     *
     * @return NTAKClient
     */
    public function fakeResponse(array $response): NTAKClient
    {
        $this->fakeResponse = $response;
        return $this;
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

        $this->logging('request', $json);

        $headers = $this->requestHeaders($json);

        // Send request with guzzle
        try {
            $this->lastRequest = $json;

            $start = Carbon::now();

            $response = $this->fakeResponse
                ?: $this->client->request(
                    'post',
                    $uri,
                    compact('json', 'headers')
                );

            $this->lastRequestTime = Carbon::now()->diffInMilliseconds($start);
        } catch (RequestException $e) {
            $this->logging('RequestException', json_decode($e->getResponse()->getBody()->getContents(), true), Level::Error);
            throw new NTAKClientException(
                $e->getResponse()->getBody()->getContents()
            );
        } catch (ClientException $e) {
            $this->logging('ClientException', $e->getMessage(), Level::Error);
            throw new NTAKClientException(
                $e->getMessage()
            );
        }

        $this->lastResponse = is_array($response)
            ? $response
            : json_decode($response->getBody(), true) ?? [];

        $this->logging('response', $this->lastResponse);

        return $this->lastResponse;
    }

    /**
     * prodUrl
     *
     * @return string
     */
    public static function prodUrl(): string
    {
        return self::$prodUrl;
    }

    /**
     * testUrl
     *
     * @return string
     */
    public static function testUrl(): string
    {
        return self::$testUrl;
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
            openssl_pkey_get_private(file_get_contents($this->certPath))
        );

        $tmp = explode('.', $jws);

        openssl_x509_export(file_get_contents($this->certPath), $tmpCert);

        return [
            'x-jws-signature' => $tmp[0].'..'.$tmp[2],
            'x-certificate'   => base64_encode($tmpCert)
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

    /**
     * lastRequestTime getter (ms)
     *
     * @return int
     */
    public function lastRequestTime(): ?int
    {
        return $this->lastRequestTime;
    }

    public function logging($message, $context = [], Level $level = Level::Info)
    {
        if ($this->logger) {
            $formatter = new LineFormatter(null, null, false, true);
            $this->logger->getHandlers()[0]->setFormatter($formatter);
            $this->logger->addRecord($level->value, $message, $context);
        }
    }
}
