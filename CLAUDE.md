# CLAUDE.md

Guidance for Claude Code when working in this repository.

## Project

Searchable TfL line/route viewer. Laravel + **Inertia** + Vue 3 (one app, no
separate JSON API, no client-side router, no Axios client). **No database** —
the app is a stateless pass-through to the free TfL Unified API
(`GET https://api.tfl.gov.uk/Line/Route`, no key required). The full line
dataset (~692 lines, all modes) is fetched once, cached for one hour, and
searched in memory. No auth, no sessions, no persistence. See
`PLAN_IMPLEMENTATION.md` for the full design and trade-offs.

## Coding Principles

Write code that is **simple, straightforward, and easy to understand** — never
over-engineered — while remaining efficient, standard, and fully solving the
stated problem.

- Prefer the simplest correct solution for the current requirement. Don't build
  for hypothetical future needs.
- Do not add abstractions, layers, or patterns (extra services, interfaces,
  events, observers) without a present, concrete need. The one repository that
  exists is justified by the external-API boundary — do not add a repository
  interface until a second provider exists.
- Follow the patterns already established in the area you are touching before
  introducing new ones.
- Favour clear, readable code over cleverness; use descriptive names and early
  returns.

## Architecture

**Two routes, one page** (`routes/web.php`, both `throttle:60,1`):
`GET /` → `LineController@index` (paginated browse of all lines), `GET /search`
→ `LineController@search` (filter the cache, paginate matches). Both render the
one `Lines/Index` Inertia page.

**Request flow:** Route → Form Request (validation) → thin Controller →
`LineService` → `TflLineRepository`, returning an `Inertia::render(...)`
response shaped through `LineResource`. Business logic lives in the service,
never in controllers or views.

- **Repository** (`app/Repositories/TflLineRepository.php`): the **only** thing
  that talks to TfL. Builds the request (base URL + optional app key from
  config), uses Laravel's HTTP client with a short timeout and bounded retry,
  validates the response, and throws `LineProviderUnavailableException` when the
  provider fails. No search logic, no framework/HTTP-response concerns.
- **Service** (`app/Services/LineService.php`): orchestration — fetches the
  dataset through the repository, caches it (see Caching), then returns a
  `LengthAwarePaginator`: `paginate($page)` over all lines (browse) or
  `search($query, $page)` (normalise query → filter collection → paginate).
  `PER_PAGE = 15`. Knows nothing about Inertia, Vue, or HTTP responses.
- **Controller** (`app/Http/Controllers/LineController.php`): thin — `index`
  paginates; `search` validates via Form Request (blank query → redirect to
  `lines.index`) and delegates. Each maps the paginator's items through
  `LineResource` (`->through(fn ($line) => (new LineResource($line))->resolve())`)
  and passes **the paginator itself** to `Inertia::render`. Do **not** pass
  `LineResource::collection($paginator)` — inertia-laravel resolves `Arrayable`
  before `Responsable`, so the collection would flatten and drop pagination meta.
- **Form Request** (`app/Http/Requests/LineSearchRequest.php`): all request
  validation (`search` nullable string, max length). No inline validation in
  controllers.
- **Resource** (`app/Http/Resources/LineResource.php`): the single place the
  client-facing shape is defined. Maps raw TfL fields (`modeName`,
  `routeSections[].originationName/destinationName`, `serviceTypes`,
  `disruptions`) to the app contract. The full third-party payload is never
  exposed.
- **Config** (`config/services.php`): `'tfl' => ['base_url', 'app_key']`, read
  via `config()` — never `env()` outside config files. Base URL is
  application-controlled (no user-supplied URL) to avoid SSRF.

## Search

- Case-insensitive substring match against: line `id`, `name`, `mode`, and each
  route section's `origin`/`destination` name.
- Operates entirely against the cached in-memory collection — no full-text
  engine, no fuzzy search, **no call to TfL's `/Line/Search` endpoint** (its
  payload is thinner and differently shaped; not worth a second integration).
- Deterministic; results are paginated (15/page), not capped.

## Caching

- **One** cache layer: the provider dataset, keyed `tfl:lines:v1`, TTL 1 hour,
  via `Cache::remember`. Laravel's default cache driver (`database`/`file`) —
  **not Redis**.
- **No search-result cache** — filtering a few hundred in-memory rows is
  microsecond work; caching it would add freshness coupling for no benefit. This
  omission is deliberate; do not add it.

## Frontend (Inertia + Vue 3)

- **Pages** in `resources/js/Pages/Lines/`; state arrives as props from the
  server, never fetched client-side. Inertia is the only Laravel↔Vue bridge —
  no `apiClient.js`, no Axios, no CORS. Plain JS + `<script setup>` (no
  TypeScript — the resource + feature tests are the shape contract).
- **Props** are `{ mode: 'browse'|'search', search, lines, error }`, where
  `lines` is the flat Laravel paginator payload (`data`, `current_page`,
  `last_page`, `total`, `prev_page_url`, `next_page_url`).
- **Search box** issues a debounced (~350ms) Inertia GET to `/` (empty) or
  `/search` (with `search`), all with `{ preserveState, preserveScroll,
  replace }`. The **pager** (`Pagination.vue`) uses Inertia `<Link>`s with
  `preserve-state`/`preserve-scroll`.
- **No client-side state store** — Inertia props are the source of truth.
- **Components** (`LineSearch`, `LineList`, `LineCard`, `Pagination`) stay
  presentational; no business logic in templates. Cap route sections per card.
- Handle the states explicitly: browse, search results, no matches, error.

## Error Handling

- The service/repository fail gracefully: TfL timeout, connection failure, 4xx/5xx,
  malformed payload all resolve to a generic user-facing message
  (`Line data is temporarily unavailable. Please try again shortly.`).
- Never expose stack traces, config, keys, or raw exception detail to the client.
  Log technical detail server-side.

## Development Commands

### Frontend
```bash
npm run dev      # Vite dev server (HMR)
npm run build    # Production build
```

### Backend
```bash
php artisan serve            # Local dev server
./vendor/bin/pint            # Format PHP (laravel preset)
./vendor/bin/pint --test     # Check formatting (CI-safe)
```

### Tests
```bash
php artisan test                              # All tests
php artisan test --filter=LineServiceTest     # Single class
php artisan test tests/Feature/LineSearchTest.php
```

## Testing Conventions

- **TDD:** write the test first, watch it fail, then implement.
- The **real TfL API is never called** in tests. Use `Http::fake()` for all
  provider interactions so tests are deterministic, fast, and offline.
- **Unit tests** cover `TflLineRepository` (correct endpoint, app key sent when
  present, failure → controlled exception, malformed payload) and `LineService`
  (`paginate` pages/totals, each search field matches, no-match returns empty
  paginator, search results paginate, cache reused on repeat).
- **Feature tests** cover both routes: index shows first page, second page
  returns the remainder, search returns transformed paginated results, no-match
  is empty, blank search redirects to `/`, oversized query fails validation,
  provider failure is handled, only expected resource fields are exposed.
- No database, so no `RefreshDatabase` — there is nothing to migrate.

## Conventions

- Format PHP with **Pint** before committing; keep it clean in CI.
- Modern PHP: type declarations, return types, constructor injection, `final`
  classes where appropriate. Descriptive names (`$lines`, `$routeSections`) —
  never `$data`, `$item`, `$thing`.
- Both inbound routes (`/`, `/search`) are throttled (`throttle:60,1`) to
  protect app resources and indirectly the provider.

## Skills

Invoke the relevant skill **before** writing code or a plan.

| Trigger | Skill |
|---------|-------|
| PHP / Laravel controllers, services, repositories | `php-pro`, `laravel-specialist` |
| Routes, HTTP client, resources, testing | `laravel-specialist` |
| `*.vue` files, component work | `vue-expert` |
| Inertia pages, props, partial reloads | `laravel-specialist` + `vue-expert` |
| Writing or reviewing tests | `test-driven-development` |
| New feature / multi-file change | `brainstorming` then `writing-plans` |
| Code / PR review | `code-reviewer` |
