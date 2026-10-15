<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrganisationRequest;
use App\Http\Resources\OrganisationResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

/**
 * The signed-in user's own organisation. There's no id in the URL, so there's
 * no way to address another tenant's organisation at all.
 */
class OrganisationController extends Controller
{
    /**
     * Show your organisation
     */
    public function show(#[CurrentUser] User $user): OrganisationResource
    {
        return new OrganisationResource($user->organisation);
    }

    /**
     * Update your organisation
     *
     * Issuing an invoice needs the registration number (EIK) and address, which registration does not ask for.
     */
    public function update(UpdateOrganisationRequest $request, #[CurrentUser] User $user): OrganisationResource
    {
        $organisation = $user->organisation;
        $organisation->update($request->validated());

        return new OrganisationResource($organisation);
    }
}
