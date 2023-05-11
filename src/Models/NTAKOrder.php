<?php

namespace Kiralyta\Ntak\Models;

use Carbon\Carbon;
use Kiralyta\Ntak\Enums\OrderType;
use Kiralyta\Ntak\Enums\PaymentType;

class NTAKOrder
{
    /**
     * __construct
     *
     * @param  OrderType   $orderType
     * @param  string      $orderId
     * @param  string      $ntakOrderId
     * @param  Carbon      $start
     * @param  Carbon      $end
     * @param  bool        $isAtTheSpot
     * @param  int         $total
     * @param  PaymentType $paymentType
     * @return void
     */
    public function __construct(
        public readonly OrderType    $orderType,
        public readonly string       $orderId,
        public readonly ?string      $ntakOrderId = null,
        public readonly ?Carbon      $start = null,
        public readonly ?Carbon      $end = null,
        public readonly bool         $isAtTheSpot = true,
        public readonly ?int         $total = null,
        public readonly ?PaymentType $paymentType = null
    ) {
        if ($orderType !== OrderType::NORMAL) {
            assert($ntakOrderId !== null);
        }

        if ($orderType !== OrderType::STORNO) {
            assert($start !== null);
            assert($end !== null);
            assert($total !== null);
            assert($paymentType !== null);
        }
    }
}
