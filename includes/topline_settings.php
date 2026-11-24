<?php
// 20261113 ASR The old page bars in the top bar's navy (#1f4373, css/topbar.css) with white text for every user, not the user's button colour.
global $buttonColor;
global $buttonTxtColor;

if (!function_exists('brightenColor')) {
    /**
     * Brightens a hex color by a given amount.
     * @param string $color The hex color code (e.g., '#ff0000').
     * @param float $amount The amount to brighten (0 to 1).
     * @return string The brightened hex color code.
     */
    function brightenColor($color, $amount = 0.2) {
        // Remove # if present
        $color = ltrim($color, '#');
        
        // Convert hex to RGB
        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));
        
        // Brighten each component
        $r = min(255, $r + ($amount * (255 - $r)));
        $g = min(255, $g + ($amount * (255 - $g)));
        $b = min(255, $b + ($amount * (255 - $b)));
        
        // Convert back to hex
        return '#' . sprintf('%02x%02x%02x', round($r), round($g), round($b));
    }
}

if (!function_exists('darkenColor')) {
    /**
     * Darkens a hex color by a given amount.
     * @param string $color The hex color code (e.g., '#ff0000').
     * @param float $amount The amount to darken (0 to 1).
     * @return string The darkened hex color code.
     */
    function darkenColor($color, $amount = 0.2) {
        // Remove # if present
        $color = ltrim($color, '#');
        
        // Convert hex to RGB
        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));
        
        // Darken each component
        $r = max(0, $r - ($amount * $r));
        $g = max(0, $g - ($amount * $g));
        $b = max(0, $b - ($amount * $b));
        
        // Convert back to hex
        return '#' . sprintf('%02x%02x%02x', round($r), round($g), round($b));
    }
}

$topCol       = '#1f4373';   // the top bar's navy (css/topbar.css), not the user's $buttonColor
$butDownCol   = brightenColor($topCol, 0.2);
$butUpCol     = darkenColor($topCol, 0.2);
$buttonTxtColor = '#ffffff';
$topStyle     = "border:0;border-color:$topCol;color:$buttonTxtColor;border-radius:5px;background-color:$topCol;"; //height:100%;
$buttonStyle  = "border:0;border-color:$topCol;color:$buttonTxtColor;border-radius:5px;background-color:$topCol;";
$butDownStyle = "border:0;border-color:$butDownCol;color:$buttonTxtColor;border-radius:5px;background-color:$butDownCol;";
$butUpStyle   = "border:0;border-color:$butUpCol;color:$buttonTxtColor;border-radius:5px;background-color:$butUpCol;";

?>
