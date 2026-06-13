# Rhine Sailing Conditions WordPress Plugin

A WordPress plugin that displays real-time sailing conditions for the Rhine River between Driel boven and Arnhem Nederrijn.

## Features

- **Current Conditions:** Real wind speed/direction; water level; current speed; water temperature
- **Forecast:** 6-hour wind + precipitation forecast (mm and probability), both from Open-Meteo in one request
- **Real Data:** Wind/precipitation from Open-Meteo; water measurements from Rijkswaterstaat DDAPI
- **Sailing Assessment:** Integrated wind + water analysis with sailing recommendations
- **Auto-Update:** Data refreshes automatically every 15-30 minutes
- **Resilient:** Gracefully handles API downtime with cached data
- **Responsive:** Works on desktop, tablet, and mobile
- **Dutch Interface:** Full Dutch localization for Dutch sailing community

## Installation

1. Download the plugin folder to `/wp-content/plugins/rhine-sailing-conditions/`
2. Activate the plugin in WordPress admin (Plugins menu)
3. Add the shortcode to any page or post:
   ```
   [rhine-sailing-conditions]
   ```

## Running the example dashboards

The `examples/` folder contains two standalone dashboards that fetch live data
directly — no WordPress needed, just PHP. They're handy for previewing the look
and verifying the APIs.

```bash
cd examples
php -S localhost:8765
```

Then open http://localhost:8765/ — it redirects to the combined dashboard.
The individual pages are:
- http://localhost:8765/combined-sailing-conditions.php — full dashboard (wind + water + 6-hour forecast)
- http://localhost:8765/rws-debug-dashboard.php — raw RWS measurements view

(Opening `localhost:8765` without a path used to 404 — the `index.php` redirect fixes that.)

The UI is Dutch by default. To run in another language, set `RSC_LANG` (a
matching table must exist in `examples/lang/`, e.g. `fy.php`):

```bash
RSC_LANG=fy php -S localhost:8765
```

Requirements: PHP 7.4+ with outbound internet access (the dashboards call
Open-Meteo and the Rijkswaterstaat DDAPI). The cURL extension is recommended;
the dashboards fall back to a stream wrapper if it is unavailable. Press
`Ctrl+C` to stop the server.

**Production-ready:** the dashboards share `examples/lib/data.php`, which fetches
over cURL and caches every response on disk (`sys_get_temp_dir()`, 15 min for
current conditions, 30 min for the forecast). This keeps concurrent visitors
from hammering the upstream APIs, and a failed fetch falls back to the last good
cached value instead of blanking the page — so they can be deployed publicly,
not just run locally.

## Data Sources

- **Weather (Wind):** Open-Meteo API (free, no authentication)
- **Water Data:** Rijkswaterstaat DDAPI (Driel boven location)
  - Water height (Waterhoogte) - meters NAP
  - Current speed (Stroomsnelheid) - knots/m/s
  - Water temperature (Temperatuur) - °C
  - Other available: pH, water clarity, discharge, saturation

## Technical Details

### Architecture
- Self-contained WordPress plugin
- Fetches data from public APIs every 15-30 minutes via WordPress cron
- Caches data in WordPress options table
- Renders as a responsive dashboard widget

### Classes
- `RSC_Fetcher` – Handles API calls to Open-Meteo and Rijkswaterstaat DDAPI
  - `fetch_current_conditions()` – Orchestrates wind + water fetch and caching
  - `fetch_openmeteo_wind()` – Current wind (incl. real measured gusts) from Open-Meteo
  - `fetch_openmeteo_forecast()` – 6-hour wind + precipitation forecast from Open-Meteo (single request)
  - `fetch_rws_measurements()` – Water height (WATHTE), current speed (STROOMSHD)
    and temperature (T) from a single RWS DDAPI request
  - `maybe_refresh()` – On-render self-heal when the cache is stale (see Caching Strategy)
- `RSC_Validator` – Validates API response data and measurement values
  (`validate_wind`, `validate_water_level`, `validate_current_speed`, `validate_temperature`)
- `RSC_Assessment` – Framework-agnostic sailing assessment (`evaluate()`). Pure
  PHP with no WordPress dependency, so the standalone example dashboards reuse
  the **same** thresholds (no drift between plugin and examples).
- `RSC_Cache` – Wraps WordPress options (non-autoloaded) for data storage with timestamps
- `RSC_Display` – Renders the shortcode (Dutch UI): recommendation eyecatcher,
  wind/water/assessment cards, 6-hour forecast, and a stale-data warning

### Rijkswaterstaat DDAPI request format
The `OphalenLaatsteWaarnemingen` endpoint expects PascalCase keys with locations
as objects, e.g.:
```json
{"LocatieLijst":[{"Code":"driel.boven"}],"AquoPlusWaarnemingMetadataLijst":[{"AquoMetadata":{"MessageID":1}}]}
```
Measurement timestamps are returned in the `Tijdstip` field (ISO 8601).

### Caching Strategy
- Current conditions fetched every 15 min, forecasts every 30 min, via WP-Cron.
- **Activation fetch:** data is fetched once on plugin activation so the
  dashboard is never empty on a fresh install.
- **On-render self-heal:** if the cached current conditions are older than
  20 minutes (e.g. WP-Cron didn't run on a low-traffic site), the shortcode
  refreshes them before rendering. A 30-second transient lock prevents
  concurrent page loads from all hitting the APIs at once.
- Stale data is still displayed with an "outdated" warning if older than 60 min.
- Sites that prefer cron-only operation can disable the on-render refresh:
  ```php
  add_filter( 'rsc_enable_lazy_refresh', '__return_false' );
  ```

### Reliable cron in production
WP-Cron only runs when the site receives traffic. The on-render self-heal above
covers low-traffic sites, but for the most reliable scheduling, disable WP-Cron
and drive it from a real system cron:
```php
// wp-config.php
define( 'DISABLE_WP_CRON', true );
```
```cron
# every 15 minutes
*/15 * * * * curl -s https://your-site.example/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

## Running the tests

The plugin ships PHPUnit tests under `tests/`. HTTP is mocked via the
`pre_http_request` filter using the JSON fixtures in `tests/fixtures/`, so the
fetcher tests run **offline and deterministically** (no live API calls).

They use the WordPress PHPUnit test harness, which must be installed once. The
easiest way is via WP-CLI, which generates the installer script and config:

```bash
# Generates phpunit.xml.dist + bin/install-wp-tests.sh in the plugin
wp scaffold plugin-tests rhine-sailing-conditions

# Installs the WP test library + a throwaway test database, then run PHPUnit
bin/install-wp-tests.sh wordpress_test root '' localhost latest
phpunit
```

`RSC_Assessment` is pure PHP and is covered by `tests/test-assessment.php`
without needing WordPress.

## Future Enhancements

- Admin settings page for configuration
- Historical data graphs
- Wind/water level alerts
- Multiple location support
- Chart library integration

## Internationalization (i18n)

All source strings in the code are **English**; the user-facing language is provided by translations.

**Plugin (WordPress / gettext):**
- Source strings are wrapped in `__()` / `esc_html__()` / `_n()` with the text domain `rhine-sailing-conditions`.
- Translations live in `languages/`:
  - `rhine-sailing-conditions.pot` — template for translators
  - `rhine-sailing-conditions-nl_NL.po` / `.mo` — Dutch (shipped default)
  - `rhine-sailing-conditions-fy_NL.po` / `.mo` — West Frisian (example; needs native review)
- The plugin **defaults its UI to Dutch** regardless of the site locale, via a `plugin_locale` filter. To switch language, drop in a matching `.mo` and override:
  ```php
  add_filter( 'rsc_locale', function () { return 'fy_NL'; } );
  ```
- **Add a language:** copy the `.pot` to `rhine-sailing-conditions-<locale>.po`, translate the `msgstr` entries, then compile:
  ```bash
  cp languages/rhine-sailing-conditions.pot languages/rhine-sailing-conditions-<locale>.po
  # ...translate the msgstr lines...
  msgfmt languages/rhine-sailing-conditions-<locale>.po -o languages/rhine-sailing-conditions-<locale>.mo
  ```
- **Update strings (after changing/adding `__()` calls in the code):** regenerate the template, merge it into each existing translation, then recompile:
  ```bash
  # 1. Rebuild the .pot from source (note the _n keyword for plurals)
  xgettext --language=PHP --from-code=UTF-8 \
    --keyword=__ --keyword=esc_html__ --keyword=esc_attr__ --keyword=_n:1,2 \
    -o languages/rhine-sailing-conditions.pot \
    rhine-sailing-conditions.php includes/*.php

  # 2. Merge new/changed strings into each translation (keeps existing ones)
  for po in languages/*.po; do msgmerge --update --backup=none "$po" languages/rhine-sailing-conditions.pot; done

  # 3. Fill in any new/“fuzzy” msgstr, then recompile every .mo
  for po in languages/*.po; do msgfmt "$po" -o "${po%.po}.mo"; done
  ```
  Requires the GNU gettext CLI (`xgettext`, `msgmerge`, `msgfmt`).

**Example dashboards (standalone, non-WordPress):**
- They can't use gettext, so they use a simple translation table in `examples/lang/` (`nl.php`, `fy.php`) via a `t()` helper. Missing keys fall back to the English source string.
- Default is Dutch; switch with an environment variable:
  ```bash
  RSC_LANG=fy php -S localhost:8765
  ```
- **Add a language:** drop a new `examples/lang/<code>.php` returning an `English => translation` array.
- **Update strings:** when you add a new `t('English')` call in a dashboard, add the matching `'English' => '…'` entry to each `examples/lang/<code>.php`. Until you do, that string simply renders in English (the fallback) — no errors.

## Requirements

- WordPress 5.0 or later
- PHP 7.4 or later
- Internet connection for API calls

## License

GPL2

## Support

For issues or questions, contact the sailing club administrator.
