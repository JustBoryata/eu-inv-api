<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A VAT rate that applies in one country. Shared reference data, so it has
 * no tenant scope.
 *
 * @property string $rate A percentage with two decimals, e.g. "20.00".
 */
class VatRate extends Model
{
    public $timestamps = false;
}
