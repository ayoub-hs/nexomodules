<?php

namespace Modules\NsSpecialCustomer\Exceptions;

use Exception;

/**
 * Exception thrown when a customer has insufficient balance for an operation.
 */
class InsufficientBalanceException extends Exception
{
    /**
     * The current balance of the customer.
     */
    private float $currentBalance;

    /**
     * The required amount for the operation.
     */
    private float $requiredAmount;

    /**
     * Create a new InsufficientBalanceException instance.
     *
     * @param string $message The exception message
     * @param float $currentBalance The current balance
     * @param float $requiredAmount The required amount
     * @param int $code The exception code
     * @param Exception|null $previous The previous exception
     */
    public function __construct(
        string $message = 'Insufficient balance',
        float $currentBalance = 0,
        float $requiredAmount = 0,
        int $code = 400,
        ?Exception $previous = null
    ) {
        $this->currentBalance = $currentBalance;
        $this->requiredAmount = $requiredAmount;

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the current balance.
     */
    public function getCurrentBalance(): float
    {
        return $this->currentBalance;
    }

    /**
     * Get the required amount.
     */
    public function getRequiredAmount(): float
    {
        return $this->requiredAmount;
    }

    /**
     * Get the shortfall amount.
     */
    public function getShortfall(): float
    {
        return max(0, $this->requiredAmount - $this->currentBalance);
    }
}
