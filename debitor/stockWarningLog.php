<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/stockWarningLog.php --- 2026-05-22 ---
// 20261008 Sawaneh The page on the new page head (page_bar) instead of the blue bar.
// Standalone viewer page for the out-of-stock approval log of a single order.
// Wrapped in saldi's standard chrome (top_header + top_menu + back button +
// footer) so it looks and behaves like every other saldi page.
//
// Usage: stockWarningLog.php?id=<ordre_id>
//
// The actual table rendering is delegated to render_stock_warning_log()
// which lives in orderIncludes/stockWarningLog.php (used both here and as a
// standalone include from ordre.php server hook).

@session_start();
$s_id = session_id();
include("../includes/std_func.php");

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$modulnr = 6; 
$css = "../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/topline_settings.php");


include_once("orderIncludes/stockWarningLog.php");

$t = function_exists('stock_warning_texts')
	? stock_warning_texts(isset($sprog_id) ? $sprog_id : null)
	: array('log_heading' => 'Out-of-stock sales — approvals', 'log_empty' => 'No entries for order');

$title = $t['log_heading'];
if ($id) $title .= " — ordre $id";

$backLink   = $id ? "ordre.php?id=$id"           : 'javascript:history.back()';
$backTarget = $id ? "/debitor/ordre.php?id=$id"  : null;
$backOnclick = $backTarget
	? "if(window.parent&&typeof window.parent.update_iframe==='function'){window.parent.update_iframe('$backTarget');return false;}return true;"
	: '';
$classtable2 = "class=dataTableForm";
include_once '../includes/top_header.php';

include_once("../includes/stdFunc/pageBar.php");
page_bar(array('title' => $title, 'back' => $backLink, 'back_onclick' => $backOnclick));
print "<div style='padding:8px 8px 0 8px;'>";

if (!$id) {
	print "<p>Order ID missing.</p>";
} else {
	$html = render_stock_warning_log($id, 'h2');
	if ($html) {
		print $html;
	} else {
		print "<p style=\"padding:12px;background:#f4f4f4;border:1px solid #ddd;border-radius:4px;\">" . htmlspecialchars($t['log_empty']) . " $id.</p>";
	}
}

print "</div>"; 

