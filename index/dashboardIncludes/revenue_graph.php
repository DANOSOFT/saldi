<?php
function revenue_graph($startDate, $endDate) {
    global $kontomin;
    global $kontomaks;
    global $regnaar;

    echo '
<div style="
    flex: 2;
    min-width: 500px;
    background-color: #fff;
    border-radius: 5px;
    box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;
    padding: 1.4em 2em;
    display: flex;
    flex-direction: column;
    height: 350px;
">
    <h4 style="margin: 0; color: #999">Din omsætning sammenlignet med sidste år, ekskl. moms</h4>
    <div style="flex: 1; width: 100%">
      <canvas id="myChart"></canvas>
    </div>
    </div>
    ';


    $qtxt = "SELECT beskrivelse FROM grupper WHERE kodenr='$regnaar' AND art='RA'";
    $startLabel = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))["beskrivelse"];
    $qtxt = "SELECT beskrivelse FROM grupper WHERE kodenr='".($regnaar-1)."' AND art='RA'";
    $r = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    if ($r) {
        $slutResult = db_fetch_array($r);
        $slutLabel = $slutResult ? $slutResult["beskrivelse"] : NULL;
    } else {
        $slutLabel = "";
    }

    // Parse start and end dates
    $startYear = date('Y', strtotime($startDate));
    $endYear = date('Y', strtotime($endDate));
    $startMonth = date('n', strtotime($startDate));
    $endMonth = date('n', strtotime($endDate));

    $revenue_now = [];
    $revenue_last = [];
    $monthLabels = [];

    // Loop through each year and month in the range
    for ($year = $startYear; $year <= $endYear; $year++) {
        $start = ($year == $startYear) ? $startMonth : 1;
        $end = ($year == $endYear) ? $endMonth : 12;

        for ($month = $start; $month <= $end; $month++) {
            // Generate dates for the current year
            $firstDayOfMonth = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
            $lastDayOfMonth = date('Y-m-d', mktime(0, 0, 0, $month + 1, 0, $year));

            $q = db_select("
            SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
            FROM transaktioner T
            WHERE T.transdate >= '$firstDayOfMonth'
            AND T.transdate <= '$lastDayOfMonth'
            AND T.kontonr >= $kontomin
            AND T.kontonr <= $kontomaks
            ", __FILE__ . " linje " . __LINE__);

            $value = db_fetch_array($q)[0];
            array_push($revenue_now, $value);

            // Generate dates for the previous year
            $firstDayOfLastYear = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year - 1));
            $lastDayOfLastYear = date('Y-m-d', mktime(0, 0, 0, $month + 1, 0, $year - 1));

            $q = db_select("
            SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
            FROM transaktioner T
            WHERE T.transdate >= '$firstDayOfLastYear'
            AND T.transdate <= '$lastDayOfLastYear'
            AND T.kontonr >= $kontomin
            AND T.kontonr <= $kontomaks
            ", __FILE__ . " linje " . __LINE__);

            $value = db_fetch_array($q)[0];
            array_push($revenue_last, $value);

            // Add month label
            $monthLabels[] = date('F', mktime(0, 0, 0, $month, 1, $year));
        }
    }

    // Generate chart with data for both current and last year
    echo "
    <script>
      const ctx = document.getElementById('myChart');

      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: ['".implode("','", $monthLabels)."'],
          datasets: [{
            label: 'Omsætning $slutLabel',
            data: ['".implode("','", $revenue_last)."'],
            borderWidth: 1
          },
          {
            label: 'Omsætning $startLabel',
            data: ['".implode("','", $revenue_now)."'],
            borderWidth: 1
          }]
        },
        options: {
          interaction: {
            mode: 'index',
            intersect: false,
          },
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true
            }
          }
        }
      });
    </script>";
}
