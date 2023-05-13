<?php

namespace Kiralyta\Ntak\Models;

use Carbon\Carbon;
use InvalidArgumentException;
use Kiralyta\Ntak\Enums\NTAKOrderType;
use Kiralyta\Ntak\Enums\NTAKPaymentType;

class NTAKOrder
{
    /**
     * __construct
     *
     * @param  NTAKOrderType         $orderType
     * @param  string                $orderId
     * @param  array|NTAKOrderItem[] $orderItems
     * @param  string                $ntakOrderId
     * @param  Carbon                $start
     * @param  Carbon                $end
     * @param  bool                  $isAtTheSpot
     * @param  int                   $total
     * @param  NTAKPaymentType       $paymentType
     * @return void
     */
    public function __construct(
        public readonly NTAKOrderType    $orderType,
        public readonly string           $orderId,
        public readonly ?array           $orderItems = null,
        public readonly ?string          $ntakOrderId = null,
        public readonly ?Carbon          $start = null,
        public readonly ?Carbon          $end = null,
        public readonly bool             $isAtTheSpot = true,
        public readonly ?int             $total = null,
        public readonly ?NTAKPaymentType $paymentType = null
    ) {
        if ($orderType !== NTAKOrderType::NORMAL) {
            $this->validateIfNotNormal();
        }

        if ($orderType !== NTAKOrderType::STORNO) {
            $this->validateIfNotStorno();
        }
    }

    /**
     * buildOrderItems
     *
     * @return array
     */
    public function buildOrderItems(): ?array
    {
        return $this->orderItems === null
            ? null
            : array_map(
                fn (NTAKOrderItem $orderItem) => $orderItem->buildRequest(),
                $this->orderItems
            );

    }

    /**
     * validateIfNotNormal
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function validateIfNotNormal(): void
    {
        if ($this->ntakOrderId === null) {
            throw new InvalidArgumentException('ntakOrderId cannot be null in this case');
        }
    }

    /**
     * validateIfNotStorno
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function validateIfNotStorno(): void
    {
        if ($this->orderItems === null || count($this->orderItems) === 0) {
            throw new InvalidArgumentException('orderItems cannot be null in this case');
        }

        foreach ($this->orderItems as $orderItem) {
            if (! $orderItem instanceof NTAKOrderItem) {
                throw new InvalidArgumentException('orderItems must be an array of NTAKOrderItem instances');
            }
        }

        if ($this->start === null) {
            throw new InvalidArgumentException('start cannot be null in this case');
        }

        if ($this->end === null) {
            throw new InvalidArgumentException('end cannot be null in this case');
        }

        if ($this->total === null) {
            throw new InvalidArgumentException('total cannot be null in this case');
        }

        if ($this->paymentType === null) {
            throw new InvalidArgumentException('paymentType cannot be null in this case');
        }
    }
}
