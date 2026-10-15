## What and why

<!-- What this changes, and the problem it solves. Link the issue. -->

## How it was tested

<!-- New or changed tests, and anything checked by hand. -->

## Checklist

- [ ] `composer test`, `composer analyse` and `composer lint` pass
- [ ] `openapi.json` is regenerated if the API changed (`php artisan scramble:export`)
- [ ] New migrations keep the rules in the database (constraints) and not only in the code
