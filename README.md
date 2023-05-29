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

### Store / Update / Destroy Order (Rendelésösszesítő)

## Enums

### NTAKAmount

Namespace of the enums:

``` php
namespace Kiralyta\Ntak\Enums;
```

### NTAKAmount

| name      | value     |
| --------  | --------- |
| DARAB     | darab     |
| LITER     | liter     |
| KILOGRAMM | kilogramm |
| EGYSEG    | egyseg    |

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
