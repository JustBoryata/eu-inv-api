<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Rendered as 409 Conflict: only an issued invoice can be marked as paid, so
 * a draft or an already paid invoice is refused.
 */
final class InvoiceNotIssued extends ConflictHttpException
{
    public static function cannotBePaid(): self
    {
        return new self('Only issued invoices can be marked as paid.');
    }
}
