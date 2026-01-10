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
        $this->validate();

        // 1. Total of physical products (Tonic 696 + Soda 261 + DRS 50 = 1007)
        $this->totalOfProducts = (int) $this->calculateTotalOfProducts();

        // 2. Service fee (957 * 0.15 = 143.55 -> floor = 143)
        $this->serviceFeeTotal = (int) $this->calculateServiceFeeTotal();

        // 3. Final Total (1007 + 143 = 1150)
        $this->total = $this->totalOfProducts + $this->serviceFeeTotal;
        $this->totalWithDiscount = $this->total;

        $this->end = $end ?: Carbon::now();
    }

    public function buildOrderItems(): ?array
    {
        $drsQuantity = $this->calculateDrsQuantity();

        // Start with the raw products
        $requestItems = $this->orderItems === null
            ? null
            : array_map(
                fn(NTAKOrderItem $item) => $item->buildRequest($this->isAtTheSpot),
                $this->orderItems
            );

        // Add DRS line
        if ($requestItems !== null && $drsQuantity > 0) {
            $requestItems[] = NTAKOrderItem::buildDrsRequest($drsQuantity, $this->end);
        }

        // Apply Discount lines
        if ($requestItems !== null && $this->discount > 0) {
            $requestItems = $this->buildDiscountRequests($requestItems);
        }

        // Apply Service Fee lines
        if ($requestItems !== null && $this->serviceFee > 0) {
            $requestItems = $this->correctServiceFeeOrderItems(
                $this->buildServiceFeeRequests($requestItems)
            );
        }

        return $requestItems;
    }

    protected function calculateTotalOfProducts(): float
    {
        if ($this->orderType === NTAKOrderType::SZTORNO || empty($this->orderItems)) {
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
        if (empty($this->orderItems) || $this->serviceFee === 0) {
            return 0;
        }

        // Base = Everything except DRS and existing Service Fee items
        $base = array_reduce(
            $this->orderItems,
            fn(float $c, NTAKOrderItem $i) => $i->isDrs ? $c : $c + $i->roundedSum(),
            0
        );

        $discountedBase = $base * (1 - $this->discount / 100);
        return floor($discountedBase * ($this->serviceFee / 100));
    }

    protected function buildServiceFeeRequests(array $orderItems): array
    {
        $vats = $this->uniqueVats();
        $this->serviceFeeItems = [];

        foreach ($vats as $vat) {
            $itemsWithVat = array_filter($this->orderItems, fn($i) => $i->vat === $vat && !$i->isDrs);
            $vatBase = array_reduce($itemsWithVat, fn($c, $i) => $c + $i->roundedSum(), 0);

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

        if ($difference === 0) return $orderItems;

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

    protected function buildDiscountRequests(array $orderItems): array
    {
        foreach ($this->uniqueVats() as $vat) {
            $itemsWithVat = array_filter($this->orderItems, fn($i) => $i->vat === $vat && !$i->isDrs);
            $sum = array_reduce($itemsWithVat, fn($c, $i) => $c + $i->roundedSum(), 0);
            $diff = (int) (round($sum * (1 - $this->discount / 100)) - $sum);

            if ($diff !== 0) {
                $orderItems[] = NTAKOrderItem::buildDiscountRequest($vat, $diff, $this->end);
            }
        }
        return $orderItems;
    }

    protected function uniqueVats(): array
    {
        $vats = [];
        foreach ($this->orderItems as $item) {
            if (!$item->isDrs) $vats[] = $item->vat;
        }
        return array_unique($vats, SORT_REGULAR);
    }

    protected function calculateDrsQuantity(): int
    {
        return array_reduce($this->orderItems ?? [], fn($c, $i) => $c + ($i->isDrs ? $i->quantity : 0), 0);
    }

    public function buildPaymentTypes(): array
    {
        $payments = array_map(fn($p) => $p->buildRequest(), $this->payments ?? []);
        // Check if we need a rounding (KEREKITES) line to reach the payment total
        // Note: You should compare this against the total payment provided in your RMS
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

    protected function validate(): void
    {
        if ($this->orderType === NTAKOrderType::NORMAL && $this->discount > 100) throw new InvalidArgumentException('Discount too high');
        if ($this->orderType !== NTAKOrderType::NORMAL && !$this->ntakOrderId) throw new InvalidArgumentException('Missing NTAK ID');
    }
}
