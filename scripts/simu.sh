#!/usr/bin/env bash
# simu.sh – simulation on the grid, publishing every 2 seconds

# Simplified check: ensure docker is available on the host
if ! command -v docker >/dev/null 2>&1; then
  echo "Erreur : lancez ce script sur l'hôte, pas dans le container." >&2
  exit 1
fi

cd "$(dirname "$0")"

MOSQ_CONTAINER=mosquittosae24
ROOM="E103"
TOPIC="SAE24/${ROOM}/son"
MICROS=("S01" "S02" "S03")

# Build a 16×16 trajectory
PATH_COORDS=()
for j in $(seq 0 15); do
  if [ $((j%2)) -eq 0 ]; then
    for i in $(seq 0 15); do
      PATH_COORDS+=( "${i},${j}" )
    done
  else
    for i in $(seq 15 -1 0); do
      PATH_COORDS+=( "${i},${j}" )
    done
  fi
done

# Infinite loop: for each coordinate, publish then wait 2 seconds
while true; do
  for coord in "${PATH_COORDS[@]}"; do
    for DEV in "${MICROS[@]}"; do
      AMP=$(( RANDOM % 1024 ))
      MSG="[ {\"amplitude\":${AMP}}, {\"devId\":\"${DEV}\",\"room\":\"${ROOM}\"} ]"
      docker exec -i "$MOSQ_CONTAINER" mosquitto_pub -t "$TOPIC" -m "$MSG"
    done
    sleep 2
  done
done
