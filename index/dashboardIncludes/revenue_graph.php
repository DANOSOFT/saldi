<?php
function revenue_graph($startDate, $endDate) {
  // Globals for account limits
  global $kontomin, $kontomaks;

  // HTML for the chart container
  echo '
  <div style="
    flex: 2;
    min-width: 500px;
    background-color: #fff;
    border-radius: 5px;
    box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;
    padding: 1.4em 2em;
  ">
    <h4 style="margin: 0; color: #999">
      Din omsætning sammenlignet med sidste år, ekskl. moms
    </h4>
    <div style="flex: 1; width: 100%">
      <canvas id="myChart"></canvas>
    </div>
  </div>
  ';

  // Parse dates to extract year and month
  $startYear = (int)date('Y', strtotime($startDate));
  $endYear = (int)date('Y', strtotime($endDate));
  $startMonth = (int)date('n', strtotime($startDate));
  $endMonth = (int)date('n', strtotime($endDate));

  $revenue_now = [];
  $revenue_last = [];

  // Loop through each month in the date range
  for ($month = $startMonth; $month <= $endMonth; $month++) {
    // Current year revenue
    $firstDayOfMonth = date('Y-m-d', mktime(0, 0, 0, $month, 1, $startYear));
    $lastDayOfMonth = date('Y-m-d', mktime(0, 0, 0, $month + 1, 0, $startYear));

    $query_now = "
    SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
    FROM transaktioner T
    WHERE T.transdate >= '$firstDayOfMonth'
    AND T.transdate <= '$lastDayOfMonth'
    AND T.kontonr >= $kontomin
    AND T.kontonr <= $kontomaks";

    $value_now = db_fetch_array(db_select($query_now, __FILE__ . " linje " . __LINE__))[0] ?? 0;
    $revenue_now[] = $value_now;

    // Last year revenue
    $firstDayOfLastYear = date('Y-m-d', mktime(0, 0, 0, $month, 1, $startYear - 1));
    $lastDayOfLastYear = date('Y-m-d', mktime(0, 0, 0, $month + 1, 0, $startYear - 1));

    $query_last = "
    SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
    FROM transaktioner T
    WHERE T.transdate >= '$firstDayOfLastYear'
    AND T.transdate <= '$lastDayOfLastYear'
    AND T.kontonr >= $kontomin
    AND T.kontonr <= $kontomaks";

    $value_last = db_fetch_array(db_select($query_last, __FILE__ . " linje " . __LINE__))[0] ?? 0;
    $revenue_last[] = $value_last;
  }

  // Prepare month labels
  $monthLabels = [];
  for ($month = $startMonth; $month <= $endMonth; $month++) {
    $monthLabels[] = date('F', mktime(0, 0, 0, $month, 1));
  }

  // Output the chart script
  echo "
  <script>
    const ctx = document.getElementById('myChart').getContext('2d');

    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['" . implode("','", $monthLabels) . "'],
        datasets: [
          {
            label: 'Omsætning " . ($startYear - 1) . "',
            data: [" . implode(',', $revenue_last) . "],
            backgroundColor: 'rgba(153, 102, 255, 0.6)',
            borderWidth: 1
          },
          {
            label: 'Omsætning $startYear',
            data: [" . implode(',', $revenue_now) . "],
            backgroundColor: 'rgba(75, 192, 192, 0.6)',
            borderWidth: 1
          },
        ]
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
?>
