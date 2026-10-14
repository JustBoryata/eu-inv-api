<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A buyer the organisation invoices. organisation_id is deliberately not
 * fillable: it always comes from the signed-in user, never from the request.
 *
 * @property-read Organisation $organisation
 */
#[Fillable(['name', 'vat_number', 'registration_number', 'address', 'country_code', 'email'])]
class Client extends Model
{
    use BelongsToOrganisation;

    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
