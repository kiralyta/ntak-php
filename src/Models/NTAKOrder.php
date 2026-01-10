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

        // 1. Sum items exactly as they appear (696 + 261 + 50 = 1007)
        $this->totalOfProducts = (int) $this->calculateTotalOfProducts();

        // 2. Service fee: floor((696 + 261) * 0.15) = 143
        $this->serviceFeeTotal = (int) $this->calculateServiceFeeTotal();

        // 3. Grand Total: 1007 + 143 = 1150
        $this->total = $this->totalOfProducts + $this->serviceFeeTotal;
        $this->end = $end ?: Carbon::now();
    }

    public function buildOrderItems(): ?array
    {
        $drsQuantity = array_reduce($this->orderItems ?? [], fn($c, $i) => $c + ($i->isDrs ? $i->quantity : 0), 0);

        // Build base products
        $requestItems = $this->orderItems === null
            ? null
            : array_map(fn($item) => $item->buildRequest($this->isAtTheSpot), $this->orderItems);

        // Add DRS
        if ($requestItems !== null && $drsQuantity > 0) {
            $requestItems[] = NTAKOrderItem::buildDrsRequest($drsQuantity, $this->end);
        }

        // Add Discounts
        if ($requestItems !== null && $this->discount > 0) {
            foreach ($this->uniqueVats() as $vat) {
                $items = $this->itemsForFeeCalculation($vat);
                $sum = array_reduce($items, fn($c, $i) => $c + $i->roundedSum(), 0);
                $diff = (int) (round($sum * (1 - $this->discount / 100)) - $sum);
                if ($diff !== 0) {
                    $requestItems[] = NTAKOrderItem::buildDiscountRequest($vat, $diff, $this->end);
                }
            }
        }

        // Add Service Fee
        if ($requestItems !== null && $this->serviceFee > 0) {
            $this->serviceFeeItems = [];
            foreach ($this->uniqueVats() as $vat) {
                $items = $this->itemsForFeeCalculation($vat);
                $vatBase = array_reduce($items, fn($c, $i) => $c + $i->roundedSum(), 0);
                $feeAmount = (int) round($vatBase * (1 - $this->discount / 100) * $this->serviceFee / 100);

                $feeLine = NTAKOrderItem::buildServiceFeeRequest($vat, $feeAmount, $this->end);
                $this->serviceFeeItems[] = $feeLine;
                $requestItems[] = $feeLine;
            }
            $requestItems = $this->correctServiceFeeLines($requestItems);
        }

        return $requestItems;
    }

    protected function calculateTotalOfProducts(): float
    {
        return array_reduce($this->orderItems ?? [], fn($c, $i) => $c + $i->roundedSum(), 0);
    }

    protected function calculateServiceFeeTotal(): float
    {
        $base = array_reduce($this->itemsForFeeCalculation(), fn($c, $i) => $c + $i->roundedSum(), 0);
        return floor($base * (1 - $this->discount / 100) * ($this->serviceFee / 100));
    }

    protected function itemsForFeeCalculation(?NTAKVat $vat = null): array
    {
        return array_filter($this->orderItems ?? [], function ($i) use ($vat) {
            // WHIELIST: Exclude only DRS and existing fee/discount subcategories
            $isProduct = !$i->isDrs &&
                $i->subcategory !== NTAKSubcategory::SZERVIZDIJ &&
                $i->subcategory !== NTAKSubcategory::KEDVEZMENY;
            return $vat ? ($isProduct && $i->vat === $vat) : $isProduct;
        });
    }

    protected function uniqueVats(): array
    {
        return array_unique(array_map(fn($i) => $i->vat, $this->itemsForFeeCalculation()), SORT_REGULAR);
    }

    protected function correctServiceFeeLines(array $requestItems): array
    {
        $diff = $this->serviceFeeTotal - array_sum(array_column($this->serviceFeeItems, 'tetelOsszesito'));
        if ($diff === 0) return $requestItems;

        $lastVat = end($this->serviceFeeItems)['afaKategoria'];
        foreach ($requestItems as &$item) {
            if (($item['alkategoria'] ?? '') === NTAKSubcategory::SZERVIZDIJ->name && $item['afaKategoria'] === $lastVat) {
                $item['bruttoEgysegar'] += $diff;
                $item['tetelOsszesito'] += $diff;
                break;
            }
        }
        return $requestItems;
    }

    /**
     * Re-introduced NTAKPaymentType and added dynamic rounding (KEREKITES)
     */
    public function buildPaymentTypes(): array
    {
        $paymentRequests = array_map(fn($p) => $p->buildRequest(), $this->payments ?? []);
        $paymentSum = array_sum(array_column($paymentRequests, 'fizetettOsszegHUF'));

        // If customer paid 1158 but total is 1150, we add a -8 HUF Rounding line
        $roundingDifference = $this->total - $paymentSum;

        if ($roundingDifference !== 0) {
            $paymentRequests[] = [
                'fizetesiMod' => NTAKPaymentType::KEREKITES->name,
                'fizetettOsszegHUF' => (float) $roundingDifference,
            ];
        }

        return $paymentRequests;
    }

    public function totalWithDiscount(): int
    {
        return $this->total;
    }

    protected function validate(): void
    {
        if (empty($this->orderItems) && $this->orderType !== NTAKOrderType::NORMAL) {
            // Allow empty items for Storno if needed, but usually Normal requires items
        }
    }
}
