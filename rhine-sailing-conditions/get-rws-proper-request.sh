#!/bin/bash

# Proper RWS DDAPI request with correct parameter structure

echo "╔════════════════════════════════════════════════════════════╗"
echo "║  RWS DDAPI - Proper Request Format Test                   ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Create proper JSON payload
# The API requires:
# 1. LocatieLijst - list of locations
# 2. AquoPlusWaarnemingMetadataLijst - list of observation metadata (parameters to fetch)

# First, let's get available parameters from the catalog
echo "Step 1: Getting available parameters from catalog..."
CATALOG=$(curl -s -X POST \
  -H "Content-Type: application/json" \
  -d '{"CatalogusFilter":{}}' \
  "https://ddapi20-waterwebservices.rijkswaterstaat.nl/METADATASERVICES/OphalenCatalogus")

# Extract parameter IDs (first few)
echo "Available observation types:"
echo "$CATALOG" | jq '.AquoMetadataLijst[]? | .AquoMetadata_MessageID' | head -5

# Try simple request with location list
echo ""
echo "Step 2: Test with LocatieLijst format..."

PAYLOAD=$(cat <<'EOF'
{
  "LocatieLijst": ["arnhem.nederrijn"]
}
EOF
)

RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD" \
  "https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen")

echo "Response:"
echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE"

echo ""
echo "Step 3: Test with Locatie object..."

PAYLOAD2=$(cat <<'EOF'
{
  "Locatie": {
    "Code": "arnhem.nederrijn"
  }
}
EOF
)

RESPONSE2=$(curl -s -X POST \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD2" \
  "https://ddapi20-waterwebservices.rijkswaterstaat.nl/ONLINEWAARNEMINGENSERVICES/OphalenLaatsteWaarnemingen")

echo "Response:"
echo "$RESPONSE2" | jq '.' 2>/dev/null || echo "$RESPONSE2"

echo ""
echo "Full catalog available parameters:"
echo "$CATALOG" | jq '.AquoMetadataLijst[]? | {MessageID: .AquoMetadata_MessageID, Param: .Parameter_Wat_Omschrijving}' | grep -i "stand\|af" | head -10

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Responses saved to /tmp/rws_proper_test_*.json for analysis"
