<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -----------------finans/autoudlign.php------------lap 5.0.0--------2026.05.21----------
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
//
// Copyright (c) 2003-2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20170607 PHR genkender nu også kontonr. Søg 20170707
// 2018.12.20 MSC - Rettet isset fejl og rettet topmenu design til
// 2019.03.12 MSC - Rettet db argument fejl og isset fejl
// 2019.03.13 PHR - Rettet db argument fejl 
// 2020.07.10 PHR - Added recognition af payment ID ($betalings_id) 
// 2020.08.20 PHR - Added recognition of outgoing payments from Cultura Sparebank, Norway 20200820
// 2020.09.11 PHR - Added query without Payment ID if no marching order found. 20200911 
// 2020.09.14 PHR - Added search for account if 'afr:' in text
// 2020.11.07 PHR - Added controle for duplicates when displaying matching openposts 'distinct(openpost.id)'
// 2026.05.14 LOE - General code cleanup and modernization; no functional changes intended.
// 20260519 CL/PHR Fixet problem that it did not find some openoposts.

@session_start();
$s_id = session_id();

#$css = "../css/standard.css";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$kladde_id = if_isset($_GET['kladde_id']);
$id        = intval(if_isset($_GET, 0, ['id']));
$skipped   = max(0, intval($_GET['skipped']  ?? 0));
$settled   = max(0, intval($_GET['settled']  ?? 0));

/* ---------------------------------------------------------------
   POST: persist the chosen settlement
--------------------------------------------------------------- */
$save_error   = '';
$save_success = false;

if (isset($_POST['action']) && $_POST['action'] === 'udlign') {
    $post_kontonr = trim($_POST['kontonr']   ?? '');
    $post_art     = trim($_POST['art']       ?? '');
    $post_faktnr  = trim($_POST['faktnr']    ?? '');
    $post_amount  = floatval($_POST['amount'] ?? 0);
    $post_id      = intval($_POST['entry_id'] ?? 0);

    if ($post_art && $post_kontonr && $post_id) {
        $kontonr_esc = db_escape_string($post_kontonr);
        $art_esc     = db_escape_string($post_art);
        $faktnr_esc  = db_escape_string($post_faktnr);

        if ($post_amount < 0) {
            $qtxt = "UPDATE kassekladde SET d_type='$art_esc', debet='$kontonr_esc', faktura='$faktnr_esc' WHERE id = $post_id";
        } else {
            $qtxt = "UPDATE kassekladde SET k_type='$art_esc', kredit='$kontonr_esc', faktura='$faktnr_esc' WHERE id = $post_id";
        }
        db_modify($qtxt, __FILE__ . " line " . __LINE__);
        $save_success = true;
        $settled++;   // increment for URL carry-through
    } else {
        $save_error = 'Invalid data – please fill in all required fields.';
    }
}

/* ---------------------------------------------------------------
   Collect already-used invoice numbers in this batch
--------------------------------------------------------------- */
$brugt = [];
if ($kladde_id) {
    $q = db_select("SELECT faktura FROM kassekladde WHERE kladde_id=$kladde_id AND faktura != ''", __FILE__ . " line " . __LINE__);
    while ($r = db_fetch_array($q)) {
        $brugt[] = trim($r['faktura']);
    }
}

/* ---------------------------------------------------------------
   Find the next unsettled entry starting after $id
--------------------------------------------------------------- */
$entry = null;
if ($kladde_id) {
    $q = db_select(
        "SELECT * FROM kassekladde WHERE kladde_id=$kladde_id AND id > $id ORDER BY id",
        __FILE__ . " line " . __LINE__
    );
    while ($r = db_fetch_array($q)) {
        $amount = 0;
        if ($r['debet'] && !$r['kredit'])  $amount =  floatval($r['amount']);
        elseif (!$r['debet'] && $r['kredit']) $amount = -floatval($r['amount']);
        if ($amount != 0) {
            $entry = $r;
            $entry['resolved_amount'] = $amount;
            break;
        }
    }
}

/* ---------------------------------------------------------------
   Build the search hint from the description (numeric tokens)
--------------------------------------------------------------- */
$hint_tokens = [];
if ($entry) {
    $besk = $entry['beskrivelse'] ?? '';
    preg_match_all('/\d+/', $besk, $matches);
    foreach ($matches[0] as $tok) {
        if (strlen($tok) >= 3) $hint_tokens[] = $tok; // skip single digits
    }
}

$amount_fmt = $entry
    ? number_format($entry['resolved_amount'], 2, ',', '.')
    : '';
$amount_raw = $entry ? $entry['resolved_amount'] : 0;

$kassekladde_url = "kassekladde.php?kladde_id=" . urlencode($kladde_id);
$returside       = $kassekladde_url;

/* ---------------------------------------------------------------
   Count total unsettled entries in this journal (for progress)
--------------------------------------------------------------- */
$total_unsettled = 0;
if ($kladde_id) {
    $q = db_select(
        "SELECT debet, kredit FROM kassekladde WHERE kladde_id=$kladde_id",
        __FILE__ . " line " . __LINE__
    );
    while ($r = db_fetch_array($q)) {
        if (($r['debet'] && !$r['kredit']) || (!$r['debet'] && $r['kredit'])) {
            $total_unsettled++;
        }
    }
}
// Progress: how many have we processed so far this run
$progress_done  = $settled + $skipped;
$progress_total = $total_unsettled + $settled; // total that needed settling when we started

// Restart URL: go back to id=0 with skipped/settled reset
$restart_url = 'autoudlign.php?kladde_id=' . urlencode($kladde_id) . '&id=0&skipped=0&settled=0';
?>
<!DOCTYPE html>
<html lang="da">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Auto-Settlement</title>
<style>
  /* ── Design tokens ────────────────────────────────────────── */
  body, table, tr, td, th,
.system-header table, .system-header td,
.candidates-table, .candidates-table th, .candidates-table td {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10pt;
}
  :root {
    --bg:          #f5f4f1;
    --surface:     #ffffff;
    --surface-alt: #f9f8f6;
    --border:      #dbd9d4;
    --border-strong: #b5b2ab;
    --text:        #1a1917;
    --text-muted:  #6b6861;
    --accent:      #2563eb;
    --accent-hover:#1d4ed8;
    --accent-light:#eff6ff;
    --danger:      #dc2626;
    --success:     #16a34a;
    --success-bg:  #f0fdf4;
    --highlight:   #fefce8;
    --highlight-border: #fde047;
    --radius:      6px;
    --radius-lg:   10px;
    --shadow:      0 1px 3px rgba(0,0,0,.08), 0 4px 12px rgba(0,0,0,.05);
    --shadow-lg:   0 4px 16px rgba(0,0,0,.10), 0 1px 4px rgba(0,0,0,.06);
  }

  /* ── Reset ───────────────────────────────────────────────── */
  *, *::before, *::after { box-sizing: border-box; }
 
  body {
    color: var(--text);
    min-height: 100vh;
    padding: 0;
  }

  /* ── Google Fonts ─────────────────────────────────────────── */
  @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap');

  /* ── System header (legacy table-based) ─────────────────── */
  /* Sits at the top of the flex-column body; must not grow. */
  .system-header {
    flex-shrink: 0;
    width: 100%;
  }
  .system-header table { width: 100%; }

  /* ── Full-height layout ──────────────────────────────────── */
  html, body { height: 100%; }
  body { display: flex; flex-direction: column; overflow: hidden; }

  /* ── Page wrapper ────────────────────────────────────────── */
  .page {
    flex: 1;
    max-width: 900px;
    width: 100%;
    margin: 0 auto;
    padding: 20px 20px 0;
    display: flex;
    flex-direction: column;
    min-height: 0;
  }

  /* ── Entry card ──────────────────────────────────────────── */
  .entry-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;   /* keeps border-radius; internal scroll is on .candidates-wrap */
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    margin-bottom: 12px;
  }
  .entry-card-header {
    background: var(--surface-alt);
    border-bottom: 1px solid var(--border);
    padding: 14px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    flex-shrink: 0;
  }
  .entry-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--text-faint);
  }
  .entry-date {
    font-family: var(--mono);
    font-size: 13px;
    font-weight: 500;
    color: var(--text-muted);
  }
  .entry-description {
    font-size: 14px;
    font-weight: 500;
    flex: 1;
    color: var(--text);
  }
  .entry-amount {
    font-family: var(--mono);
    font-size: 16px;
    font-weight: 600;
    color: var(--text);
    white-space: nowrap;
  }
  .entry-amount.positive { color: var(--success); }
  .entry-amount.negative { color: var(--danger); }

  /* ── Search bar ──────────────────────────────────────────── */
  .search-row {
    padding: 14px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
  }
  .search-input {
    flex: 1;
    height: 36px;
    padding: 0 12px;
    font-family: var(--sans);
    font-size: 13px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text);
    outline: none;
    transition: border-color .15s, box-shadow .15s;
  }
  .search-input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(37,99,235,.12);
  }
  .search-input::placeholder { color: var(--text-faint); }
  .search-hint {
    font-size: 12px;
    color: var(--text-faint);
    white-space: nowrap;
  }

  /* ── Candidates table ────────────────────────────────────── */
  .candidates-wrap {
    overflow-x: auto;
    overflow-y: auto;
    flex: 1;
    min-height: 0;
  }
  .candidates-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
  }
  .candidates-table thead tr {
    background: var(--surface-alt);
  }
  .candidates-table thead {
    position: sticky;
    top: 0;
    z-index: 10;
  }
  .candidates-table th {
    padding: 9px 14px;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--text-faint);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
    background: var(--surface-alt);
    box-shadow: 0 1px 0 var(--border);
  }
  .candidates-table th.r { text-align: right; }
  .candidates-table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
    color: var(--text);
  }
  .candidates-table td.mono {
    /* font-family: var(--mono);
    font-size: 12px; */
  }
  .candidates-table td.r { text-align: right; }
  .candidates-table tbody tr {
    cursor: pointer;
    transition: background .1s;
  }
  .candidates-table tbody tr:hover { background: var(--accent-light); }
  .candidates-table tbody tr.selected {
    background: var(--accent-light);
    outline: 2px solid var(--accent);
    outline-offset: -2px;
  }
  .candidates-table tbody tr.amount-match {
    background: var(--highlight);
  }
  .candidates-table tbody tr.amount-match td:first-child::before {
    content: '✦ ';
    color: #ca8a04;
    font-size: 10px;
  }
  .candidates-table tbody tr.amount-match.selected {
    background: var(--accent-light);
  }

  /* Radio-style indicator */
  .radio-cell { width: 36px; text-align: center; }
  .radio-indicator {
    display: inline-block;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid var(--border-strong);
    background: var(--surface);
    transition: border-color .15s, background .15s;
    vertical-align: middle;
    position: relative;
  }
  .candidates-table tbody tr.selected .radio-indicator {
    border-color: var(--accent);
    background: var(--accent);
  }
  .candidates-table tbody tr.selected .radio-indicator::after {
    content: '';
    position: absolute;
    inset: 3px;
    border-radius: 50%;
    background: white;
  }

  /* ── State messages ──────────────────────────────────────── */
  .state-msg {
    padding: 32px 20px;
    text-align: center;
    color: var(--text-faint);
    font-size: 13px;
    line-height: 1.7;
  }
  .state-msg.loading::before {
    content: '';
    display: block;
    width: 24px;
    height: 24px;
    border: 2px solid var(--border);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin .7s linear infinite;
    margin: 0 auto 10px;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  /* ── Pagination ──────────────────────────────────────────── */
  .pagination {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-top: 1px solid var(--border);
    font-size: 12px;
    color: var(--text-faint);
    background: var(--surface);
    flex-shrink: 0;
  }
  .pagination-sep { flex: 1; }
  .page-btn {
    height: 28px;
    padding: 0 10px;
    font-size: 12px;
    font-family: var(--sans);
    font-weight: 500;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    color: var(--text-muted);
    cursor: pointer;
    transition: background .15s;
  }
  .page-btn:hover { background: var(--surface-alt); }

  /* ── Footer / action bar ─────────────────────────────────── */
  .action-bar {
    padding: 14px 20px;
    background: var(--surface-alt);
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
  }
  .action-bar-info {
    flex: 1;
    font-size: 12px;
    color: var(--text-faint);
  }
  .btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 36px;
    padding: 0 16px;
    font-family: var(--sans);
    font-size: 13px;
    font-weight: 600;
    border-radius: var(--radius);
    border: none;
    cursor: pointer;
    transition: background .15s, opacity .15s, box-shadow .15s;
    text-decoration: none;
  }
  .btn:disabled { opacity: .45; cursor: default; }
  .btn-primary {
    background: var(--accent);
    color: #fff;
    box-shadow: 0 1px 3px rgba(37,99,235,.25);
  }
  .btn-primary:hover:not(:disabled) {
    background: var(--accent-hover);
    box-shadow: 0 2px 6px rgba(37,99,235,.35);
  }
  .btn-secondary {
    background: var(--surface);
    color: var(--text);
    border: 1px solid var(--border);
  }
  .btn-secondary:hover:not(:disabled) { background: var(--surface-alt); }
  .btn-danger-soft {
    background: var(--surface);
    color: var(--danger);
    border: 1px solid #fca5a5;
  }
  .btn-danger-soft:hover:not(:disabled) { background: #fef2f2; }

  /* ── Flash notices ───────────────────────────────────────── */
  .notice {
    padding: 12px 16px;
    border-radius: var(--radius);
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .notice-success { background: var(--success-bg); color: var(--success); border: 1px solid #86efac; }
  .notice-error   { background: #fef2f2; color: var(--danger); border: 1px solid #fca5a5; }

  /* ── No-entry state ──────────────────────────────────────── */
  .done-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    padding: 48px 32px;
    text-align: center;
  }
  .done-icon { font-size: 40px; margin-bottom: 12px; }
  .done-title { font-size: 18px; font-weight: 600; margin-bottom: 6px; }
  .done-sub { font-size: 13px; color: var(--text-muted); margin-bottom: 24px; }

  /* ── Keyboard legend (inline in action bar) ─────────────── */
  .kbd-legend {
    display: flex;
    gap: 8px;
    flex-wrap: nowrap;
    font-size: 11px;
    color: var(--text-faint);
    align-items: center;
  }
  .kbd-legend span { display: flex; align-items: center; gap: 3px; white-space: nowrap; }
  kbd {
    display: inline-block;
    padding: 1px 4px;
    font-family: var(--mono);
    font-size: 10px;
    background: var(--surface-alt);
    border: 1px solid var(--border);
    border-radius: 3px;
    color: var(--text-muted);
  }

  /* ── Progress pill ───────────────────────────────────────── */
  .progress-pill {
    font-family: var(--mono);
    font-size: 13px;
    font-weight: 600;
    color: var(--text-muted);
    white-space: nowrap;
  }
  .progress-skipped {
    display: inline-block;
    margin-left: 6px;
    padding: 1px 6px;
    background: #fef9c3;
    color: #854d0e;
    border: 1px solid #fde047;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
    font-family: var(--sans);
    vertical-align: middle;
  }

  /* ── Match signal badges ─────────────────────────────────── */
  .match-badge {
    display: inline-block;
    margin-left: 6px;
    padding: 1px 5px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: .04em;
    vertical-align: middle;
    font-family: var(--mono);
    line-height: 1.6;
  }
  .badge-amount  { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
  .badge-name    { background: #ede9fe; color: #5b21b6; border: 1px solid #c4b5fd; }
  .badge-invoice { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
  .badge-account { background: #e0f2fe; color: #075985; border: 1px solid #7dd3fc; }

  /* Rows with any signal get a subtle left accent */
  .candidates-table tbody tr.has-signals td:first-child {
    border-left: 3px solid var(--accent);
  }
  /* Separator line between scored and unscored groups */
  .candidates-table tbody tr.group-divider td {
    padding: 0;
    height: 1px;
    background: var(--border);
    border: none;
  }
  .candidates-table tbody tr.group-divider-label td {
    padding: 4px 14px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--text-faint);
    background: var(--surface-alt);
    border-bottom: 1px solid var(--border);
  }
</style>
</head>
<body>

<!-- System header -->
<div class="system-header">
<?php
include("../includes/topline_settings.php");
$icon_back = '<svg xmlns="http://www.w3.org/2000/svg" width="20px" height="20px" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8l-4 4 4 4M16 12H9"></path></svg>';
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; # tabel 1
print "<tr class='backBtn'><td colspan=\"2\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>"; # tabel 1.1
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.1.1

print "<td width=5% style='$buttonStyle'>
	<a href=\"javascript:confirmClose('" . htmlspecialchars($returside, ENT_QUOTES, $charset) . "','$tekst')\" accesskey='L'>
	<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
	$icon_back ".findtekst('30|Tilbage', $sprog_id)."</button></a></td>";
   print " <td align='center' style='$topStyle' width'75%'>Equalize — Journal $kladde_id<br></td>
    <td width=\"5%\" style='$topStyle'><br></td></tr>
    </tbody></table></td></tr>"; # <- tabel 1.1.1
print "</tbody></table></td></tr></tbody></table>";
?>
<style>
	.headerbtn, .center-btn {
		display: flex;
		align-items: center;
		text-decoration: none;
		gap: 5px;
	}
	.backBtn a,
	.backBtn a:link,
	.backBtn a:visited,
	.backBtn a:hover,
	.backBtn a:focus,
	.backBtn a:active {
	text-decoration: none;
	}
</style>
</div>

<div class="page">

  <?php if ($save_success): ?>
    <div class="notice notice-success">✓ Settled. Loading next entry…</div>
  <?php endif; ?>
  <?php if ($save_error): ?>
    <div class="notice notice-error">⚠ <?= htmlspecialchars($save_error) ?></div>
  <?php endif; ?>

  <?php if (!$entry): ?>
    <!-- All done -->
    <div class="done-card">
      <?php if ($skipped > 0): ?>
        <div class="done-icon">⚠</div>
        <div class="done-title">End of journal reached</div> 
        <div class="done-sub">
          <?= $settled ?> <?= $settled === 1 ? 'entry' : 'entries' ?> settled,
          <strong><?= $skipped ?> skipped</strong>.
          Skipped entries are still unsettled — restart to go through them.
        </div>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
          <a class="btn btn-primary" href="<?= htmlspecialchars($restart_url) ?>">
            ↺ Restart from beginning
          </a>
          <a class="btn btn-secondary" href="<?= htmlspecialchars($kassekladde_url) ?>">
            Back to journal
          </a>
        </div>
      <?php else: ?>
        <div class="done-icon">✓</div>
        <div class="done-title">No more entries</div>
        <div class="done-sub">
          <?= $settled > 0 ? $settled . ' ' . ($settled === 1 ? 'entry' : 'entries') . ' settled this run.' : 'There are no more open entries in this journal.' ?>
        </div>
        <a class="btn btn-primary" href="<?= htmlspecialchars($kassekladde_url) ?>">
          Back to journal
        </a>
      <?php endif; ?>
    </div>

  <?php else:
    $amt = $entry['resolved_amount'];
    $amt_class = $amt >= 0 ? 'positive' : 'negative';
  ?>

    <!-- Entry card -->
    <div class="entry-card">
      <div class="entry-card-header">
        <div>
          <div class="entry-label">Date</div>
          <div class="entry-date"><?= htmlspecialchars($entry['transdate']) ?></div>
        </div>
        <div style="flex:1;min-width:0;">
          <div class="entry-label">Description</div>
          <div class="entry-description"><?= htmlspecialchars($entry['beskrivelse']) ?></div>
        </div>
        <div>
          <div class="entry-label">Amount</div>
          <div class="entry-amount <?= $amt_class ?>"><?= $amount_fmt ?></div>
        </div>
        <?php if ($progress_total > 0): ?>
        <div style="text-align:center;flex-shrink:0;">
          <div class="entry-label">Progress</div>
          <div class="progress-pill">
            <?= $progress_done + 1 ?> / <?= $progress_total ?>
            <?php if ($skipped > 0): ?>
              <span class="progress-skipped"><?= $skipped ?> skipped</span>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Search bar -->
      <div class="search-row">
        <input
          class="search-input"
          type="text"
          id="searchInput"
          placeholder="Search by invoice no., name, account no. …"
          autocomplete="off"
          autofocus
        >
        <span class="search-hint" id="matchHint"></span>
      </div>

      <!-- Candidate list -->
      <div class="candidates-wrap">
        <table class="candidates-table">
          <thead>
            <tr>
              <th class="radio-cell"></th>
              <th>Account</th>
              <th>Company name</th>
              <th>Invoice no.</th>
              <th>Date</th>
              <th class="r">Amount</th>
            </tr>
          </thead>
          <tbody id="candidateBody">
            <tr><td colspan="6"><div class="state-msg loading">Loading…</div></td></tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination" id="paginationBar" style="display:none;">
        <span id="pageInfo"></span>
        <span class="pagination-sep"></span>
        <button class="page-btn" id="prevBtn" style="display:none;">← Previous</button>
        <button class="page-btn" id="nextBtn" style="display:none;">Next →</button>
      </div>

      <!-- Action bar -->
      <div class="action-bar">
        <div class="action-bar-info" id="selectionInfo">
          None selected — use ↑↓ or click to choose
        </div>
        <div class="kbd-legend">
          <span><kbd>↑</kbd><kbd>↓</kbd></span>
          <span><kbd>Enter</kbd> Settle</span>
          <span><kbd>Tab</kbd> Skip</span>
          <span><kbd>Esc</kbd> Clear</span>
        </div>
        <button class="btn btn-secondary" id="skipBtn" type="button">
          Skip →
        </button>
        <button class="btn btn-primary" id="udlignBtn" type="button" disabled>
          Settle
        </button>
      </div>
    </div>

  <?php endif; ?>

</div><!-- /.page -->

<?php if ($entry): ?>
<script>
(function () {
  'use strict';

  /* ── PHP data passed to JS ──────────────────────────────── */
  const KLADDE_ID   = <?= json_encode($kladde_id) ?>;
  const ENTRY_ID    = <?= json_encode((int)$entry['id']) ?>;
  const AMOUNT      = <?= json_encode((float)$entry['resolved_amount']) ?>;
  const BESKRIVELSE = <?= json_encode($entry['beskrivelse'] ?? '') ?>;
  const BRUGT       = <?= json_encode(array_values($brugt)) ?>;
  const HINT_TOKENS = <?= json_encode($hint_tokens) ?>;
  let   SKIPPED     = <?= json_encode($skipped) ?>;
  let   SETTLED     = <?= json_encode($settled) ?>;

  /* ── State ──────────────────────────────────────────────── */
  let currentPage      = 1;
  let totalCount       = 0;
  let hasMore          = false;
  let candidates       = [];       // current page results
  let selectedIndex    = -1;
  let debounceTimer    = null;
  let autoSelected     = false;    // did we auto-pick a candidate?

  /* ── DOM ────────────────────────────────────────────────── */
  const searchInput   = document.getElementById('searchInput');
  const candidateBody = document.getElementById('candidateBody');
  const udlignBtn     = document.getElementById('udlignBtn');
  const skipBtn       = document.getElementById('skipBtn');
  const selInfo       = document.getElementById('selectionInfo');
  const matchHint     = document.getElementById('matchHint');
  const pageInfo      = document.getElementById('pageInfo');
  const paginationBar = document.getElementById('paginationBar');
  const prevBtn       = document.getElementById('prevBtn');
  const nextBtn       = document.getElementById('nextBtn');

  /* ── Search path ────────────────────────────────────────── */
  function getSearchUrl(search, page) {
    const base = window.location.pathname.includes('/finans/')
        ? 'kassekladde_includes/invoiceSearch.php'
        : (window.location.pathname.includes('/includes/')
            ? '../finans/kassekladde_includes/invoiceSearch.php'
            : 'finans/kassekladde_includes/invoiceSearch.php');

    const params = new URLSearchParams({
        search:        search,
        currentAmount: AMOUNT,
        page:          page,
        mode:          'open_post',                  
        hintTokens:    JSON.stringify(HINT_TOKENS),
        descWords:     JSON.stringify(DESC_WORDS)
    });
    return base + '?' + params.toString();
}

  /* ── Description word tokens (for company-name matching) ── */
  // Split the full description into normalised words (3+ chars) once.
  const DESC_WORDS = BESKRIVELSE
    .toUpperCase()
    .split(/[\s\-\/\\.,;:_()[\]{}]+/)
    .filter(w => w.length >= 3);

  /* ── Score a single candidate (higher = better match) ───── */
  //
 
  // The score drives sort order AND which signals are shown as badges.
  function scoreCandidate(c) {
    let score = 0;
    const signals = [];   

    // Amount match (server already computed this)
    if (c.amountMatch) {
      score += 40;
      signals.push('amount');
    }

  
// Company name words present in description
    if (c.firmanavn) {
      const nameWords = c.firmanavn
        .toUpperCase()
        .split(/[\s\-\/\\.,;:_()[\]{}]+/)
        .filter(w => w.length >= 3);
      const nameHit = nameWords.some(nw => DESC_WORDS.includes(nw));
      if (nameHit) {
        let matchCount = 0;
        if (c.firmanavn) {
            const nameWords = c.firmanavn.toUpperCase().split(/[\s\-\/\\.,;:_()[\]{}]+/).filter(w => w.length >= 3);
            matchCount = nameWords.filter(nw => DESC_WORDS.includes(nw)).length;
            if (matchCount > 0) {
                score += 30 + (matchCount * 5);   // base 30 + 5 per extra word
                signals.push('name');
            }
        }
      }
    } 

    // Invoice number appears in description tokens
    if (c.faktnr) {
      const faktnrStr = String(c.faktnr).toUpperCase();
      const invoiceHit = HINT_TOKENS.some(tok => faktnrStr.includes(tok))
        || DESC_WORDS.some(dw => faktnrStr.includes(dw) && dw.length >= 3);
      if (invoiceHit) {
        score += 20;
        signals.push('invoice');
      }
    }

    //  Account number appears in description tokens
    if (c.kontonr) {
      const kontoStr = String(c.kontonr).toUpperCase();
      const kontoHit = HINT_TOKENS.some(tok => kontoStr.includes(tok));
      if (kontoHit) {
        score += 10;
        signals.push('account');
      }
    }

    return { score, signals };
  }

  /* ── Sort candidates: highest score first, then by date ─── */
  function sortAndScore(list) {
    return list.map(c => {
      const { score, signals } = scoreCandidate(c);
      return Object.assign({}, c, { _score: score, _signals: signals });
    }).sort((a, b) => {
      if (b._score !== a._score) return b._score - a._score;
      // Secondary: more recent first
      return (b.transdate || '').localeCompare(a.transdate || '');
    });
  }

  /* ── Fetch candidates ───────────────────────────────────── */
  function fetchCandidates(search, page) {
    search = (search || '').trim();
    page   = page || 1;
    currentPage = page;

    setLoading();

    fetch(getSearchUrl(search, page))
      .then(r => r.json())
      .then(data => {
        const raw   = (data.results || []).filter(c => !BRUGT.includes(String(c.faktnr)));
        candidates  = sortAndScore(raw);
        totalCount  = data.pagination ? data.pagination.total : candidates.length;
        hasMore     = data.pagination ? data.pagination.hasMore : false;
        render(search);
        autoSelectBest();
      })
      .catch(() => {
        candidateBody.innerHTML = '<tr><td colspan="6"><div class="state-msg">Error loading results. Please try again.</div></td></tr>';
      });
  }

  /* ── Signal badge HTML ───────────────────────────────────── */
  function signalBadges(signals) {
    if (!signals || signals.length === 0) return '';
    const map = {
      amount:  { cls: 'badge-amount',  label: '✦ amount' },
      name:    { cls: 'badge-name',    label: '~ name'   },
      invoice: { cls: 'badge-invoice', label: '# inv.'   },
      account: { cls: 'badge-account', label: '# acct'   },
    };
    return signals.map(s => {
      const b = map[s];
      return b ? `<span class="match-badge ${b.cls}">${b.label}</span>` : '';
    }).join('');
  }

  /* ── Render table ───────────────────────────────────────── */
  function render(search) {
    if (candidates.length === 0) {
      candidateBody.innerHTML = '<tr><td colspan="6">' +
        '<div class="state-msg">No open entries match.</div></td></tr>';
      setSelected(-1);
      updatePagination();
      return;
    }

    // Split into two groups: candidates with signals vs without
    const scored   = candidates.filter(c => c._score > 0);
    const unscored = candidates.filter(c => c._score === 0);

    let html = '';

    if (scored.length > 0) {
      if (unscored.length > 0) {
        // Label for top group only when there are two groups
        html += `<tr class="group-divider-label"><td colspan="6">Best matches</td></tr>`;
      }
      html += scored.map((c, i) => candidateRow(c, i)).join('');
    }

    if (unscored.length > 0 && scored.length > 0) {
      html += `<tr class="group-divider"><td colspan="6"></td></tr>`;
      html += `<tr class="group-divider-label"><td colspan="6">Other open entries</td></tr>`;
      html += unscored.map((c, i) => candidateRow(c, scored.length + i)).join('');
    } else if (unscored.length > 0) {
      html += unscored.map((c, i) => candidateRow(c, i)).join('');
    }

    candidateBody.innerHTML = html;
    selectedIndex = -1;

    candidateBody.querySelectorAll('.candidate-row').forEach(row => {
      row.addEventListener('click', () => {
        setSelected(parseInt(row.dataset.index, 10));
      });
      row.addEventListener('dblclick', () => {
        setSelected(parseInt(row.dataset.index, 10));
        doUdlign();
      });
    });

    updatePagination();
  }

 

   function candidateRow(c, i) {
    const hasSignals = c._signals && c._signals.length > 0;
    const rowClass = ['candidate-row', hasSignals ? 'has-signals' : '', c.amountMatch ? 'amount-match' : ''].filter(Boolean).join(' ');
    return `<tr class="${rowClass}" data-index="${i}">
      <td class="radio-cell"><span class="radio-indicator"></span></td>
      <td class="mono">${esc(c.kontonr)}${signalBadges(c._signals)}</td>  <!-- add badges here -->
      <td>${esc(c.firmanavn)}</td>
      <td class="mono">${esc(c.faktnr)}</td>
      <td class="mono">${fmtDate(c.transdate)}</td>
      <td class="r mono">${fmtNum(c.amount)}</td>
    </tr>`;
} 

  /* ── Auto-select best candidate ─────────────────────────── */
  function autoSelectBest() {
    if (candidates.length === 0) return;

    const best = candidates[0];
    if (best._score > 0 || candidates.length === 1) {
      setSelected(0);
      autoSelected = true;
    }

    // Hint bar: count candidates that have ANY signal
    const matchCount = candidates.filter(c => c._score > 0).length;
    if (matchCount > 0) {
      matchHint.textContent = matchCount === 1
        ? '✦ 1 match'
        : `✦ ${matchCount} matches`;
      matchHint.style.color = '#ca8a04';
    } else {
      matchHint.textContent = '';
    }
  }

  /* ── Select row ──────────────────────────────────────────── */
  function setSelected(idx) {
    selectedIndex = idx;
    candidateBody.querySelectorAll('.candidate-row').forEach((row, i) => {
      row.classList.toggle('selected', i === idx);
      if (i === idx) row.scrollIntoView({ block: 'nearest' });
    });

    if (idx >= 0 && candidates[idx]) {
      const c = candidates[idx];
      udlignBtn.disabled = false;
      selInfo.innerHTML =
        `<strong>${esc(c.firmanavn)}</strong> — inv. <strong>${esc(c.faktnr)}</strong> — ${fmtNum(c.amount)}`;
    } else {
      udlignBtn.disabled = true;
      selInfo.textContent = 'None selected — use ↑↓ or click to choose';
    }
  }

  /* ── Do udlign ───────────────────────────────────────────── */
  function doUdlign() {
    if (selectedIndex < 0 || !candidates[selectedIndex]) return;
    const c = candidates[selectedIndex];

    const formData = new FormData();
    formData.append('action',   'udlign');
    formData.append('entry_id', ENTRY_ID);
    formData.append('kontonr',  c.kontonr);
    formData.append('art',      c.art);
    formData.append('faktnr',   c.faktnr);
    formData.append('amount',   AMOUNT);

    udlignBtn.disabled = true;
    udlignBtn.textContent = 'Saving…';

    fetch(window.location.pathname + '?kladde_id=' + encodeURIComponent(KLADDE_ID) + '&id=' + ENTRY_ID, {
      method: 'POST',
      body:   formData
    })
    .then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      // Advance to next entry, carrying counters (settled+1, skipped unchanged)
      window.location.href = window.location.pathname +
        '?kladde_id=' + encodeURIComponent(KLADDE_ID) +
        '&id='      + ENTRY_ID +
        '&settled=' + (SETTLED + 1) +
        '&skipped=' + SKIPPED;
    })
    .catch(() => {
      udlignBtn.disabled = false;
      udlignBtn.textContent = 'Settle';
      alert('An error occurred. Please try again.');
    });
  }

  /* ── Skip ────────────────────────────────────────────────── */
  function doSkip() {
    window.location.href = window.location.pathname +
      '?kladde_id=' + encodeURIComponent(KLADDE_ID) +
      '&id=' + ENTRY_ID;
  }

  /* ── Pagination ──────────────────────────────────────────── */
  function updatePagination() {
    const limit  = 50;
    const start  = ((currentPage - 1) * limit) + 1;
    const end    = Math.min(currentPage * limit, totalCount);
    const show   = totalCount > limit;

    paginationBar.style.display = show ? 'flex' : 'none';
    if (show) {
      pageInfo.textContent = `Showing ${start}–${end} of ${totalCount}`;
      prevBtn.style.display = currentPage > 1 ? '' : 'none';
      nextBtn.style.display = hasMore ? '' : 'none';
    }
  }

  /* ── Loading state ───────────────────────────────────────── */
  function setLoading() {
    candidateBody.innerHTML =
      '<tr><td colspan="6"><div class="state-msg loading">Searching…</div></td></tr>';
    paginationBar.style.display = 'none';
    setSelected(-1);
    matchHint.textContent = '';
  }

  /* ── Keyboard navigation ─────────────────────────────────── */
  document.addEventListener('keydown', e => {
    const rows = candidateBody.querySelectorAll('.candidate-row'); 
    if (!rows.length) return;

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setSelected(Math.min(selectedIndex + 1, rows.length - 1));
        break;
      case 'ArrowUp':
        e.preventDefault();
        setSelected(Math.max(selectedIndex - 1, 0));
        break;
      case 'Enter':
        if (selectedIndex >= 0) { e.preventDefault(); doUdlign(); }
        break;
      case 'Tab':
        e.preventDefault();
        doSkip();
        break;
      case 'Escape':
        searchInput.value = '';
        searchInput.focus();
        fetchCandidates('', 1);
        break;
    }
  });

  /* ── Search debounce ─────────────────────────────────────── */
  searchInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      fetchCandidates(searchInput.value, 1);
    }, 220);
  });

  /* ── Pagination buttons ──────────────────────────────────── */
  prevBtn.addEventListener('click', () => fetchCandidates(searchInput.value, currentPage - 1));
  nextBtn.addEventListener('click', () => fetchCandidates(searchInput.value, currentPage + 1));

  /* ── Action buttons ──────────────────────────────────────── */
  udlignBtn.addEventListener('click', doUdlign);
  skipBtn.addEventListener('click', doSkip);

  /* ── Utility ─────────────────────────────────────────────── */
  function esc(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function fmtNum(n) {
    if (n === null || n === undefined) return '';
    return parseFloat(n).toLocaleString('da-DK', {
      minimumFractionDigits: 2, maximumFractionDigits: 2
    });
  }

  function fmtDate(s) {
    if (!s) return '';
    try {
      const d = new Date(s);
      return String(d.getDate()).padStart(2,'0') + '-' +
             String(d.getMonth()+1).padStart(2,'0') + '-' +
             d.getFullYear();
    } catch(_) { return s; }
  }

  /* ── Boot ────────────────────────────────────────────────── */
  // fetchCandidates('', 1);
  const initialSearch = BESKRIVELSE;   // the entry's description
  fetchCandidates(initialSearch, 1);

})();
</script>

<?php endif; ?>
</body>
</html>

