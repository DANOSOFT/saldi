<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- payments/flatpay.php --- lap 4.1.0 --- 2024.02.27 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2024-2024 saldi.dk aps
// ----------------------------------------------------------------------
// 20240209 PHR Added indbetaling
// 20240227 PHR Added $printfile and call to saldiprint.php

@session_start();
$s_id = session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/stdFunc/dkDecimal.php");
include("../includes/stdFunc/usDecimal.php");

$returside = if_isset($_GET["returside"], "../index/dashboard.php");
$valg = "kalender";

include("crmIncludes/topLine.php");
include("crmIncludes/getFilter.php");
include("crmIncludes/displayTopButtons.php");

// Configuration Constants
define('CONTACTED_COLOR', '#00ff00');
define('TO_CONTACT_COLOR', '#ff0000');
define('NOT_CONTACTED_COLOR', '#ffff00');

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $action = if_isset($_GET["action"], "");
    $id = if_isset($_GET["id"], "");
    $returside = if_isset($_GET["returside"], "");

    // Validate inputs
    if ($action && $id && $returside) {
        switch ($action) {
            case 'toggle':
                // Sanitize the ID input
                $id = (int)$id;

                // Update the completion status
                $qtxt = "UPDATE historik 
                SET completed = NOT COALESCE(completed, TRUE) 
                WHERE id = $id";
                echo $qtxt;
                db_modify($qtxt, __FILE__ . " linje " . __LINE__);

                echo "<script>window.location.replace('$returside');</script>";
                exit;
        }
    }
}

// Main Execution
function render_crm_calendar()
{
    // Set locale for date handling
    setlocale(LC_TIME, 'da_DK.UTF-8');

    // Determine selected year and month
    $selected_year = $_GET['year'] ?? date('Y');
    $selected_month = $_GET['month'] ?? date('n');

    render_page_header();
    render_month_navigation($selected_year, $selected_month);
    render_calendar($selected_year, $selected_month);
}

function render_page_header()
{
?>
    <style>
        .calendar-wrapper {
            width: calc(100vw - 20px);
            height: calc(100vh - 100px);
            overflow-x: scroll;
        }
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            width: calc(100vw * 1.38);
            gap: 5px;
        }

        .calendar .day {
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            padding: 10px;
        }

        .card {
            margin: 5px 0;
            border-left-width: 3px;
            border-left-style: solid;
        }
    </style>
<?php
}

function render_month_navigation($selected_year, $selected_month)
{
    $current_year = date('Y');
    // Preserve existing GET parameters
    $current_params = $_GET;
?>
    <form method="GET" id="monthForm">
        <div class="month-navigation">
            <button type="button" id="prevMonth">&larr;</button>
            <select name="year" onchange="this.form.submit()">
                <?php
                for ($year = $current_year-5; $year <= $current_year + 5; $year++) {
                    $selected = $year == $selected_year ? 'selected' : '';
                    echo "<option value='$year' $selected>$year</option>";
                }
                ?>
            </select>
            <select name="month" onchange="this.form.submit()">
                <?php
                for ($month = 1; $month <= 12; $month++) {
                    $month_name = strftime('%B', mktime(0, 0, 0, $month, 1));
                    $selected = $month == $selected_month ? 'selected' : '';
                    echo "<option value='$month' $selected>" . ucfirst($month_name) . "</option>";
                }
                ?>
            </select>
            <button type="button" id="nextMonth">&rarr;</button>
        </div>
        <?php
        // Add hidden inputs for all existing GET parameters except year and month
        foreach ($current_params as $key => $value) {
            if ($key !== 'year' && $key !== 'month') {
                echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
            }
        }
        ?>
    </form>
    <script>
        document.getElementById('prevMonth').addEventListener('click', function() {
            var form = document.getElementById('monthForm');
            var monthSelect = form.querySelector('select[name="month"]');
            var yearSelect = form.querySelector('select[name="year"]');

            if (monthSelect.selectedIndex > 0) {
                monthSelect.selectedIndex--;
            } else {
                monthSelect.selectedIndex = 11;
                yearSelect.selectedIndex = Math.max(0, yearSelect.selectedIndex - 1);
            }
            form.submit();
        });

        document.getElementById('nextMonth').addEventListener('click', function() {
            var form = document.getElementById('monthForm');
            var monthSelect = form.querySelector('select[name="month"]');
            var yearSelect = form.querySelector('select[name="year"]');

            if (monthSelect.selectedIndex < 11) {
                monthSelect.selectedIndex++;
            } else {
                monthSelect.selectedIndex = 0;
                yearSelect.selectedIndex = Math.min(yearSelect.options.length - 1, yearSelect.selectedIndex + 1);
            }
            form.submit();
        });
    </script>
<?php
}

function render_calendar($year, $month)
{
    $first_day = mktime(0, 0, 0, $month, 1, $year);
    $days_in_month = date('t', $first_day);
    $first_day_weekday = date('N', $first_day);

    $days_of_week = ['Man', 'Tir', 'Ons', 'Tor', 'Fre', 'Lør', 'Søn'];
    $today = date('Y-m-d');

    echo '<div class="calendar-wrapper">';
    echo '<div class="calendar">';

    // Render day headers
    foreach ($days_of_week as $day) {
        echo "<div class='calendar-header'>$day</div>";
    }

    // Render padding before first day
    for ($i = 1; $i < $first_day_weekday; $i++) {
        echo "<div class='empty'></div>";
    }

    // Render days
    for ($day = 1; $day <= $days_in_month; $day++) {
        $current_date = sprintf("%d-%02d-%02d", $year, $month, $day);
        $day_class = $current_date == $today ? 'day today' : 'day';

        echo "<div class='$day_class'>";
        echo "<div class='day-header'>$day";
        echo "<a href='crmopret.php?date=$current_date&returside=" . urlencode($_SERVER['REQUEST_URI']) . "'>+</a>";
        echo "</div>";

        // Fetch and render tasks for this day
        render_day_tasks($current_date);

        echo "</div>";
    }

    echo '</div>';
    echo '</div>';
}

function render_day_tasks($date)
{
    $qtxt = "SELECT H.* 
        FROM historik H
        RIGHT JOIN adresser A ON A.id = H.konto_id AND " . get_filter() . " 
        WHERE H.kontaktes = '$date'";
    $query = db_select(
        $qtxt,
        __FILE__ . " linje " . __LINE__
    );
    
    while ($task = db_fetch_array($query)) {
        render_task_card($task['konto_id'], $task['id']);
    }
}

function render_task_card($konto_id, $historik_id)
{
    // Fetch customer details
    $customer_query = db_select("SELECT * FROM adresser WHERE id='$konto_id' LIMIT 1", __FILE__ . " linje " . __LINE__);
    $customer = db_fetch_array($customer_query);

    // Fetch task details
    $task_query = db_select("SELECT * FROM historik WHERE id='$historik_id' LIMIT 1", __FILE__ . " linje " . __LINE__);
    $task = db_fetch_array($task_query);

    // Determine card color
    $color = determine_task_color($customer, $task);

    // Get current URL for return path
    $return_url = urlencode($_SERVER['REQUEST_URI']);
?>
    <div class="card" style="border-left-color: <?php echo $color; ?>;">
        <div class="card-header tooltip-container">
            <h3><?php echo htmlspecialchars($customer['firmanavn']); ?></h3>
            <div class="tooltip"><?php echo htmlspecialchars($customer['firmanavn']); ?></div>
        </div>
        <div class="tooltip-container">
            <p class="card-note"><?php echo htmlspecialchars($task['notat']); ?></p>
            <div class="tooltip"><?php echo htmlspecialchars($task['notat']); ?></div>
        </div>
        <div class="card-footer">
            <p><?php echo dkdato($task['kontaktet']); ?></p>
            <div class="tooltip"><?php echo dkdato($task['kontaktet']); ?></div>
            <div class="action-buttons">
                <!-- Complete Task Button -->
                <a href="?action=toggle&id=<?php echo $historik_id; ?>&returside=<?php echo $return_url; ?>"
                    class="action-button"
                    title="Mark as Complete">
                    <?php
                    if ($task['completed'] == "t") {
                    ?>
                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000">
                            <path d="M268-240 42-466l57-56 170 170 56 56-57 56Zm226 0L268-466l56-57 170 170 368-368 56 57-424 424Zm0-226-57-56 198-198 57 56-198 198Z" />
                        </svg>
                    <?php
                    } else {
                    ?>
                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000">
                            <path d="M382-240 154-468l57-57 171 171 367-367 57 57-424 424Z" />
                        </svg>
                    <?php
                    }
                    ?>
                </a>
                <!-- View Contact Button -->
                <a href="../debitor/debitorkort.php?id=<?php echo $konto_id; ?>&returside=<?php echo $return_url; ?>"
                    class="action-button"
                    title="View Contact">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000">
                        <path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-160v-112q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v112H160Zm80-80h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T560-640q0-33-23.5-56.5T480-720q-33 0-56.5 23.5T400-640q0 33 23.5 56.5T480-560Zm0-80Zm0 400Z" />
                    </svg>
                </a>
                <!-- View Notes Button -->
                <a href="../debitor/historikkort.php?id=<?php echo $konto_id; ?>&returside=<?php echo $return_url; ?>"
                    class="action-button"
                    title="View Notes">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000">
                        <path d="M240-400h320v-80H240v80Zm0-120h480v-80H240v80Zm0-120h480v-80H240v80ZM80-80v-720q0-33 23.5-56.5T160-880h640q33 0 56.5 23.5T880-800v480q0 33-23.5 56.5T800-240H240L80-80Zm126-240h594v-480H160v525l46-45Zm-46 0v-480 480Z" />
                    </svg>
                </a>
            </div>
        </div>
    </div>
<?php
}

function determine_task_color($customer, $task)
{
    $kontaktes_timestamp = strtotime($customer['kontaktes']);
    $today_timestamp = strtotime(date('Y-m-d'));

    if ($task['completed'] == "t") {
        return CONTACTED_COLOR;
    } elseif ($kontaktes_timestamp < $today_timestamp) {
        return TO_CONTACT_COLOR;
    } else {
        return NOT_CONTACTED_COLOR;
    }
}

// Execute the main function
render_crm_calendar();
?>

<style>
    /* Base card styles */
    .card {
        box-shadow: 0 3px 10px rgb(0 0 0 / 0.2);
        box-sizing: border-box;
        margin: .5em 0;
        background-color: #ffffff;
        border-radius: 3px;
        padding: 0.6em 0.9em;
        max-width: calc(100vw / 5 - 20px);
        width: 100%;
    }

    /* Header and footer styles */
    .card-header,
    .card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        min-width: 0;
    }

    .card-header h3,
    .card-footer p {
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
    }

    /* Note styles */
    .card-note {
        width: 100%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        padding: 10px 0;
        margin: 0;
    }

    /* Tooltip container */
    .tooltip-container {
        position: relative;
        width: 100%;
    }

    /* Tooltip styles */
    .tooltip {
        display: none;
        position: fixed;
        background: #333;
        color: white;
        padding: 8px;
        border-radius: 4px;
        z-index: 1000;
        max-width: 250px;
        word-wrap: break-word;
        white-space: normal;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        font-size: 14px;
        line-height: 1.4;
    }

    /* Show tooltip on hover */
    .tooltip-container:hover .tooltip {
        display: block;
    }

    /* Position tooltip with JavaScript */
    .tooltip-container {
        cursor: default;
    }

    .action-buttons {
        display: flex;
        gap: 1px;
        margin-left: auto;
    }

    .action-button {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4px;
        border-radius: 4px;
        transition: background-color 0.2s;
        text-decoration: none;
    }

    .action-button:hover {
        background-color: rgba(0, 0, 0, 0.1);
    }

    .action-button svg {
        display: block;
    }

    .card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 2px;
    }

    .card-footer p {
        flex-shrink: 0;
        margin-right: auto;
    }

    .day-header {
        display: flex;
        justify-content: space-between;
    }

    .day-header a {
        text-decoration: none;
    }
    .today {
        border: 3px red solid !important;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle tooltip positioning
        document.addEventListener('mousemove', function(e) {
            const tooltipContainer = e.target.closest('.tooltip-container');
            if (tooltipContainer) {
                const tooltip = tooltipContainer.querySelector('.tooltip');
                if (tooltip) {
                    // Get viewport dimensions
                    const viewportWidth = window.innerWidth;
                    const viewportHeight = window.innerHeight;

                    // Calculate position
                    let left = e.pageX + 10;
                    let top = e.pageY + 10;

                    // Check right edge
                    if (left + tooltip.offsetWidth > viewportWidth) {
                        left = e.pageX - tooltip.offsetWidth - 10;
                    }

                    // Check bottom edge
                    if (top + tooltip.offsetHeight > viewportHeight) {
                        top = e.pageY - tooltip.offsetHeight - 10;
                    }

                    // Apply position
                    tooltip.style.left = left + 'px';
                    tooltip.style.top = top + 'px';
                }
            }
        });
    });
</script>