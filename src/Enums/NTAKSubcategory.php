<?php

namespace Kiralyta\Ntak\Enums;

use Kiralyta\Ntak\Traits\EnumToArray;

enum NTAKSubcategory: string
{
    use EnumToArray;

    case REGGELI = 'reggeli';
    case SZENDVICS = 'szendvics';
    case ELOETEL = 'előétel';
    case LEVES = 'leves';
    case FOETEL = 'főétel';
    case KORET = 'köret';
    case SAVANYUSAG_SALATA = 'savanyúság/saláta';
    case KOSTOLO = 'kóstolóétel, kóstolófalat';
    case PEKSUTEMENY = 'péksütemény, pékáru';
    case DESSZERT = 'desszert';
    case SNACK = 'snack';
    case FOETEL_KORETTEL = 'főétel körettel';
    case ETELCSOMAG = 'ételcsomag';
    case EGYEB = 'egyéb';
    case VIZ = 'víz';
    case LIMONADE_SZORP_FACSART = 'limonádé / szörp / frissen facsart ital';
    case ALKOHOLMENTES_KOKTEL = 'alkoholmentes koktél, alkoholmentes kevert ital';
    case TEA_FORROCSOKOLADE = 'tea, forrócsoki és egyéb tejalapú italok';
    case ITALCSOMAG = 'italcsomag';
    case KAVE = 'kávé';
    case ROSTOS_UDITO = 'rostos üdítő';
    case SZENSAVAS_UDITO = 'szénsavas üdítő';
    case SZENSAVMENTES_UDITO = 'szénsavmentes üdítő';
    case KOKTEL = 'koktél, kevert ital';
    case LIKOR = 'likőr';
    case PARLAT = 'párlat';
    case SOR = 'sör';
    case BOR = 'bor';
    case PEZSGO = 'pezsgő';
    case SZERVIZDIJ = 'szervizdíj';
    case BORRAVALO = 'borravaló';
    case KISZALLITASI_DIJ = 'kiszállítási díj';
    case NEM_VENDEGLATAS = 'nem vendéglátás';
    case KORNYEZETBARAT_CSOMAGOLAS = 'környezetbarát csomagolás';
    case MUANYAG_CSOMAGOLAS = 'műanyag csomagolás';
    case KEDVEZMENY = 'kedvezmény';
}
