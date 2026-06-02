# Rhine Sailing Conditions WordPress Plugin — Design Spec

**Date:** 2026-06-02  
**Project:** Sailing Conditions Dashboard for Rhine River (Driel boven to Arnhem Nederrijn)  
**Version:** 1.0 (MVP)

---

## Executive Summary

A WordPress plugin that displays real-time sailing conditions on the Rhine River between Driel boven and Arnhem Nederrijn. The plugin fetches data from two public APIs (KNMI for weather, Rijkswaterstaat for water data), caches locally, and displays current conditions + forecasts via a dashboard-style widget embedded in WordPress pages using a shortcode.

**Scope:** MVP focuses on simplicity with no admin configuration—data fetching and display only. Configuration UI and extended features (historical data, alerts, etc.) deferred to future versions.

---

## 1. Architecture Overview

### High-Level Flow
```
1. Scheduled background task (WordPress cron) runs every 15-30 minutes
2. Plugin fetches from:
   - KNMI API: Wind direction, speed, gusts + weather alerts
   - Rijkswaterstaat API: Water level, current flow rates
3. Data validated and cached in WordPress options table
4. Shortcode [rhine-sailing-conditions] displays cached data on page
5. Display updates next time user reloads page or cron refreshes
```

### Technology Stack
- **WordPress**: Plugin framework, cron scheduling, options API for caching
- **PHP**: Data fetching, API calls, validation
- **JavaScript/CSS**: Frontend display, responsive layout
- **External APIs**: KNMI (weather), Rijkswaterstaat (water data)

### Why This Approach
- **Self-contained:** No external services; WordPress handles everything
- **Simple:** Club installs plugin, adds shortcode, it works
- **Resilient:** Cached data survives API downtime
- **Maintainable:** All logic in one WordPress plugin; no separate backend to manage

---

## 2. Data Sources & Display Elements

### Current Conditions (Fetched Every 15 Minutes)

**Wind** (from KNMI):
- Direction: Cardinal/intercardinal (N, NE, E, SE, S, SW, W, NW)
- Speed: Knots
- Gust: Peak gust speed (knots)

**Water Level** (from Rijkswaterstaat):
- Current level: Meters (relative to reference datum NAP)

**Current/Flow** (from Rijkswaterstaat):
- Flow rate: Cubic meters per second (m³/s)

### Forecasts (Fetched Every 30 Minutes)

**Wind Forecast:**
- Hourly predictions for next 6-12 hours
- Speed (knots) and direction per hour

**Water Level Forecast:**
- 24-48 hour outlook if available from Rijkswaterstaat

**Alerts:**
- KNMI weather alerts for the Rhine region (storms, wind warnings, etc.)

### Data Freshness
- Current conditions: Display timestamp of last fetch
- Visual indicator: "Updated X minutes ago"
- Stale data: If older than 60 minutes, show "Data may be outdated" notice

---

## 3. Display & User Interface

### Layout: Dashboard (Split View)

**Desktop/Tablet (≥600px width):**
- **Left panel (40%):** Current conditions
  - Wind: Direction, speed, gust (prominent)
  - Water level: Current level
  - Current: Flow rate
  - Updated timestamp
  
- **Right panel (60%):** Forecast trends
  - Wind speed forecast: Next 6 hours (hourly)
  - Optional: Simple line chart or bar chart of wind trend
  - Water level trend if available

**Mobile (<600px width):**
- Stack vertically: Current conditions on top, forecast below
- Full width, scrollable if needed

### Visual Design
- **Container:** Bordered card or panel with subtle background
- **Colors:** Neutral (inherit from WordPress theme)
- **Typography:** Readable, accessible font sizes
- **Units:** Always show units (kts, m, m³/s)
- **Icons:** Optional wind direction indicator (compass rose or arrow)

### Shortcode
```
[rhine-sailing-conditions]
```
No attributes for v1 (future: allow custom styling, location override).

---

## 4. Plugin Installation & Configuration

### Installation Steps
1. Club downloads plugin folder
2. Uploads to `/wp-content/plugins/rhine-sailing-conditions/`
3. Activates plugin in WordPress admin (Plugins menu)
4. Adds shortcode to desired page/post: `[rhine-sailing-conditions]`
5. Plugin starts fetching and displaying data immediately

### Configuration (V1)
- **Hardcoded location:** Rhine coordinates (Driel boven to Arnhem Nederrijn)
- **Hardcoded refresh intervals:**
  - Current conditions: Every 15 minutes
  - Forecasts: Every 30 minutes
- **No admin panel:** Settings managed via code constants (future enhancement)

### Plugin Structure
```
rhine-sailing-conditions/
├── rhine-sailing-conditions.php    (main plugin file, hooks, shortcode)
├── includes/
│   ├── Fetcher.php                (API calls to KNMI & Rijkswaterstaat)
│   ├── Validator.php              (data validation & sanitization)
│   └── Cache.php                  (wrapper around WordPress options)
├── public/
│   ├── css/
│   │   └── display.css            (shortcode display styles)
│   └── js/
│       └── forecast-chart.js      (optional: wind trend visualization)
└── README.md
```

---

## 5. Data Fetching & Caching

### WordPress Cron Jobs
Two scheduled events:
- **`rhsc_refresh_current`** (every 15 min): Fetches wind, water level, current
- **`rhsc_refresh_forecast`** (every 30 min): Fetches wind & water level forecasts

### Caching Strategy
- **Storage:** WordPress options table (`wp_options`)
- **Keys:**
  - `rhsc_current_conditions`: Current data (wind, water, current)
  - `rhsc_forecast_wind`: Wind forecast array
  - `rhsc_forecast_water`: Water level forecast array
  - `rhsc_last_update_current`: Timestamp of last current-data refresh
  - `rhsc_last_update_forecast`: Timestamp of last forecast refresh
  - `rhsc_api_errors`: Last known API error (for debugging)

### Cache Lifetime
- **Current conditions:** Valid for 30 minutes (fetched every 15 min)
- **Forecasts:** Valid for 60 minutes (fetched every 30 min)
- **Stale data handling:** If no refresh in >60 min, show "Data may be outdated" warning

---

## 6. Error Handling & Resilience

### API Failures
- **If KNMI API is down:** Use cached wind data if available; mark as outdated
- **If Rijkswaterstaat API is down:** Use cached water/current data if available; mark as outdated
- **If both down and no cache:** Display message: "Current conditions unavailable. Please try again later."

### Data Validation
Before caching any API response:
1. Check HTTP status (expect 200)
2. Validate JSON structure (required fields present)
3. Validate data types and ranges (e.g., wind speed ≥ 0, valid direction)
4. Discard invalid responses; keep previous cache

### Logging
- Log API errors to WordPress debug log (if `WP_DEBUG` enabled)
- Store last error message in cache for admin inspection (future: admin dashboard)

### Rate Limiting
- Caching prevents hammering APIs
- If Rijkswaterstaat/KNMI rate-limits us, cached data continues serving the widget
- Monitor via error logs; alert if API becomes consistently unavailable

---

## 7. Security & Performance

### Security
- **API authentication:** Use public APIs (no secrets required initially)
- **Input validation:** All API responses validated before use
- **Output escaping:** All data escaped when rendered in HTML
- **Plugin stability:** Shortcode fails gracefully; never breaks page

### Performance
- **Caching:** Eliminates redundant API calls
- **Asynchronous updates:** WordPress cron handles fetches in background
- **No database queries on page render:** Only options table lookup (cached)
- **Minimal JavaScript:** No heavy libraries; vanilla JS for optional chart

---

## 8. Testing Strategy

### Manual Testing
1. **Fresh install:** Activate plugin, add shortcode, verify data appears
2. **API failures:** Simulate offline API, verify fallback to cached data
3. **Data validation:** Test with malformed API responses
4. **Display:** Check layout on desktop, tablet, mobile
5. **Refresh:** Monitor cron, verify data updates every 15-30 min

### Edge Cases
- No cached data + API down: Shortcode displays error message
- Partial data: If only wind available but not water level, display what's available
- Stale cache: Display "outdated" warning if no refresh >60 min

---

## 9. Future Enhancements (Out of Scope for V1)

- Admin settings page to configure location, refresh intervals
- Historical data graphs (wind/water trends over days/weeks)
- User alerts: "Wind exceeds X knots" or "Water level dangerous"
- Multiple locations support
- Chart visualization library for forecast trends
- REST API for external integrations

---

## Success Criteria (V1)

- ✓ Plugin installs without errors
- ✓ Shortcode displays current wind, water level, current
- ✓ Shortcode displays wind forecast for next 6 hours
- ✓ Data refreshes automatically every 15-30 minutes
- ✓ API downtime doesn't break the plugin
- ✓ Layout responsive on mobile/tablet/desktop
- ✓ Club can manage without technical support

