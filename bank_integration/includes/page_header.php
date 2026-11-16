<?php
// bank_integration/includes/page_header.php
// Minimal self-contained HTML head + CSS for bank-integration pages.
// Expects $title (string) to be set by the caller before include.
// Outputs <!DOCTYPE html> … <body> and leaves the document open.
// The caller is responsible for rendering .topline and .content-noside.
// 20261028 CL/LH Sticky action bar and narrow checkbox column for the bank feed pick list; secondary and danger buttons win over the global !important submit style that includes/online.php prints.

$icon_back = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="24" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($title) ? htmlspecialchars('◖ Saldi • ' . $title . ' ◗') : '◖ Saldi ◗' ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Exo:wght@600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 8px;
            background: <?= $bgcolor ?? '#ededed' ?>;
            font-family: system-ui, Tahoma, sans-serif;
            color: #1a1a2e;
        }

        /* ── Topline row ─────────────────────────────────────────── */
        .topline {
            display: flex;
            gap: 2px;
            align-items: stretch;
            margin-bottom: 2px;
        }

        /* Shared style for all topline cells */
        .topline-btn,
        .topline-center {
            background-color: <?= $buttonColor ?? '#114691' ?>;
            color: <?= $buttonTxtColor ?? '#ffffff' ?>;
            border: none;
            border-radius: 5px;
        }

        /* Luk / action buttons */
        .topline-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 2px 6px;
            cursor: pointer;
            font-size: 13px;
            white-space: nowrap;
            text-decoration: none;
            transition: opacity .2s;
        }
        .topline-btn:hover { opacity: .8; }

        /* Center section — title + auth icon */
        .topline-center {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 2px 6px;
            font-family: 'Exo', Tahoma, sans-serif;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
        }
        .topline-center .center-title { flex: 1; }

        /* Auth status icon (from auth_check_icon.php) */
        .auth-status-icon {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 0;
        }
        .auth-status-icon img { width: 20px; height: 20px; }

        /* ── Content area ────────────────────────────────────────── */
        .content-noside {
            color: #000;
            border-radius: 10px;
            border: 1px solid #ddd;
            padding: 15px;
        }

        /* ── Bank feed pages (connect, accounts, feed) ───────────── */
        .eb-back { min-width: 90px; }
        .eb-panel {
            background: #fff;
            border: 1px solid #e3e6ee;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 12px;
        }
        .eb-panel h2 { font-size: 15px; margin: 0 0 8px 0; }
        .eb-panel p { margin: 4px 0 8px 0; }
        .eb-muted { color: #666; font-size: 12px; }
        .eb-grid { display: grid; grid-template-columns: max-content 1fr; gap: 4px 16px; font-size: 13px; }
        .eb-grid dt { color: #555; }
        .eb-grid dd { margin: 0; }
        .eb-flash { border-radius: 6px; padding: 8px 12px; margin-bottom: 10px; font-size: 13px; }
        .eb-flash-ok { background: #e6f4ea; border: 1px solid #9cd3ad; }
        .eb-flash-info { background: #e8effa; border: 1px solid #a8bfe6; }
        .eb-flash-warn { background: #fff6dd; border: 1px solid #e8cf7a; }
        .eb-flash-error { background: #fde8e6; border: 1px solid #eba59c; }
        .eb-badge { display: inline-block; border-radius: 10px; padding: 1px 8px; font-size: 12px; white-space: nowrap; }
        .eb-badge.ok { background: #e6f4ea; color: #1a6b3a; }
        .eb-badge.warn { background: #fff3cd; color: #7a5a00; }
        .eb-badge.bad { background: #fde8e6; color: #9b2a1c; }
        .eb-badge.muted { background: #eceef2; color: #555; }
        .eb-btn {
            background: <?= $buttonColor ?? '#114691' ?>;
            color: <?= $buttonTxtColor ?? '#ffffff' ?>;
            border: 1px solid transparent;
            border-radius: 5px;
            padding: 4px 12px;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            white-space: nowrap;
        }
        .eb-btn:hover { opacity: .85; }
        .eb-btn.secondary { background: #fff; color: #1a1a2e; border-color: #c4c8d2; }
        .eb-btn.danger { background: #fff; color: #9b2a1c; border-color: #e0a59c; }
        .eb-btn.small { padding: 1px 8px; font-size: 12px; }
        /* includes/online.php prints button[type=submit] { background: ... !important }. */
        button.eb-btn.secondary[type=submit] { background: #fff !important; color: #1a1a2e !important; border-color: #c4c8d2 !important; }
        button.eb-btn.danger[type=submit] { background: #fff !important; color: #9b2a1c !important; border-color: #e0a59c !important; }
        .eb-btn[disabled] { opacity: .45; cursor: default; }
        .eb-inline { display: inline; margin: 0; }
        .eb-actions { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-top: 8px; }
        .eb-tabs { display: flex; flex-wrap: wrap; gap: 4px; margin: 8px 0; }
        .eb-tabs a {
            padding: 4px 10px;
            border-radius: 5px 5px 0 0;
            border: 1px solid #c4c8d2;
            border-bottom: none;
            background: #f4f5f8;
            color: #1a1a2e;
            text-decoration: none;
            font-size: 13px;
        }
        .eb-tabs a.active { background: #fff; font-weight: 600; }
        table.eb-table { border-collapse: collapse; width: 100%; background: #fff; font-size: 13px; }
        table.eb-table th {
            background-color: rgba(17,70,145,0.09);
            text-align: left;
            font-weight: 600;
            padding: 5px 8px;
            white-space: nowrap;
        }
        table.eb-table td { padding: 4px 8px; border-top: 1px solid #eceef2; vertical-align: top; }
        table.eb-table tbody tr:hover td { background-color: #f7f9fc; }
        table.eb-table .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        table.eb-table .nowrap { white-space: nowrap; }
        .eb-amount-neg { color: #9b2a1c; }
        .eb-hint { display: block; color: #7a5a00; font-size: 12px; }
        .eb-sub { display: block; color: #666; font-size: 12px; }
        .eb-form-row { display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: flex-end; }
        .eb-form-row label { display: flex; flex-direction: column; font-size: 12px; color: #555; gap: 2px; }
        .eb-pickbar { position: sticky; top: 0; z-index: 1; background: #fff; margin: 0; padding: 6px 0; }
        table.eb-table .eb-check { width: 1%; padding-right: 0; }
        table.eb-table td label { cursor: pointer; }
        .eb-form-row input[type=text], .eb-form-row input[type=date], .eb-form-row select {
            font-size: 13px; padding: 3px 6px; border: 1px solid #c4c8d2; border-radius: 4px;
        }
    </style>
</head>
<body>
