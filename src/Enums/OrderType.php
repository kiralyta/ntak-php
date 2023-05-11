<?php

namespace Kiralyta\Ntak\Enums;

enum OrderType: string
{
    case NORMAL = 'Normál';
    case STORNO = 'Storno';
    case HELYESBITO = 'Helyesbítő';
}
