#!/bin/bash

# Find simpler RWS water data endpoints (CSV, XML, direct REST without complex Aquo format)

echo "╔════════════════════════════════════════════════════════════╗"
echo "║  Searching for Simpler RWS Data Access Methods            ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Test 1: RWS data portal downloads (CSV/Excel)
echo "Test 1: RWS Data Downloads (CSV/Excel files)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

curl -s "https://data.rijkswaterstaat.nl/" | grep -i "download\|csv\|excel" | head -5

echo ""
echo "Test 2: Direct RWS GeoServer REST (alternative to DDAPI)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Some government agencies publish GeoServer with simpler REST API
GEOSERVER_ENDPOINTS=(
    "https://services.rijkswaterstaat.nl/ArcGIS/rest/services"
    "https://services.arcgisonline.nl/ArcGIS/rest/services/Rijkswaterstaat"
    "https://webservices.rijkswaterstaat.nl/arcgis/rest"
)

for endpoint in "${GEOSERVER_ENDPOINTS[@]}"; do
    echo "Trying: $endpoint"
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$endpoint")
    echo "  HTTP: $STATUS"

    if [ "$STATUS" = "200" ] || [ "$STATUS" = "404" ]; then
        # 404 means server exists but path doesn't - better than no response
        echo "  ✓ Server responsive"
    fi
done

echo ""
echo "Test 3: Try WFS (Web Feature Service) - standard geospatial API"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

WFS_URLS=(
    "https://services.rijkswaterstaat.nl/wfs?service=WFS&version=2.0.0&request=GetFeature&typeName=water_level&outputFormat=json"
    "https://services.rijkswaterstaat.nl/wfs?service=WFS&version=1.0.0&request=GetCapabilities"
)

for url in "${WFS_URLS[@]}"; do
    echo "Trying: ${url:0:80}..."
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$url")
    echo "  HTTP: $STATUS"
done

echo ""
echo "Test 4: CKAN Data Portal - Dataset listing"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo "Searching for 'waterstand' datasets..."
curl -s "https://data.rijkswaterstaat.nl/api/3/action/package_search?q=waterstand&limit=3" | jq '.result.results[]? | {title: .title, url: .organization.name}' 2>/dev/null || echo "CKAN API not responding"

echo ""
echo "Test 5: Direct data file URLs (if RWS publishes CSV/JSON)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Try some common data file patterns
FILES=(
    "https://rijkswaterstaatdata.nl/data/waterstand_arnhem.csv"
    "https://rijkswaterstaatdata.nl/data/waterstand_arnhem.json"
    "https://data.rijkswaterstaat.nl/waterstand/arnhem.json"
)

for url in "${FILES[@]}"; do
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$url")
    [ "$STATUS" = "200" ] && echo "✓ Found: $url" || echo "✗ Not found: $url"
done

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Summary: If tests above found accessible endpoints,"
echo "those are the paths to real RWS water data without complex Aquo format."
