<?php

namespace Beter\Yii2\LogRequestResponse\Exception;

use Beter\ExceptionWithContext\ExceptionWithContext;

class BaseException extends ExceptionWithContext
{
    /**
     * @param string $message
     * @param array $context key-value array with context data you want to store in this exception for further
     *   processing
     * @param \Throwable|null $previous previous Throwable (https://php.net/manual/en/throwable.getprevious.php)
     */
    public function __construct($message = "", array $context = [], \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous, $context);
    }
}
