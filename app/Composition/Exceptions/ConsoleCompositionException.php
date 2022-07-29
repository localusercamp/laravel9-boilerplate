<?php

namespace App\Composition\Exceptions;

use Exception;
use Throwable;

class ConsoleCompositionException extends Exception
{
  public function __construct(string $message = "", int $code = 0, Throwable|null $previous = null)
  {
    $message = 'It\'s internal composition bug. ' . $message;
    parent::__construct($message, $code, $previous);
  }
}
