<?php

namespace App\Actions;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RegisterOrganisation
{
    /**
     * Create an organisation together with its first user.
     *
     * Both rows are written in one transaction, so a failure can never leave
     * an organisation without an owner or a user without an organisation.
     *
     * @param  array{name: string, country_code: string}  $organisation
     * @param  array{name: string, email: string, password: string}  $owner
     */
    public function handle(array $organisation, array $owner): User
    {
        return DB::transaction(function () use ($organisation, $owner): User {
            $created = Organisation::create($organisation);

            return $created->users()->create($owner);
        });
    }
}
