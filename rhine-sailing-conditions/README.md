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
  - `fetch_openmeteo_wind()` – Current wind from Open-Meteo
  - `fetch_openmeteo_forecast()` – 6-hour wind + precipitation forecast from Open-Meteo (single request)
  - `fetch_rws_measurements()` – Water height (WATHTE), current speed (STROOMSHD)
    and temperature (T) from a single RWS DDAPI request
- `RSC_Validator` – Validates API response data and measurement values
  (`validate_wind`, `validate_water_level`, `validate_current_speed`, `validate_temperature`)
- `RSC_Cache` – Wraps WordPress options for data storage with timestamps
- `RSC_Display` – Renders the shortcode (Dutch UI) with a stale-data warning

### Caching Strategy
- Current conditions: Cached for 30 minutes (fetched every 15 min)
- Forecasts: Cached for 60 minutes (fetched every 30 min)
- Stale data displayed with "outdated" warning if >60 min old

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
