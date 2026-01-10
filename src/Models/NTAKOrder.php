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
    protected int   $drsTotal = 0;

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
        $this->end = $end ?: Carbon::now();
        $this->validate();

        // 1. Calculate DRS amount upfront (50 HUF)
        $drsQuantity = array_reduce($this->orderItems ?? [], fn($c, $i) => $c + ($i->isDrs ? $i->quantity : 0), 0);
        $this->drsTotal = $drsQuantity * 50;

        // 2. Sum physical products (696 + 261 = 957)
        // We exclude items already marked as isDrs to avoid double counting
        $this->totalOfProducts = (int) array_reduce(
            $this->orderItems ?? [],
            fn($c, $i) => $i->isDrs ? $c : $c + $i->roundedSum(),
            0
        );

        // 3. Service fee: floor(957 * 0.15) = 143
        $this->serviceFeeTotal = (int) $this->calculateServiceFeeTotal();

        // 4. Grand Total: 957 + 50 + 143 = 1150
        $this->total = $this->totalOfProducts + $this->drsTotal + $this->serviceFeeTotal;
    }

    public function buildOrderItems(): ?array
    {
        $drsQuantity = array_reduce($this->orderItems ?? [], fn($c, $i) => $c + ($i->isDrs ? $i->quantity : 0), 0);

        // Build base products (Tonic, Soda)
        $requestItems = $this->orderItems === null
            ? null
            : array_map(fn($item) => $item->buildRequest($this->isAtTheSpot), $this->orderItems);

        // Add DRS line item (50 HUF)
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

        // Add Service Fee line item (143 HUF)
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

    protected function calculateServiceFeeTotal(): float
    {
        $base = array_reduce($this->itemsForFeeCalculation(), fn($c, $i) => $c + $i->roundedSum(), 0);
        return floor($base * (1 - $this->discount / 100) * ($this->serviceFee / 100));
    }

    protected function itemsForFeeCalculation(?NTAKVat $vat = null): array
    {
        return array_filter($this->orderItems ?? [], function ($i) use ($vat) {
            // Include everything except DRS and technical lines
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

    public function buildPaymentTypes(): array
    {
        $paymentRequests = array_map(fn($p) => $p->buildRequest(), $this->payments ?? []);
        $paymentSum = array_sum(array_column($paymentRequests, 'fizetettOsszegHUF'));

        // Difference between 1150 (Order) and 1158 (Payment) = -8 HUF
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
        if (empty($this->orderItems) && $this->orderType === NTAKOrderType::NORMAL) {
            throw new InvalidArgumentException('Order items required');
        }
    }
}
