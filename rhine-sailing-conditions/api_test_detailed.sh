#!/bin/bash

# Rhine Sailing Conditions - Detailed API Testing
# Tests discovered working endpoints with proper parameters

LAT="51.985"
LON="5.891"

echo "========================================"
echo "🌊 Testing Real Working APIs"
echo "========================================"
echo ""

# Test 1: Rijkswaterstaat Metadata Service
echo "Test 1: RWS Metadata Service"
echo "----------------------------"
echo "Endpoint: OphalenCatalogus"
echo "Purpose: Get available measurement stations and types"
echo ""
curl -s -m 5 -X POST \
  -H "Content-Type: application/json" \
  "https://ddapi20-waterwebservices.rijkswaterstaat.nl/METADATASERVICES/OphalenCatalogus" \
  -d '{}' | jq . 2>/dev/null | head -50
echo ""

# Test 2: RWS Latest Observations (Water Level)
echo "Test 2: RWS Latest Observations"
echo "--------------------------------"
echo "Purpose: Get latest water level, flow, temperature data"
echo "Trying various station codes for Arnhem area..."
echo ""

# Common Rijkswaterstaat station codes for Arnhem/Rhine area
stations=("ARNM" "ARNHEM" "ARN" "DRIEL" "ZALK")

for station in "${stations[@]}"; do
  echo "Station: $station"
  curl -s -m 5 -X POST \
    -H "Content-Type: application/json" \
    "https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen" \
    -d "{\"Locatie\": \"$station\"}" | jq . 2>/dev/null | head -20
  echo ""
done

# Test 3: Open-Meteo with corrected query
echo "Test 3: Open-Meteo API (Wind Data)"
echo "-----------------------------------"
echo "Endpoint: https://api.open-meteo.com/v1/forecast"
echo "Purpose: Get current and forecast wind conditions"
echo ""
curl -s "https://api.open-meteo.com/v1/forecast?latitude=$LAT&longitude=$LON&current=temperature,wind_speed,wind_direction,relative_humidity&hourly=wind_speed,wind_direction&forecast_days=1&timezone=Europe/Amsterdam" | jq . 2>/dev/null
echo ""

echo "========================================"
echo "✅ Test Complete"
echo "========================================"
