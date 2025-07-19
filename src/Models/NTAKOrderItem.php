<?php

namespace Kiralyta\Ntak\Models;

use Carbon\Carbon;
use Kiralyta\Ntak\Enums\NTAKAmount;
use Kiralyta\Ntak\Enums\NTAKCategory;
use Kiralyta\Ntak\Enums\NTAKSubcategory;
use Kiralyta\Ntak\Enums\NTAKVat;

class NTAKOrderItem
{
    protected int $drsSum;

    /**
     * __construct
     *
     * @param string $name
     * @param NTAKCategory    $category
     * @param NTAKSubcategory $subcategory
     * @param NTAKVat         $vat
     * @param float           $price
     * @param NTAKAmount      $amountType
     * @param float           $amount
     * @param int             $quantity
     * @param Carbon          $when
     * @param bool            $isDrs
     *
     * @return void
     */
    public function __construct(
        public readonly string          $name,
        public readonly NTAKCategory    $category,
        public readonly NTAKSubcategory $subcategory,
        public          NTAKVat         $vat,
        public readonly float           $price,
        public readonly NTAKAmount      $amountType,
        public readonly float           $amount,
        public readonly int             $quantity,
        public readonly Carbon          $when,
        public readonly bool            $isDrs = false
    ) {
        $this->drsSum = $isDrs
            ? $this->quantity * 50
            : 0;
    }

    /**
     * buildRequest
     *
     * @param  bool $isAtTheSpot
     * @return array
     */
    public function buildRequest(bool $isAtTheSpot = true): array
    {
        $this->vat = ! $isAtTheSpot && $this->category === NTAKCategory::ALKMENTESITAL_HELYBEN
            ?  NTAKVat::C_27
            : $this->vat;

        return [
            'megnevezes'        => $this->name,
            'fokategoria'       => $this->category->name,
            'alkategoria'       => $this->subcategory->name,
            'afaKategoria'      => $this->vat->name,
            'bruttoEgysegar'    => $this->price - ($this->drsSum / $this->quantity),
            'mennyisegiEgyseg'  => $this->amountType->name,
            'mennyiseg'         => round($this->amount, 2),
            'tetelszam'         => $this->quantity,
            'rendelesIdopontja' => $this->when->timezone('Europe/Budapest')->toIso8601String(),
            'tetelOsszesito'    => $this->roundedSum(),
        ];
    }

    /**
     * buildDiscountRequest
     *
     * @param  NTAKVat $vat
     * @param  int     $price
     * @param  Carbon  $when
     * @return array
     */
    public static function buildDiscountRequest(NTAKVat $vat, int $price, Carbon $when): array
    {
        return (
            new static(
                name:       'Kedvezmény',
                category:    NTAKCategory::EGYEB,
                subcategory: NTAKSubcategory::KEDVEZMENY,
                vat:         $vat,
                price:       $price,
                amountType:  NTAKAmount::DARAB,
                amount:      1,
                quantity:    1,
                when:        $when
            )
        )->buildRequest();
    }

    /**
     * buildServiceFeeRequest
     *
     * @param  NTAKVat $vat
     * @param  int     $price
     * @param  Carbon  $when
     * @return array
     */
    public static function buildServiceFeeRequest(NTAKVat $vat, int $price, Carbon $when): array
    {
        return (
            new static(
                name:       'Szervízdíj',
                category:    NTAKCategory::EGYEB,
                subcategory: NTAKSubcategory::SZERVIZDIJ,
                vat:         $vat,
                price:       $price,
                amountType:  NTAKAmount::DARAB,
                amount:      1,
                quantity:    1,
                when:        $when
            )
        )->buildRequest();
    }

    /**
     * buildDrsRequest
     *
     * @param  int     $quantity
     * @param  Carbon  $when
     * @return array
     */
    public static function buildDrsRequest(int $quantity, Carbon $when): array
    {
        return (
            new static(
                name: 'DRS',
                category: NTAKCategory::EGYEB,
                subcategory: NTAKSubcategory::KORNYEZETBARAT_CSOMAGOLAS,
                vat: NTAKVat::E_0,
                price: 50,
                amountType: NTAKAmount::DARAB,
                amount: 1,
                quantity: $quantity,
                when: $when
            )
        )->buildRequest();
    }

    public function roundedSum(): int
    {
        return round($this->quantity * $this->price - $this->drsSum);
    }
}
