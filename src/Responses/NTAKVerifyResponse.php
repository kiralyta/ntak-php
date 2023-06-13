<?php

namespace Kiralyta\Ntak\Responses;

use Kiralyta\Ntak\Enums\NTAKVerifyStatus;

class NTAKVerifyResponse
{
    /**
     * __construct
     *
     * @param  array            $successfulMessages
     * @param  array            $unsuccessfulMessages
     * @param  array            $headerErrors
     * @param  NTAKVerifyStatus $status
     * @return void
     */
    public function __construct(
        public readonly array            $successfulMessages,
        public readonly array            $unsuccessfulMessages,
        public readonly array            $headerErrors,
        public readonly NTAKVerifyStatus $status
    ) {
    }

    /**
     * successful
     *
     * @return bool
     */
    public function successful(): bool
    {
        return $this->status === NTAKVerifyStatus::TELJESEN_SIKERES;
    }

    /**
     * unsuccessful
     *
     * @return bool
     */
    public function unsuccessful(): bool
    {
        return ! $this->successful();
    }
}
