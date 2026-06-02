#!/bin/bash

# Get all RWS locations and find Arnhem

echo "Fetching complete RWS location catalog..."
echo ""

# Get full catalog with all locations
RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/json" \
  -d '{"CatalogusFilter":{}}' \
  "https://ddapi20-waterwebservices.rijkswaterstaat.nl/METADATASERVICES/OphalenCatalogus")

# Save full response for analysis
echo "$RESPONSE" > /tmp/rws_catalog.json

# Try to extract location information using jq if available
if command -v jq &> /dev/null; then
    echo "✓ Extracting location data with jq..."
    echo ""

    # Look for location listings
    echo "$RESPONSE" | jq '.LocatieLijst[]? | select(.Naam | contains("Arnhem") or contains("arnhem"))' 2>/dev/null || true

    echo ""
    echo "All locations containing 'Arnhem':"
    echo "$RESPONSE" | jq '.LocatieLijst[]? | select(.Naam | contains("Arnhem")) | {Code: .Code, Naam: .Naam}' 2>/dev/null || true
else
    echo "jq not available - parsing with grep..."
    echo ""

    # Search for Arnhem in response
    echo "Searching response for Arnhem-related data:"
    grep -i "arnhem" /tmp/rws_catalog.json | head -5
fi

echo ""
echo "Full catalog saved to: /tmp/rws_catalog.json"
echo "Size: $(wc -c < /tmp/rws_catalog.json) bytes"
echo ""
echo "Response structure:"
echo "$RESPONSE" | head -c 500
echo "..."
