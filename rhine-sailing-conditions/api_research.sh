#!/bin/bash

# Rhine Sailing Conditions - API Endpoint Research Script
# This script tests various API endpoints to find working data sources
# for wind, water level, and current conditions near Arnhem, Netherlands

# Coordinates for Arnhem
LAT="51.985"
LON="5.891"

echo "========================================"
echo "🌊 Rhine Sailing API Endpoint Research"
echo "========================================"
echo "Location: Arnhem, Netherlands ($LAT, $LON)"
echo ""

# Test 1: KNMI Official API
echo "Test 1: KNMI Official API Endpoints"
echo "-----------------------------------"
echo "Trying: https://api.knmi.nl/odata3/v1/Hourlydata"
curl -s -m 5 -w "\nHTTP Status: %{http_code}\n" "https://api.knmi.nl/odata3/v1/Hourlydata?select=*&skip=0&top=1" 2>&1 | head -15
echo ""

# Test 2: Open-Meteo (Free, No Auth Required)
echo "Test 2: Open-Meteo API (Free Weather Data)"
echo "------------------------------------------"
echo "Trying: https://api.open-meteo.com/v1/forecast"
curl -s "https://api.open-meteo.com/v1/forecast?latitude=$LAT&longitude=$LON&current=temperature,wind_speed,wind_direction&hourly=wind_speed,wind_direction&timezone=Europe/Amsterdam" | jq . 2>/dev/null | head -40
echo ""

# Test 3: Rijkswaterstaat Waterdata
echo "Test 3: Rijkswaterstaat Waterdata Portal"
echo "----------------------------------------"
echo "Trying: https://rijkswaterstaatdata.nl/waterdata/ (looking for API docs)"
curl -s -m 5 "https://rijkswaterstaatdata.nl/waterdata/" | grep -oE "https://[^\"]+api[^\"]*|https://[^\"]+data[^\"]*" | sort -u | head -10
echo ""

# Test 4: Try direct Rijkswaterstaat API with different format
echo "Test 4: Rijkswaterstaat Alternative Endpoints"
echo "---------------------------------------------"
echo "Trying various RWS endpoints..."

endpoints=(
  "https://rijkswaterstaatdata.nl/webservices/rest/waterdata/?Arnhem"
  "https://rijkswaterstaatdata.nl/geoserver/web/"
  "https://data.rijkswaterstaat.nl/water-level/latest"
)

for endpoint in "${endpoints[@]}"; do
  echo "  Endpoint: $endpoint"
  status=$(curl -s -o /dev/null -w "%{http_code}" -m 3 "$endpoint")
  echo "  HTTP Status: $status"
done
echo ""

# Summary
echo "========================================"
echo "📋 Summary of Findings"
echo "========================================"
echo "Use this output to:"
echo "1. Identify which endpoints return data (HTTP 200)"
echo "2. Check the response format (JSON vs HTML vs XML)"
echo "3. Look for water level and wind data"
echo "4. Note any API key requirements"
echo ""
echo "Good alternatives if RWS/KNMI don't work:"
echo "- Open-Meteo: Free weather API (wind data available)"
echo "- Alternative water sources: Check Dutch water board sites"
echo "========================================"
