<?php

namespace Kiralyta\Ntak\Enums;

use Kiralyta\Ntak\Traits\EnumToArray;

enum NTAKCategory: string
{
    use EnumToArray;

    case ETEL = 'Étel';
    case ALKMENTESITAL_HELYBEN = 'Helyben készített alkoholmentes ital';
    case ALKMENTESITAL_NEM_HELYBEN = 'Nem helyben készített alkoholmentes ital';
    case ALKOHOLOSITAL = 'Alkoholos Ital';
    case EGYEB = 'Egyéb';

    /**
     * subCategories
     *
     * @return array
     */
    public function subCategories(): array
    {
        return match($this)
        {
            NTAKCategory::ETEL => [
                NTAKSubCategory::REGGELI,
                NTAKSubCategory::SZENDVICS,
                NTAKSubCategory::ELOETEL,
                NTAKSubCategory::LEVES,
                NTAKSubCategory::FOETEL,
                NTAKSubCategory::KORET,
                NTAKSubCategory::SAVANYUSAG_SALATA,
                NTAKSubCategory::KOSTOLO,
                NTAKSubCategory::PEKSUTEMENY,
                NTAKSubCategory::DESSZERT,
                NTAKSubCategory::SNACK,
                NTAKSubCategory::FOETEL_KORETTEL,
                NTAKSubCategory::ETELCSOMAG,
                NTAKSubCategory::EGYEB,
            ],
            NTAKCategory::ALKMENTESITAL_HELYBEN => [
                NTAKSubCategory::VIZ,
                NTAKSubCategory::LIMONADE_SZORP_FACSART,
                NTAKSubCategory::ALKOHOLMENTES_KOKTEL,
                NTAKSubCategory::TEA_FORROCSOKOLADE,
                NTAKSubCategory::ITALCSOMAG,
                NTAKSubCategory::KAVE,
            ],
            NTAKCategory::ALKMENTESITAL_NEM_HELYBEN => [
                NTAKSubCategory::VIZ,
                NTAKSubCategory::ROSTOS_UDITO,
                NTAKSubCategory::SZENSAVAS_UDITO,
                NTAKSubCategory::SZENSAVMENTES_UDITO,
                NTAKSubCategory::SZENSAVAS_UDITO,
                NTAKSubCategory::SZENSAVMENTES_UDITO,
                NTAKSubCategory::ITALCSOMAG,
            ],
            NTAKCategory::ALKOHOLOSITAL => [
                NTAKSubCategory::KOKTEL,
                NTAKSubCategory::LIKOR,
                NTAKSubCategory::PARLAT,
                NTAKSubCategory::SOR,
                NTAKSubCategory::BOR,
                NTAKSubCategory::PEZSGO,
                NTAKSubCategory::ITALCSOMAG,
            ],
            NTAKCategory::EGYEB => [
                NTAKSubCategory::EGYEB,
                NTAKSubCategory::SZERVIZDIJ,
                NTAKSubCategory::BORRAVALO,
                NTAKSubCategory::KISZALLITASI_DIJ,
                NTAKSubCategory::NEM_VENDEGLATAS,
                NTAKSubCategory::KORNYEZETBARAT_CSOMAGOLAS,
                NTAKSubCategory::MUANYAG_CSOMAGOLAS,
                NTAKSubCategory::KEDVEZMENY,
            ]
        };
    }
}
