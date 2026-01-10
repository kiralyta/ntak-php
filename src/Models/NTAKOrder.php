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

        // 1. Sum of physical items (Tonic + Soda + DRS) using their rounded values
        $this->totalOfProducts = (int) $this->calculateTotalOfProducts();

        // 2. Service fee as a discrete integer (floor to match your 143 HUF receipt)
        $this->serviceFeeTotal = (int) $this->calculateServiceFeeTotal();

        // 3. Grand total is the sum of products (after internal discounts) + service fee
        // We calculate this as an integer sum to prevent "negative service fee" bugs
        $this->total = $this->totalOfProducts + $this->serviceFeeTotal;
        $this->totalWithDiscount = $this->total;

        $this->end = $end ?: Carbon::now();
    }

    public function buildOrderItems(): ?array
    {
        $drsQuantity = $this->calculateDrsQuantity();

        $orderItems = $this->orderItems === null
            ? null
            : array_map(
                fn(NTAKOrderItem $orderItem) => $orderItem->buildRequest($this->isAtTheSpot),
                $this->orderItems
            );

        if ($orderItems !== null && $drsQuantity > 0) {
            $orderItems[] = NTAKOrderItem::buildDrsRequest($drsQuantity, $this->end);
        }

        // Apply Discounts first
        if ($orderItems !== null && $this->discount > 0) {
            $orderItems = $this->buildDiscountRequests($orderItems);
        }

        // Apply Service Fees second (and correct rounding differences)
        if ($orderItems !== null && $this->serviceFee > 0) {
            $orderItems = $this->correctServiceFeeOrderItems(
                $this->buildServiceFeeRequests($orderItems)
            );
        }

        return $orderItems;
    }

    protected function calculateTotalOfProducts(): float
    {
        if ($this->orderType === NTAKOrderType::SZTORNO || $this->orderItems === null) {
            return 0;
        }

        return array_reduce(
            $this->orderItems,
            fn(float $carry, NTAKOrderItem $item) => $carry + $item->roundedSum(),
            0
        );
    }

    protected function calculateServiceFeeTotal(): float
    {
        if ($this->orderItems === null || $this->serviceFee === 0) {
            return 0;
        }

        $base = array_reduce(
            $this->getSimpleOrderItems($this->orderItems),
            fn(float $carry, NTAKOrderItem $item) => $carry + $item->roundedSum(),
            0
        );

        $discountedBase = $base * (1 - $this->discount / 100);

        // Matches 957 * 0.15 = 143.55 -> 143 HUF
        return floor($discountedBase * ($this->serviceFee / 100));
    }

    /**
     * DISCOUNT LOGIC
     */
    protected function buildDiscountRequests(array $orderItems): array
    {
        foreach ($this->uniqueVats() as $vat) {
            $orderItems = $this->addDiscountRequestByVat($orderItems, $vat);
        }
        return $orderItems;
    }

    protected function addDiscountRequestByVat(array $orderItems, NTAKVat $vat): array
    {
        $itemsWithVat = $this->orderItemsWithVat($vat);

        $sumOfItems = array_reduce($itemsWithVat, fn($c, $i) => $c + $i->roundedSum(), 0);
        $discountedSum = $sumOfItems * (1 - $this->discount / 100);

        // Difference is negative for NTAK discount lines
        $diff = (int) (round($discountedSum) - $sumOfItems);

        if ($diff !== 0) {
            $orderItems[] = NTAKOrderItem::buildDiscountRequest($vat, $diff, $this->end);
        }

        return $orderItems;
    }

    /**
     * SERVICE FEE LOGIC
     */
    protected function buildServiceFeeRequests(array $orderItems): array
    {
        $vats = $this->uniqueVats();
        $this->serviceFeeItems = [];

        foreach ($vats as $vat) {
            $orderItemsWithVat = $this->orderItemsWithVat($vat);
            $vatBase = array_reduce($orderItemsWithVat, fn($c, $i) => $c + $i->roundedSum(), 0);

            $feeAmount = (int) round($vatBase * (1 - $this->discount / 100) * $this->serviceFee / 100);

            $serviceFeeItem = NTAKOrderItem::buildServiceFeeRequest($vat, $feeAmount, $this->end);
            $this->serviceFeeItems[] = $serviceFeeItem;
            $orderItems[] = $serviceFeeItem;
        }

        return $orderItems;
    }

    protected function correctServiceFeeOrderItems(array $orderItems): array
    {
        if (empty($this->serviceFeeItems)) {
            return $orderItems;
        }

        $generatedFeeSum = array_sum(array_column($this->serviceFeeItems, 'tetelOsszesito'));
        $difference = $this->serviceFeeTotal - $generatedFeeSum;

        if ($difference === 0) {
            return $orderItems;
        }

        $lastFeeRef = end($this->serviceFeeItems);

        foreach ($orderItems as &$item) {
            if (
                ($item['alkategoria'] ?? null) === NTAKSubcategory::SZERVIZDIJ->name &&
                $item['afaKategoria'] === $lastFeeRef['afaKategoria']
            ) {
                $item['bruttoEgysegar'] += $difference;
                $item['tetelOsszesito'] += $difference;
                break;
            }
        }

        return $orderItems;
    }

    /**
     * HELPERS
     */
    protected function calculateDrsQuantity(): int
    {
        return array_reduce(
            $this->orderItems ?? [],
            fn(int $carry, NTAKOrderItem $item) => $carry + ($item->isDrs ? $item->quantity : 0),
            0
        );
    }

    public function buildPaymentTypes(): array
    {
        $payments = array_map(fn(NTAKPayment $p) => $p->buildRequest(), $this->payments);
        foreach ($this->payments as $payment) {
            if ($payment->round() !== 0) {
                $payments[] = [
                    'fizetesiMod' => NTAKPaymentType::KEREKITES->name,
                    'fizetettOsszegHUF' => $payment->round(),
                ];
                break;
            }
        }
        return $payments;
    }

    protected function getSimpleOrderItems(array $orderItems): array
    {
        return array_filter(
            $orderItems,
            fn($i) =>
            !($i->category === NTAKCategory::EGYEB && $i->subcategory === NTAKSubcategory::EGYEB) && !$i->isDrs
        );
    }

    protected function orderItemsWithVat(NTAKVat $vat): array
    {
        return array_filter($this->getSimpleOrderItems($this->orderItems), fn($i) => $i->vat === $vat);
    }

    protected function uniqueVats(): array
    {
        return array_unique(array_map(fn($i) => $i->vat, $this->getSimpleOrderItems($this->orderItems)), SORT_REGULAR);
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
        if ($this->discount > 100) throw new InvalidArgumentException('Invalid discount');
    }
    protected function validateIfNotNormal(): void
    {
        if ($this->ntakOrderId === null) throw new InvalidArgumentException('Missing NTAK Order ID');
    }
    protected function validateIfNotStorno(): void
    {
        if (empty($this->orderItems) || !$this->start || !$this->end || empty($this->payments)) throw new InvalidArgumentException('Invalid order data');
    }
}
