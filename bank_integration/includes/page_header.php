<?php
// bank_integration/includes/page_header.php
// Minimal self-contained HTML head + CSS for bank-integration pages.
// Expects $title (string) to be set by the caller before include.
// Outputs <!DOCTYPE html> … <body> and leaves the document open.
// The caller is responsible for rendering .topline and .content-noside.

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
    </style>
</head>
<body>
