<?php

namespace App\Exceptions;

class PolicyChatException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The AI service could not complete your answer. Please try again shortly or contact HRDO.');
    }
}
