<?php

namespace Kiralyta\Ntak;

use Carbon\Carbon;
use Kiralyta\Ntak\Enums\NTAKCategory;
use Kiralyta\Ntak\Enums\NTAKDayType;
use Kiralyta\Ntak\Enums\NTAKOrderType;
use Kiralyta\Ntak\Enums\NTAKPaymentType;
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
        return NTAKCategory::values();
    }

    /**
     * Lists the subcategories of a category
     *
     * @param  NTAKCategory $category
     * @return array
     */
    public static function subCategories(NTAKCategory $category): array
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
     * handleOrder
     *
     * @param  NTAKOrder $ntakOrder
     * @return void
     */
    public function handleOrder(
        NTAKOrder $ntakOrder
    ): void {
        $message = [
            'rendelesOsszesitok' => [
                [
                    'rendelesBesorolasa'           => $ntakOrder->orderType->name,
                    'rmsRendelesAzonosito'         => $ntakOrder->orderId,
                    'hivatkozottRendelesOsszesito' => $ntakOrder->orderType === NTAKOrderType::NORMAL
                        ? null
                        : $ntakOrder->ntakOrderId,
                    'targynap'                     => $ntakOrder->end->format('Y-m-d'),
                    'rendelesKezdete'              => $ntakOrder->orderType === NTAKOrderType::STORNO
                        ? null
                        : $ntakOrder->start->toRfc3339String(true),
                    'rendelesVege'                 => $ntakOrder->orderType === NTAKOrderType::STORNO
                        ? null
                        : $ntakOrder->end->toRfc3339String(true),
                    'helybenFogyasztott'           => $ntakOrder->isAtTheSpot,
                    'osszesitett'                  => false,
                    'fizetésiInformációk'          => $ntakOrder->orderType === NTAKOrderType::STORNO
                        ? null
                        : [
                        'rendelesVegosszegeHUF' => $ntakOrder->total,
                        'fizetesiModok'         => [
                            [
                                'fizetesiMod'       => $ntakOrder->paymentType->name,
                                'fizetettOsszegHUF' => $ntakOrder->paymentType !== NTAKPaymentType::KESZPENZHUF
                                    ? $ntakOrder->total
                                    : (int) round($ntakOrder->total / 5) * 5
                            ]
                        ]
                    ],
                    'rendelesTetelek'              => $ntakOrder->orderType === NTAKOrderType::STORNO
                        ? null
                        : $ntakOrder->buildOrderItems(),
                ]
            ],
        ];

        $this->client->message($message, $this->when, '/rms/rendeles-osszesito');
    }

    /**
     * closeDay
     *
     * @param  Carbon      $start
     * @param  Carbon      $end
     * @param  NTAKDayType $dayType
     * @param  int         $tips
     * @return void
     */
    public function closeDay(
        ?Carbon     $start,
        ?Carbon     $end,
        NTAKDayType $dayType,
        int         $tips = 0
    ): void {
        $message = [
            'zarasiInformaciok' => [
                'targynap'           => $start->format('Y-m-d'),
                'targynapBesorolasa' => $dayType->name,
                'nyitasIdopontja'    => $dayType !== NTAKDayType::ADOTT_NAPON_ZARVA
                    ? $start->toRfc3339String(true)
                    : null,
                'zarasIdopontja'     => $dayType !== NTAKDayType::ADOTT_NAPON_ZARVA
                    ? $end->toRfc3339String(true)
                    : null,
                'osszesBorravalo'    => $tips,
            ],
        ];

        $this->client->message($message, $this->when, '/close');
    }
}
