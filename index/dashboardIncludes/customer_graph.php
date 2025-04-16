<?php
function customer_graph() {
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
        <h4 style="margin: 0; color: #999">Antal kunder per tidspunkt gennemsnit de sidste 90 dage</h4>
        <div id="customerHeatmap" style="flex: 1; width: 100%; height: 100%;"></div>
    </div>
    ';
    
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
    for ($i = 0; $i < 90; $i++) {
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
    
    // Output JavaScript with ApexCharts
    echo "<script>
    document.addEventListener('DOMContentLoaded', function() {
        const series = " . json_encode($heatmapSeries) . ";
        const maxValue = " . json_encode($maxValue) . ";
        
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
                                name: 'Lav'
                            },
                            {
                                from: maxValue * 0.05,
                                to: maxValue * 0.25,
                                color: '#C0DEFF',
                                name: 'Medium'
                            },
                            {
                                from: maxValue * 0.25,
                                to: maxValue * 0.50,
                                color: '#90CAF9',
                                name: 'Høj'
                            },
                            {
                                from: maxValue * 0.50,
                                to: maxValue * 0.75,
                                color: '#42A5F5',
                                name: 'Meget høj'
                            },
                            {
                                from: maxValue * 0.75,
                                to: maxValue,
                                color: '#1E88E5',
                                name: 'Ekstrem'
                            }
                        ]
                    }
                }
            }
        };

        const chart = new ApexCharts(document.querySelector('#customerHeatmap'), options);
        chart.render();
    });
    </script>";
}
?>