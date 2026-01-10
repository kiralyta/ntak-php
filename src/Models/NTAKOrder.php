<?php

namespace Kiralyta\Ntak\Models;

use Carbon\Carbon;
use InvalidArgumentException;
use Kiralyta\Ntak\Enums\NTAKCategory;
use Kiralyta\Ntak\Enums\NTAKOrderType;
use Kiralyta\Ntak\Enums\NTAKPaymentType;
use Kiralyta\Ntak\Enums\NTAKSubcategory;
use Kiralyta\Ntak\Enums\NTAKVat;
use Kiralyta\Ntak\NTAK;

class NTAKOrder
{
    protected int   $total;
    protected int   $totalWithDiscount;
    protected int   $totalOfProducts;
    protected array $serviceFeeItems = [];
    protected int   $serviceFeeTotal = 0;

    /**
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

        // Calculate discrete integer components first
        $this->totalOfProducts = (int) $this->calculateTotalOfProducts();
        $this->serviceFeeTotal = (int) $this->calculateServiceFeeTotal();

        // Target Total is the literal sum of the parts to match the receipt image
        $this->total = $this->totalOfProducts + $this->serviceFeeTotal;
        $this->totalWithDiscount = $this->total;

        $this->end = $end ?: Carbon::now();
    }

    /**
     * buildOrderItems
     */
    public function buildOrderItems(): ?array
    {
        $drsQuantity = array_reduce(
            $this->orderItems,
            function (int $carry, NTAKOrderItem $orderItem) {
                return $carry + ($orderItem->isDrs ? $orderItem->quantity : 0);
            },
            0
        );

        $orderItems = $this->orderItems === null
            ? null
            : array_map(
                fn(NTAKOrderItem $orderItem) => $orderItem->buildRequest($this->isAtTheSpot),
                $this->orderItems
            );

        if ($orderItems !== null && $drsQuantity > 0) {
            $orderItems[] = NTAKOrderItem::buildDrsRequest($drsQuantity, $this->end);
        }

        if ($orderItems !== null && $this->discount > 0) {
            $orderItems = $this->buildDiscountRequests($orderItems);
        }

        if ($orderItems !== null && $this->serviceFee > 0) {
            // Apply grouped service fees then correct for rounding
            $orderItems = $this->correctServiceFeeOrderItems(
                $this->buildServiceFeeRequests($orderItems)
            );
        }

        return $orderItems;
    }

    /**
     * buildPaymentTypes
     */
    public function buildPaymentTypes(): array
    {
        $payments = array_map(
            fn(NTAKPayment $payment) => $payment->buildRequest(),
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

    public function total(): ?int
    {
        return $this->total;
    }

    public function totalWithDiscount(): ?int
    {
        return $this->totalWithDiscount;
    }

    protected function validateIfNormal(): void
    {
        if ($this->discount > 100) {
            throw new InvalidArgumentException('discount cannot be greater than 100');
        }
    }

    protected function validateIfNotNormal(): void
    {
        if ($this->ntakOrderId === null) {
            throw new InvalidArgumentException('ntakOrderId cannot be null in this case');
        }
    }

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

        if ($this->start === null || $this->end === null || empty($this->payments)) {
            throw new InvalidArgumentException('Required order data is missing');
        }
    }

    /**
     * Sum up the actual rounded total of every physical item (including DRS)
     */
    protected function calculateTotalOfProducts(): float
    {
        if ($this->orderType === NTAKOrderType::SZTORNO) {
            return 0;
        }

        return array_reduce(
            $this->orderItems,
            fn(float $carry, NTAKOrderItem $orderItem) => $carry + $orderItem->roundedSum(),
            0
        );
    }

    /**
     * Calculate service fee using floor() to match receipt (957 * 0.15 = 143.55 -> 143)
     */
    protected function calculateServiceFeeTotal(): float
    {
        if ($this->orderItems === null || $this->serviceFee === 0) {
            return 0;
        }

        $base = $this->totalOfOrderItemsWithDiscount($this->getSimpleOrderItems($this->orderItems));

        return floor($base * ($this->serviceFee / 100));
    }

    public function drsQuantityByVat(NTAKVat $vat): int
    {
        return array_reduce(
            $this->orderItems,
            fn(int $carry, NTAKOrderItem $item) => $carry + (($item->isDrs && $item->vat === $vat) ? $item->quantity : 0),
            0
        );
    }

    protected function buildServiceFeeRequests(array $orderItems): array
    {
        $vats = $this->uniqueVats();
        $this->serviceFeeItems = []; // Reset tracking

        foreach ($vats as $vat) {
            $orderItems = $this->addServiceFeeRequestByVat($orderItems, $vat);
        }

        return $orderItems;
    }

    protected function addServiceFeeRequestByVat(array $orderItems, NTAKVat $vat): array
    {
        $orderItemsWithVat = $this->orderItemsWithVat($vat);
        $totalOfOrderItemsWithDiscount = $this->totalOfOrderItemsWithDiscount($orderItemsWithVat);

        // Individual VAT-line service fee
        $feeAmount = (int) round($totalOfOrderItemsWithDiscount * $this->serviceFee / 100);

        $serviceFeeItem = NTAKOrderItem::buildServiceFeeRequest(
            $vat,
            $feeAmount,
            $this->end
        );

        $this->serviceFeeItems[] = $serviceFeeItem;
        $orderItems[] = $serviceFeeItem;

        return $orderItems;
    }

    protected function correctServiceFeeOrderItems(array $orderItems): array
    {
        if (empty($this->serviceFeeItems)) {
            return $orderItems;
        }

        // Difference between the discrete target total and sum of current rounded lines
        $targetServiceFeeTotal = $this->serviceFeeTotal;
        $generatedServiceFeeTotal = array_sum(array_column($this->serviceFeeItems, 'tetelOsszesito'));
        $difference = $targetServiceFeeTotal - $generatedServiceFeeTotal;

        if ($difference === 0) {
            return $orderItems;
        }

        $lastServiceFeeRef = end($this->serviceFeeItems);

        foreach ($orderItems as &$item) {
            if (
                ($item['alkategoria'] ?? null) === NTAKSubcategory::SZERVIZDIJ->name &&
                $item['afaKategoria'] === $lastServiceFeeRef['afaKategoria']
            ) {
                $item['bruttoEgysegar'] += $difference;
                $item['tetelOsszesito'] += $difference;
                break;
            }
        }

        return $orderItems;
    }

    protected function buildDiscountRequests(array $orderItems): array
    {
        foreach ($this->uniqueVats() as $vat) {
            $orderItems = $this->addDiscountRequestByVat($orderItems, $vat);
        }
        return $orderItems;
    }

    protected function addDiscountRequestByVat(array $orderItems, NTAKVat $vat): array
    {
        $items = $this->orderItemsWithVat($vat);
        $diff = $this->totalOfOrderItemsWithDiscount($items) - $this->totalOfOrderItems($items);

        $orderItems[] = NTAKOrderItem::buildDiscountRequest($vat, $diff, $this->end);
        return $orderItems;
    }

    protected function orderItemsWithVat(NTAKVat $vat): array
    {
        return array_filter(
            $this->orderItems,
            fn(NTAKOrderItem $item) => $item->vat === $vat && !($item->category === NTAKCategory::EGYEB && $item->subcategory === NTAKSubcategory::EGYEB)
        );
    }

    protected function totalOfOrderItems(array $orderItems): float
    {
        return array_reduce($orderItems, fn(float $carry, NTAKOrderItem $item) => $carry + $item->price * $item->quantity, 0);
    }

    protected function totalOfOrderItemsWithDiscount(array $orderItems): float
    {
        return array_reduce(
            $orderItems,
            fn(float $carry, NTAKOrderItem $item) => $carry + ($item->price * $item->quantity) * (1 - $this->discount / 100),
            0
        );
    }

    protected function uniqueVats(): array
    {
        return array_unique(
            array_map(
                fn(NTAKOrderItem $item) => $item->vat,
                array_filter($this->orderItems, fn(NTAKOrderItem $item) => !($item->category === NTAKCategory::EGYEB && $item->subcategory === NTAKSubcategory::EGYEB))
            ),
            SORT_REGULAR
        );
    }

    protected function getSimpleOrderItems(array $orderItems): array
    {
        return array_filter(
            $orderItems,
            fn(NTAKOrderItem $item) =>
            !($item->category === NTAKCategory::EGYEB && $item->subcategory === NTAKSubcategory::EGYEB)
                && !$item->isDrs
        );
    }

    protected function getSpecialOrderItems(array $orderItems): array
    {
        return array_filter(
            $orderItems,
            fn(NTAKOrderItem $item) => ($item->category === NTAKCategory::EGYEB && $item->subcategory === NTAKSubcategory::EGYEB)
        );
    }
}
