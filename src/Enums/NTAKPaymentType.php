<?php

namespace Kiralyta\Ntak\Enums;

use Kiralyta\Ntak\Traits\EnumToArray;

enum NTAKPaymentType: string
{
    use EnumToArray;

    case KESZPENZHUF = 'Készpénz huf';
    case KESZPENZEUR = 'Készpénz eur';
    case SZEPKARTYA = 'Szépkártya';
    case BANKKARTYA = 'Bankkártya';
    case ATUTALAS = 'Átutalás';
    case EGYEB = 'Egyéb';
    case VOUCHER = 'Voucher';
    case SZOBAHITEL = 'Szobahitel';
    case KEREKITES = 'Kerekítés';
}
