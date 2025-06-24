#!/usr/bin/env bash

MYSQL_CMD="/opt/lampp/bin/mysql sae24db"

# Infinite loop: compute a new estimate every 2 seconds
while true; do
  # Insert a new estimated position into the positions_estimees table
  #   coord_x = Σ(pos_x * amplitude) / Σ(amplitude)
  #   coord_y = Σ(pos_y * amplitude) / Σ(amplitude)
  $MYSQL_CMD -e "\
INSERT INTO positions_estimees (coord_x, coord_y, ts)
SELECT
  SUM(c.pos_x * m.amplitude)  / SUM(m.amplitude)  AS coord_x,
  SUM(c.pos_y * m.amplitude)  / SUM(m.amplitude)  AS coord_y,
  NOW() AS ts
FROM
  (SELECT * FROM son_mesures ORDER BY ts DESC LIMIT 3) AS m
  JOIN capteurs AS c
    ON m.capteur_id = c.id;"
  
  # Wait 2 seconds before the next calculation
  sleep 2
done
