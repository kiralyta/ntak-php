<?php

namespace Kiralyta\Ntak;

use PHPUnit\Framework\TestCase as FrameworkTestCase;

class TestCase extends FrameworkTestCase
{
    protected string $taxNumber = '11223344122';
    protected string $regNumber = 'ET23002480';
    protected string $softwareRegNumber = 'TABTENDER';
    protected string $version = '1.4.21';
    protected string $certPath = __DIR__.'/../auth/cer.cer';
    protected string $keyPath = __DIR__.'/../auth/pem.pem';
}
