<?php

namespace Database\Seeders;

use App\Actions\CreateDraftInvoice;
use App\Actions\IssueInvoice;
use App\Actions\MarkInvoicePaid;
use App\Actions\RegisterOrganisation;
use App\Models\Client;
use App\Models\Organisation;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * A demo organisation for the README quickstart: one user, two clients and
 * one invoice in each state. Every company and person here is made up.
 *
 * Invoices go through the same actions as the API, so numbering, snapshots
 * and PDF jobs behave exactly as they do for real requests.
 */
final class DemoSeeder extends Seeder
{
    public const string EMAIL = 'demo@example.com';

    public const string PASSWORD = 'demo-password';

    public function run(
        RegisterOrganisation $register,
        CreateDraftInvoice $createDraft,
        IssueInvoice $issue,
        MarkInvoicePaid $markPaid,
    ): void {
        // The password is in the README.
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must not run in production.');
        }

        if (User::query()->where('email', self::EMAIL)->exists()) {
            $this->command->info('The demo user already exists. Nothing to do.');

            return;
        }

        $organisation = $register->handle(
            ['name' => 'Демо Студио ЕООД', 'country_code' => 'BG'],
            ['name' => 'Demo User', 'email' => self::EMAIL, 'password' => self::PASSWORD],
        )->organisation;

        // Registration doesn't ask for these, but issuing needs them.
        $organisation->update([
            'registration_number' => '200000001',
            'vat_number' => 'BG200000001',
            'address' => 'ул. „Примерна“ 1, 1000 София',
        ]);

        $bakery = $this->client($organisation, [
            'name' => 'Пекарна Изгрев ООД',
            'registration_number' => '200000002',
            'vat_number' => 'BG200000002',
            'address' => 'бул. „Тестов“ 15, 4000 Пловдив',
            'country_code' => 'BG',
            'email' => 'accounts@bakery.example',
        ]);

        // Bulgarian on purpose: a German business customer would mean reverse
        // charge, which v0.1.0 doesn't model yet.
        $hotel = $this->client($organisation, [
            'name' => 'Хотел Морско Око ЕООД',
            'registration_number' => '200000003',
            'vat_number' => null,
            'address' => 'ул. „Крайбрежна“ 3, 9000 Варна',
            'country_code' => 'BG',
            'email' => 'office@hotel.example',
        ]);

        $standard = $this->rate('20.00');
        $reduced = $this->rate('9.00');

        $paid = $createDraft->handle($organisation, ['client_id' => $bakery->id, 'due_date' => now()->addDays(14)->toDateString()], [
            ['description' => 'Изработка на уебсайт / Website development', 'quantity' => '1', 'unit_price' => 240000, 'vat_rate_id' => $standard],
            ['description' => 'Хостинг, 12 месеца / Hosting, 12 months', 'quantity' => '12', 'unit_price' => 1500, 'vat_rate_id' => $standard],
        ]);
        $markPaid->handle($issue->handle($paid));

        $issued = $createDraft->handle($organisation, ['client_id' => $bakery->id, 'due_date' => now()->addDays(14)->toDateString(), 'notes' => 'Плащане по банков път. / Payment by bank transfer.'], [
            ['description' => 'Поддръжка, часове / Maintenance, hours', 'quantity' => '6.5', 'unit_price' => 4500, 'vat_rate_id' => $standard],
            ['description' => 'Печатно ръководство / Printed handbook', 'quantity' => '3', 'unit_price' => 2990, 'vat_rate_id' => $reduced],
        ]);
        $issue->handle($issued);

        $createDraft->handle($organisation, ['client_id' => $hotel->id], [
            ['description' => 'Система за резервации / Booking system integration', 'quantity' => '1', 'unit_price' => 180000, 'vat_rate_id' => $standard],
        ]);

        $this->command->info('Demo organisation created. Log in as '.self::EMAIL.' with password '.self::PASSWORD.'.');
    }

    /**
     * @param  array{name: string, registration_number: ?string, vat_number: ?string, address: string, country_code: string, email: string}  $attributes
     */
    private function client(Organisation $organisation, array $attributes): Client
    {
        return $organisation->clients()->create($attributes);
    }

    private function rate(string $rate): int
    {
        return VatRate::query()->where('country_code', 'BG')->where('rate', $rate)->firstOrFail()->id;
    }
}
