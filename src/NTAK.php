<?php

namespace Kiralyta\Ntak;

use Carbon\Carbon;
use Kiralyta\Ntak\Enums\NTAKCategory;
use Kiralyta\Ntak\Enums\NTAKDayType;
use Kiralyta\Ntak\Enums\NTAKOrderType;
use Kiralyta\Ntak\Enums\NTAKSubcategory;
use Kiralyta\Ntak\Enums\NTAKVerifyStatus;
use Kiralyta\Ntak\Models\NTAKOrder;
use Kiralyta\Ntak\Responses\NTAKVerifyResponse;

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
        public readonly NTAKClient $client,
        protected       Carbon $when
    ) {
    }

    /**
     * Lists the categories
     *
     * @return array|NTAKCategory[]
     */
    public static function categories(): array
    {
        return NTAKCategory::values();
    }

    /**
     * Lists the subcategories of a category
     *
     * @param  NTAKCategory $category
     * @return array|NTAKSubcategory[]
     */
    public static function subcategories(NTAKCategory $category): array
    {
        return $category->subcategories();
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
     * fake
     *
     * @param  array $expectedResponse
     * @return NTAK
     */
    public function fake(array $expectedResponse): NTAK
    {
        $this->client->fakeResponse($expectedResponse);
        return $this;
    }

    /**
     * handleOrder
     *
     * @param  NTAKOrder $ntakOrders
     * @return string
     */
    public function handleOrder(NTAKOrder ...$ntakOrders): string
    {
        $orders = [];
        foreach ($ntakOrders as $ntakOrder) {
            $orders[] = [
                'rendelesBesorolasa'           => $ntakOrder->orderType->name,
                'rmsRendelesAzonosito'         => $ntakOrder->orderId,
                'hivatkozottRendelesOsszesito' => $ntakOrder->orderType === NTAKOrderType::NORMAL
                    ? null
                    : $ntakOrder->ntakOrderId,
                'targynap'                     => $ntakOrder->end->format('Y-m-d'),
                'rendelesKezdete'              => $ntakOrder->orderType === NTAKOrderType::SZTORNO
                    ? null
                    : $ntakOrder->start->timezone('Europe/Budapest')->toIso8601String(),
                'rendelesVege'                 => $ntakOrder->orderType === NTAKOrderType::SZTORNO
                    ? null
                    : $ntakOrder->end->timezone('Europe/Budapest')->toIso8601String(),
                'helybenFogyasztott'           => $ntakOrder->isAtTheSpot,
                'osszesitett'                  => false,
                'fizetesiInformaciok'          => $ntakOrder->orderType === NTAKOrderType::SZTORNO
                    ? null
                    : [
                        'rendelesVegosszegeHUF' => $ntakOrder->totalWithDiscount(),
                        'fizetesiModok'         => $ntakOrder->buildPaymentTypes(),
                    ],
                'rendelesTetelek'              => $ntakOrder->orderType === NTAKOrderType::SZTORNO
                    ? null
                    : $ntakOrder->buildOrderItems(),
            ];
        }

        $message = [
            'rendelesOsszesitok' => $orders,
        ];

        return $this->client->message(
            $message,
            $this->when,
            '/rms/rendeles-osszesito'
        )['feldolgozasAzonosito'];
    }

    /**
     * closeDay
     *
     * @param  Carbon      $start
     * @param  Carbon      $end
     * @param  NTAKDayType $dayType
     * @param  int         $tips
     * @return string
     */
    public function closeDay(
        Carbon      $start,
        ?Carbon     $end = null,
        NTAKDayType $dayType,
        int         $tips = 0
    ): string {
        $message = [
            'zarasiInformaciok' => [
                'targynap'           => $start->format('Y-m-d'),
                'targynapBesorolasa' => $dayType->name,
                'nyitasIdopontja'    => $dayType !== NTAKDayType::ADOTT_NAPON_ZARVA
                    ? $start->timezone('Europe/Budapest')->toIso8601String()
                    : null,
                'zarasIdopontja'     => $dayType !== NTAKDayType::ADOTT_NAPON_ZARVA
                    ? $end->timezone('Europe/Budapest')->toIso8601String()
                    : null,
                'osszesBorravalo'    => $tips,
            ],
        ];

        return $this->client->message(
            $message,
            $this->when,
            '/rms/napi-zaras'
        )['feldolgozasAzonosito'];
    }

    /**
     * verify
     *
     * @param  string $processId
     * @return NTAKVerifyResponse
     */
    public function verify(
        string $processId
    ): NTAKVerifyResponse {
        $message = [
            'feldolgozasAzonositok' => [
                [
                    'feldolgozasAzonosito' => $processId,
                ]
            ],
        ];

        $response = $this->client->message(
            $message,
            $this->when,
            '/rms/ellenorzes'
        )['uzenetValaszok'][0];
        
        return new NTAKVerifyResponse(
            successfulMessages:   $response['sikeresUzenetek'] ?? [],
            unsuccessfulMessages: $response['sikertelenUzenetek'] ?? [],
            headerErrors: $response['fejlecHibak'] ?? [],
            status:               NTAKVerifyStatus::from($response['statusz'])
        );
    }
}
