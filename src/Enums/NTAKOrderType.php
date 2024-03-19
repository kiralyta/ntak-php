<?php

namespace Kiralyta\Ntak\Enums;

use Kiralyta\Ntak\Traits\EnumToArray;

enum NTAKOrderType: string
{
    use EnumToArray;
    case NORMAL = 'Normál';
    case SZTORNO = 'Storno';
    case HELYESBITO = 'Helyesbítő';
}
