<?php

namespace Kiralyta\Ntak\Models;

use Carbon\Carbon;
use Kiralyta\Ntak\Enums\NTAKAmount;
use Kiralyta\Ntak\Enums\NTAKCategory;
use Kiralyta\Ntak\Enums\NTAKSubcategory;
use Kiralyta\Ntak\Enums\NTAKVat;

class NTAKOrderItem
{
    /**
     * __construct
     *
     * @param string $name
     * @param NTAKCategory    $category
     * @param NTAKSubcategory $subcategory
     * @param NTAKVat         $vat
     * @param float           $price
     * @param NTAKAmount      $amountType
     * @param flot            $amount
     * @param int             $quantity
     * @param Carbon          $when
     *
     * @return void
     */
    public function __construct(
        public readonly string          $name,
        public readonly NTAKCategory    $category,
        public readonly NTAKSubcategory $subcategory,
        public readonly NTAKVat         $vat,
        public readonly int             $price,
        public readonly NTAKAmount      $amountType,
        public readonly float           $amount,
        public readonly int             $quantity,
        public readonly Carbon          $when
    ) {
    }

    /**
     * buildRequest
     *
     * @return array
     */
    public function buildRequest(): array
    {
        return [
            'megnevezes'        => $this->name,
            'fokategoria'       => $this->category->name,
            'alkategoria'       => $this->subcategory->name,
            'afaKategoria'      => $this->vat->name,
            'bruttoEgysegar'    => $this->price,
            'mennyisegiEgyseg'  => $this->amountType->name,
            'mennyiseg'         => $this->amount,
            'tetelszam'         => $this->quantity,
            'rendelesIdopontja' => $this->when->toRfc3339String(true),
            'tetelOsszesito'    => $this->quantity * $this->price,
        ];
    }
}
