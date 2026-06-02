# Rhine Sailing Conditions WordPress Plugin

A WordPress plugin that displays real-time sailing conditions for the Rhine River between Driel boven and Arnhem Nederrijn.

## Features

- **Current Conditions:** Wind speed, direction, gusts; water level; current flow
- **Forecasts:** Wind forecast for next 6 hours; water level trend
- **Auto-Update:** Data refreshes automatically every 15-30 minutes
- **Resilient:** Gracefully handles API downtime with cached data
- **Responsive:** Works on desktop, tablet, and mobile

## Installation

1. Download the plugin folder to `/wp-content/plugins/rhine-sailing-conditions/`
2. Activate the plugin in WordPress admin (Plugins menu)
3. Add the shortcode to any page or post:
   ```
   [rhine-sailing-conditions]
   ```

## Data Sources

- **Weather:** KNMI (Royal Netherlands Meteorological Institute)
- **Water Data:** Rijkswaterstaat (Dutch water authority)

## Technical Details

### Architecture
- Self-contained WordPress plugin
- Fetches data from public APIs every 15-30 minutes via WordPress cron
- Caches data in WordPress options table
- Renders as a responsive dashboard widget

### Classes
- `RSC_Fetcher` – Handles API calls to KNMI and Rijkswaterstaat
- `RSC_Validator` – Validates API response data
- `RSC_Cache` – Wraps WordPress options for data storage
- `RSC_Display` – Renders the shortcode

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
