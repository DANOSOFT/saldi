<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/orderIncludes/stockWarningLog.php --- 2026-05-18 ---

if (!function_exists('render_stock_warning_log')) {
function render_stock_warning_log($ordre_id, $headingLevel = 'h3')
{
	$ordre_id = (int)$ordre_id;
	if (!$ordre_id) return '';
	$rows = array();
	$q = db_select("select * from order_stock_warning_log where ordre_id = '$ordre_id' order by logged_at desc, id desc", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $rows[] = $r;
	if (!$rows) return '';
	$t = function_exists('stock_warning_texts') ? stock_warning_texts(isset($GLOBALS['sprog_id']) ? $GLOBALS['sprog_id'] : null) : array(
		'log_heading' => 'Out-of-stock sales &mdash; approvals',
		'col_time' => 'Time', 'col_employee' => 'Employee', 'col_varenr' => 'Item no.',
		'col_item' => 'Item', 'col_note' => 'Reason',
	);
	$out  = '<div class="stock-warning-log" style="margin:12px 0;padding:10px;border:1px solid #d99;background:#fff8f8;border-radius:4px;">';
	$out .= '<' . $headingLevel . ' style="margin:0 0 8px;color:#900;font-size:14px;">' . $t['log_heading'] . ' (' . count($rows) . ')</' . $headingLevel . '>';
	$out .= '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
	$out .= '<thead><tr style="background:#f3dcdc;text-align:left;">';
	$out .= '<th style="padding:4px 6px;">' . htmlspecialchars($t['col_time']) . '</th>';
	$out .= '<th style="padding:4px 6px;">' . htmlspecialchars($t['col_employee']) . '</th>';
	$out .= '<th style="padding:4px 6px;">' . htmlspecialchars($t['col_varenr']) . '</th>';
	$out .= '<th style="padding:4px 6px;">' . htmlspecialchars($t['col_item']) . '</th>';
	$out .= '<th style="padding:4px 6px;">' . htmlspecialchars($t['col_note']) . '</th>';
	$out .= '</tr></thead><tbody>';
	foreach ($rows as $r) {
		$ts   = htmlspecialchars($r['logged_at']);
		$emp  = htmlspecialchars($r['employee_name'] ?: ('#' . $r['employee_id']));
		$vnr  = htmlspecialchars($r['varenr']);
		$desc = htmlspecialchars($r['beskrivelse']);
		$note = nl2br(htmlspecialchars($r['note']));
		$out .= '<tr style="border-top:1px solid #ecc;">';
		$out .= '<td style="padding:4px 6px;white-space:nowrap;">' . $ts . '</td>';
		$out .= '<td style="padding:4px 6px;">' . $emp . '</td>';
		$out .= '<td style="padding:4px 6px;">' . $vnr . '</td>';
		$out .= '<td style="padding:4px 6px;">' . $desc . '</td>';
		$out .= '<td style="padding:4px 6px;">' . $note . '</td>';
		$out .= '</tr>';
	}
	$out .= '</tbody></table></div>';
	return $out;
}}

// Legacy standalone access -- send users to the new properly-chrome'd page.
// Older bookmarks / banner links may still point at this URL. Use a JS
// redirect (more reliable than relative HTTP Location headers).
//
// IMPORTANT: This block must NOT fire when the file is `include`d by
// debitor/stockWarningLog.php (which is also named stockWarningLog.php, so
// a basename() comparison would falsely match). Check the URL path instead.
$_swPhpSelf = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
if (strpos($_swPhpSelf, '/orderIncludes/stockWarningLog.php') !== false) {
	$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
	$qs = $id ? "?id=$id" : '';
	header('Content-Type: text/html; charset=utf-8');
	echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
	echo "<title>Redirecting...</title></head><body><script>";
	echo "if(window.parent && typeof window.parent.update_iframe==='function'){";
	echo "  window.parent.update_iframe('/debitor/stockWarningLog.php$qs');";
	echo "}else{";
	echo "  var p=window.location.pathname;";
	echo "  var root=p.split('/').slice(0,2).join('/');";
	echo "  window.location.href=window.location.origin+root+'/debitor/stockWarningLog.php$qs';";
	echo "}";
	echo "</script></body></html>";
	exit;
}
