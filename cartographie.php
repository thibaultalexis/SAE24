<?php
// cartographie.php

// Return the last 10 positions as JSON if ?action=fetch is set
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    header('Content-Type: application/json');
    // Connect to MySQL
    $mysqli = new mysqli('localhost', 'sae24', 'PassRoot', 'sae24db');
    if ($mysqli->connect_error) {
        http_response_code(500);
        echo json_encode(['error' => 'Connection failed']);
        exit;
    }
    // Query the 10 most recent estimated positions
    $res = $mysqli->query(
        "SELECT coord_x, coord_y, ts
         FROM positions_estimees
         ORDER BY ts DESC
         LIMIT 10"
    );
    $data = [];
    // Prepend rows so earliest comes first
    while ($row = $res->fetch_assoc()) {
        array_unshift($data, $row);
    }
    echo json_encode($data);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>SAÉ24 – Cartographie</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="styles/style2.css">
</head>
<body>
  <header>
    <nav>
      <ul>
        <li><a href="index.html">Accueil</a></li>
        <li><a href="presentation.html">Présentation</a></li>
        <li><a href="simulation.html">Simulation</a></li>
        <li><a href="technique.html">Technique</a></li>
        <li><a href="equipe.html">Équipe</a></li>
        <li><a href="cartographie.php" class="active">Cartographie</a></li>
        <li><a href="mentions-legales.html">Mentions légales</a></li>
      </ul>
    </nav>
  </header>

  <div class="container-flex">
    <div id="map-container" class="case">
      <!-- Canvas for map drawing -->
      <canvas id="map" width="800" height="800"></canvas>
    </div>
    <div id="legend">
      <h3>Légende</h3>
      <div>
        <span class="legend-symbol" style="background: var(--mic-color);"></span>
        Microphone
      </div>
      <div>
        <span class="legend-symbol" style="background: var(--obj-color);"></span>
        Current position
      </div>
      <div>
        <span class="legend-symbol" style="background: var(--prev-obj-color);"></span>
        Previous position
      </div>
    </div>
  </div>

  <table id="data-table" class="case">
    <thead>
      <tr>
        <th>Horodatage</th>
        <th>X (m)</th>
        <th>Y (m)</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>

  <footer>
    <ul>
      <li>IUT Blagnac</li>
      <li>Bismuth – Alexis – Nassereddine – Taho-Taza – Jaballah</li>
      <li>2025-2026</li>
    </ul>
  </footer>

  <script>
    // Get the CSS variable for microphone color
    const micColor = getComputedStyle(document.documentElement)
                       .getPropertyValue('--mic-color').trim();

    const canvas = document.getElementById('map');
    const ctx = canvas.getContext('2d');
    const gridSize = 16;    // 16×16 grid
    const roomSize = 8;     // 8 meters square
    const cellPx = canvas.width / gridSize;

    // Fixed sensor positions (center of cells)
    const sensors = [
      { x: 0.25, y: 0.25 },
      { x: 0.25, y: 7.75 },
      { x: 7.75, y: 7.75 }
    ];

    // Convert real coordinates to canvas X
    function toPxX(x) {
      return (x / roomSize) * canvas.width;
    }
    // Convert real coordinates to canvas Y
    function toPxY(y) {
      return canvas.height - (y / roomSize) * canvas.height;
    }

    // Draw grid and sensors
    function drawGrid() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);

      for (let i = 0; i <= gridSize; i++) {
        // Thin lines every 0.5m
        ctx.strokeStyle = '#eee';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(i * cellPx, 0);
        ctx.lineTo(i * cellPx, canvas.height);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(0, i * cellPx);
        ctx.lineTo(canvas.width, i * cellPx);
        ctx.stroke();

        // Thicker lines every 1m
        if (i % 2 === 0) {
          ctx.strokeStyle = '#ccc';
          ctx.lineWidth = 2;
          ctx.beginPath();
          ctx.moveTo(i * cellPx, 0);
          ctx.lineTo(i * cellPx, canvas.height);
          ctx.stroke();
          ctx.beginPath();
          ctx.moveTo(0, i * cellPx);
          ctx.lineTo(canvas.width, i * cellPx);
          ctx.stroke();
        }
      }

      // Draw microphone markers
      ctx.fillStyle = micColor;
      sensors.forEach(s => {
        const px = toPxX(s.x);
        const py = toPxY(s.y);
        ctx.beginPath();
        ctx.arc(px, py, 8, 0, 2 * Math.PI);
        ctx.fill();
      });
    }

    // Draw a line between two points
    function drawLine(pt1, pt2) {
      ctx.strokeStyle = 'var(--arrow-color)';
      ctx.lineWidth = 2;
      const x1 = toPxX(pt1.coord_x);
      const y1 = toPxY(pt1.coord_y);
      const x2 = toPxX(pt2.coord_x);
      const y2 = toPxY(pt2.coord_y);
      ctx.beginPath();
      ctx.moveTo(x1, y1);
      ctx.lineTo(x2, y2);
      ctx.stroke();
    }

    // Draw a point with given opacity
    function drawPoint(pt, alpha) {
      ctx.fillStyle = `rgba(211,47,47,${alpha})`;
      const px = toPxX(pt.coord_x);
      const py = toPxY(pt.coord_y);
      ctx.beginPath();
      ctx.arc(px, py, 10, 0, 2 * Math.PI);
      ctx.fill();
    }

    // Fetch data and update map and table
    function updateData() {
      fetch('?action=fetch')
        .then(response => response.json())
        .then(data => {
          drawGrid();
          const n = data.length;
          if (n >= 2) {
            const prev = data[n - 2];
            const curr = data[n - 1];
            drawLine(prev, curr);
            drawPoint(prev, 0.4);
            drawPoint(curr, 1.0);
          } else if (n === 1) {
            drawPoint(data[0], 1.0);
          }

          // Update table rows
          const tbody = document.querySelector('#data-table tbody');
          tbody.innerHTML = '';
          data.forEach(row => {
            const tr = document.createElement('tr');
            const date = new Date(row.ts);
            tr.innerHTML = `
              <td>${date.toLocaleString()}</td>
              <td>${parseFloat(row.coord_x).toFixed(2)}</td>
              <td>${parseFloat(row.coord_y).toFixed(2)}</td>
            `;
            tbody.appendChild(tr);
          });
        });
    }

    // Initial draw and periodic updates
    drawGrid();
    updateData();
    setInterval(updateData, 2000);
  </script>
</body>
</html>
