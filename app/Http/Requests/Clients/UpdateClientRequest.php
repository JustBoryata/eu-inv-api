<?php

namespace App\Http\Requests\Clients;

/**
 * Same rules as creating, but every field is optional so PATCH can send
 * only what changed.
 */
class UpdateClientRequest extends StoreClientRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return array_map(fn (array $rules): array => ['sometimes', ...$rules], parent::rules());
    }
}
