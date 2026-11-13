<?php
// 20261113 CDX/NTR SD-599 Extract stock-warning checks and approval logging without changing their public API.

require_once __DIR__ . '/../opdat_func/ensureStockWarningLog.php';

// --- Out-of-stock warning helpers ----------------------------------------
// Returns localized strings for the warning popup, settings UI and log
// banner. sprog_id == 2 -> English, otherwise Danish (the default).
function stock_warning_texts($sprog_id = null)
{
	if ($sprog_id === null && isset($GLOBALS['sprog_id'])) $sprog_id = $GLOBALS['sprog_id'];
	$en = ((int)$sprog_id === 2);
	if ($en) {
		return array(
			'popup_title'         => 'Item low stock',
			'popup_text'          => 'This item has low or no stock - do you still want to proceed with the sale?',
			'btn_no'              => 'No',
			'btn_yes'             => 'Yes, continue',
			'note_title'          => 'Reason required',
			'note_text'           => 'Please enter a reason for selling an out-of-stock item:',
			'note_placeholder'    => 'E.g.: expected back in stock on XX, or customer informed of delay',
			'btn_cancel'          => 'Cancel',
			'btn_confirm'         => 'Confirm sale',
			'error_required'      => 'A reason is required.',
			'setting_label'       => 'Warn when selling out-of-stock items (popup + reason)',
			'setting_title'       => 'Shows a popup and requires an approval note when an out-of-stock item is added to a POS or Debtor order. The approval is logged on the order.',
			'banner_text'         => 'Out-of-stock sales',
			'banner_suffix'       => 'approval(s) logged - click for details',
			'log_heading'         => 'Out-of-stock sales - approvals',
			'col_time'            => 'Time',
			'col_employee'        => 'Employee',
			'col_varenr'          => 'Item no.',
			'col_item'            => 'Item',
			'col_note'            => 'Reason',
			'log_empty'           => 'No out-of-stock approvals logged for this order.',
		);
	}
	return array(
		'popup_title'         => 'Vare er lav eller ikke på lager',
		'popup_text'          => 'Denne vare er lav eller ikke på lager - ønsker du alligevel at fortsætte med salget?',
		'btn_no'              => 'Nej',
		'btn_yes'             => 'Ja, fortsæt',
		'note_title'          => 'Begrundelse påkrævet',
		'note_text'           => 'Angiv venligst en begrundelse for at sælge en udsolgt vare:',
		'note_placeholder'    => 'Fx: Varen forventes hjem d. XX, eller kunden er informeret om forsinkelse',
		'btn_cancel'          => 'Annullér',
		'btn_confirm'         => 'Bekræft salg',
		'error_required'      => 'Begrundelse er påkrævet.',
		'setting_label'       => 'Advar ved salg af udsolgte varer (popup + begrundelse)',
		'setting_title'       => 'Aktiverer popup-advarsel og krav om begrundelse ved salg af udsolgte varer i både POS og Debitor/Ordre. Godkendelsen logges på ordren.',
		'banner_text'         => 'Salg af udsolgte varer',
		'banner_suffix'       => 'godkendelse(r) loggede - klik for detaljer',
		'log_heading'         => 'Salg af udsolgte varer - godkendelser',
		'col_time'            => 'Tidspunkt',
		'col_employee'        => 'Medarbejder',
		'col_varenr'          => 'Varenr',
		'col_item'            => 'Vare',
		'col_note'            => 'Begrundelse',
		'log_empty'           => 'Ingen godkendelser registreret for denne ordre.',
	);
}

function is_stock_warning_enabled()
{
	// Make sure the log table exists before any caller starts querying it.
	// Migration may not have run on older databases; without this the missing
	// relation can break print/PDF headers and quick-invoice flows.
	if (function_exists('_sw_ensure_log_table')) _sw_ensure_log_table();
	if (function_exists('get_settings_value')) {
		return get_settings_value("stockWarningEnabled", "ordre", "off") === "on";
	}
	$r = db_fetch_array(db_select("select var_value from settings where var_name = 'stockWarningEnabled' and grp = 'ordre'", __FILE__ . " linje " . __LINE__));
	return ($r && $r['var_value'] === 'on');
}

function check_stock_warning($vare_id, $sale_qty = 0)
{
	$result = array('out_of_stock' => false, 'beholdning' => 0, 'min_lager' => 0, 'beskrivelse' => '', 'varenr' => '', 'projected_beholdning' => 0);
	if (!$vare_id || !is_numeric($vare_id)) return $result;
	$vare_id = (int)$vare_id;
	$r = db_fetch_array(db_select("select varenr, beskrivelse, beholdning, min_lager, gruppe from varer where id = '$vare_id'", __FILE__ . " linje " . __LINE__));
	if (!$r) return $result;
	$result['varenr']      = $r['varenr'];
	$result['beskrivelse'] = $r['beskrivelse'];
	$gruppe = $r['gruppe'];
	// Current stock must be read the SAME way the order lines display it (and
	// the red-highlight uses): the sum across all warehouses in lagerstatus.
	// varer.beholdning is a separate field that drifts from the real per-warehouse
	// total on multi-warehouse setups, so relying on it made the popup miss sales
	// that bring stock below minimum. Fall back to varer.beholdning only when the
	// item has no per-warehouse rows at all (single-stock / lagerstatus not used).
	$rStock = db_fetch_array(db_select("select sum(beholdning) as qty from lagerstatus where vare_id = '$vare_id'", __FILE__ . " linje " . __LINE__));
	$beholdning = ($rStock && $rStock['qty'] !== null) ? (float)$rStock['qty'] : (float)$r['beholdning'];
	$result['beholdning']  = $beholdning;
	// Trigger rule: stock-tracked item where the proposed sale would leave the
	// remaining stock below the product minimum. The threshold is the product's
	// own min_lager; products without one fall back to 'Standard minimumsbeholdning'.
	$minStock = (float)$r['min_lager'];
	if ($minStock <= 0) $minStock = _sw_standard_min_stock();
	$result['min_lager'] = $minStock;
	$saleQty = is_numeric($sale_qty) ? (float)$sale_qty : 0;
	$projectedStock = $beholdning - $saleQty;
	$result['projected_beholdning'] = $projectedStock;
	$r2 = db_fetch_array(db_select("select kodenr from grupper where art = 'VG' and box8 = 'on' and kodenr = '$gruppe'", __FILE__ . " linje " . __LINE__));
	if ($r2 && $projectedStock < $minStock) {
		$result['out_of_stock'] = true;
	}
	return $result;
}

// 'Standard minimumsbeholdning' from Varerelaterede valg (systemdata/diverse).
// Used as threshold fallback for products without their own min_lager.
function _sw_standard_min_stock()
{
	static $cached = null;
	if ($cached !== null) return $cached;
	$cached = 0;
	$r = db_fetch_array(db_select("select var_value from settings where var_name = 'min_beholdning' and var_grp = 'productOptions'", __FILE__ . " linje " . __LINE__));
	if ($r && is_numeric(trim($r['var_value']))) $cached = (float)trim($r['var_value']);
	return $cached;
}

// Persist an approval log entry for an out-of-stock sale.
function log_stock_warning($ordre_id, $vare_id, $note, $linje_id = null)
{
	global $brugernavn;
	if (function_exists('_sw_ensure_log_table')) _sw_ensure_log_table();
	if (!$ordre_id || $note === null || trim($note) === '') return false;
	$ordre_id = (int)$ordre_id;
	$vare_id  = $vare_id ? (int)$vare_id : 0;
	$linje_id = $linje_id ? (int)$linje_id : 0;
	$info = check_stock_warning($vare_id);
	$varenr      = db_escape_string($info['varenr']);
	$beskrivelse = db_escape_string($info['beskrivelse']);
	$beholdning  = is_numeric($info['beholdning']) ? $info['beholdning'] : 0;
	$min_lager   = is_numeric($info['min_lager']) ? $info['min_lager'] : 0;
	$note_esc    = db_escape_string(trim($note));
	$emp_id = 0; $emp_name = '';
	if (isset($brugernavn) && $brugernavn) {
		$emp_name = db_escape_string($brugernavn);
		$r = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$emp_name'", __FILE__ . " linje " . __LINE__));
		if ($r && $r['ansat_id']) {
			$emp_id = (int)$r['ansat_id'];
			$r2 = db_fetch_array(db_select("select navn from ansatte where id = '$emp_id'", __FILE__ . " linje " . __LINE__));
			if ($r2 && $r2['navn']) $emp_name = db_escape_string($r2['navn']);
		}
	}
	$linje_val = $linje_id ? "'$linje_id'" : "NULL";
	$qtxt = "insert into order_stock_warning_log (ordre_id, linje_id, vare_id, varenr, beskrivelse, beholdning, min_lager, employee_id, employee_name, note) values ('$ordre_id', $linje_val, '$vare_id', '$varenr', '$beskrivelse', '$beholdning', '$min_lager', '$emp_id', '$emp_name', '$note_esc')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	return true;
}

