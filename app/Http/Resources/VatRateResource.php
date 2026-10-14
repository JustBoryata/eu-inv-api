<?php

namespace App\Http\Resources;

use App\Models\VatRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VatRate
 */
class VatRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_code' => $this->country_code,
            // A string, so "9.00" never turns into 8.999999 in a JSON client.
            'rate' => $this->rate,
            'name' => $this->name,
        ];
    }
}
