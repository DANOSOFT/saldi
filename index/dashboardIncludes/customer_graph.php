<?php
function customer_graph() {
	# Omsætningsgraf
	echo '
<div style="
	flex: 2;
	min-width: 500px;
	background-color: #fff;
	border-radius: 5px;
	box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;
	padding: 1.4em 2em;
">
	<h4 style="margin: 0; color: #999">Antal kunder per tidspunkt gennesnit de sidste 30 dage</h4>
	<div style="flex: 1; width: 100%">
	  <canvas id="customerChart"></canvas>
	</div>
	</div>
	';


	$weekdayDates = array(
	    'Monday'    => array(),
	    'Tuesday'   => array(),
	    'Wednesday' => array(),
	    'Thursday'  => array(),
	    'Friday'    => array(),
	    'Saturday'  => array(),
	    'Sunday'    => array()
	);

	$currentDate = new DateTime();

	for ($i = 0; $i < 30; $i++) {

	    $date = clone $currentDate;
	    $date->sub(new DateInterval('P'.$i.'D'));

	    $weekdayName = $date->format('l');

	    if (array_key_exists($weekdayName, $weekdayDates)) {
	        $weekdayDates[$weekdayName][] = $date->format('Y-m-d');

	    }
	}

	$weekdayValues = array(
	    'Monday'    => generateArray(),
	    'Tuesday'   => generateArray(),
	    'Wednesday' => generateArray(),
	    'Thursday'  => generateArray(),
	    'Friday'    => generateArray(),
	    'Saturday'  => generateArray(),
	    'Sunday'    => generateArray()
	);

        foreach ($weekdayDates as $weekday => $dates) {
            $clause = implode("' OR ordredate='", $dates);

            $q = db_select("
                SELECT SUBSTRING(tidspkt, 1, 2) AS hour_range, COUNT(*) AS count
                FROM ordrer
                WHERE (ordredate='$clause')
                GROUP BY hour_range
            ", __FILE__ . " linje " . __LINE__);

	    
	    while ($r = db_fetch_array($q)) {
                $hour_range = $r['hour_range'];
                $count = $r['count'] / sizeof($dates);
                $weekdayValues[$weekday][$hour_range] = $count;
	    }
        }


	echo "
	<script>
	  const ctx2 = document.getElementById('customerChart');

	  new Chart(ctx2, {
	    type: 'line',
	    data: {
	      labels: ['00:00', '01:00','02:00','03:00','04:00','05:00','06:00','07:00','08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00','22:00','23:00'],
	      datasets: [";

	foreach ($weekdayValues as $weekday => $valuess) {
	      echo "{
		label: '$weekday',
		data: ['";
		echo implode("','", $valuess);
		echo "'],
			borderWidth: 1,
			pointStyle: false,
		      },";
	}


	echo "]
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
		  beginAtZero: true,
		},
	      }
	    }
	  });
	</script>";
}

?>