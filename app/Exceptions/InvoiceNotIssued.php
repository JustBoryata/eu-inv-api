<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Rendered as 409 Conflict: the action needs an issued invoice, and this one
 * is still a draft (or, for mark-paid, already paid).
 */
final class InvoiceNotIssued extends ConflictHttpException
{
    public static function cannotBePaid(): self
    {
        return new self('Only issued invoices can be marked as paid.');
    }

    public static function hasNoPdf(): self
    {
        return new self('A draft has no PDF. Issue the invoice first.');
    }
}
