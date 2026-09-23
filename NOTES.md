# NOTES

## How far I got

- [x] Setup: Sanctum token auth (`POST /api/login`), PostgreSQL via Docker, test database
- [x] Schema, enums, models, factories, grid operator seeder
- [x] P1 Foundation: login, meter points, energy communities (create/list/show), adding users
- [x] P2 Registrations: register, list, transition, delete (end)
- [ ] P3 Community lifecycle
- [ ] OPT

## Running it

```bash
cp .env.example .env
composer install
php artisan key:generate
docker compose up -d          # Postgres 17 + a separate test database
php artisan migrate --seed    # admin@example.com / password, test@example.com / password
php artisan test
```

## Decisions and trade-offs

- **Database: PostgreSQL**, because of the exclusion constraint used for BR-7/BR-8 (see below).
- **Tests: PHPUnit**.
- **State machine on the enum.** `EnergyCommunityMeterPointState` holds all of BR-9
  (`allowedTransitions()`, `canTransitionTo()`, `isTerminal()`, `blocking()`, `endState()`),
  so transition, DELETE and reject all use one definition.
- **`EnergyCommunityUserRole` enum** (manager | member) is my addition. The brief names no enum for the role.

- **`users` table:** kept Laravel's `email_verified_at` and `remember_token` columns. `is_admin` was added to
  the original migration because the project has never been deployed. `is_admin` is not mass assignable.
- **403 vs 409 vs 422.** Policies only answer "who may do this" (403). State conflicts ("allowed, but not
  right now", e.g. a rejected community) throw `App\Exceptions\ConflictException` and render as 409.
  Bad input is 422.
- **Authorization before validation** on `POST .../users`: checked in the FormRequest's `authorize()`, because a
  FormRequest validates before the controller runs; an outsider gets 403, not hints about the body.
- **Business logic in action classes** (`app/Actions`), called from controllers. Actions that check state
  lock the row they check (`lockForUpdate`), e.g. adding a user locks the community so a concurrent reject
  can't slip in between.
- **Lock order** is always community -> metering point -> registration. Every action that locks more than one
  row takes them in this order, so two actions can't deadlock by waiting on each other.
- **Soft-deleted metering points** keep their code reserved (the unique index includes them).

### Testing approach

- **Mostly feature tests, few unit tests, on purpose.** Most business rules live at the database/HTTP
  boundary: the exclusion constraint, row locks, query scopes (BR-11), policies and validation. A unit test
  with a mocked database can't prove any of those, so they are tested end to end: real HTTP requests (or
  actions) against a real PostgreSQL test database.
- **Pure logic is unit tested.** The BR-9 state machine lives on the enum and needs no database, so
  `tests/Unit/Enums/EnergyCommunityMeterPointStateTest` checks all 7 x 7 = 49 state pairs against the brief.
- **No seed data in tests.** Each test builds exactly the rows it needs with factories (named states such as
  `inState()`, `period()`, `withManager()`), and `RefreshDatabase` rolls everything back afterwards. The
  seeders (grid operators, an admin and a test user) are only for trying the API by hand.
- **Trade-off:** the suite is slower than pure unit tests and needs Postgres running (`docker compose up -d`).
  I accept that: the database is part of the guarantees, so it has to be part of the tests.
- **The brief's worked example** (section 7) is a data-driven test (`RegisterMeterPointTest`), plus an
  end-to-end version of row 2 through the API (`RegistrationTransitionTest`).

### Ambiguities I decided

| Question | Decision |
| --- | --- |
| Non-member opens a community | 403 |
| Adding users to a rejected community | Not allowed (409) |
| Registering a soft-deleted metering point | Not allowed (422) |
| Ending a registration | Both `DELETE` and `POST .../transition` with `removed`/`deactivated`, one shared action |
| Page size of paginated lists | Fixed at 15 (Laravel's default); no `per_page` parameter, since the brief doesn't ask for one |
| `GET /api/meter-points` pagination | Paginated too (15), although the brief only says so for communities: an admin sees all metering points, so an unpaginated list would grow without bound |
| Invalid filter value (e.g. `?state=foo`, `?energy_direction=foo`) | 422, validated against the enum; silently ignoring it would hide client bugs |
| Adding a user who is already in the community (BR-4) | 422 on `user_id` ("already exists"); the role is not changed. The unique index on (`energy_community_id`, `user_id`) catches a concurrent duplicate, which is mapped to the same 422 |
| BR-6: owner of the metering point is not a member | 422 on `meter_point_id` (the input refers to a metering point that can't be used here) |
| What "today" means (consent not in the future, closing a period) | The app timezone, UTC. In production this should be Europe/Vienna, so a consent given just after midnight local time isn't rejected |
| `status_code` after leaving `error` (e.g. retry `error` -> `requested`) | Cleared (null). The column describes the *current* error only, so an accepted registration never carries an old error code. A full error history would need a transitions/audit table (see next steps) |
| Response of `DELETE /api/registrations/{id}` | 204 No Content, following generic HTTP semantics for DELETE, even though the row is kept and only transitioned (BR-10) |
| BR-12: must the accepted generation registration be valid today? | _Open, to decide_ |

## BR-8: race safety

### The problem

BR-7 is "check, then write": look for an overlapping blocking registration, and insert if there is none.
Two requests arriving at the same moment can both pass the check before either one inserts:

| Time | Request 1 | Request 2 |
| --- | --- | --- |
| t1 | SELECT: no overlap | |
| t2 | | SELECT: no overlap (request 1 hasn't inserted yet) |
| t3 | INSERT | |
| t4 | | INSERT (now two overlapping registrations) |

Both requests follow the rules. The gap is between the check and the write.

### My approach: an application check plus a database constraint

**1. The application check (for a clear error message).** Inside a transaction, the metering point row is
locked with `lockForUpdate()`. That makes concurrent registrations for the same metering point wait for
each other. Then the app checks for overlaps (`blocking()->overlapping()` scopes) and inserts. A conflict
returns 409 with a readable message.

On its own, this is only safe if every code path that can create an overlap remembers to take the lock.
That includes registering, the retry transition `error` -> `requested` (it turns a non-blocking row back into a blocking
one), and any future import, admin tool or tinker session.

**2. The database constraint (for the guarantee).** An exclusion constraint on `energy_community_meter_point`:

```sql
EXCLUDE USING gist (
  meter_point_id WITH =,
  daterange(from_date, to_date, '[]') WITH &&
) WHERE (state IN ('new', 'requested', 'message_received', 'accepted'))
```

This means: no two blocking registrations of the same metering point may have overlapping periods, across all
communities. `[]` makes both ends inclusive, and a NULL `to_date` is an unbounded range, which matches "open ended".
It needs the `btree_gist` extension.

If request 2 reaches the constraint anyway, the app catches SQLSTATE `23P01` and returns the same 409.

| | Application check (with lock) | Database constraint |
| --- | --- | --- |
| Clear 409 message | yes | no (raw database error, mapped by us) |
| Safe under concurrency | only if every path locks | always |
| Covers code written later | no | yes |

### The guarantee this gives on PostgreSQL

The database never stores two overlapping blocking registrations for one metering point, whatever the code
path or timing. This holds at the default READ COMMITTED isolation level. Postgres checks the constraint against
rows that other transactions are still inserting, and the second one waits for the first to commit, then fails
with `23P01`. No SERIALIZABLE isolation or retry loop is needed.

The state list is hard-coded in the migration (not read from the enum), so the migration always builds
the same schema. It mirrors `EnergyCommunityMeterPointState::blocking()`.

On MySQL/MariaDB there are no exclusion constraints. There, the `lockForUpdate()` on the metering point row would
be the only guarantee, and every write path would have to take it.

### Transitions (BR-9)

Every state change goes through one action, `App\Actions\TransitionRegistration`, used by
`POST /registrations/{id}/transition`, by `DELETE /registrations/{id}`.
Inside one transaction it:

1. locks the metering point row, then the registration row (`lockForUpdate`, following the lock order),
2. re-reads the registration's state *after* taking the lock,
3. checks `canTransitionTo()` against that fresh state, 409 if not allowed,
4. for `error` -> `requested` (the retry), re-runs the BR-7 overlap check, because the registration becomes
   blocking again (the exclusion constraint backs this up too),
5. writes the new state (and `to_date` for terminal states).

Two concurrent transitions out of the same state: the second request waits on the row lock, then reads the
state the first one wrote, and "X -> X" is not an allowed transition, so it gets 409. Both can never succeed.

I chose a pessimistic row lock over an optimistic compare-and-set (`UPDATE ... WHERE state = <expected>`):
both stop double transitions, but the retry transition needs the overlap check inside the same lock anyway,
and one pattern everywhere is easier to reason about.

### What is guaranteed, and what is not

On PostgreSQL at the default READ COMMITTED isolation:

- **Overlaps (BR-7): guaranteed by the database.** The exclusion constraint makes two overlapping blocking
  registrations impossible, whatever the code path or timing. The lock exists to give a clean 409 instead of a raw error.
- **Transitions (BR-9): guaranteed as long as every state change goes through `TransitionRegistration`.**
  The guarantee comes from the lock inside it; a direct `$registration->update(['state' => ...])` elsewhere
  would bypass it. That's why transition, DELETE and reject all use this one action.

### Testing the race

For now I cannot truly test parallel requests: the test suite runs on a single database connection, one
request after another (and `RefreshDatabase` wraps each test in one transaction). The tests prove the rules,
and that the constraint rejects overlapping rows inserted directly, but not two requests racing each other.
The concurrent behaviour can be seen on a running instance (deployed, or locally with several PHP workers) by
sending two identical requests at the same moment: one succeeds, the other gets 409. A proper automated
concurrency test would need two separate database connections or processes; see next steps.

## `meter_points.grid_operator_id`

Holds `grid_operators.identifier`, not `grid_operators.id`. It's kept as in production and modelled as
`belongsTo(GridOperator::class, 'grid_operator_id', 'identifier')`.

What I would change: a real foreign key. Steps, each deployable on its own:
1. Add a nullable `grid_operator_fk` bigint FK column.
2. Backfill it with one `UPDATE ... FROM grid_operators` and write both columns from then on.
3. Switch reads to the new column, then make it NOT NULL.
4. Drop the old string column once nothing uses it.

## Deviations from the brief

- _None so far._

## What's missing / next steps

- **No planned end date for an open registration.** An open-ended registration blocks its metering point from
  `from_date` onwards, in every community. The only way to end it is a terminal transition (`deactivated` /
  `removed`), which sets `to_date` to *today*. So a planned switch ("leave community A on 2026-06-30, join C on
  2026-07-01") can't be booked in advance; the manager has to end the registration on the day itself, or accept a gap.
  Possible fix: an endpoint that lets a manager set a future `to_date` on a blocking registration, and only
  ever *shorten* the period (new `to_date` >= `from_date` and, if one is set, <= the current `to_date`). Shortening can
  never create an overlap, so it needs no BR-7 check; `from_date` stays immutable as the brief requires.
- **Automated concurrency test** for BR-8: two separate database connections (or processes) racing the same
  registration / transition, asserting exactly one success and one 409.
- **Transition history.** Only the current state and the current error code are stored; an audit table of
  transitions (who, when, from, to, status_code) would give settlement and support a full history.
- _Rest to be filled at the end._
