<?php

namespace Kiralyta\Ntak\Enums;

use Kiralyta\Ntak\Traits\EnumToArray;

enum NTAKDayType: string
{
    use EnumToArray;

    case ADOTT_NAPON_ZARVA = 'Adott napon zárva';
    case FORGALOM_NELKULI_NAP = 'Forgalom nélküli nap';
    case NORMAL_NAP = 'Normál nap';
}
