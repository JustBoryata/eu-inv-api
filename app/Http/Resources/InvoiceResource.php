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
            'client_id' => $this->client_id,
            'client' => new ClientResource($this->whenLoaded('client')),
            'currency' => $this->currency,
            'due_date' => $this->due_date?->toDateString(),
            'notes' => $this->notes,
            'net_total' => $this->net_total,
            'vat_total' => $this->vat_total,
            'gross_total' => $this->gross_total,
            'vat_breakdown' => $this->vat_breakdown,
            'lines' => InvoiceLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}
