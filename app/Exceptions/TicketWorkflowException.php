<?php

namespace App\Exceptions;

use RuntimeException;

/** A ticket or SLA operation was rejected by a domain rule. */
class TicketWorkflowException extends RuntimeException
{
}
