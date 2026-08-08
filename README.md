# TfL Lines

A small, searchable viewer for Transport for London (TfL) lines and routes.
Laravel + Inertia + Vue 3, backed by the free **TfL Unified API**. No database,
no auth, no client-side API. The app is a stateless read-through: it fetches the
full line dataset once, caches it for an hour, and searches it in memory.

## Requirements

- PHP **8.3+**
- Composer 2
- Node 20+ / npm

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

### Environment variables

| Variable | Required | Default | Notes |
|----------|----------|---------|-------|
| `TFL_BASE_URL` | no | `https://api.tfl.gov.uk` | Provider base URL (application-controlled; no user input → no SSRF). |
| `TFL_APP_KEY` | no | _(empty)_ | Optional TfL API key. Not needed to run; it only raises the rate limit. Never commit it. |

The endpoint used (`GET /Line/Route`) requires **no key**.

## Run

```bash
php artisan serve   # backend  → http://localhost:8000
npm run dev         # frontend (Vite HMR)
```

`npm run build` produces the production assets.

## Test

```bash
php artisan test          # full suite (unit + feature)
./vendor/bin/pint         # format PHP (run before committing)
./vendor/bin/pint --test  # CI-safe format check
```

The real TfL API is **never** called in tests. All provider interactions use
`Http::fake()`, so the suite is deterministic and offline. No database, so no
migrations to run.

## How it works

```
GET /        → LineController@index  ┐
GET /search  → LineController@search ┘→ LineService → TflLineRepository
                                  ↓
                            LineResource → Inertia → Vue 3
```

Both routes read the **same cached `/Line/Route` dataset**: `index` paginates
all lines; `search` filters that cache in memory, then paginates the matches.
Both render the one `Lines/Index` page (empty search box → browse, typing →
search).

- **`TflLineRepository`**: the only class that talks to TfL. Short timeout,
  bounded retry on transient failures (connection / 5xx, never 4xx), throws
  `LineProviderUnavailableException` on any failure.
- **`LineService`**: fetches via the repository, caches the dataset
  (`tfl:lines:v1`, TTL 1 hour). `paginate()` returns a page of all lines;
  `search()` filters the in-memory collection with a case-insensitive substring
  match over line id, name, mode, and each route section's origin/destination,
  then paginates. **15 per page** (`LineService::PER_PAGE`).
- **`LineController`**: thin. `index` paginates, `search` validates via
  `LineSearchRequest` (blank query → redirect to browse) and delegates. Each maps
  the paginator's items through `LineResource` and passes the paginator to
  Inertia (so the page gets `data` + pagination meta). Provider failure resolves
  to a generic on-page message; technical detail is logged.
- **`LineResource`**: the single definition of the client-facing shape. The raw
  third-party payload is never exposed.
- **Vue**: presentational only; state arrives as Inertia props. The search box
  issues a debounced Inertia GET to `/` or `/search` (`preserveState`,
  `preserveScroll`, `replace`); the pager uses Inertia `<Link>`s. States handled
  explicitly: browse, search results, no matches, error.

## Key decisions

- **Inertia, no separate JSON API / Axios / CORS.** The server is the source of
  truth; state arrives as props.
- **No database.** Nothing to persist.
- **One cache layer** (the provider dataset, default cache driver, not Redis).
  No search-result cache: filtering a few hundred in-memory rows is trivial, and
  caching it would add freshness coupling for no gain.
- **Search runs off the cache, not TfL's `/Line/Search` endpoint.** The full
  dataset is already cached, so filtering it in memory is instant, offline-
  testable, and matches the same fields the browse cards display. TfL's dedicated
  search returns a thinner, differently-shaped payload, not worth a second
  integration here.
- **Pagination is in-memory** (`LengthAwarePaginator` over the cached array),
  15 per page, for both browse and search.
- **No repository interface.** One provider, one implementation. A contract is
  added only when a second provider justifies it.
- **Plain JS Vue, no TypeScript.** The prop shapes are small, and the client-
  facing contract is pinned server-side by `LineResource` plus the feature tests.
  TS is a mechanical, incremental add-on if the frontend grows.
- Inbound routes are throttled (`throttle:60,1`).

## Known limitations

- Search is substring-only (no fuzzy/typo tolerance, no ranking).
- The whole dataset is refreshed atomically once per hour; new lines appear only
  after the cache expires.
- Pagination is offset-based over an in-memory array; fine for ~700 rows, not a
  pattern for large datasets.

## Possible future improvements

- Per-line detail view (stops, live disruptions).
- Fuzzy search / result ranking.
- Filter chips by mode (tube, bus, DLR, …).
