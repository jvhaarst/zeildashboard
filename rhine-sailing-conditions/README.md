# Rhine Sailing Conditions WordPress Plugin

A WordPress plugin that displays real-time sailing conditions for the Rhine River between Driel boven and Arnhem Nederrijn.

## Features

- **Current Conditions:** Real wind speed/direction; water level; current speed; water temperature
- **Real Data:** Wind from Open-Meteo; water measurements from Rijkswaterstaat DDAPI
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
  - `fetch_knmi_wind()` – Current wind from Open-Meteo
  - `fetch_rijkswaterstaat_water_level()` – Water height (WATHTE) from RWS
  - `fetch_rijkswaterstaat_current_speed()` – Current speed (STROOMSHD) from RWS
  - `fetch_rijkswaterstaat_temperature()` – Water temperature (T) from RWS
  - `fetch_knmi_wind_forecast()` – Wind forecast from Open-Meteo
  - `fetch_rijkswaterstaat_water_forecast()` – Water forecast (precipitation-based)
- `RSC_Validator` – Validates API response data and measurement values
- `RSC_Cache` – Wraps WordPress options for data storage with timestamps
- `RSC_Display` – Renders the shortcode with sailing conditions assessment

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

## Requirements

- WordPress 5.0 or later
- PHP 7.4 or later
- Internet connection for API calls

## License

GPL2

## Support

For issues or questions, contact the sailing club administrator.
