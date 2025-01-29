<?php
function varegrp_doughnut($startDate, $endDate) {
    // Globals for account limits
    global $kontomin, $kontomaks, $regnaar;

    // HTML for the chart container
    ?>
    <div style="
      flex: 1.5;
      min-width: 500px;
      background-color: #fff;
      border-radius: 5px;
      box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;
      padding: 1.4em 2em;
      height: 350px;
    ">
      <h4 style="margin: 0; color: #999">
        Omsætning baseret på varegrupper i det gældene regnskabsår
      </h4>
      <div style="padding-top: 22.4px"></div>
      <div style="flex: 1; width: 100%">
        <canvas id="varegrp_doughnut" height="260px"></canvas>
      </div>

    </div>
    <?php

    // SQL query to fetch the data
    $qtxt = "
    SELECT
      sum(OL.pris) as pris, V.gruppe as gruppe, G.beskrivelse
    FROM ordrelinjer OL
    JOIN ordrer O ON O.id = OL.ordre_id
    JOIN varer V ON  V.varenr = OL.varenr
    JOIN grupper G ON G.art = 'VG' AND G.fiscal_year = '$regnaar' AND G.kodenr = V.gruppe 
    WHERE O.fakturadate >= '$startDate'
    AND O.fakturadate <= '$endDate'
    AND O.status = 4
    GROUP BY V.gruppe, G.beskrivelse";
    $result = db_select($qtxt, __FILE__ . " linje " . __LINE__);

    // Initialize arrays for chart data
    $labels = [];
    $data = [];

    // Process the database result into labels and data
    while ($r = db_fetch_array($result)) {
      $labels[] = $r['beskrivelse'];
      $data[] = $r['pris'];
    }

    // Output the chart script
    ?>
    <script>
      const ctx3 = document.getElementById('varegrp_doughnut').getContext('2d');

      new Chart(ctx3, {
        type: 'doughnut',
        data: {
          labels: ['<?php print implode("','", $labels); ?>'],
          datasets: [
            {
              data: [<?php print implode(",", $data); ?>],
            }
          ]
        },
        options: {
          responsive: true,
          interaction: {
            intersect: false,
          },
          maintainAspectRatio: false,
          plugins: {
            legend: {
					    display: false // Hides the legend
            },
          }
        },
      });
    </script>
    <?php
}
?>
