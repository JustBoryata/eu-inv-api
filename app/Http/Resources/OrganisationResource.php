<?php

namespace App\Http\Resources;

use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organisation
 */
class OrganisationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'country_code' => $this->country_code,
            'registration_number' => $this->registration_number,
            'vat_number' => $this->vat_number,
            'address' => $this->address,
        ];
    }
}
