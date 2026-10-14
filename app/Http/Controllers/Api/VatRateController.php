<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VatRateResource;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VatRateController extends Controller
{
    /**
     * The rates an invoice line can use: those of the organisation's country.
     */
    public function index(#[CurrentUser] User $user): AnonymousResourceCollection
    {
        $rates = VatRate::query()
            ->where('country_code', $user->organisation->country_code)
            ->orderByDesc('rate')
            ->get();

        return VatRateResource::collection($rates);
    }
}
