<?php
function customer_graph() {
    global $sprog_id;
    # Customer Heatmap

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
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h4 style="margin: 0; color: #999">'.findtekst('2577|Gennemsnitligt antal kunder pr. time de sidste', $sprog_id).'</h4>
            <select style="width: 150px; padding: 0.5em; border-radius: 5px; border: 1px solid #ccc;" id="customerHeatmapSelect">
                <option value="30">30 '.strtolower(findtekst('2578|Dage', $sprog_id)).'</option>
                <option value="90">90 '.strtolower(findtekst('2578|Dage', $sprog_id)).'</option>
                <option value="180">180 '.strtolower(findtekst('2578|Dage', $sprog_id)).'</option>
                <option value="365">365 '.strtolower(findtekst('2578|Dage', $sprog_id)).'</option>
            </select>
        </div>
        <div id="customerHeatmap" style="flex: 1; width: 100%; height: 100%;"></div>
    </div>
    ';
    $days = 30; // Default to 30 days
    if (isset($_POST['days'])) {
        $days = intval($_POST['days']);
    }
    $days = max(1, min($days, 365)); // Ensure days is between 1 and 365
    $weekdayDates = array(
        'Monday' => array(),
        'Tuesday' => array(),
        'Wednesday' => array(),
        'Thursday' => array(),
        'Friday' => array(),
        'Saturday' => array(),
        'Sunday' => array()
    );
    
    $currentDate = new DateTime();
    for ($i = 0; $i < $days; $i++) {
        $date = clone $currentDate;
        $date->sub(new DateInterval('P'.$i.'D'));
        $weekdayName = $date->format('l');
        if (array_key_exists($weekdayName, $weekdayDates)) {
            $weekdayDates[$weekdayName][] = $date->format('Y-m-d');
        }
    }
    
    $weekdayValues = array(
        'Monday' => generateArray(),
        'Tuesday' => generateArray(),
        'Wednesday' => generateArray(),
        'Thursday' => generateArray(),
        'Friday' => generateArray(),
        'Saturday' => generateArray(),
        'Sunday' => generateArray()
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
    
    // Format data for ApexCharts heatmap
    $heatmapSeries = [];
    $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $hours = ['00', '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', 
              '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23'];
    
    // Find max value for better color scaling
    $maxValue = 0;
    foreach ($weekdayValues as $values) {
        foreach ($values as $value) {
            if ($value > $maxValue) {
                $maxValue = $value;
            }
        }
    }
    
    // Build series data for each weekday
    foreach ($weekdays as $index => $weekday) {
        $data = [];
        foreach ($hours as $hour) {
            $value = isset($weekdayValues[$weekday][$hour]) ? $weekdayValues[$weekday][$hour] : 0;
            $data[] = [
                'x' => $hour . ':00',
                'y' => round($value, 1)
            ];
        }
        
        $heatmapSeries[] = [
            'name' => $weekday,
            'data' => $data
        ];
    }
    
    // Output JavaScript with ApexCharts and add event listener for select change
    echo "<script>
    document.addEventListener('DOMContentLoaded', function() {
        let chart;
        
        function initChart(series, maxValue) {
            const options = {
                series: series,
                chart: {
                    height: 310,
                    type: 'heatmap',
                    toolbar: {
                        show: false
                    }
                },
                dataLabels: {
                    enabled: false
                },
                colors: ['#008FFB'],
                title: {
                    show: false
                },
                xaxis: {
                    type: 'category',
                    tickPlacement: 'on',
                    labels: {
                        rotate: -45,
                        rotateAlways: true
                    }
                },
                yaxis: {
                    reversed: true
                },
                tooltip: {
                    y: {
                        formatter: function(value) {
                            return parseFloat(value).toFixed(1) + ' customers';
                        }
                    }
                },
                plotOptions: {
                    heatmap: {
                        shadeIntensity: 0.5,
                        radius: 0,
                        colorScale: {
                            ranges: [
                                {
                                    from: 0,
                                    to: maxValue * 0.05,
                                    color: '#EBEDF0',
                                    name: '".findtekst('2579|Lav', $sprog_id)."'
                                },
                                {
                                    from: maxValue * 0.05,
                                    to: maxValue * 0.25,
                                    color: '#C0DEFF',
                                    name: '".findtekst('2580|Medium', $sprog_id)."'
                                },
                                {
                                    from: maxValue * 0.25,
                                    to: maxValue * 0.50,
                                    color: '#90CAF9',
                                    name: '".findtekst('2581|Høj', $sprog_id)."'
                                },
                                {
                                    from: maxValue * 0.50,
                                    to: maxValue * 0.75,
                                    color: '#42A5F5',
                                    name: '".findtekst('2582|Meget høj', $sprog_id)."'
                                },
                                {
                                    from: maxValue * 0.75,
                                    to: maxValue,
                                    color: '#1E88E5',
                                    name: '".findtekst('2583|Ekstrem', $sprog_id)."'
                                }
                            ]
                        }
                    }
                }
            };

            if (chart) {
                chart.destroy();
            }
            
            chart = new ApexCharts(document.querySelector('#customerHeatmap'), options);
            chart.render();
        }
        
        // Initialize chart with default data
        const initialSeries = " . json_encode($heatmapSeries) . ";
        const initialMaxValue = " . json_encode($maxValue) . ";
        initChart(initialSeries, initialMaxValue);
        
        // Add event listener for select change
        document.getElementById('customerHeatmapSelect').addEventListener('change', function() {
            const days = this.value;
            const container = document.getElementById('customerHeatmap');
            
            // Show loading indicator
            container.innerHTML = '<div style=\"display: flex; justify-content: center; align-items: center; height: 100%;\"><div style=\"border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite;\"></div></div>';
            
            // Fetch new data based on selected days
            fetch('customer_graph_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'days=' + days
            })
            .then(response => response.json())
            .then(data => {
                // Initialize chart with new data
                initChart(data.series, data.maxValue);
            })
            .catch(error => {
                console.error('Error fetching data:', error);
                container.innerHTML = '<div style=\"display: flex; justify-content: center; align-items: center; height: 100%;\">Error loading data</div>';
            });
        });
    });
    
    // Add CSS for loading spinner
    document.head.insertAdjacentHTML('beforeend', `
        <style>
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    `);
    </script>";
}
?>