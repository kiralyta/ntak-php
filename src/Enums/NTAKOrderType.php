<?php

namespace Kiralyta\Ntak\Enums;

enum NTAKOrderType: string
{
    case NORMAL = 'Normál';
    case SZTORNO = 'Storno';
    case HELYESBITO = 'Helyesbítő';
}
