<?php

namespace Modules\NsSpecialCustomer\Exceptions;

use Exception;

/**
 * Exception thrown when attempting to process cashback that has already been processed.
 */
class CashbackAlreadyProcessedException extends Exception
{
    /**
     * The customer ID for which cashback was already processed.
     */
    private int $customerId;

    /**
     * The year for which cashback was already processed.
     */
    private int $year;

    /**
     * Create a new CashbackAlreadyProcessedException instance.
     *
     * @param string $message The exception message
     * @param int $customerId The customer ID
     * @param int $year The year
     * @param int $code The exception code
     * @param Exception|null $previous The previous exception
     */
    public function __construct(
        string $message = 'Cashback has already been processed for this period',
        int $customerId = 0,
        int $year = 0,
        int $code = 409,
        ?Exception $previous = null
    ) {
        $this->customerId = $customerId;
        $this->year = $year;

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the customer ID.
     */
    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    /**
     * Get the year.
     */
    public function getYear(): int
    {
        return $this->year;
    }
}
