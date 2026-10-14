# eu-inv-api: spec (v0.1.0)

Status: approved 2026-10-14.

## Problem

Small EU businesses, and the agencies that build software for them, need to issue invoices that are legally sound. Each business needs its own gap-free invoice numbering. Buyer details and VAT rates must be frozen at the moment of issue. Each invoice needs a VAT breakdown per rate and a PDF the customer can download.

Most "invoice app" demos skip the hard parts: concurrent numbering, immutability after issue, and tenant isolation. This project is a small multi-tenant REST API built around exactly those parts.

## v1 scope

**Tenancy and auth**
- `POST /api/auth/register` creates an organisation (the tenant) and its first user, and returns a Sanctum token.
- `POST /api/auth/login` and `POST /api/auth/logout`.
- Every other route requires `auth:sanctum`. Every query is scoped to the user's organisation. A record from another tenant returns **404, not 403**, so the API doesn't reveal that the record exists.

**Clients**
- CRUD at `/api/clients`, with pagination and search by name.
- Fields: name, VAT number (optional), registration number (EIK), address, country (ISO 3166-1 alpha-2) and email.

**Invoices**
- `/api/invoices`: list (paginated, filter by status/client/date), create a draft with lines, show, update (drafts only), and delete (drafts only).
- `POST /api/invoices/{id}/issue` takes a draft to `issued`:
  - assigns the next number for the organisation inside a DB transaction with `SELECT … FOR UPDATE` on that organisation's sequence row
  - snapshots the buyer's details and each line's VAT rate onto the invoice
  - sets `issued_at`
  - dispatches the PDF job
- `POST /api/invoices/{id}/mark-paid` takes an invoice from `issued` to `paid`.
- `GET /api/invoices/{id}/pdf` streams the PDF, or returns `202 Accepted` while it's still rendering.
- Once issued, an invoice can't be changed: updating or deleting it returns `409 Conflict`.

**VAT**
- `GET /api/vat-rates` lists the rates for the organisation's country. The seed data is BG: 20%, 9% and 0%.
- Line VAT rates are checked against that table.
- Totals are computed per rate group: net amounts are summed per rate, and VAT is rounded once per group. This produces the per-rate breakdown that invoices have to show.

**Async**
- `RenderInvoicePdf` is a queued job on Redis that renders a Blade view to PDF with dompdf. It stores the file on the `local` disk and records `pdf_path`. Its retries are idempotent.

**Docs and quality**
- OpenAPI 3.1 is generated from the code with Scramble. It's served at `/docs/api`, and the exported `openapi.json` is committed. P5's typed Angular client will be generated from this file.
- Pest tests run against **PostgreSQL**, not SQLite, because locking and constraints behave differently on SQLite.
- Larastan at level 8 or higher, Pint, and GitHub Actions with a matrix of PHP 8.4 and 8.5 using Postgres and Redis service containers.
- Docker Compose for local development: app, queue worker, postgres and redis.
- README (with a screenshot of the API docs and a sample PDF), MIT license, `SECURITY.md`, Dependabot, issue and PR templates, `.editorconfig`, and a tagged release `v0.1.0`.

## Out of scope for v1 (each one becomes a GitHub issue)

- Credit notes and cancelling issued invoices. Under Bulgarian and EU rules, you correct an issued invoice with a credit note, not by editing it.
- Emailing invoices.
- Multiple users per organisation, and roles.
- Multiple currencies. v1 uses EUR, which Bulgaria has used since 2026-01-01. The `currency` column still exists so the schema doesn't lock this in.
- VIES and VAT-number validation. That's P3's job, and P1 adopts the package once it exists.
- Live demo deployment and production images. That's P4.
- Horizon, rate-limit tuning, webhooks.

## Architecture

```
HTTP ─► routes/api.php ─► FormRequest (validation)
                       ─► Controller (thin)
                       ─► Action (IssueInvoice, CreateDraftInvoice, …)  ◄─ business rules live here
                       ─► Eloquent models + BelongsToOrganisation global scope
                       ─► JsonResource (response shape)
IssueInvoice ─► DB::transaction { lock sequence row → next number → snapshot → save }
             ─► afterCommit: dispatch RenderInvoicePdf ─► Redis queue ─► worker ─► storage
```

**Key decisions (and why)**

| Decision | Why | Alternative rejected |
| -------- | --- | -------------------- |
| One database with an `organisation_id` column and a global scope | Simplest tenancy that's still correct at this size. It's easy to explain and test. | Schema per tenant or `stancl/tenancy`: too much machinery for v1. |
| Number assigned at **issue**, not at draft creation | Deleted drafts never burn a number, so the sequence stays gap-free. | Numbering drafts creates gaps on delete. |
| Per-organisation `invoice_sequences` row locked with `FOR UPDATE` | Two concurrent issue calls serialise on that row instead of reading the same `MAX(number)`. | A Postgres `SEQUENCE` isn't transactional: a rollback leaves a gap. `MAX()+1` races. |
| 10-digit zero-padded number, plus a unique index on `(organisation_id, number)` | Matches the Bulgarian VAT Act's numbering format. The index is the last line of defence. | — |
| Buyer details and VAT rates **copied** onto the invoice at issue | Editing a client or a rate later must not rewrite a legal document. | Joining live client data at render time. |
| Money as integer cents (`bigint`). Quantities as `numeric(12,3)`, multiplied with `BcMath\Number` | No float rounding errors. Rounding is explicit, half-up, and happens once per VAT group. | Floats; or `brick/money`, which is fine but adds a dependency I'd have to defend. |
| Dispatch the PDF job `afterCommit` | The worker never sees an invoice the transaction then rolled back. | Dispatching inside the transaction. |
| Action classes between controllers and models | One place for business rules, and directly unit-testable. | Fat controllers or fat models. |
| Pest on Postgres, also in CI | Tests the real locking and constraint behaviour. | SQLite in-memory: faster, but hides exactly the bugs this project is about. |

**Tables:** `organisations`, `users`, `personal_access_tokens`, `clients`, `invoices`, `invoice_lines`, `invoice_sequences`, `vat_rates`, `jobs`/`failed_jobs` (failed jobs only; the queue itself runs on Redis).

## How it proves the CV

| CV claim | Evidence in this repo |
| -------- | --------------------- |
| PHP 8+, Laravel | Laravel 13 on PHP 8.4/8.5: actions, policies, form requests, API resources, enums, global scopes |
| PostgreSQL | Row locking, unique and check constraints, `numeric` and `bigint` money, tests against real Postgres |
| Redis | Queue driver and cache store, a worker in Compose and CI |
| API design | Consistent JSON resources, correct status codes (201/202/404/409/422), pagination, OpenAPI 3.1 |
| Testing and code quality | Pest feature and unit tests (including a concurrent-issue test), Larastan level 8+, Pint, CI badge |
| Docker, CI/CD | Compose dev stack, GitHub Actions matrix with service containers |
| EU compliance | Gap-free numbering, immutability after issue, per-rate VAT breakdown, buyer snapshot |

## Milestones (each one pauses for review)

1. **Week 1: scaffold and auth.** Laravel 13, Compose stack, Sanctum register/login/logout, the organisation model and tenant scope, Pest on Postgres, Pint, Larastan, a first CI run.
2. **Week 2: domain.** Clients CRUD, VAT rates, draft invoices with lines and totals, the issue flow with locking, immutability rules. Tests, including the concurrency test.
3. **Week 3: async and release.** PDF job, mark-paid, Scramble docs and `openapi.json`, README with screenshots, repo hygiene files, `v0.1.0`. Then create the public repo (after you approve the exact command) and add it to the profile.

## Decisions on the open questions

- Repo name: `eu-inv-api`.
- Demo data: yes. `php artisan db:seed --class=DemoSeeder` creates a demo organisation and user so the README quickstart works.
