<?php

namespace Kiralyta\Ntak\Traits;

use InvalidArgumentException;
use Kiralyta\Ntak\Enums\NTAKAmount;
use Kiralyta\Ntak\Enums\NTAKCategory;
use Kiralyta\Ntak\Enums\NTAKDayType;
use Kiralyta\Ntak\Enums\NTAKOrderType;
use Kiralyta\Ntak\Enums\NTAKPaymentType;
use Kiralyta\Ntak\Enums\NTAKSubcategory;
use Kiralyta\Ntak\Enums\NTAKVat;
use Kiralyta\Ntak\Enums\NTAKVerifyStatus;

trait EnumToArray
{
    /**
     * names
     *
     * @return array
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * values
     *
     * @return array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * array
     *
     * @return array
     */
    public static function array(): array
    {
        return array_combine(self::values(), self::names());
    }

    /**
     * random
     *
     * @return mixed
     */
    public static function random()
    {
        return self::cases()[array_rand(self::cases())];
    }

    /**
     * fromName
     *
     * @param string $name
     * @return NTAKAmount|NTAKCategory|NTAKDayType|NTAKOrderType|NTAKPaymentType|NTAKSubcategory|NTAKVat|NTAKVerifyStatus|EnumToArray $case belong to $name
     */
    public static function fromName(string $name)
    {
        foreach (self::cases() as $case) {
            if( $name === $case->name ){
                return $case;
            }
        }

        throw new InvalidArgumentException("$name is not a valid backing value for enum " . self::class );
    }
}
