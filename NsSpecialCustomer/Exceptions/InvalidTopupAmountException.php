<?php

namespace Modules\NsSpecialCustomer\Exceptions;

use Exception;

/**
 * Exception thrown when an invalid topup amount is provided.
 */
class InvalidTopupAmountException extends Exception
{
    /**
     * The invalid amount that was provided.
     */
    private float $invalidAmount;

    /**
     * Create a new InvalidTopupAmountException instance.
     *
     * @param string $message The exception message
     * @param float $invalidAmount The invalid amount provided
     * @param int $code The exception code
     * @param Exception|null $previous The previous exception
     */
    public function __construct(
        string $message = 'Invalid topup amount',
        float $invalidAmount = 0,
        int $code = 400,
        ?Exception $previous = null
    ) {
        $this->invalidAmount = $invalidAmount;

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the invalid amount.
     */
    public function getInvalidAmount(): float
    {
        return $this->invalidAmount;
    }
}
