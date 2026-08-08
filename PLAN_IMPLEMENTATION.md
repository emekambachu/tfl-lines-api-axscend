# PLAN_IMPLEMENTATION.md
---

## 1. Objective

A production-minded web app that fetches data from the free TfL Unified API,
displays it, and lets the user search it. Scope is deliberately small and
focused.

## 2. External API

- Endpoint: `GET https://api.tfl.gov.uk/Line/Route` — **no key required**.
- Returns every line across all modes in one response (~692), so the full
  dataset is fetched once, cached, then browsed/searched and paginated in memory
  (the provider has no pagination of its own).
- Config (via `config/services.php`, read through `config()` not `env()`):
  - `TFL_BASE_URL` (default `https://api.tfl.gov.uk`)
  - `TFL_APP_KEY` — **optional**, only raises the rate limit. Read from `.env`
    (with a placeholder in `.env.example`); never committed.

## 3. Functional Requirements

- **Retrieve:** expose only the fields the app needs (id, name, mode, route
  sections with origin/destination, service types, disruption flag) via a
  Laravel Resource — never the raw provider payload.
- **Browse:** `GET /` paginates the full dataset, 15 lines per page.
- **Search:** `GET /search` — case-insensitive substring match over line id,
  name, mode, and route section origin/destination names (e.g. "Brixton",
  "tube", "victoria"), then paginated 15/page. Runs against the cache, **not**
  TfL's `/Line/Search` endpoint. A blank query redirects to browse.
- **Page states:** heading, labelled search input, browse / loading / search
  results / no matches / error. Responsive and keyboard-friendly.

## 4. Architecture

```text
GET /       → LineController@index  ┐
GET /search → LineController@search ┘→ LineService → TflLineRepository
                                    ↓
                     LineResource (per item) → paginator → Inertia → Vue 3
```

| Layer | Responsibility |
|---|---|
| `TflLineRepository` | The only thing that talks to TfL (HTTP client, timeout, bounded retry, throws on failure) |
| `LineService` | Fetch via repo, cache, then `paginate($page)` (browse) or `search($query, $page)` (filter → paginate); 15/page |
| `LineController` | Thin: `index` paginates, `search` validates + delegates (blank → redirect); maps items via `LineResource`, passes the paginator |
| `LineSearchRequest` | Request validation (`search` nullable string, max length) |
| `LineResource` | Single definition of the client-facing shape |
| Vue components | Presentation only |

**Project structure:**

```text
app/Http/Controllers/LineController.php
app/Http/Requests/LineSearchRequest.php
app/Http/Resources/LineResource.php
app/Repositories/TflLineRepository.php
app/Services/LineService.php
app/Exceptions/LineProviderUnavailableException.php
config/services.php
resources/js/Pages/Lines/Index.vue
resources/js/Components/{LineSearch,LineList,LineCard,Pagination}.vue
routes/web.php
tests/Unit/{TflLineRepositoryTest,LineServiceTest}.php
tests/Feature/LineSearchTest.php
README.md   .env.example
```

## 5. Key Decisions

- **Inertia, no `apiClient.js`.** One app, no separate JSON API, no Axios, no
  CORS. Server is the source of truth; state arrives as props.
- **Tailwind CSS for styling.** Utility-first, no separate component CSS files or
  UI framework; keeps styling colocated in the Vue templates.
- **No database.** Nothing to persist; the app is a stateless read-through.
- **No repository interface.** One provider, one implementation — add a contract
  only when a second provider justifies it.
- **One cache layer.** Provider dataset keyed `tfl:lines:v1`, TTL 1 hour, default
  cache driver (not Redis). No search-result cache — filtering the in-memory
  collection is trivial; caching it adds freshness coupling for no gain.
- **Search runs off the cache, not TfL's `/Line/Search`.** The dataset is already
  cached, so in-memory filtering is instant, offline-testable, and matches the
  same fields the browse cards show. TfL's dedicated search returns a thinner,
  differently-shaped payload — not worth a second integration.
- **In-memory pagination, 15/page, for browse and search.** A
  `LengthAwarePaginator` over the cached array. The controller maps items through
  `LineResource` and passes the paginator itself (not `LineResource::collection`,
  which inertia-laravel would flatten, dropping pagination meta).

## 6. Resilience & Security

- HTTP client with short timeout and bounded retry (retry only transient
  failures — connection/5xx, never 4xx/auth).
- Provider failure (timeout, connection, 4xx/5xx, malformed payload) resolves to
  a generic user message; technical detail logged server-side. Never expose
  stack traces, config, or keys.
- Both inbound routes (`/`, `/search`) throttled (`throttle:60,1`).
- Base URL application-controlled (no user-supplied URL → no SSRF). Search input
  validated. Vue escapes output.

## 7. Testing

- **`Http::fake()` for all provider calls** — the real API is never hit. No DB,
  so no `RefreshDatabase`.
- **Unit — repository:** correct endpoint, app key sent when present, failure →
  controlled exception, malformed payload handled.
- **Unit — service:** `paginate` pages and totals, each search field matches
  case-insensitively, no-match returns an empty paginator, search results
  paginate, cache reused on repeat.
- **Feature:** index shows first page, second page returns the remainder, search
  returns transformed paginated results, no-match is empty, blank search
  redirects to `/`, oversized query fails validation, provider failure handled,
  only expected resource fields exposed.
- TDD where practical; Pint clean before commit.

## 8. Docs

- `README.md`: overview, requirements (PHP version), setup, env vars, run, test,
  key decisions, known limitations, future improvements.
