<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared query parameters for list endpoints.
 */
abstract class PaginatedRequest extends FormRequest
{
    private const int DEFAULT_PER_PAGE = 15;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Capped so one request can't ask the database for every row.
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', self::DEFAULT_PER_PAGE);
    }
}
