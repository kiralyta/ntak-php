<?php

namespace Kiralyta\Ntak\Enums;

use Kiralyta\Ntak\Traits\EnumToArray;

enum Category: string
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
            Category::ETEL => [
                SubCategory::REGGELI,
                SubCategory::SZENDVICS,
                SubCategory::ELOETEL,
                SubCategory::LEVES,
                SubCategory::FOETEL,
                SubCategory::KORET,
                SubCategory::SAVANYUSAG_SALATA,
                SubCategory::KOSTOLO,
                SubCategory::PEKSUTEMENY,
                SubCategory::DESSZERT,
                SubCategory::SNACK,
                SubCategory::FOETEL_KORETTEL,
                SubCategory::ETELCSOMAG,
                SubCategory::EGYEB,
            ],
            Category::ALKMENTESITAL_HELYBEN => [
                SubCategory::VIZ,
                SubCategory::LIMONADE_SZORP_FACSART,
                SubCategory::ALKOHOLMENTES_KOKTEL,
                SubCategory::TEA_FORROCSOKOLADE,
                SubCategory::ITALCSOMAG,
                SubCategory::KAVE,
            ],
            Category::ALKMENTESITAL_NEM_HELYBEN => [
                SubCategory::VIZ,
                SubCategory::ROSTOS_UDITO,
                SubCategory::SZENSAVAS_UDITO,
                SubCategory::SZENSAVMENTES_UDITO,
                SubCategory::SZENSAVAS_UDITO,
                SubCategory::SZENSAVMENTES_UDITO,
                SubCategory::ITALCSOMAG,
            ],
            Category::ALKOHOLOSITAL => [
                SubCategory::KOKTEL,
                SubCategory::LIKOR,
                SubCategory::PARLAT,
                SubCategory::SOR,
                SubCategory::BOR,
                SubCategory::PEZSGO,
                SubCategory::ITALCSOMAG,
            ],
            Category::EGYEB => [
                SubCategory::EGYEB,
                SubCategory::SZERVIZDIJ,
                SubCategory::BORRAVALO,
                SubCategory::KISZALLITASI_DIJ,
                SubCategory::NEM_VENDEGLATAS,
                SubCategory::KORNYEZETBARAT_CSOMAGOLAS,
                SubCategory::MUANYAG_CSOMAGOLAS,
                SubCategory::KEDVEZMENY,
            ]
        };
    }
}
