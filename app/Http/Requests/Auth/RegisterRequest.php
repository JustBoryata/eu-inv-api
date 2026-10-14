<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organisation.name' => ['required', 'string', 'max:255'],
            'organisation.country_code' => ['required', 'string', Rule::in(config()->array('invoicing.supported_countries'))],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array{name: string, country_code: string}
     */
    public function organisationData(): array
    {
        return [
            'name' => $this->string('organisation.name')->value(),
            'country_code' => $this->string('organisation.country_code')->value(),
        ];
    }

    /**
     * @return array{name: string, email: string, password: string}
     */
    public function ownerData(): array
    {
        return [
            'name' => $this->string('name')->value(),
            'email' => $this->string('email')->value(),
            'password' => $this->string('password')->value(),
        ];
    }

    protected function prepareForValidation(): void
    {
        // Postgres compares strings case-sensitively, so without this
        // "Ana@x.bg" and "ana@x.bg" could register as two different users.
        $this->merge(['email' => mb_strtolower(trim($this->string('email')->value()))]);
    }
}
