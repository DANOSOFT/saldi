<?php
function weekly_graph() {
    global $kontomin, $kontomaks, $regnaar, $sprog_id;
    global $regnstart, $regnslut, $sameDayLastYear, $currentDay;

print '
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
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h4 style="margin: 0; color: #999">'.findtekst('2670|Din daglige omsætning sammenlignet med sidste år, ekskl. moms', $sprog_id).'</h4>
        <select id="weekSelector" style="width: 150px; padding: 0.5em; border-radius: 5px; border: 1px solid #ccc; font-size: 0.9em;">';

        $currentWeek = isset($_GET['week']) ? (int)$_GET['week'] : (int)date('W');
        for ($i = 1; $i <= 52; $i++) {
            $selected = ($i === $currentWeek) ? 'selected' : '';
            print "<option value=\"$i\" $selected>".findtekst('2669|Uge', $sprog_id)." $i</option>";
        }

print '
        </select>
    </div>

    <div id="weeklyChartContainer" style="flex: 1; width: 100%; height: 100%;">
        <canvas id="weeklyChart"></canvas>
    </div>
</div>
';

print '

<script>
  const weeklyCt = document.getElementById("weeklyChart").getContext("2d");
  let chart;

  const kontomin = ' . json_encode($kontomin) . ';
  const kontomaks = ' . json_encode($kontomaks) . ';
  const regnaar = ' . json_encode($regnaar) . ';
  const regstart = ' . json_encode($regnstart) . ';
  const regslut = ' . json_encode($regnslut) . ';
  const dayLastYear = ' . json_encode($sameDayLastYear) . ';
  const currentDay = ' . json_encode($currentDay) . ';

  // Helper: add days to a date string
  function addDays(dateStr, days) {
    const date = new Date(dateStr);
    date.setDate(date.getDate() + days);
    return date.toISOString().split("T")[0]; // "YYYY-MM-DD"
  }

  function fetchAndRenderChart(week) {
    fetch(`weekly_graph_data.php?dayLastYear=${dayLastYear}&currentDay=${currentDay}&week=${week}&kontomin=${encodeURIComponent(kontomin)}&kontomaks=${encodeURIComponent(kontomaks)}&regnaar=${encodeURIComponent(regnaar)}&regstart=${encodeURIComponent(regstart)}&regslut=${encodeURIComponent(regslut)}`)
      .then(response => response.json())
      .then(data => {
        if (chart) chart.destroy();

        const fetchedDayLastYear = data.dayLastYear; // Array of dates
        const fetchedCurrentDay = data.currentDay;   // Array of dates

        const backgroundColorNow = [];
        const backgroundColorLast = [];

        const currentDates = [];
        const lastYearDates = [];

        // Use corresponding dates directly from arrays
        for (let i = 0; i < data.revenue_now.length; i++) {
          // Use date strings from arrays directly, or addDays if needed
          // Assuming these arrays already contain proper date strings for each index
          currentDates.push(fetchedCurrentDay[i]);
          lastYearDates.push(fetchedDayLastYear[i]);

          if (data.revenue_now[i] > data.revenue_last[i]) {
            backgroundColorNow.push("rgba(0, 200, 0, 0.5)");
            backgroundColorLast.push("rgba(200, 0, 0, 0.5)");
          } else {
            backgroundColorNow.push("rgba(0, 200, 0, 0.5)");  
            backgroundColorLast.push("rgba(200, 0, 0, 0.5)");
          }
        }

        chart = new Chart(weeklyCt, {
          type: "bar",
          data: {
            labels: data.dayLabels,
            datasets: [
              {
                label: "' . findtekst('1166|Omsætning', $sprog_id) . '" + " " + fetchedDayLastYear[0] + " to " + fetchedDayLastYear[fetchedDayLastYear.length - 1],
                data: data.revenue_last,
                borderWidth: 1,
                backgroundColor: backgroundColorLast
              },
              {
                label: "' . findtekst('1166|Omsætning', $sprog_id) . '"  + " " + fetchedCurrentDay[0] + " to " + fetchedCurrentDay[fetchedCurrentDay.length - 1],
                data: data.revenue_now,
                borderWidth: 1,
                backgroundColor: backgroundColorNow
              }
            ]
          },
          options: {
            interaction: {
              mode: "index",
              intersect: false
            },
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                  callback: value => value + " kr"
                }
              },
              x: {
                ticks: {
                  autoSkip: false
                }
              }
            },
            plugins: {
              tooltip: {
                callbacks: {
                  label: function(context) {
                    const index = context.dataIndex;
                    const datasetIndex = context.datasetIndex;
                    const date = datasetIndex === 0 ? lastYearDates[index] : currentDates[index];
                    return context.dataset.label + " (" + date + "): " + context.parsed.y + " kr";
                  }
                }
              }
            }
          }
        });

      })
      .catch(err => console.error("Error fetching data:", err));
  }

  const selector = document.getElementById("weekSelector");
  selector.addEventListener("change", e => {
    fetchAndRenderChart(e.target.value);
  });

  fetchAndRenderChart(selector.value);
</script>

';
}
?>
