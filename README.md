# NTAK RMS PHP Api / SDK

Welcome to my little package, that helps you make NTAK RMS requests like a boss.

## Installation

``` bash
composer require kiralyta/ntak-php
```

## Usage



## Contribution

``` bash
git clone git@github.com:kiralyta/ntak-php.git
cd ntak-php
composer install --dev
```

Run tests:

Put your cer.cer and pem.pem files in ./auth directory, then run:

``` bash
vendor/bin/phpunit src/Tests
```

> Your .pem file is basically a concatenated file of your .cer and .key files.
