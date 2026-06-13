# Production Hardening — Rhine Sailing Conditions

Date: 2026-06-13
Status: Approved

Goal: make **both** deliverables production-ready — the WordPress plugin
(primary product, deployed on the peterhensen.nl Divi site) and the standalone
example dashboards (publicly deployable fallback/alternative).

## A. WordPress plugin

### A1 — Self-healing data (no empty dashboards)
- **Activation fetch:** `rsc_schedule_cron()` performs one `fetch_current_conditions()`
  and `fetch_forecast()` so the dashboard has data the instant the plugin is active.
- **Lazy refresh on render:** `RSC_Fetcher::maybe_refresh()` runs at the top of
  `render_shortcode()`. If current-conditions cache is stale (older than
  `REFRESH_TTL`, 20 min), it refreshes before rendering. Guarded by a transient
  lock (`rsc_refresh_lock`, 30 s) so concurrent requests don't stampede the APIs.
- WP-Cron stays as the steady-state backup. Net: works on fresh install,
  self-heals on low-traffic sites, no server config required.

### A2 — Real gust data
- Add `wind_gusts_10m` to the Open-Meteo `current=` query; use it when present,
  fall back to the 1.5× estimate only if absent.

### A3 — Polished look + sailing assessment
- **New `RSC_Assessment` class** — pure PHP, no WordPress dependency. `evaluate(
  $wind_knots, $current_knots )` returns English source-string keys/labels
  (`status`, `recommendation`, `wind` level/description, `water` level/description).
  Display translates via `__()`. Mirrors the example's `get_sailing_conditions()`.
- `RSC_Display` renders a recommendation "eyecatcher" card first, then
  wind/water/assessment cards and the forecast — matching
  `examples/combined-sailing-conditions.php`.
- Rewrite `public/css/display.css` to the peterhensen.nl palette (navy `#222e65`,
  blue `#326bff`, yellow `#f4d011`, Open Sans body / Montserrat-italic headings),
  all scoped under `.rsc-` so it won't collide with the Divi theme.

### A4 — Housekeeping
- `RSC_Cache::set()` writes options with `autoload = 'no'`.
- User-Agent derives from `RSC_PLUGIN_VERSION` via a helper.

## B. Standalone examples (publicly deployable)

### B1 — Shared library `examples/lib/data.php`
- cURL-based `http_get_json()` / `http_post_json()` (timeout + User-Agent,
  graceful failure on error / non-200).
- File-based cache in `sys_get_temp_dir()`, atomic write, TTL ~15 min current /
  ~30 min forecast. Prevents every page load from hitting the upstream APIs.
- Shared pure helpers: `degrees_to_direction()`, `knots_to_beaufort()`,
  `get_sailing_conditions()`, real-gust extraction.
- Both dashboards become thin render layers over this library.

### B2 — Robustness
- Replace `@file_get_contents` with cURL (works where `allow_url_fopen` is off).
- `date_default_timezone_set('Europe/Amsterdam')` for correct timestamps.
- Per-source error handling so one dead API doesn't blank the whole page.

### Note on duplication
Plugin classes are WordPress-coupled (`wp_remote_*`, options), so the standalone
examples cannot reuse them. The two sides stay parallel and this is documented.
B1 removes the example-to-example duplication, which is the cheap win.

## C. Testing / CI
- `RSC_Fetcher` already uses `wp_remote_get/post`, which the WP test harness can
  intercept via the `pre_http_request` filter. Fetcher tests get fixed fixtures so
  they run **offline**.
- New `RSC_Assessment` test — pure, runnable without WordPress.
- Document the one-time `bin/install-wp-tests.sh` harness step in the README.
  Running the WP-harness suite requires that library installed locally.

## Out of scope (YAGNI)
Admin settings page, multi-location config, historical graphs. Single hardcoded
location stays.
