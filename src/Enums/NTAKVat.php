<?php

namespace Kiralyta\Ntak\Enums;

use Kiralyta\Ntak\Traits\EnumToArray;

enum NTAKVat: string
{
    use EnumToArray;

    case A_5 = '5%';
    case B_18 = '18%';
    case C_27 = '27%';
    case D_AJT = 'Ajt';
    case E_0 = '0%';
}
