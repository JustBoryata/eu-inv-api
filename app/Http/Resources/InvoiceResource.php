<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Money is in integer cents of the invoice's currency.
 *
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'number' => $this->number,
            'issue_date' => $this->issue_date?->toDateString(),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'client_id' => $this->client_id,
            'client' => new ClientResource($this->whenLoaded('client')),
            'currency' => $this->currency,
            'due_date' => $this->due_date?->toDateString(),
            'notes' => $this->notes,
            'net_total' => $this->net_total,
            'vat_total' => $this->vat_total,
            'gross_total' => $this->gross_total,
            'vat_breakdown' => $this->vat_breakdown,
            // Null on drafts. Once issued, these are the frozen copies, not the
            // live organisation and client.
            'seller' => $this->number === null ? null : [
                'name' => $this->seller_name,
                'registration_number' => $this->seller_registration_number,
                'vat_number' => $this->seller_vat_number,
                'address' => $this->seller_address,
                'country_code' => $this->seller_country_code,
            ],
            'buyer' => $this->number === null ? null : [
                'name' => $this->buyer_name,
                'registration_number' => $this->buyer_registration_number,
                'vat_number' => $this->buyer_vat_number,
                'address' => $this->buyer_address,
                'country_code' => $this->buyer_country_code,
            ],
            'lines' => InvoiceLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}
