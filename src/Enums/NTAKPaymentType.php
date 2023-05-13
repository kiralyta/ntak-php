<?php

namespace Kiralyta\Ntak\Enums;

enum NTAKPaymentType: string
{
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
