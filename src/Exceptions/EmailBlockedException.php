<?php

namespace AshrafBendary\SecureEmail\Exceptions;

use Exception;

class EmailBlockedException extends Exception
{
    /**
     * The blocked email address.
     */
    protected string $email;

    /**
     * The reason for blocking.
     */
    protected string $reason;

    /**
     * Create a new exception instance.
     */
    public function __construct(string $email, string $reason = 'Email blocked due to bounces or complaints')
    {
        $this->email = $email;
        $this->reason = $reason;
        
        parent::__construct($reason);
    }

    /**
     * Get the blocked email address.
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Get the reason for blocking.
     */
    public function getReason(): string
    {
        return $this->reason;
    }
}
