<?php

namespace Kiralyta\Ntak;

use Carbon\Carbon;

class NTAKClient
{
    protected Carbon $when;

    /**
     * __construct
     *
     * @param  string $taxNumber
     * @param  string $regNumber
     * @param  string $softwareRegNumber
     * @param  string $version
     * @return void
     */
    public function __construct(
        protected string $taxNumber,
        protected string $regNumber,
        protected string $softwareRegNumber,
        protected string $version,
    ) {

    }

    /**
     * message
     *
     * @param  array  $message
     * @param  Carbon $when
     * @return void
     */
    public function message(array $message, Carbon $when): void
    {
        $this->when = $when;

        $body = array_merge(
            $this->header(),
            $message
        );

        dump($body);

        // Send request with guzzle
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
