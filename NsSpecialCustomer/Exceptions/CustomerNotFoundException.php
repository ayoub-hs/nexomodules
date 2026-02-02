<?php

namespace Modules\NsSpecialCustomer\Exceptions;

use Exception;

/**
 * Exception thrown when a requested customer cannot be found.
 */
class CustomerNotFoundException extends Exception
{
    /**
     * Create a new CustomerNotFoundException instance.
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param Exception|null $previous The previous exception
     */
    public function __construct(string $message = 'Customer not found', int $code = 404, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
