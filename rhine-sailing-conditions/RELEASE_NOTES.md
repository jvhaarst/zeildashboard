# Rhine Sailing Conditions Plugin - v1.4 Release

## Release Information
- **Version**: 1.4.0
- **Initial Release**: June 2, 2026
- **Latest Update**: June 13, 2026 (Production hardening — plugin + standalone dashboards)
- **Status**: Production Ready with Real Data

## v1.4.0 Changes
- **Fixed a production-breaking RWS DDAPI change:** the `OphalenLaatsteWaarnemingen`
  endpoint now requires PascalCase keys with locations as objects
  (`{"LocatieLijst":[{"Code":"driel.boven"}], ...}`); the old lowercase/string-list
  payload returned HTTP 400 and silently broke all water data. Timestamps moved
  from `Tijd.waarde` to `Tijdstip`. Fixed in the plugin and both example dashboards.
- **Real measured wind gusts** (`wind_gusts_10m`) instead of a 1.5× estimate.
- **No more empty dashboards:** data is fetched on activation, and the shortcode
  self-heals on render when the cache is stale (transient-locked), with an
  `rsc_enable_lazy_refresh` filter to opt out. Cache options are now non-autoloaded.
- **Polished shortcode UI:** recommendation eyecatcher + wind/water/assessment
  cards + 6-hour forecast, restyled to the peterhensen.nl palette (scoped CSS).
- **New `RSC_Assessment`** (pure PHP) holds the sailing thresholds; the standalone
  dashboards reuse it, so plugin and examples can't drift.
- **Standalone dashboards are now publicly deployable:** shared `examples/lib/data.php`
  fetches over cURL (with a stream fallback) and caches responses on disk; a
  failed fetch falls back to the last good value. Timestamps use Europe/Amsterdam.
- **Offline, deterministic tests:** fetcher tests mock HTTP via `pre_http_request`
  using fixtures in `tests/fixtures/`; added `tests/test-assessment.php`.
- Added Dutch translations for all new strings (nl_NL complete; fy_NL best-effort).

## v1.3.0 Changes
- Added a **6-hour precipitation forecast** (mm + probability) from Open-Meteo,
  fetched in the **same single request** as the wind forecast (one source).
- New `fetch_openmeteo_forecast()` returns wind + precipitation together;
  cached as `forecast_wind` and `forecast_precipitation`.
- Display renders a precipitation chart alongside the wind chart; forecast
  headers relabelled ("Wind (next 6 hours)" / "Precipitation (next 6 hours)").
- Added translations for the new strings (nl_NL, fy_NL).

## v1.2.0 Changes
- Proper i18n: all source strings are now **English**, with gettext
  translations in `languages/` (Dutch `nl_NL` shipped as default, Frisian
  `fy_NL` included as an example). Plurals use `_n()`.
- Plugin UI defaults to Dutch via a `plugin_locale` filter; switchable with
  the `rsc_locale` filter.
- Example dashboards use a lightweight `examples/lang/` translation table with
  a `t()` helper, switchable via the `RSC_LANG` environment variable; both are
  now Dutch by default and emoji-free.

## v1.1.0 Changes
- Registered the custom 15-min/30-min cron intervals (without this the fetch
  jobs never ran)
- Backported the reference data model into the plugin: Driel boven location,
  current speed (STROOMSHD) and water temperature (T)
- Water level, current speed and temperature now come from a single RWS request
- Added HTTP status-code checks to all API calls
- Fixed cache timestamps not updating when fetched data was unchanged
- Removed the precipitation-based "water level forecast" (it produced a
  meaningless near-constant value)
- Dutch UI strings are now translatable; added a stale-data warning
- Fixed test assertions (`assertGreaterThan`) and aligned tests with the new model

## What's Included

### Core Features
- **Real-time sailing conditions** for Rhine River at Driel boven
- **Real wind data** from Open-Meteo API (no authentication required)
- **Real water measurements** from Rijkswaterstaat DDAPI:
  - Water height (Waterhoogte) in meters NAP
  - Current speed (Stroomsnelheid) in knots/m/s
  - Water temperature (Temperatuur) in °C
- **6-hour wind + precipitation forecast** display (one Open-Meteo request)
- **Responsive HTML shortcode** `[rhine-sailing-conditions]`
- **Dutch interface** for Dutch sailing community

### Architecture
- **RSC_Cache**: WordPress options-based caching with timestamps
- **RSC_Validator**: Data validation (`validate_wind`, `validate_water_level`,
  `validate_current_speed`, `validate_temperature`)
- **RSC_Fetcher**: Real API integration
  - `fetch_openmeteo_wind()` - Real wind data from Open-Meteo
  - `fetch_openmeteo_forecast()` - 6-hour wind + precipitation forecast from Open-Meteo (one request)
  - `fetch_rws_measurements()` - Water height (WATHTE), current speed (STROOMSHD)
    and temperature (T) parsed from one RWS DDAPI request
- **RSC_Display**: Frontend rendering with graceful degradation and stale-data warning

### WordPress Integration
- Automatic cron scheduling on activation (15-min current conditions, 30-min forecasts)
- Clean unscheduling on deactivation
- Plugin activation/deactivation hooks
- Responsive CSS stylesheet with modern design
- Proper WordPress conventions and hooks throughout

## Data Integration Details

### Wind Data (Open-Meteo)
- **API**: https://api.open-meteo.com/v1/forecast
- **No authentication required**
- **Location**: Driel boven (51.9397°N, 5.3897°E)
- **Measurements**: Wind speed, direction, gust estimation

### Water Data (Rijkswaterstaat DDAPI)
- **API**: https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen
- **No authentication required**
- **Location Code**: driel.boven
- **Current Measurements**:
  - WATHTE (Water height) - in centimeters
  - STROOMSHD (Current speed) - in m/s
  - T (Temperature) - in °C
- **Additional Available**: pH, water clarity, discharge volume, saturation

## Code Quality
- All private static methods have proper error handling
- Cache operations gracefully handle missing options
- Display methods handle missing/empty data with fallbacks
- Input validation on all data endpoints
- Comprehensive inline section comments for maintainability
- Real API responses validated with proper error messages
- Unit tests work with real APIs (no mocks)

## Testing
- Unit test coverage for all major classes
- Integration tests verify real API connectivity
- Edge case testing for validators
- Data integrity verification
- Plugin structure validation
- Live data verified from both APIs

## Production Deployment Notes
1. ✅ Real API endpoints integrated in RSC_Fetcher class
2. ✅ Proper error handling for API timeouts implemented
3. ✅ All data validated before caching
4. Cache TTL configured: 30 min for current conditions, 60 min for forecasts
5. No API keys or credentials required
6. Plugin gracefully handles API downtime with cached data

## Files Structure
```
rhine-sailing-conditions/
├── rhine-sailing-conditions.php    (Main plugin file with hooks)
├── includes/
│   ├── class-cache.php             (Options wrapper)
│   ├── class-validator.php         (Data validation)
│   ├── class-fetcher.php           (Real API integration - UPDATED)
│   └── class-display.php           (Frontend rendering)
├── public/
│   └── css/
│       └── display.css             (Responsive styling)
├── languages/
│   ├── rhine-sailing-conditions.pot          (translation template)
│   ├── rhine-sailing-conditions-nl_NL.po/.mo (Dutch, default)
│   └── rhine-sailing-conditions-fy_NL.po/.mo (Frisian, example)
├── tests/
│   ├── test-cache.php
│   ├── test-validator.php
│   ├── test-display.php
│   └── test-fetcher.php
├── examples/
│   ├── lang/
│   │   ├── i18n.php                (t() helper + loader)
│   │   ├── nl.php                  (Dutch table)
│   │   └── fy.php                  (Frisian table)
│   ├── rws-debug-dashboard.php     (Standalone RWS water data reference)
│   └── combined-sailing-conditions.php  (Standalone full dashboard reference)
├── README.md                        (Installation guide - UPDATED)
├── RWS_INTEGRATION_SUMMARY.md      (Technical documentation - NEW)
└── RELEASE_NOTES.md               (This file - UPDATED)
```

## Reference Implementations

Two standalone PHP dashboards are included in the `examples/` folder for reference when iterating on the WordPress integration:

- **rws-debug-dashboard.php**: Displays RWS water data only (water level, discharge, temperature)
- **combined-sailing-conditions.php**: Full dashboard with wind + water + sailing conditions assessment (Dutch interface)

Both demonstrate real API integration patterns and can be run standalone: `php -S localhost:8765 examples/combined-sailing-conditions.php`

## Next Steps
- ✅ Implement real API endpoints in RSC_Fetcher methods - **COMPLETED**
- Integrate dashboard into WordPress plugin as shortcode
- Add user configuration options (location settings, update frequency)
- Add admin dashboard for manual data refresh and error monitoring
- Implement WP-CLI commands for debugging and troubleshooting
- Historical data graphs and trends
- Water condition alerts (temperature, flow rate thresholds)
