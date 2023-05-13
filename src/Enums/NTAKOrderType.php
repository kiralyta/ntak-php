<?php

namespace Kiralyta\Ntak\Enums;

enum NTAKOrderType: string
{
    case NORMAL = 'Normál';
    case STORNO = 'Storno';
    case HELYESBITO = 'Helyesbítő';
}
