<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The per-organisation invoice counter. Only IssueInvoice touches it, and
 * only while holding its row lock.
 */
class InvoiceSequence extends Model
{
    protected $primaryKey = 'organisation_id';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }
}
