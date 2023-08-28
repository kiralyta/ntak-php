<?php

namespace Kiralyta\Ntak\Models;

use Kiralyta\Ntak\Enums\NTAKPaymentType;

class NTAKPayment
{
    protected int $round = 0;

    /**
     * __construct
     *
     * @param  NTAKPaymentType $paymentType
     * @return void
     */
    public function __construct(
        public readonly NTAKPaymentType $paymentType,
        public readonly int             $total
    ) {
    }

    /**
     * buildRequest
     *
     * @return array
     */
    public function buildRequest(): array
    {
        $rounded = 0;
        $request = [
            'fizetesiMod'       => $this->paymentType->name,
            'fizetettOsszegHUF' => ! in_array($this->paymentType, [NTAKPaymentType::KESZPENZHUF, NTAKPaymentType::KESZPENZEUR])
                ? $this->total
                : $rounded = (int) (round($this->total / 5) * 5)
        ];

        if (in_array($this->paymentType, [
            NTAKPaymentType::KESZPENZHUF, NTAKPaymentType::KESZPENZEUR
        ])) {
            $this->round = $this->total - $rounded;
        }

        return $request;
    }

    /**
     * round getter
     *
     * @return int
     */
    public function round(): int
    {
        return $this->round;
    }
}
