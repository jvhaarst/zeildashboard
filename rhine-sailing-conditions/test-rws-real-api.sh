#!/bin/bash

# RWS Real API Testing Script
# Testing actual Rijkswaterstaat DDAPI endpoints for water measurements

echo "╔════════════════════════════════════════════════════════════╗"
echo "║  RWS DDAPI Testing - Real Water Data for Rhine Arnhem     ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

CATALOG_URL="https://ddapi20-waterwebservices.rijkswaterstaat.nl/METADATASERVICES/OphalenCatalogus"
OBSERVATIONS_URL="https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen"

# Test 1: Get Catalog (list of all stations)
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Test 1: Fetching RWS Catalog"
echo "URL: $CATALOG_URL"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

echo "Attempt 1: Empty request (original)"
CATALOG_RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{}' \
  --max-time 10 \
  "$CATALOG_URL" 2>&1)

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST \
  -H "Content-Type: application/json" \
  -d '{}' \
  "$CATALOG_URL")

echo "  Response: $CATALOG_RESPONSE" | head -c 200
echo ""
echo ""
echo "Attempt 2: With catalogFilter parameter"

# The API requires catalogFilter parameter
CATALOG_RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"CatalogusFilter":{}}' \
  --max-time 10 \
  "$CATALOG_URL" 2>&1)

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST \
  -H "Content-Type: application/json" \
  -d '{"CatalogusFilter":{}}' \
  "$CATALOG_URL")

echo "HTTP Status: $HTTP_CODE"
echo ""
echo "Response (first 1000 chars):"
echo "$CATALOG_RESPONSE" | head -c 1000
echo ""
echo ""

# Test 2: Try to get observations for Arnhem
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Test 2: Getting Latest Observations for Arnhem"
echo "URL: $OBSERVATIONS_URL"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Try different location identifiers
for LOCATION in "ARNHEM" "ARNM" "AR" "NEDR" "ARN"; do
    echo ""
    echo "Trying location code: $LOCATION"

    OBS_RESPONSE=$(curl -s -X POST \
      -H "Content-Type: application/json" \
      -H "Accept: application/json" \
      -d "{\"Locatie\":\"$LOCATION\"}" \
      --max-time 10 \
      "$OBSERVATIONS_URL" 2>&1)

    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST \
      -H "Content-Type: application/json" \
      -d "{\"Locatie\":\"$LOCATION\"}" \
      "$OBSERVATIONS_URL")

    echo "  HTTP Status: $HTTP_CODE"

    # Check if response contains data
    if echo "$OBS_RESPONSE" | grep -q "Waarnemingen\|waarde\|value"; then
        echo "  ✓ Contains measurement data!"
        echo "  Response (first 500 chars):"
        echo "$OBS_RESPONSE" | head -c 500
        break
    elif [ "$HTTP_CODE" = "200" ]; then
        echo "  Response (first 300 chars):"
        echo "$OBS_RESPONSE" | head -c 300
    else
        echo "  No data"
    fi
done

echo ""
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Test 3: Alternative Endpoints"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Test FEWS alternative endpoints (with redirects)
echo "Testing FEWS endpoints with redirect following..."
FEWS_ENDPOINTS=(
    "https://www.rijkswaterstaatdata.nl/webservices/rest/fewswebservices/v2/timeseries"
    "https://www.rijkswaterstaatdata.nl/webservices/rest/fewswebservices/v2/locations"
)

for endpoint in "${FEWS_ENDPOINTS[@]}"; do
    echo ""
    echo "Testing: $endpoint"

    # Follow redirects
    HTTP_CODE=$(curl -s -L -o /dev/null -w "%{http_code}" "$endpoint?documentFormat=json")
    echo "  HTTP Status (follow redirects): $HTTP_CODE"

    if [ "$HTTP_CODE" = "200" ]; then
        # If success, get actual response
        RESPONSE=$(curl -s -L "$endpoint?documentFormat=json" --max-time 5)
        echo "  Response (first 300 chars):"
        echo "$RESPONSE" | head -c 300
    fi
done

echo ""
echo "Testing CKAN data portal..."
CKAN_RESPONSE=$(curl -s "https://data.rijkswaterstaat.nl/api/3/action/package_search?q=water&rows=5" --max-time 10)
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "https://data.rijkswaterstaat.nl/api/3/action/package_search?q=water&rows=5")
echo "HTTP Status: $HTTP_CODE"
if [ "$HTTP_CODE" = "200" ]; then
    echo "Response (first 400 chars):"
    echo "$CKAN_RESPONSE" | head -c 400
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Summary"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "If Test 1 succeeds (HTTP 200):"
echo "  → Use catalog response to find exact Arnhem station code"
echo "  → Look for 'LocatieLijst' array with station names and codes"
echo ""
echo "If Test 2 succeeds with location found:"
echo "  → Parse 'Waarnemingen' array for water measurements"
echo "  → Look for parameters like 'waarde' (value), 'eenheid' (unit)"
echo "  → Identify which parameter is water level vs discharge"
echo ""
echo "If Tests fail:"
echo "  → Check if RWS DDAPI requires authentication"
echo "  → Try accessing via data.rijkswaterstaat.nl data portal"
echo "  → Search for alternative Dutch water data APIs"
