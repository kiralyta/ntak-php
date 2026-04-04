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
    protected array $serviceFeeItems = [];
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

        $this->drsQuantity = $this->drsQuantity();
        
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
            $orderItems = $this->buildServiceFeeRequests($orderItems);
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
     * Returns the sum of all payments associated with the order.
     *
     * @return float
     */
    public function paymentsTotal(): float
    {
        if ($this->orderType === NTAKOrderType::SZTORNO) {
            return 0;
        }

        return array_reduce(
            $this->payments ?? [],
            fn (float $carry, NTAKPayment $payment) => $carry + $payment->total,
            0
        );
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
        $totalRoundedDiscount = 0;

        // Calculate and round discount for each item individually, in this vat category
        foreach ($this->orderItemsWithVat($vat) as $item) {
            $itemDiscount = $item->roundedSum() * ($this->discount / 100);
            
            // calculate discount with round half down, because if a product price is .5, it will be rounded up, so its corresponding discount item must be rounded down to keep their sum correct
            // e.g. 905 Ft with 10% discount: new price will be 905 * 0.9 = 814.5 ~ 815 (round up), and discount will be 905 * 0.1 = 90.5 ~ 90 (round down), check their sum: 815 + 90 = 905
            $totalRoundedDiscount += (int) round($itemDiscount, 0, PHP_ROUND_HALF_DOWN);
        }

        // Handle DRS as a separate block for the E_0 category
        if ($vat === NTAKVat::E_0 && $this->drsQuantity > 0) {
            $drsDiscount = ($this->drsQuantity * NTAK::drsAmount) * ($this->discount / 100);
            $totalRoundedDiscount += (int) round($drsDiscount, 0, PHP_ROUND_HALF_DOWN);
        }

        if ($totalRoundedDiscount > 0) {
            $orderItems[] = NTAKOrderItem::buildDiscountRequest(
                $vat,
                -$totalRoundedDiscount,
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

        // calculate service fee only on the discounted total for this VAT
        $totalOfOrderItemsWithDiscount = $this->totalOfOrderItemsWithDiscount($orderItemsWithVat);
        $serviceFeeAmount = (int) round($totalOfOrderItemsWithDiscount * $this->serviceFee / 100);

        // skip if there is no service fee for this category (e.g. E_0 containing only DRS)
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
            fn (NTAKOrderItem $orderItem) => $orderItem->vat === $vat
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
        // Calculate the total base price (excluding DRS) for all items 
        // in this VAT group that are subject to service fee.
        $groupBaseTotal = array_reduce(
            $orderItems,
            function (float $carry, NTAKOrderItem $orderItem) {
                // Check if this specific item should bypass the service fee calculation
                if ($orderItem->bypassServiceFee) {
                    return $carry;
                }

                return $carry + $orderItem->roundedSum();
            },
            0
        );

        // Apply the discount to the aggregate total
        return $groupBaseTotal * (1 - $this->discount / 100);
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
            $this->orderItems
        );

        if ($this->drsQuantity > 0) {
            $vats[] = NTAKVat::E_0; // for DRS discount line
        }

        return array_unique($vats, SORT_REGULAR);
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
}
