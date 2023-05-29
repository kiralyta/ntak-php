# NTAK RMS PHP Api / SDK

Welcome to my little package, that helps you make NTAK RMS requests like a boss.

## Installation

``` bash
composer require kiralyta/ntak-php
```

> The package requires PHP ^8.1 since it was built around PHP enums.

## Usage

### Create an API Client Instance

``` php
use Kiralyta\Ntak\NTAKClient;

$client = new NTAKClient(
    taxNumber:        'NTAK client tax nr', // without `-` chars
    regNumber:        'NTAK client registration nr',
    sofwareReqNumber: 'NTAK RMS registration id',
    version:          'NTAK RMS version',
    certPath:         '/path/to/your.cer',
    keyPath:          'path/to/your.pem',
    testing:          false // whether to hit the test NTAK API
)
```

> Your ```.pem``` file is basically a concatenated file of your ```.cer``` and ```.key``` files.
>
> It is recommended to have a singleton ```NTAKClient``` instance during one request cycle. This means, you can create multiple requests with a single ```NTAKClient``` instance.

### Create an Order Item Instance

``` php
use Carbon\Carbon;
use Kiralyta\Ntak\Enums\NTAKAmount;
use Kiralyta\Ntak\Enums\NTAKCategory;
use Kiralyta\Ntak\Enums\NTAKSubcategory;
use Kiralyta\Ntak\Enums\NTAKVat;
use Kiralyta\Ntak\Models\NTAKOrderItem;

$orderItem = new NTAKOrderItem(
    name:            'Absolut Vodka', // Any kind of string
    category:        NTAKCategory::ALKOHOLOSITAL, // Main category
    subcategory:     NTAKSubcategory::PARLAT, // Subcategory
    vat:             NTAKVat::C_27,
    price:           1000
    amountType:      NTAKAmount::LITER,
    amount:          0.04,
    quantity:        2,
    when:            Carbon::now()
)
```

> - [NTAKCategory](#ntakcategory)
> - [NTAKSubcategory](#ntaksubcategory)
> - [NTAKVat](#ntakvat)
> - [NTAKAmount](#ntakamount)

### Store / Update / Destroy Order (Rendelésösszesítő)

## Enums

Namespace of the enums:

``` php
namespace Kiralyta\Ntak\Enums;
```

### NTAKAmount

| name      | value ***string*** |
| --------  | ---------          |
| DARAB     | darab              |
| LITER     | liter              |
| KILOGRAMM | kilogramm          |
| EGYSEG    | egyseg             |

### NTAKCategory

| name                      | value ***string***                       |
| --------                  | -----------------                        |
| ETEL                      | Étel                                     |
| ALKMENTESITAL_HELYBEN     | Helyben készített alkoholmentes ital     |
| ALKMENTESITAL_NEM_HELYBEN | Nem helyben készített alkoholmentes ital |
| ALKOHOLOSITAL             | Alkoholos Ital                           |
| EGYEB                     | Egyéb                                    |

### NTAKSubCategory

| name                      | value ***string***                              |
| --------                  | ---------                                       |
| REGGELI                   | reggeli                                         |
| SZENDVICS                 | szendvics                                       |
| ELOETEL                   | előétel                                         |
| LEVES                     | leves                                           |
| FOETEL                    | főétel                                          |
| KORET                     | köret                                           |
| SAVANYUSAG_SALATA         | savanyúság/saláta                               |
| KOSTOLO                   | kóstolóétel, kóstolófalat                       |
| PEKSUTEMENY               | péksütemény, pékáru                             |
| DESSZERT                  | desszert                                        |
| SNACK                     | snack                                           |
| FOETEL_KORETTEL           | főétel körettel                                 |
| ETELCSOMAG                | ételcsomag                                      |
| EGYEB                     | egyéb                                           |
| VIZ                       | víz                                             |
| LIMONADE_SZORP_FACSART    | limonádé / szörp / frissen facsart ital         |
| ALKOHOLMENTES_KOKTEL      | alkoholmentes koktél, alkoholmentes kevert ital |
| TEA_FORROCSOKOLADE        | tea, forrócsoki és egyéb tejalapú italok        |
| ITALCSOMAG                | italcsomag                                      |
| KAVE                      | kávé                                            |
| ROSTOS_UDITO              | rostos üdítő                                    |
| SZENSAVAS_UDITO           | szénsavas üdítő                                 |
| SZENSAVMENTES_UDITO       | szénsavmentes üdítő                             |
| KOKTEL                    | koktél, kevert ital                             |
| LIKOR                     | likőr                                           |
| PARLAT                    | párlat                                          |
| SOR                       | sör                                             |
| BOR                       | bor                                             |
| PEZSGO                    | pezsgő                                          |
| SZERVIZDIJ                | szervizdíj                                      |
| BORRAVALO                 | borravaló                                       |
| KISZALLITASI_DIJ          | kiszállítási díj                                |
| NEM_VENDEGLATAS           | nem vendéglátás                                 |
| KORNYEZETBARAT_CSOMAGOLAS | környezetbarát csomagolás                       |
| MUANYAG_CSOMAGOLAS        | műanyag csomagolás                              |
| KEDVEZMENY                | kedvezmény                                      |

### NTAKDayType

| name                 | value ***string***   |
| --------             | -----------------    |
| ADOTT_NAPON_ZARVA    | Adott napon zárva    |
| FORGALOM_NELKULI_NAP | Forgalom nélküli nap |
| NORMAL_NAP           | Normál nap           |

### NTAKOrderType

| name       | value ***string*** |
| --------   | -----------------  |
| NORMAL     | Normál             |
| STORNO     | Storno             |
| HELYESBITO | Helyesbítő         |

## Contribution

``` bash
git clone git@github.com:kiralyta/ntak-php.git
cd ntak-php
composer install --dev
```

### Run Tests

Put your ```cer.cer``` and ```pem.pem``` files in ```./auth``` directory, then run:

``` bash
vendor/bin/phpunit src/Tests
```
