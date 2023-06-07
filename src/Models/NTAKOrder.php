<?php

namespace Kiralyta\Ntak\Models;

use Carbon\Carbon;
use InvalidArgumentException;
use Kiralyta\Ntak\Enums\NTAKAmount;
use Kiralyta\Ntak\Enums\NTAKCategory;
use Kiralyta\Ntak\Enums\NTAKOrderType;
use Kiralyta\Ntak\Enums\NTAKPaymentType;
use Kiralyta\Ntak\Enums\NTAKSubcategory;
use Kiralyta\Ntak\Enums\NTAKVat;

class NTAKOrder
{
    protected int $total;
    protected int $totalWithDiscount;

    /**
     * __construct
     *
     * @param  NTAKOrderType            $orderType
     * @param  string                   $orderId
     * @param  array|NTAKOrderItem[]    $orderItems
     * @param  string                   $ntakOrderId
     * @param  Carbon                   $start
     * @param  Carbon                   $end
     * @param  bool                     $isAtTheSpot
     * @param  array|null|NTAKPayment[] $payments
     * @param  int                      $discount
     * @param  int                      $serviceFee
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
        public readonly ?array           $payments = null,
        public readonly int              $discount = 0,
        public readonly int              $serviceFee = 0
    ) {
        if ($orderType === NTAKOrderType::NORMAL) {
            $this->validateIfNormal();
        }
        if ($orderType !== NTAKOrderType::NORMAL) {
            $this->validateIfNotNormal();
        }
        if ($orderType !== NTAKOrderType::STORNO) {
            $this->validateIfNotStorno();
        }

        $this->total = $this->calculateTotal();
        $this->totalWithDiscount = $this->calculateTotalWithDiscount();
    }

    /**
     * buildOrderItems
     *
     * @return array
     */
    public function buildOrderItems(): ?array
    {
        $orderItems = $this->orderItems === null
            ? null
            : array_map(
                fn (NTAKOrderItem $orderItem) => $orderItem->buildRequest(),
                $this->orderItems
            );

        if ($orderItems !== null && $this->discount > 0) {
            $orderItems = $this->buildDiscountRequests($orderItems);
        }

        return $orderItems;
    }

    /**
     * buildPaymentTypes
     *
     * @return array
     */
    public function buildPaymentTypes(): array
    {
        $payments = array_map(
            fn (NTAKPayment $payment) => $payment->buildRequest(),
            $this->payments
        );

        foreach ($this->payments as $payment) {
            if ($payment->round() !== 0) {
                $payments[] = [
                    'fizetesiMod'       => NTAKPaymentType::KEREKITES->name,
                    'fizetettOsszegHUF' => $payment->round(),
                ];

                break;
            }
        }

        return $payments;
    }

    /**
     * total getter
     *
     * @return int|null
     */
    public function total(): ?int
    {
        return $this->total;
    }

    /**
     * totalWithDiscount getter
     *
     * @return int|null
     */
    public function totalWithDiscount(): ?int
    {
        return $this->totalWithDiscount;
    }

    /**
     * validateIfNormal
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function validateIfNormal(): void
    {
        if ($this->discount > 100) {
            throw new InvalidArgumentException('discount cannot be greater than 100');
        }
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

        if (count($this->payments) === 0 || $this->payments === null) {
            throw new InvalidArgumentException('paymentType cannot be null in this case');
        }
    }

    /**
     * calculateTotal
     *
     * @return int
     */
    protected function calculateTotal(): ?int
    {
        if ($this->orderType !== NTAKOrderType::STORNO) {
            $total = $this->totalOfOrderItems($this->orderItems);

            return $total + $total * $this->serviceFee / 100;
        }

        return null;
    }

    /**
     * calculateTotalWithDiscount
     *
     * @return int
     */
    protected function calculateTotalWithDiscount(): ?int
    {
        if ($this->discount === 0) {
            return $this->total;
        }

        if ($this->orderType !== NTAKOrderType::STORNO) {
            $total = $this->totalOfOrderItemsWithDiscount($this->orderItems);

            return $total + $total * $this->serviceFee / 100;
        }

        return null;
    }

    /**
     * buildDiscountRequests
     *
     * @param  array $orderItems
     * @return array
     */
    protected function buildDiscountRequests(array $orderItems): array
    {
        $vats = array_unique(
            array_map(
                fn (NTAKOrderItem $orderItem) => $orderItem->vat,
                $this->orderItems
            ),
            SORT_REGULAR
        );

        foreach ($vats as $vat) {
            $orderItems = $this->addDiscountRequestByVat($orderItems, $vat);
        }

        return $orderItems;
    }

    /**
     * addDiscountRequestByVat
     *
     * @param  array   $orderItems
     * @param  NTAKVat $vat
     * @return array
     */
    protected function addDiscountRequestByVat(array $orderItems, NTAKVat $vat): array
    {
        $orderItemsWithVat = $this->orderItemsWithVat($vat);

        $totalOfOrderItems = $this->totalOfOrderItems($orderItemsWithVat);
        $totalOfOrderItemsWithDiscount = $this->totalOfOrderItemsWithDiscount($orderItemsWithVat);


        $orderItems[] =
            (new NTAKOrderItem(
                name:       'Kedvezmény',
                category:    NTAKCategory::EGYEB,
                subcategory: NTAKSubcategory::KEDVEZMENY,
                vat:         $vat,
                price:       $totalOfOrderItemsWithDiscount - $totalOfOrderItems,
                amountType:  NTAKAmount::DARAB,
                amount:      1,
                quantity:    1,
                when:        $this->end
            ))->buildRequest();

        return $orderItems;
    }

    /**
     * orderItemsWithVat
     *
     * @param  NTAKVat $vat
     * @return array
     */
    protected function orderItemsWithVat(NTAKVat $vat): array
    {
        return array_filter(
            $this->orderItems,
            fn (NTAKOrderItem $orderItem) => $orderItem->vat === $vat
        );
    }

    /**
     * totalOfOrderItems
     *
     * @param  array $orderItems
     * @return int
     */
    protected function totalOfOrderItems(array $orderItems): int
    {
        return array_reduce(
            $orderItems,
            function (int $carry, NTAKOrderItem $orderItem) {
                return $carry + $orderItem->price * $orderItem->quantity;
            },
            0
        );
    }

    /**
     * totalOfOrderItemsWithDiscount
     *
     * @param  array|NTAKOrderItem[] $orderItems
     * @return int
     */
    protected function totalOfOrderItemsWithDiscount(array $orderItems): int
    {
        return array_reduce(
            $orderItems,
            function (int $carry, NTAKOrderItem $orderItem) {
                $price = ($orderItem->price * $orderItem->quantity) *
                         (1 - $this->discount / 100);

                return $carry + $price;
            },
            0
        );
    }
}
