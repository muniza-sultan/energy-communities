# Energy Communities API

A small JSON API for the enixi Laravel coding challenge: energy communities, metering points and the
registrations that connect them, plus the users who own and administer them.

**Decisions, trade-offs, the BR-8 race-safety answer and what's missing are in [NOTES.md](NOTES.md).**

## Stack

- Laravel 12, PHP 8.2+
- PostgreSQL 17 (Docker), because an exclusion constraint enforces BR-7 at database level
- Laravel Sanctum (personal access tokens)
- PHPUnit

## Setup

Requirements: PHP 8.2+ with `pdo_pgsql` enabled, Composer, Docker.

```bash
cp .env.example .env
composer install
php artisan key:generate
docker compose up -d          # Postgres + a separate test database
php artisan migrate --seed
php artisan serve
```

The seeder creates five grid operators and two users (password `password`):

| Email | Role |
| --- | --- |
| `admin@example.com` | platform admin (`is_admin`) |
| `test@example.com` | normal user |

## Tests

```bash
php artisan test
```

Tests run against the `energy_communities_test` database (see `phpunit.xml`), so Postgres must be running.
Business rules that live in the database (constraint, locks, scopes) are covered by feature tests against real
Postgres; the registration state machine is unit tested. Details in NOTES.md.

## Authentication

```bash
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

Send the returned token as `Authorization: Bearer <token>` on every other request.

## Endpoints

| Method | Path | What it does | Rules |
| --- | --- | --- | --- |
| POST | `/api/login` | e-mail + password, returns a token | |
| GET | `/api/me` | the authenticated user | |
| POST | `/api/meter-points` | register a metering point of your own | BR-1, BR-2 |
| GET | `/api/meter-points` | own metering points (admins: all); `?energy_direction=` | BR-2 |
| POST | `/api/energy-communities` | create; creator becomes manager, state `new` | BR-3 |
| GET | `/api/energy-communities` | communities you belong to (admins: all); `?state=`; paginated | BR-11 |
| GET | `/api/energy-communities/{energyCommunity}` | details and members; members and admins only | BR-11 |
| POST | `/api/energy-communities/{energyCommunity}/users` | a manager adds a user with a role | BR-4 |
| POST | `/api/energy-communities/{energyCommunity}/meter-points` | register a metering point into the community | BR-5 to BR-8 |
| GET | `/api/energy-communities/{energyCommunity}/meter-points` | the community's registrations; `?state=` | BR-11 |
| POST | `/api/registrations/{registration}/transition` | apply a state-machine transition | BR-8, BR-9 |
| DELETE | `/api/registrations/{registration}` | end the registration (never a hard delete) | BR-10 |
| POST | `/api/energy-communities/{energyCommunity}/activate` | `new` -> `activated`; needs an accepted generation registration valid today | BR-12 |
| POST | `/api/energy-communities/{energyCommunity}/reject` | `new`/`activated` -> `rejected`, ending every blocking registration, atomically | BR-13 |

Request bodies use the field names from the brief, e.g.:

```json
POST /api/energy-communities/{energyCommunity}/meter-points
{ "meter_point_id": 42, "from_date": "2026-04-01", "to_date": null, "consent_date": "2026-03-20" }

POST /api/registrations/{registration}/transition
{ "state": "error", "status_code": 4711 }
```

Status codes: `403` = not allowed for you, `409` = allowed but conflicts with the current state
(e.g. overlapping registration, forbidden transition), `422` = invalid input.

## Where things live

| Folder | Contents |
| --- | --- |
| `app/Enums` | backed enums from the brief; the BR-9 state machine is on `EnergyCommunityMeterPointState` |
| `app/Actions` | business logic (transactions, locks, rule checks) called by thin controllers |
| `app/Policies` | who may do what (403) |
| `app/Rules` | BR-1 validation rules |
| `app/Http` | controllers, form requests, JSON resources |
| `database/migrations` | schema from the brief, plus the BR-7 exclusion constraint |
