<?php
/**
 * Performance Log Viewer for Vareliste
 * View the performance log in a readable format
 */

$log_file = "../../temp/$db/vareliste_performance.log";

// Check if log file exists
if (!file_exists($log_file)) {
    echo "<h3>No performance log found</h3>";
    echo "<p>Load the vareliste.php page first to generate performance data.</p>";
    exit;
}

// Read and display log
$log_content = file_get_contents($log_file);
$lines = explode("\n", trim($log_content));

echo "<html><head><title>Vareliste Performance Log</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.log-entry { margin: 5px 0; padding: 5px; background-color: #f5f5f5; border-left: 3px solid #007cba; }
.slow { border-left-color: #ff6b6b; background-color: #fff5f5; }
.medium { border-left-color: #ffa500; background-color: #fff8f0; }
.fast { border-left-color: #51cf66; background-color: #f0fff4; }
.timestamp { color: #666; font-size: 0.9em; }
.message { font-weight: bold; }
.timing { color: #007cba; font-weight: bold; }
h1 { color: #333; }
.summary { background-color: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
.clear-btn { background-color: #ff6b6b; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-bottom: 20px; }
.clear-btn:hover { background-color: #ff5252; }
</style></head><body>";

echo "<h1>Vareliste Performance Log</h1>";

// Add clear log button
echo "<form method='post' style='display: inline;'>";
echo "<button type='submit' name='clear_log' class='clear-btn'>Clear Log</button>";
echo "</form>";

// Handle clear log request
if (isset($_POST['clear_log'])) {
    file_put_contents($log_file, '');
    echo "<p><strong>Log cleared!</strong></p>";
    echo "<script>setTimeout(function(){ location.reload(); }, 1000);</script>";
    exit;
}

echo "<div class='summary'>";
echo "<h3>Log Summary</h3>";
echo "<p>Total entries: " . count($lines) . "</p>";
echo "<p>Log file: $log_file</p>";
echo "<p><em>Refresh this page after loading vareliste.php to see updated performance data.</em></p>";
echo "</div>";

// Parse and display log entries
foreach ($lines as $line) {
    if (empty(trim($line))) continue;
    
    // Parse log entry
    if (preg_match('/\[(.*?)\] (.*)/', $line, $matches)) {
        $timestamp = $matches[1];
        $message = $matches[2];
        
        // Extract timing if present
        $timing = '';
        $class = 'log-entry';
        if (preg_match('/\(took ([\d.]+)ms\)/', $message, $timing_matches)) {
            $timing = $timing_matches[1];
            $timing_num = floatval($timing);
            
            // Color code based on timing
            if ($timing_num > 1000) {
                $class .= ' slow';
            } elseif ($timing_num > 100) {
                $class .= ' medium';
            } else {
                $class .= ' fast';
            }
        }
        
        echo "<div class='$class'>";
        echo "<span class='timestamp'>$timestamp</span> - ";
        echo "<span class='message'>$message</span>";
        if ($timing) {
            echo " <span class='timing'>($timing ms)</span>";
        }
        echo "</div>";
    }
}

echo "<br><p><em>Page auto-refreshes every 30 seconds</em></p>";
echo "<script>setTimeout(function(){ location.reload(); }, 30000);</script>";
echo "</body></html>";
?>
