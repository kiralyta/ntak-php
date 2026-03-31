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
     * subcategories
     *
     * @return array|NTAKSubcategory[]
     */
    public function subcategories(): array
    {
        return match($this) {
            NTAKCategory::ETEL => [
                NTAKSubcategory::REGGELI,
                NTAKSubcategory::SZENDVICS,
                NTAKSubcategory::ELOETEL,
                NTAKSubcategory::LEVES,
                NTAKSubcategory::FOETEL,
                NTAKSubcategory::KORET,
                NTAKSubcategory::SAVANYUSAG_SALATA,
                NTAKSubcategory::KOSTOLO,
                NTAKSubcategory::PEKSUTEMENY,
                NTAKSubcategory::DESSZERT,
                NTAKSubcategory::SNACK,
                NTAKSubcategory::FOETEL_KORETTEL,
                NTAKSubcategory::ETELCSOMAG,
                NTAKSubcategory::EGYEB,
            ],
            NTAKCategory::ALKMENTESITAL_HELYBEN => [
                NTAKSubcategory::VIZ,
                NTAKSubcategory::LIMONADE_SZORP_FACSART,
                NTAKSubcategory::ALKOHOLMENTES_KOKTEL,
                NTAKSubcategory::TEA_FORROCSOKOLADE,
                NTAKSubcategory::ITALCSOMAG,
                NTAKSubcategory::KAVE,
            ],
            NTAKCategory::ALKMENTESITAL_NEM_HELYBEN => [
                NTAKSubcategory::VIZ,
                NTAKSubcategory::ROSTOS_UDITO,
                NTAKSubcategory::SZENSAVAS_UDITO,
                NTAKSubcategory::SZENSAVMENTES_UDITO,
                NTAKSubcategory::ITALCSOMAG,
            ],
            NTAKCategory::ALKOHOLOSITAL => [
                NTAKSubcategory::KOKTEL,
                NTAKSubcategory::LIKOR,
                NTAKSubcategory::PARLAT,
                NTAKSubcategory::SOR,
                NTAKSubcategory::BOR,
                NTAKSubcategory::PEZSGO,
                NTAKSubcategory::ITALCSOMAG,
            ],
            NTAKCategory::EGYEB => [
                NTAKSubcategory::EGYEB,
                NTAKSubcategory::SZERVIZDIJ,
                NTAKSubcategory::BORRAVALO,
                NTAKSubcategory::KISZALLITASI_DIJ,
                NTAKSubcategory::NEM_VENDEGLATAS,
                NTAKSubcategory::KORNYEZETBARAT_CSOMAGOLAS,
                NTAKSubcategory::MUANYAG_CSOMAGOLAS,
                NTAKSubcategory::KEDVEZMENY,
            ]
        };
    }

    /**
     * hasSubcategory
     *
     * @param  NTAKSubcategory $subcategory
     * @return bool
     */
    public function hasSubcategory(NTAKSubcategory $subcategory): bool
    {
        $subcategories = $this->subcategories();

        foreach ($subcategories as $validSubcategory) {
            if ($validSubcategory === $subcategory) {
                return true;
            }
        }

        return false;
    }
}
