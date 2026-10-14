<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Rendered as 409 Conflict: the request is valid, but the invoice's state
 * doesn't allow it.
 */
final class InvoiceNotDraft extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('Only draft invoices can be changed. An issued invoice is corrected with a credit note.');
    }
}
