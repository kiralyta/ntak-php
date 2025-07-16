<?php

namespace Kiralyta\Ntak\Models;

use Carbon\Carbon;
use InvalidArgumentException;
use Kiralyta\Ntak\Enums\NTAKCategory;
use Kiralyta\Ntak\Enums\NTAKOrderType;
use Kiralyta\Ntak\Enums\NTAKPaymentType;
use Kiralyta\Ntak\Enums\NTAKSubcategory;
use Kiralyta\Ntak\Enums\NTAKVat;

class NTAKOrder
{
    protected int   $total;
    protected int   $totalWithDiscount;
    protected array $serviceFeeItems = [];
    protected int   $serviceFeeTotal = 0;

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
        public          ?Carbon          $end = null,
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
        if ($orderType !== NTAKOrderType::SZTORNO) {
            $this->validateIfNotStorno();
        }

        $this->total = $this->calculateTotal();
        $this->totalWithDiscount = $this->calculateTotalWithDiscount();
        $this->serviceFeeTotal = $this->calculateServiceFeeTotal();
        $this->end = $end ?: Carbon::now();
    }

    /**
     * buildOrderItems
     *
     * @return array
     */
    public function buildOrderItems(): ?array
    {
        $drsQuantity = array_reduce(
            $this->orderItems,
            function (int $carry, NTAKOrderItem $orderItem) {
                $quantity = 0;
                if ($orderItem->isDrs) {
                    $quantity = $orderItem->quantity;
                }

                return $carry + $quantity;
            },
            0
        );

        $orderItems = $this->orderItems === null
            ? null
            : array_map(
                fn (NTAKOrderItem $orderItem) => $orderItem->buildRequest($this->isAtTheSpot),
                $this->orderItems
            );

        if ($orderItems !== null && $drsQuantity > 0) {
            $orderItems[] = NTAKOrderItem::buildDrsRequest($drsQuantity, $this->end);
        }

        if ($orderItems !== null && $this->discount > 0) {
            $orderItems = $this->buildDiscountRequests($orderItems);
        }

        if ($orderItems !== null && $this->serviceFee > 0) {
            $orderItems = $this->correctServiceFeeOrderItems(
                $this->buildServiceFeeRequests($orderItems)
            );
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
    protected function calculateTotal(): int
    {
        if ($this->orderType !== NTAKOrderType::SZTORNO) {
            $sumOfSimpleOrderItems = $this->totalOfOrderItems($this->getSimpleOrderItems($this->orderItems));
            $sumOfSpecialOrderItems = $this->totalOfOrderItems($this->getSpecialOrderItems($this->orderItems));
            return $sumOfSimpleOrderItems + round($sumOfSimpleOrderItems * $this->serviceFee / 100) + $sumOfSpecialOrderItems;
        }

        return 0;
    }

    /**
     * calculateTotalWithDiscount
     *
     * @return int
     */
    protected function calculateTotalWithDiscount(): int
    {
        if ($this->discount === 0) {
            return $this->total;
        }

        if ($this->orderType !== NTAKOrderType::SZTORNO) {
            $sumOfSimpleOrderItems = $this->totalOfOrderItemsWithDiscount($this->getSimpleOrderItems($this->orderItems));
            $sumOfSpecialOrderItems = $this->totalOfOrderItems($this->getSpecialOrderItems($this->orderItems));

            return $sumOfSimpleOrderItems + round($sumOfSimpleOrderItems * $this->serviceFee / 100) + $sumOfSpecialOrderItems;
        }

        return 0;
    }

    /**
     * calculateServiceFeeTotal
     *
     * @return int
     */
    protected function calculateServiceFeeTotal(): int
    {
        if ($this->orderItems === null || $this->orderItems === []) {
            return 0;
        }

        return round($this->totalOfOrderItemsWithDiscount($this->getSimpleOrderItems($this->orderItems)) * ($this->serviceFee / 100));
    }

    /**
     * buildDiscountRequests
     *
     * @param  array $orderItems
     * @return array
     */
    protected function buildDiscountRequests(array $orderItems): array
    {
        $vats = $this->uniqueVats();

        foreach ($vats as $vat) {
            $orderItems = $this->addDiscountRequestByVat($orderItems, $vat);
        }

        return $orderItems;
    }

    /**
     * buildServiceFeeRequests
     *
     * @param  array $orderItems
     * @return array
     */
    protected function buildServiceFeeRequests(array $orderItems): array
    {
        $vats = $this->uniqueVats();

        foreach ($vats as $vat) {
            $orderItems = $this->addServiceFeeRequestByVat($orderItems, $vat);
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

        $orderItems[] = NTAKOrderItem::buildDiscountRequest(
            $vat,
            $totalOfOrderItemsWithDiscount - $totalOfOrderItems,
            $this->end
        );

        return $orderItems;
    }

    /**
     * addServiceFeeRequestByVat
     *
     * @param  array   $orderItems
     * @param  NTAKVat $vat
     * @return array
     */
    protected function addServiceFeeRequestByVat(array $orderItems, NTAKVat $vat): array
    {
        $orderItemsWithVat = $this->orderItemsWithVat($vat);

        $totalOfOrderItemsWithDiscount = $this->totalOfOrderItemsWithDiscount($orderItemsWithVat);

        $serviceFeeItem = NTAKOrderItem::buildServiceFeeRequest(
            $vat,
            round($totalOfOrderItemsWithDiscount * $this->serviceFee / 100),
            $this->end
        );

        $orderItems[] = $serviceFeeItem;

        $this->serviceFeeItems[] = $serviceFeeItem;

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
            fn (NTAKOrderItem $orderItem) => $orderItem->vat === $vat && !($orderItem->category === NTAKCategory::EGYEB && $orderItem->subcategory === NTAKSubcategory::EGYEB)
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
                return $carry + round($orderItem->price * $orderItem->quantity);
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

                return $carry + round($price);
            },
            0
        );
    }

    /**
     * uniqueVats
     *
     * @return array
     */
    protected function uniqueVats(): array
    {
        return array_unique(
            array_map(
                fn (NTAKOrderItem $orderItem) => $orderItem->vat,
                array_filter(
                    $this->orderItems,
                    fn (NTAKOrderItem $orderItem) => !($orderItem->category === NTAKCategory::EGYEB && $orderItem->subcategory === NTAKSubcategory::EGYEB)
                )
            ),
            SORT_REGULAR
        );
    }

    /**
     * correctServiceFeeOrderItems
     *
     * @param  array $orderItems
     * @return array
     */
    protected function correctServiceFeeOrderItems(array $orderItems): array
    {
        $lastServiceFeeItem = end($this->serviceFeeItems);

        $currentServiceFeeTotal = 0;
        $correctedServiceFeeAmount = 0;

        /** @var NTAKOrderItem $orderItem **/
        foreach ($this->serviceFeeItems as $orderItem) {
            if ($orderItem === $lastServiceFeeItem) {
                $correctedServiceFeeAmount = $this->serviceFeeTotal - $currentServiceFeeTotal;
            }

            $currentServiceFeeTotal = $currentServiceFeeTotal + $orderItem['tetelOsszesito'];
        }

        return array_map(
            function (array $orderItem) use ($lastServiceFeeItem, $correctedServiceFeeAmount) {
                if ($lastServiceFeeItem === $orderItem) {
                    $orderItem['tetelOsszesito'] = $orderItem['bruttoEgysegar'] = $correctedServiceFeeAmount;

                    return $orderItem;
                }

                return $orderItem;
            },
            $orderItems
        );
    }

    /**
     * serviceFeeItems
     *
     * @param  array $orderItems
     * @return array
     */
    protected function serviceFeeItems(array $orderItems): array
    {
        return array_filter(
            $orderItems,
            fn (NTAKOrderItem $orderItem) => $orderItem->subcategory === NTAKSubcategory::SZERVIZDIJ
        );
    }

    /**
     * getSimpleOrderItems
     *
     * @param  array $orderItems
     * @return array of NTAKOrderItem needed discount and fee
     */
    protected function getSimpleOrderItems(array $orderItems): array
    {
        return array_filter(
            $orderItems,
            fn (NTAKOrderItem $orderItem) => !($orderItem->category === NTAKCategory::EGYEB && $orderItem->subcategory === NTAKSubcategory::EGYEB)
        );
    }

    /**
     * getSpecialOrderItems
     *
     * @param  array $orderItems
     * @return array of NTAKOrderItem without discount and fee
     */
    protected function getSpecialOrderItems(array $orderItems): array
    {
        return array_filter(
            $orderItems,
            fn (NTAKOrderItem $orderItem) => ($orderItem->category === NTAKCategory::EGYEB && $orderItem->subcategory === NTAKSubcategory::EGYEB)
        );
    }
}
