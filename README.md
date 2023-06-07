# NTAK RMS PHP API / SDK

Welcome to my little package, that helps you make NTAK RMS requests like a boss.

Table of Contents:
- [Installation](#installation)
- [Usage](#usage)
    - [Create an API Client Instance](#create-an-api-client-instance)
    - [Create an Order Item Instance](#create-an-order-item-instance)
    - [Create a Payment Instance](#create-a-payment-instance)
    - [Create an Order Instance](#create-an-order-instance)
    - [Store, Update, Destroy Order (Rendelésösszesítő)](#store-update-destroy-order-rendelésösszesítő)
    - [Close Day (Napzárás)](#close-day-napzárás)
    - [Verify (Ellenőrzés)](#verify-ellenőrzés)
- [Enums](#enums)
- [Contribution](#contribution)
- [Last Words](#last-words)

## Installation

``` bash
composer require kiralyta/ntak-php
```

> The package requires PHP ^8.1 since it was built around PHP enums.

## Usage

### Instances

#### Create an API Client Instance

``` php
use Kiralyta\Ntak\NTAKClient;

$client = new NTAKClient(
    taxNumber:         'NTAK client tax nr',         // without `-` chars
    regNumber:         'NTAK client registration nr',
    softwareRegNumber: 'NTAK RMS registration id',
    version:           'NTAK RMS version',
    certPath:          '/path/to/your.cer',
    keyPath:           '/path/to/your.pem',
    testing:           false                         // whether to hit the test NTAK API
);
```

> Your ```.pem``` file is basically a concatenated file of your ```.cer``` and ```.key``` files.
>
> It is recommended to have a singleton ```NTAKClient``` instance during one request cycle. This means, you can create multiple requests with a single ```NTAKClient``` instance.

You can get the last request, response and respone time (in milliseconds) from the client.

``` php
$client->lastRequest();     // Returns an array
$client->lastResponse();    // Returns an array
$client->lastRequestTime(); // Returns an integer
```

#### Create an Order Item Instance

``` php
use Carbon\Carbon;
use Kiralyta\Ntak\Enums\NTAKAmount;
use Kiralyta\Ntak\Enums\NTAKCategory;
use Kiralyta\Ntak\Enums\NTAKSubcategory;
use Kiralyta\Ntak\Enums\NTAKVat;
use Kiralyta\Ntak\Models\NTAKOrderItem;

$orderItem = new NTAKOrderItem(
    name:            'Absolut Vodka',             // Any kind of string
    category:        NTAKCategory::ALKOHOLOSITAL, // Main category
    subcategory:     NTAKSubcategory::PARLAT,     // Subcategory
    vat:             NTAKVat::C_27,
    price:           1000,
    amountType:      NTAKAmount::LITER,
    amount:          0.04,
    quantity:        2,
    when:            Carbon::now()
);
```

> - [NTAKCategory](#ntakcategory)
> - [NTAKSubcategory](#ntaksubcategory)
> - [NTAKVat](#ntakvat)
> - [NTAKAmount](#ntakamount)

#### Create a Payment Instance

``` php
use Kiralyta\Ntak\Enums\NTAKPaymentType;
use Kiralyta\Ntak\Models\NTAKPayment;

$payment = new NTAKPayment(
    paymentType:     NTAKPaymentType::BANKKARTYA,
    total:           2000 // Total payed with this method type
);
```

> - [NTAKPaymentType](#ntakpaymenttype)

#### Create an Order Instance

``` php
use Carbon\Carbon;
use Kiralyta\Ntak\Enums\NTAKOrderType;
use Kiralyta\Ntak\Models\NTAKOrderItem;
use Kiralyta\Ntak\Models\NTAKOrder;
use Kiralyta\Ntak\Models\NTAKPayment;

$order = new NTAKOrder(
    orderType:  NTAKOrderType::NORMAL,         // You can control whether to store, update, or destroy an order
    orderId:    'your-rms-order-id',           // RMS Order ID
    orderItems: [new NTAKOrderItem(...)],      // Array of the order items
    start:      Carbon::now()->addMinutes(-7), // Start of the order
    end:        Carbon::now(),                 // End of the order
    payments:   [new NTAKPayment(...)],         // Array of the payments

    // Discount is automatically managed by the package
    // You don't have to manually add an OrderItem with "KEDVEZMENY" subcategory
    // This means 20% discount (defaults to 0)
    discount:   20
);
```

> - [NTAKOrderType](#ntakordertype)
> - [NTAKOrderItem](#create-an-order-item-instance)
> - [NTAKPayment](#create-a-payment-instance)

### Messages (Requests)

#### Store, Update, Destroy Order (Rendelésösszesítő)

``` php
use Carbon\Carbon;
use Kiralyta\Ntak\Models\NTAKOrder;
use Kiralyta\Ntak\Models\NTAKPayment;
use Kiralyta\Ntak\NTAK;

$processId = NTAK::message($client, Carbon::now())
    ->handleOrder(new NTAKOrder(...));
```

> Returns the NTAK process ID string.
>
> - [NTAKOrder](#create-an-order-instance)

#### Close Day (Napzárás)

``` php
use Carbon\Carbon;
use Kiralyta\Ntak\Enums\NTAKDayType;
use Kiralyta\Ntak\NTAK;

$processId = NTAK::message($client, Carbon::now())
    ->closeDay(
        start:   Carbon::now()->addHours(-10), // Opening time (nullable)
        end:     Carbon::now(),                // Closing time (nullable)
        dayType: NTAKDayType::NORMAL_NAP,      // Day type
        tips:    1000                          // Tips (default 0)
    );
```

> Returns the NTAK process ID string.
>
> - [NTAKDayType](#ntakdaytype)

#### Verify (Ellenőrzés)

``` php
use Carbon\Carbon;
use Kiralyta\Ntak\Enums\NTAKDayType;
use Kiralyta\Ntak\NTAK;

$response = NTAK::message($client, Carbon::now())
    ->verify(
        process_id: 'NTAK Process ID'
    );
```

> Returns an ```NTAKVerifyResponse``` instance

``` php
$response->successful();         // Check whether our message was processed successfully
$response->unsuccessful();       // Check whether our message was processed unsuccessfully
$response->status;               // Returns an NTAKVerifyStatus
$response->successfulMessages;   // Returns an array of the successful messages
$response->unsuccessfulMessages; // Returns an array of the unsuccessful messages
```

> If you encounter an unsuccessful message, you should further examine [NTAKVerifyStatus](#ntakverifystatus).
> It's recommended to wait at least 60 seconds before the first verification attempt of a processs ID.

## Enums

Namespace of the enums:

``` php
namespace Kiralyta\Ntak\Enums;
```

You can use the ```values()``` static method on any of the enums, in order to get the available values.

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

### NTAKSubcategory

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

### NTAKPaymentType

| name        | value ***string*** |
| --------    | -----------------  |
| KESZPENZHUF | Készpénz huf       |
| KESZPENZEUR | Készpénz eur       |
| SZEPKARTYA  | Szépkártya         |
| BANKKARTYA  | Bankkártya         |
| ATUTALAS    | Átutalás           |
| EGYEB       | Egyéb              |
| VOUCHER     | Voucher            |
| SZOBAHITEL  | Szobahitel         |
| KEREKITES   | Kerekítés          |

### NTAKVat

| name  | value ***string*** |
| ----- | -----------------  |
| A_5   | 5%                 |
| B_18  | 18%                |
| C_27  | 27%                |
| D_AJT | Ajt                |
| E_0   | 0%                 |

### NTAKVerifyStatus

| name             | value ***string*** |
| --------         | ---------          |
| BEFOGADVA        | BEFOGADVA          |
| TELJESEN_HIBAS   | TELJESEN_HIBAS     |
| RESZBEN_SIKERES  | RESZBEN_SIKERES    |
| TELJESEN_SIKERES | TELJESEN_SIKERES   |
| UJRA_KULDENDO    | UJRA_KULDENDO      |

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

## Last Words

I am not taking any responsiblities for the use of this package.

This is simply a personal project that could help other fellow software artisans to make requests to MTÜ.

It's still recommended to read the documentation of the RMS Interface, even in case of using this package.

Please feel free to open an issue if you encounter one.
