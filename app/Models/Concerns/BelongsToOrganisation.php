<?php

namespace App\Models\Concerns;

use App\Models\Organisation;
use App\Models\Scopes\OrganisationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For tenant-owned models: adds the organisation relation and the global
 * scope that hides other tenants' rows.
 */
trait BelongsToOrganisation
{
    public static function bootBelongsToOrganisation(): void
    {
        static::addGlobalScope(new OrganisationScope);
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
