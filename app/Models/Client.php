<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A buyer the organisation invoices. organisation_id is deliberately not
 * fillable: it always comes from the signed-in user, never from the request.
 */
#[Fillable(['name', 'vat_number', 'registration_number', 'address', 'country_code', 'email'])]
class Client extends Model
{
    use BelongsToOrganisation;

    /** @use HasFactory<ClientFactory> */
    use HasFactory;
}
