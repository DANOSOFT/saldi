<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/revisorpakke.php --- ai/mcp-assistant --- 2026.10.05
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// Printable, read-only year-end report and CSV exports from the same permission-filtered service result.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.
// 20261007 CL/LH V1 Stille: page head, sticky year and print toolbar, summary strip, captioned cards with
//                hairline tables and CSV buttons, totals in a table foot; print rules in css/saldi_assist_pages.css.
// 20261007 CL/LH Page and CSV use the complete pack, built without the tool response limit; any error is shown,
//                never a silent fallback. A partial pack (bounded tool result) is marked: notice, "N+" in the
//                strip and a first CSV line. One Udskriv style; the period is shown once.

/**
 * Variables injected by ../includes/online.php during normal page requests:
 * @var string $db
 * @var int|string $sprog_id
 * @var string $buttonColor
 * @var string $buttonTxtColor
 */
require_once __DIR__ . '/../includes/runtime/bootstrap.php';
require_once __DIR__ . '/../includes/automation/approvalCard.php';
require_once __DIR__ . '/../includes/tools/SaldiInsightTools.php';

/**
 * E7 grants settings.admin OR ledger.read. Adapt admin-only access for the read-only
 * year-end service, which currently requires ledger.read; never change the session context.
 * @return SaldiTenantContext Isolated report context retaining identity and every other right.
 */
function saldi_revisorpakke_context(SaldiTenantContext $ctx, SaldiDb $db): SaldiTenantContext
{
	if (!$ctx->isHuman()) {
		throw new SaldiRuntimeError('PERMISSION_DENIED', 'Human required');
	}
	if (SaldiPermissionService::allows($ctx, $db, 'ledger.read')) {
		return $ctx;
	}
	SaldiPermissionService::require($ctx, $db, 'settings.admin');
	$rights = str_pad($ctx->rights, 4, '0');
	$rights[3] = '2';
	return new SaldiTenantContext($ctx->installId, $ctx->regnskabId, $ctx->db, $ctx->dbType, $ctx->userId,
		$ctx->username, $rights, $ctx->fiscalYear, $ctx->languageId, $ctx->principal, $ctx->grantId, $ctx->scopes, $ctx->baseCurrency);
}

/**
 * The HTML and CSV use exactly the same field order and raw decimal strings.
 * @return array<string,array{title:string,headers:list<string>,rows:list<list<string>>}>
 */
function saldi_revisorpakke_tables(array $pack, ?SaldiDb $db, int $lang): array
{
	$t = static fn(int $id, string $fallback): string => saldi_ui_text($db, $lang, $id, $fallback);
	$trial = $items = $vat = $journals = $periods = [];
	foreach ($pack['trial_balance']['rows'] as $row) {
		$trial[] = [$row['account'], $row['name'], $row['opening'], $row['debit'], $row['credit'], $row['closing'], $row['currency']];
	}
	$total = $pack['trial_balance']['totals'];
	$trial[] = [$t(2373, 'I alt'), '', $total['opening'], $total['debit'], $total['credit'], $total['closing'], $total['currency']];
	foreach ($pack['open_items'] as $row) {
		$items[] = [$row['party_account'], $row['party_name'], $row['invoice'], $row['date'], (string)$row['due_date'], $row['amount'], $row['currency']];
	}
	foreach ($pack['vat'] as $row) {
		$vat[] = [(string)$row['quarter'], $row['from'], $row['to'], $row['sales_vat'], $row['purchase_vat'], $row['payable'], $row['currency']];
	}
	foreach ($pack['unposted_journals'] as $row) {
		$journals[] = [(string)$row['journal_id'], (string)$row['date'], $row['note'], (string)$row['lines'], $row['created_by']];
	}
	foreach ($pack['periods_closed'] as $row) {
		if ($row['closed']) {
			$periods[] = [$row['month'], $t(387, 'Lukket')];
		}
	}
	return [
		'trial_balance' => ['title' => $t(6161, 'Saldobalance'), 'headers' => [$t(5921, 'Konto'), $t(138, 'Navn'), $t(1165, 'Primosaldo'), $t(1000, 'Debet'), $t(1001, 'Kredit'), $t(442, 'Kontosaldo'), $t(776, 'Valuta')], 'rows' => $trial],
		'open_items' => ['title' => $t(6162, 'Åbne poster'), 'headers' => [$t(5921, 'Konto'), $t(138, 'Navn'), $t(6203, 'Faktura'), $t(438, 'Dato'), $t(1164, 'Forfaldsdato'), $t(934, 'Beløb'), $t(776, 'Valuta')], 'rows' => $items],
		'vat' => ['title' => $t(6163, 'Moms'), 'headers' => [$t(5977, 'Kvartal'), $t(903, 'fra'), $t(904, 'til'), $t(994, 'Salgsmoms (udgående moms)'), $t(996, 'Købsmoms (indgående moms)'), $t(2373, 'I alt'), $t(776, 'Valuta')], 'rows' => $vat],
		'unposted_journals' => ['title' => $t(6164, 'Ikke-bogførte kassekladder'), 'headers' => [$t(639, 'Kladdeliste'), $t(438, 'Dato'), $t(1163, 'Tekst'), $t(916, 'Antal'), $t(65, 'Oprettet')], 'rows' => $journals],
		'periods_closed' => ['title' => $t(6301, 'Lukkede momsperioder'), 'headers' => [$t(6005, 'Periode'), $t(5806, 'Status')], 'rows' => $periods],
	];
}

/**
 * Export a whitelisted table; defend spreadsheet text cells while preserving signed numbers. A partial
 * pack (the bounded tool result) starts with one line saying so, so a cut file never looks complete.
 */
function saldi_revisorpakke_csv(array $pack, string $section, ?SaldiDb $db, int $lang, bool $partial = false): string
{
	if (!in_array($section, ['trial_balance', 'open_items', 'vat'], true)) {
		throw new SaldiRuntimeError('VALIDATION_ERROR', 'Unknown CSV section');
	}
	$table = saldi_revisorpakke_tables($pack, $db, $lang)[$section];
	$stream = fopen('php://temp', 'w+');
	fwrite($stream, "\xEF\xBB\xBF");
	try {
		$lead = $partial ? [[saldi_ui_text($db, $lang, 6381, 'Delvis: filen indeholder ikke alle rækker. Hent den igen fra Revisorpakke i Saldi.')]] : [];
		foreach ([...$lead, $table['headers'], ...$table['rows']] as $row) {
			foreach ($row as &$cell) {
				if (preg_match('/^[\s]*[=+@-]/u', $cell) && !preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D', $cell)) {
					$cell = "'" . $cell;
				}
			}
			unset($cell);
			fputcsv($stream, $row, ';', '"', '', "\r\n");
		}
		rewind($stream);
		return stream_get_contents($stream);
	} finally {
		fclose($stream);
	}
}

/** A printable section per service table, plus its notes and incompleteness notice. */
function saldi_revisorpakke_html(array $result, ?SaldiDb $db, int $lang): string
{
	$pack = $result['data'];
	$t = static fn(int $id, string $fallback): string => saldi_ui_h(saldi_ui_text($db, $lang, $id, $fallback));
	$tables = saldi_revisorpakke_tables($pack, $db, $lang);
	$partial = !empty($result['possibly_incomplete']);
	// Print only: on screen the period is the toolbar select, so it is not repeated.
	$out = '<p class="sap-pack-period">' . saldi_ui_h(saldi_ui_cell($pack['fiscal_year']['start'])['text'] . ' – ' . saldi_ui_cell($pack['fiscal_year']['end'])['text'] . ' · ' . $pack['currency']) . '</p>';
	if ($partial) {
		$out .= '<div class="sai-banner sai-banner--warn" role="status"><p>' . $t(6304, 'Resultatet er afkortet. CSV-filerne indeholder de viste rækker; totalerne omfatter alle konti.') . '</p></div>';
	}
	// The strip: how many rows each section holds, linked to the section. In a partial pack the bounded
	// sections say "mindst" (N+), never an exact count.
	$out .= '<section class="sai-overview" aria-label="' . $t(6160, 'Revisorpakke') . '"><ul class="sai-overview__rows">';
	foreach ($tables as $key => $table) {
		$count = max(0, count($table['rows']) - ($key === 'trial_balance' ? 1 : 0));
		$bounded = $partial && $count > 0 && in_array($key, ['trial_balance', 'open_items', 'unposted_journals'], true);
		$value = $bounded ? $count . '+' : (string)$count;
		$out .= '<li class="sai-ov"><span class="sai-ov__value">' . $value . '</span><a class="sai-ov__label" href="#pack-' . $key . '">' . saldi_ui_h($table['title']) . '</a></li>';
	}
	$out .= '</ul></section>';
	foreach ($tables as $key => $table) {
		$out .= '<section class="sap-section" aria-labelledby="pack-' . $key . '"><div class="sap-section__head"><h2 class="sap-cap" id="pack-' . $key . '">' . saldi_ui_h($table['title']) . '</h2>';
		if (in_array($key, ['trial_balance', 'open_items', 'vat'], true)) {
			$out .= '<a class="sap-btn sap-btn--secondary sap-btn--sm sap-noprint" href="revisorpakke.php?fiscal_year=' . (int)$pack['fiscal_year']['id'] . '&amp;csv=' . $key . '">'
				. '<svg viewBox="0 0 16 16" aria-hidden="true"><path d="M8 2.5v8M5 7.5l3 3 3-3"/><path d="M2.5 11v1.25c0 .7.55 1.25 1.25 1.25h8.5c.7 0 1.25-.55 1.25-1.25V11"/></svg>'
				. $t(6167, 'Hent CSV') . '<span class="sai-visually-hidden"> · ' . saldi_ui_h($table['title']) . '</span></a>';
		}
		$out .= '</div><div class="sap-card">';
		if (!$table['rows']) {
			$out .= '<p class="sap-empty">' . $t(6077, 'Ingen data i perioden.') . '</p>';
		} else {
			$rows = $table['rows'];
			// The trial balance ends with its total, which goes in the table foot.
			$total = $key === 'trial_balance' ? array_pop($rows) : null;
			$numeric = [];
			foreach ($table['rows'] as $row) {
				foreach ($row as $index => $value) {
					if (saldi_ui_cell($value)['numeric']) {
						$numeric[$index] = true;
					}
				}
			}
			$cells = static function (array $row) use ($key): string {
				$html = '';
				foreach ($row as $index => $value) {
					$cell = saldi_ui_cell($value);
					$text = saldi_ui_h($cell['text']);
					if ($key === 'unposted_journals' && $index === 0) {
						$text = '<a href="../finans/kassekladde.php?kladde_id=' . (int)$value . '">' . $text . '</a>';
					}
					$html .= '<td' . ($cell['numeric'] ? ' class="sai-num"' : '') . '>' . $text . '</td>';
				}
				return $html;
			};
			$out .= '<div class="sac-table-wrap"><table class="sai-table"><caption class="sai-visually-hidden">' . saldi_ui_h($table['title']) . '</caption><thead><tr>';
			foreach ($table['headers'] as $index => $label) {
				$out .= '<th scope="col"' . (isset($numeric[$index]) ? ' class="sai-num"' : '') . '>' . saldi_ui_h($label) . '</th>';
			}
			$out .= '</tr></thead><tbody>';
			foreach ($rows as $row) {
				$out .= '<tr>' . $cells($row) . '</tr>';
			}
			$out .= '</tbody>' . ($total !== null ? '<tfoot><tr>' . $cells($total) . '</tr></tfoot>' : '') . '</table></div>';
		}
		$out .= '</div>';
		if ($key === 'vat' && $pack['vat']) {
			$out .= '<p class="sap-hint" style="margin: 8px 4px 0">' . saldi_ui_h($pack['vat'][0]['note']) . '</p>';
		}
		$out .= '</section>';
	}
	$out .= '<section class="sap-section" aria-labelledby="pack-notes"><div class="sap-section__head"><h2 class="sap-cap" id="pack-notes">' . $t(6165, 'Noter til revisor') . '</h2></div><div class="sap-card"><div class="sap-card__body sap-notes">';
	foreach ($pack['notes'] as $note) {
		$out .= '<p>' . saldi_ui_h($note) . '</p>';
	}
	return $out . '</div></div></section>';
}

if (defined('SALDI_REVISORPAKKE_LIBRARY')) {
	return;
}


ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}
$s_id = session_id();
$modulnr = 0;
$title = 'Revisorpakke';
include __DIR__ . '/../includes/connect.php';
include __DIR__ . '/../includes/online.php';
ob_end_clean();
$lang = in_array((int)($sprog_id ?? 1), [1, 2, 3], true) ? (int)$sprog_id : 1;
$tdb = null;
$result = null;
$error = null;
$years = [];
try {
	$open = saldi_runtime_context_for_session();
	$tdb = $open['db'];
	saldi_runtime_ensure_schema($tdb);
	$ctx = saldi_revisorpakke_context($open['ctx'], $tdb);
	$year = ifset($_GET, 'fiscal_year', (string)$ctx->fiscalYear);
	if (!is_string($year) || !preg_match('/^[1-9][0-9]{0,8}$/D', $year)) {
		throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid fiscal year');
	}
	// The complete pack, built without the tool response limit. Errors are shown; there is no partial fallback.
	$result = SaldiYearEndPack::build($ctx, $tdb, (int)$year, true);
	$years = $tdb->all("SELECT kodenr, box1, box2, box3, box4 FROM grupper WHERE art = 'RA' ORDER BY kodenr DESC");
	if (isset($_GET['csv'])) {
		if (!is_string($_GET['csv'])) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid CSV section');
		}
		$content = saldi_revisorpakke_csv($result['data'], $_GET['csv'], $tdb, $lang, $result['possibly_incomplete']);
		header('Cache-Control: no-store');
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="revisorpakke-' . (int)$year . '-' . $_GET['csv'] . '.csv"');
		print $content;
		exit;
	}
} catch (Throwable $e) {
	$error = saldi_approval_error($e, $tdb, $lang)['message_da'];
	if (!$e instanceof SaldiRuntimeError) {
		error_log('systemdata/revisorpakke.php: ' . get_class($e) . ' ' . $e->getMessage());
	}
	if (isset($_GET['csv'])) {
		http_response_code($e instanceof SaldiRuntimeError ? $e->httpStatus() : 503);
	}
}
$t = static fn(int $id, string $fallback): string => saldi_ui_h(saldi_ui_text($tdb, $lang, $id, $fallback));
$accent = is_string($buttonColor ?? null) && preg_match('/^#[0-9a-fA-F]{3,8}$/D', $buttonColor) ? $buttonColor : '#114691';
$accentText = is_string($buttonTxtColor ?? null) && preg_match('/^#[0-9a-fA-F]{3,8}$/D', $buttonTxtColor) ? $buttonTxtColor : '#ffffff';
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="<?= $lang === 2 ? 'en' : ($lang === 3 ? 'nb' : 'da') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $t(6160, 'Revisorpakke') ?></title>
<link rel="stylesheet" href="../css/approval_card.css?v=20261007">
<link rel="stylesheet" href="../css/saldi_assist_pages.css?v=20261007">
<script>document.documentElement.className += ' js';</script>
</head>
<body class="sai-body sai-v2" style="--sac-accent: <?= saldi_ui_h($accent) ?>; --sac-accent-text: <?= saldi_ui_h($accentText) ?>;">
<main class="sap-page">
	<header class="sai-head">
		<div>
			<h1 class="sai-h1"><?= $t(6160, 'Revisorpakke') ?></h1>
			<p class="sai-lead"><?= $t(6365, 'Saldobalance, åbne poster og moms for regnskabsåret, klar til revisor. Kun til læsning.') ?></p>
		</div>
		<nav class="sap-head__links" aria-label="Saldi Assist">
			<a class="sai-head__link" href="assistent.php"><?= $t(5871, 'Assist-indstillinger') ?></a>
		</nav>
	</header>
<?php if ($error !== null) { ?>
	<div class="sai-banner sai-banner--warn" role="alert"><p><?= saldi_ui_h($error) ?></p></div>
<?php } elseif ($result !== null) { ?>
	<div class="sap-toolbar">
		<form method="get" action="revisorpakke.php" class="sap-inline-form" data-sap-autosubmit>
			<label for="pack-year"><?= $t(6005, 'Periode') ?></label>
			<select id="pack-year" name="fiscal_year">
				<?php foreach ($years as $row) { $option = SaldiToolRuntime::year($row); ?><option value="<?= (int)$option['id'] ?>"<?= $option['id'] === $result['data']['fiscal_year']['id'] ? ' selected' : '' ?>><?= saldi_ui_h(saldi_ui_cell($option['start'])['text'] . ' – ' . saldi_ui_cell($option['end'])['text']) ?></option><?php } ?>
			</select>
			<button class="sap-btn sap-btn--secondary sai-js-hide" type="submit"><?= $t(1133, 'Vis') ?></button>
		</form>
		<span class="sap-toolbar__meta"><?= saldi_ui_h($result['data']['currency']) ?></span>
		<span class="sap-toolbar__end"><button type="button" class="sap-btn sap-btn--secondary sap-btn--sm sai-js-only" data-sap-print><svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4.5 6V2.5h7V6"/><rect x="2.5" y="6" width="11" height="5.5" rx="1.25"/><path d="M4.5 9.5h7v4h-7z"/></svg><?= $t(6166, 'Udskriv') ?></button></span>
	</div>
	<?= saldi_revisorpakke_html($result, $tdb, $lang) ?>
<?php } ?>
</main>
<script>
(function () {
	'use strict';
	document.addEventListener('click', function (event) {
		if (event.target && event.target.closest && event.target.closest('[data-sap-print]')) { window.print(); }
	});
	var form = document.querySelector('[data-sap-autosubmit]');
	if (form) { form.addEventListener('change', function () { form.submit(); }); }
})();
</script>
</body>
</html>
