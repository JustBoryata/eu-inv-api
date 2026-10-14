<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Limits every query to the signed-in user's organisation.
 *
 * Route model binding goes through this scope too, so a record that belongs
 * to another tenant is simply "not found" (404) rather than "forbidden" (403),
 * and the API never confirms that it exists.
 *
 * @implements Scope<Model>
 */
final class OrganisationScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        // Only authenticated requests are scoped. Queue workers and console
        // commands run without a user and work on rows they were handed by id.
        if ($user instanceof User) {
            $builder->where($model->qualifyColumn('organisation_id'), $user->organisation_id);
        }
    }
}
