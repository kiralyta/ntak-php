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
    protected int   $drsQuantity = 0;

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

        $this->drsQuantity     = $this->drsQuantity();
        $this->totalOfProducts = (int) round($this->calculateTotalOfProducts());
        
        $this->serviceFeeTotal = (int) round($this->calculateServiceFeeTotal());

        $this->total             = (int) round($this->calculateTotal());
        $this->totalWithDiscount = (int) round($this->calculateTotalWithDiscount());
        
        $this->end = $end ?: Carbon::now();
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
                fn (NTAKOrderItem $orderItem) => $orderItem->buildRequest($this->isAtTheSpot),
                $this->orderItems
            );

        if ($orderItems !== null && $this->drsQuantity > 0) {
            $orderItems[] = NTAKOrderItem::buildDrsRequest($this->drsQuantity, $this->end);
        }

        if ($orderItems !== null && $this->discount > 0) {
            $orderItems = $this->buildDiscountRequests($orderItems);
        }

        if ($orderItems !== null && $this->serviceFee > 0) {
            $orderItems = $this->correctServiceFeeOrderItems(
                $this->buildServiceFeeRequests($orderItems)
            );
            // $orderItems = $this->buildServiceFeeRequests($orderItems);
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
    public function calculateTotal(): int
    {
        // total() = Full Products (incl. DRS) + Full Service Fee (calculated on full price)
        return $this->calculateTotalOfProducts() + $this->calculateServiceFeeTotal(false);
    }

    /**
     * calculateTotalWithDiscount
     *
     * @return float
     */
    protected function calculateTotalWithDiscount(): float
    {
        $vats = $this->uniqueVats();
        $netDiscountedTotal = 0;

        foreach ($vats as $vat) {
            $items = $this->orderItemsWithVat($vat);
            
            // 1. Calculate the product base for this VAT (Net of DRS)
            $netProductSum = array_reduce($items, fn($c, $i) => $c + $i->roundedSum(), 0);
            
            // 2. Calculate and subtract product discount for this VAT
            $discountAmount = (int) round($netProductSum * $this->discount / 100, 0, PHP_ROUND_HALF_DOWN);
            $netDiscountedTotal += ($netProductSum - $discountAmount);

            // 3. Add DRS (discounted) separately for E_0
            if ($vat === NTAKVat::E_0 && $this->drsQuantity > 0) {
                $drsBase = $this->drsQuantity * NTAK::drsAmount;
                $drsDiscount = (int) round($drsBase * $this->discount / 100, 0, PHP_ROUND_HALF_DOWN);
                $netDiscountedTotal += ($drsBase - $drsDiscount);
            }
        }

        // Grand total is the sum of discounted products/DRS + sum of per-VAT rounded service fees
        return $netDiscountedTotal + $this->calculateServiceFeeTotal();
    }

    protected function calculateTotalOfProducts(): float
    {
        if ($this->orderType !== NTAKOrderType::SZTORNO) {
            return array_reduce(
                $this->orderItems,
                function (float $carry, NTAKOrderItem $orderItem) {
                    return $carry + $orderItem->roundedSum();
                },
                0
            ) + $this->drsQuantity * NTAK::drsAmount;
        }

        return 0;
    }

    /**
     * Get the total quantity of DRS items, optionally filtered by VAT category.
     */
    protected function calculateDrsQuantity(?NTAKVat $vat = null): int
    {
        if ($this->orderType !== NTAKOrderType::SZTORNO) {
            return array_reduce(
                $this->orderItems,
                function (int $carry, NTAKOrderItem $orderItem) use ($vat) {
                    $isTargetVat = $vat === null || $orderItem->vat === $vat;

                    if ($orderItem->isDrs && $isTargetVat) {
                        return $carry + (int)$orderItem->quantity;
                    }

                    return $carry;
                },
                0
            );
        }

        return 0;
    }

    /**
     * Public wrapper for specific VAT category.
     */
    public function drsQuantityByVat(NTAKVat $vat): int
    {
        return $this->calculateDrsQuantity($vat);
    }

    /**
     * Protected wrapper for total DRS quantity.
     */
    protected function drsQuantity(): int
    {
        return $this->calculateDrsQuantity();
    }

    /**
     * calculateServiceFeeTotal
     * * @param  bool $discounted If true, calculates fee on discounted price.
     * @return int
     */
    public function calculateServiceFeeTotal(bool $discounted = true): int
    {
        if ($this->serviceFee === 0) {
            return 0;
        }

        $total = 0;
        foreach ($this->uniqueVats() as $vat) {
            $items = $this->orderItemsWithVat($vat);
            
            // Service fee is only calculated on the product net (Price - DRS)
            if ($discounted) {
                // Base is the product net AFTER the order-level discount
                $base = $this->totalOfOrderItemsWithDiscount($items);
            } else {
                // Base is the FULL product net (undiscounted)
                $base = array_reduce($items, fn($c, $i) => $c + $i->roundedSum(), 0);
            }

            $total += (int) round($base * $this->serviceFee / 100);
        }

        return $total;
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
            $orderItems = $this->addServiceFeeRequestByVat(
                orderItems: $orderItems,
                vat: $vat,
                drsQuantity: $this->drsQuantityByVat($vat)
            );
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
        $discountableAmount = 0;

        // 1. Sum up prices of products in this VAT category
        foreach ($this->orderItemsWithVat($vat) as $item) {
            $discountableAmount += $item->roundedSum();
        }

        // 2. If this is the E_0 category, add the DRS amount as discountable
        if ($vat === NTAKVat::E_0) {
            $discountableAmount += $this->drsQuantity * NTAK::drsAmount;
        }

        if ($discountableAmount <= 0) {
            return $orderItems;
        }

        // 3. Calculate discount.
        // To match your test (157.5 -> 157), we use ROUND_HALF_DOWN
        $discountValue = $discountableAmount * ($this->discount / 100);
        $roundedDiscount = (int) round($discountValue, 0, PHP_ROUND_HALF_DOWN);

        if ($roundedDiscount > 0) {
            $orderItems[] = NTAKOrderItem::buildDiscountRequest(
                $vat,
                -$roundedDiscount,
                $this->end
            );
        }

        return $orderItems;
    }

    /**
     * addServiceFeeRequestByVat
     *
     * @param  array   $orderItems
     * @param  NTAKVat $vat
     * @param  int     $drsQuantity
     * @return array
     */
    protected function addServiceFeeRequestByVat(array $orderItems, NTAKVat $vat, int $drsQuantity): array
    {
        $orderItemsWithVat = $this->orderItemsWithVat($vat);

        // Calculate service fee only on the discounted net product total for this VAT
        $totalOfOrderItemsWithDiscount = $this->totalOfOrderItemsWithDiscount($orderItemsWithVat);
        $serviceFeeAmount = (int) round($totalOfOrderItemsWithDiscount * $this->serviceFee / 100);

        // Skip if there is no service fee for this category (e.g. E_0 containing only DRS)
        if ($serviceFeeAmount <= 0) {
            return $orderItems;
        }

        $serviceFeeItem = NTAKOrderItem::buildServiceFeeRequest(
            $vat,
            $serviceFeeAmount,
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
     * @return float
     */
    protected function totalOfOrderItems(array $orderItems): float
    {
        return array_reduce(
            $orderItems,
            function (float $carry, NTAKOrderItem $orderItem) {
                return $carry + $orderItem->price * $orderItem->quantity;
            },
            0
        );
    }

    /**
     * totalOfOrderItemsWithDiscount
     *
     * @param  array|NTAKOrderItem[] $orderItems
     * @return float
     */
    protected function totalOfOrderItemsWithDiscount(array $orderItems): float
    {
        return array_reduce(
            $orderItems,
            function (float $carry, NTAKOrderItem $orderItem) {
                // Apply discount to the net sum (excluding DRS)
                $netSum = $orderItem->roundedSum();
                $discountedNet = $netSum * (1 - $this->discount / 100);

                return $carry + $discountedNet;
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
        $vats = array_map(
            fn (NTAKOrderItem $orderItem) => $orderItem->vat,
            $this->getSimpleOrderItems($this->orderItems)
        );

        if ($this->drsQuantity > 0) {
            $vats[] = NTAKVat::E_0; // for DRS discount line
        }

        return array_unique($vats, SORT_REGULAR);
    }

    /**
     * correctServiceFeeOrderItems
     *
     * @param  array $orderItems
     * @return array
     */
    protected function correctServiceFeeOrderItems(array $orderItems): array
    {
        if (empty($this->serviceFeeItems)) {
            return $orderItems;
        }

        // Calculate the current sum of ALL built lines (Products, DRS, Discounts, and existing Fees)
        $currentSum = array_reduce($orderItems, fn($carry, $item) => $carry + $item['tetelOsszesito'], 0);
        
        // Find the difference between what we have and what the order total must be
        $diff = $this->totalWithDiscount - $currentSum;

        if ($diff === 0) {
            return $orderItems;
        }

        // Apply any remaining rounding difference to the LAST 'Szervízdíj' line
        $lastSfIndex = -1;
        foreach ($orderItems as $index => $item) {
            if (($item['megnevezes'] ?? '') === 'Szervízdíj') {
                $lastSfIndex = $index;
            }
        }

        if ($lastSfIndex !== -1) {
            $orderItems[$lastSfIndex]['bruttoEgysegar'] += $diff;
            $orderItems[$lastSfIndex]['tetelOsszesito'] += $diff;
        }

        return $orderItems;
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

    protected function serviceFeeMultiplier(): float
    {
        return $this->serviceFee === 0
            ? 1
            : 1 + ($this->serviceFee / 100);
    }
}
