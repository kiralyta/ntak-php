<?php

namespace Kiralyta\Ntak\Enums;

use Kiralyta\Ntak\Traits\EnumToArray;

enum NTAKAmount: string
{
    use EnumToArray;

    case DARAB = 'darab';
    case LITER = 'liter';
    case KILOGRAMM = 'kilogramm';
    case EGYSEG = 'egyseg';
}
