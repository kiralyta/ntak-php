<?php

namespace Kiralyta\Ntak\Enums;

use Kiralyta\Ntak\Traits\EnumToArray;

enum NTAKVerifyStatus: string
{
    use EnumToArray;

    case BEFOGADVA = 'BEFOGADVA';
    case TELJESEN_HIBAS = 'TELJESEN_HIBAS';
    case RESZBEN_SIKERES = 'RESZBEN_SIKERES';
    case TELJESEN_SIKERES = 'TELJESEN_SIKERES';
    case UJRA_KULDENDO = 'UJRA_KULDENDO';
}
