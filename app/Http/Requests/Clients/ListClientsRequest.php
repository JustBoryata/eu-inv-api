<?php

namespace App\Http\Requests\Clients;

use App\Http\Requests\PaginatedRequest;

class ListClientsRequest extends PaginatedRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function search(): ?string
    {
        $search = trim($this->string('search')->value());

        return $search === '' ? null : $search;
    }
}
