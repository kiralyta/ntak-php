<?php

namespace Kiralyta\Ntak;

use Carbon\Carbon;
use Kiralyta\Ntak\Enums\Category;
use Kiralyta\Ntak\Enums\DayType;
use Kiralyta\Ntak\Enums\OrderType;
use Kiralyta\Ntak\Enums\PaymentType;
use Kiralyta\Ntak\Models\NTAKOrder;

class NTAK
{
    /**
     * __construct
     *
     * @param  NTAKClient $client
     * @param  Carbon     $when
     * @return void
     */
    public function __construct(
        protected NTAKClient $client,
        protected Carbon $when
    ) {
    }

    /**
     * Lists the categories
     *
     * @return array
     */
    public static function categories(): array
    {
        return Category::values();
    }

    /**
     * Lists the subcategories of a category
     *
     * @param  Category $category
     * @return array
     */
    public static function subCategories(Category $category): array
    {
        return $category->subCategories();
    }

    /**
     * message
     *
     * @param  NTAKClient $client
     * @param  Carbon     $when
     * @return NTAK
     */
    public static function message(NTAKClient $client, Carbon $when): NTAK
    {
        return new static($client, $when);
    }

    /**
     * storeOrder
     *
     * @param  NTAKOrder $ntakOrder
     * @return void
     */
    public function storeOrder(
        NTAKOrder $ntakOrder
    ): void {
        $message = [
            'rendelesOsszesitok' => [
                [
                    'rendelesBesorolasa'           => $ntakOrder->orderType->name,
                    'rmsRendelesAzonosito'         => $ntakOrder->orderId,
                    'hivatkozottRendelesOsszesito' => $ntakOrder->ntakOrderId,
                    'targynap'                     => $ntakOrder->end->format('Y-m-d'),
                    'rendelesKezdete'              => $ntakOrder->start->toRfc3339String(true),
                    'rendelesVege'                 => $ntakOrder->end->toRfc3339String(true),
                    'helybenFogyasztott'           => $ntakOrder->isAtTheSpot,
                    'osszesitett'                  => false,
                    'fizetésiInformációk'          => [
                        'rendelesVegosszegeHUF' => $ntakOrder->total,
                        'fizetesiModok'         => [
                            [
                                'fizetesiMod'       => $ntakOrder->paymentType->name,
                                'fizetettOsszegHUF' => $ntakOrder->paymentType !== PaymentType::KESZPENZHUF
                                    ? $ntakOrder->total
                                    : (int) round($ntakOrder->total / 5) * 5
                            ]
                        ]
                    ],

                    // TODO
                ]
            ],
        ];

        $this->client->message($message, $this->when);
    }

    /**
     * closeDay
     *
     * @param  Carbon  $start
     * @param  Carbon  $end
     * @param  DayType $dayType
     * @param  int     $tips
     * @return void
     */
    public function closeDay(
        ?Carbon $start,
        ?Carbon $end,
        DayType $dayType,
        int $tips = 0
    ): void {
        $message = [
            'zarasiInformaciok' => [
                'targynap'           => $start->format('Y-m-d'),
                'targynapBesorolasa' => $dayType->name,
                'nyitasIdopontja'    => $dayType !== DayType::ADOTT_NAPON_ZARVA
                    ? $start->toRfc3339String()
                    : null,
                'zarasIdopontja'     => $dayType !== DayType::ADOTT_NAPON_ZARVA
                    ? $end->toRfc3339String()
                    : null,
                'osszesBorravalo'    => $tips,
            ],
        ];

        $this->client->message($message, $this->when);
    }
}
