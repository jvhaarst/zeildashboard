#!/bin/bash

# Get real water measurements for Rhine at Arnhem from RWS DDAPI

LOCATION_CODE="arnhem.nederrijn"

echo "╔════════════════════════════════════════════════════════════╗"
echo "║  Fetching REAL RWS Water Data for $LOCATION_CODE  ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Get latest observations
echo "Fetching latest water measurements..."
echo ""

RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/json" \
  -d "{\"Locatie\":\"$LOCATION_CODE\"}" \
  "https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen")

# Save response
echo "$RESPONSE" > /tmp/rws_observations.json

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST \
  -H "Content-Type: application/json" \
  -d "{\"Locatie\":\"$LOCATION_CODE\"}" \
  "https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen")

echo "HTTP Status: $HTTP_CODE"
echo ""

if command -v jq &> /dev/null; then
    echo "✓ Parsing response with jq..."
    echo ""

    # Check if successful
    SUCCESS=$(echo "$RESPONSE" | jq '.Succesvol' 2>/dev/null)
    echo "Request successful: $SUCCESS"
    echo ""

    # Extract observations
    echo "Observations found:"
    echo "$RESPONSE" | jq '.Waarnemingen[]?' 2>/dev/null | head -100

    echo ""
    echo "Unique parameters in response:"
    echo "$RESPONSE" | jq '.Waarnemingen[]?.Parameter_Wat_Omschrijving' 2>/dev/null | sort -u

else
    echo "Response (first 1000 chars):"
    head -c 1000 /tmp/rws_observations.json
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Full response saved to: /tmp/rws_observations.json"
echo "Size: $(wc -c < /tmp/rws_observations.json) bytes"
echo ""
echo "What to look for in response:"
echo "  - 'Waarnemingen' array containing measurements"
echo "  - 'Parameter_Wat_Omschrijving' field names"
echo "  - 'Waarde' field containing actual measurement values"
echo "  - 'Eenheid' field containing units (m for meters, m³/s for discharge)"
echo "  - 'Toestandsdatum' field containing measurement timestamp"
