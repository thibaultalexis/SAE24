#!/usr/bin/env bash

# ensure docker is available on the host
if ! command -v docker >/dev/null 2>&1; then
  echo "Error: run this script on the host, not inside the container." >&2
  exit 1
fi

# Change to the script directory
cd "$(dirname "$0")"

# Docker container name, MQTT topic pattern, and MySQL command
MOSQ_CONTAINER=mosquittosae24
TOPIC="SAE24/+/son"
MYSQL_CMD="/opt/lampp/bin/mysql sae24db"

# each message payload is ingested on arrival
docker exec -i "$MOSQ_CONTAINER" mosquitto_sub -t "$TOPIC" --quiet | \
while read payload; do
  # Extract amplitude, device ID, and room from the JSON payload
  AMP=$(echo "$payload" | jq '.[0].amplitude')
  DEVID=$(echo "$payload" | jq -r '.[1].devId')
  ROOM=$(echo "$payload" | jq -r '.[1].room')

  # Lookup sensor ID in the database
  CAP_ID=$($MYSQL_CMD -sN -e \
    "SELECT id FROM capteurs WHERE devId='$DEVID' AND room='$ROOM';")
  [ -z "$CAP_ID" ] && continue

  # Insert the measurement into the son_mesures table
  $MYSQL_CMD -e \
    "INSERT INTO son_mesures (capteur_id, amplitude, ts)
     VALUES ($CAP_ID, $AMP, NOW());"
done
