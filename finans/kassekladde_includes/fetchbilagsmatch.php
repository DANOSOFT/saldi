<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde.php --- ver 5.0.0 --- 2026-04-10 ---
// verifying fork target points to DANOSOFT/saldi
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260507 NTR - Added batch invoice matching
// 20260721 CL/SZ - Replaced the raw OR-join (amount = OR date =) with a weighted scoring
//                   engine (currency hard gate + amount/date/text/invoice-no signals, 0-100),
//                   cast kladde_id to int to close a SQL injection hole, and joined on the
//                   new indexed pool_files.norm_amount column instead of parsing amounts at
//                   query time.
// 20260731 NTR - fixed previous rework, it was missing norm_amount in the with statement for pf and trimmed the currency codes.
// 20260810 CL/LH - removed the inline norm_amount alias again: once betweenUpdates.php has
//                   created the real pool_files.norm_amount column (which it now does on
//                   every install), SELECT * plus the alias made pf.norm_amount ambiguous,
//                   so the query errored and the endpoint always returned "0 fundet".
// 20260811 CL/SZ - Added dateRange/amountTolerance/includePosted settings (see
//                   invoicematch_settings.md): the 0-45-day one-directional decay window
//                   is now additionally gated by a symmetric +/-N day hard filter around
//                   transdate, the 0.5%/1kr amount tolerance branch can be turned off, and
//                   already-attached pool files (a documents row sharing pf.filename - the
//                   delete in insertDoc.php on attach is best-effort via @db_modify, so a
//                   leftover pool_files row is possible) are excluded unless requested.
// 20260812 CL/SZ - Bug fix per client feedback (Mr. Rude, 20260812): dateRange had been
//                   wired as a hard WHERE filter on the whole row, so a pool file with an
//                   exact amount match but a far-off date was dropped entirely instead of
//                   surfacing as "Beløb matcher" - amount and date are supposed to be
//                   independent signals (a value-only match shouldn't need the date to also
//                   be in range, and vice versa). Moved the +/-dateRange check into the
//                   date_score CASE itself (same pattern amountTolerance already used
//                   correctly), so it now only zeroes the date signal when out of range
//                   instead of excluding the row outright.

    // Start buffering
    ob_start();

    @session_start();
    $s_id=session_id();
    include("../../includes/connect.php");
    include("../../includes/online.php");


    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    header('Content-Type: application/json');

    // Cast to int before it ever touches SQL - the previous version interpolated
    // $_GET['kladde_id'] straight into the query, which was a SQL injection hole.
    $kladde_id = (int) ($_GET['kladde_id'] ?? $_POST['kladde_id'] ?? 0);

    // Settings panel params (see doc/ai/../invoicematch_settings.md) - all cast to int
    // before touching SQL, same as kladde_id above.
    $date_range        = (int) ($_GET['dateRange'] ?? $_POST['dateRange'] ?? 3);
    if ($date_range < 0) {
        $date_range = 0;
    }
    $amount_tolerance  = (int) ($_GET['amountTolerance'] ?? $_POST['amountTolerance'] ?? 1) ? 1 : 0;
    $include_posted    = (int) ($_GET['includePosted'] ?? $_POST['includePosted'] ?? 0) ? 1 : 0;

    $base_currency_escaped = db_escape_string($baseCurrency ?: 'DKK');

    // pg_trgm gives a real fuzzy-text similarity score. Not every tenant DB can run
    // CREATE EXTENSION (see opdat_4.3.php), so detect it per-request and fall back to a
    // coarser ILIKE/position() substring check instead - the endpoint must not break
    // just because the extension isn't installed.
    $trgm_row = db_fetch_array(db_select("SELECT 1 FROM pg_extension WHERE extname = 'pg_trgm'", __FILE__ . " linje " . __LINE__));
    $has_trgm = (bool) $trgm_row;

    if ($has_trgm) {
        // similarity() returns a float in [0,1]; take the best match across the four
        // bilag text fields.
        $text_score_expr = "(25 * GREATEST(
                similarity(kl.beskrivelse, COALESCE(pf.subject, '')),
                similarity(kl.beskrivelse, COALESCE(pf.description, '')),
                similarity(kl.beskrivelse, COALESCE(pf.invoice_number, '')),
                similarity(kl.beskrivelse, COALESCE(pf.account, ''))
            ))";
    } else {
        // Degraded fallback: a flat partial score when any bilag text field shows up
        // verbatim inside the line's description (or vice versa), otherwise 0.
        $text_score_expr = "(CASE WHEN
                (COALESCE(pf.subject, '') <> '' AND kl.beskrivelse ILIKE '%' || pf.subject || '%')
                OR (COALESCE(pf.description, '') <> '' AND kl.beskrivelse ILIKE '%' || pf.description || '%')
                OR (COALESCE(pf.invoice_number, '') <> '' AND kl.beskrivelse ILIKE '%' || pf.invoice_number || '%')
                OR (COALESCE(pf.account, '') <> '' AND kl.beskrivelse ILIKE '%' || pf.account || '%')
                OR (COALESCE(kl.beskrivelse, '') <> '' AND pf.subject ILIKE '%' || kl.beskrivelse || '%')
            THEN 15 ELSE 0 END)";
    }

    // kassekladde.valuta is an integer code, not a currency string - it points at
    // grupper(art='VK').kodenr, whose box1 holds the actual ISO code (same lookup the
    // main kassekladde grid uses in build_kassekladde_query()). No match -> base currency.
    // pool_files.norm_amount is a real persisted NUMERIC column (populated at write time by
    // extractInvoiceHandler.php / normalizePoolAmount(), plus a one-time backfill - see
    // includes/betweenUpdates.php), already pulled in via `SELECT *` below. Re-deriving it
    // here as `NULLIF(TRIM(amount), '')::numeric` both duplicated the column name (Postgres
    // then throws "column reference is ambiguous" on every `pf.norm_amount` reference below,
    // so this query has been failing outright, on every tenant, regardless of data - the
    // silently-caught DB error is why the endpoint always returned an empty array) and
    // reintroduced the exact naive-cast bug normalizePoolAmount() exists to avoid: Postgres's
    // ::numeric cast doesn't understand Danish thousands separators, so "1.234,56" or even
    // plain "1.234" (meant as 1234 kr) would either error or silently misparse.
    $qtxt = "
        WITH kl AS (
            SELECT k.*,
                UPPER(TRIM(COALESCE(
                    (SELECT vkg.box1 FROM grupper vkg WHERE vkg.art = 'VK' AND vkg.kodenr::text = k.valuta::text LIMIT 1),
                    '$base_currency_escaped'
                ))) AS currency_code
            FROM kassekladde k
            WHERE k.kladde_id = $kladde_id
        ), pf AS (
            SELECT *,
                (CASE WHEN file_date ~ '^[0-9]{4}-[0-9]{2}-[0-9]{2}' THEN file_date::date ELSE NULL END) AS safe_file_date,
                UPPER(TRIM(COALESCE(NULLIF(currency, ''), '$base_currency_escaped'))) AS currency_code
            FROM pool_files
        )
        SELECT * FROM (
            SELECT
                kl.id AS kasse_id,
                pf.id AS pf_id,
                kl.kladde_id AS kladde_id,
                kl.amount AS amount,
                pf.norm_amount AS pool_amount,
                kl.currency_code AS currency,
                pf.subject AS subject,
                kl.beskrivelse AS description,
                pf.filename AS filename,
                pf.account AS account,
                pf.invoice_number AS invoice_number,
                kl.bilag AS bilag,
                kl.debet AS konto,
                kl.kredit AS modkonto,
                kl.transdate AS file_date,
                pf.safe_file_date AS pool_date,

                -- Amount signal (max 40): exact match, or (when the amount-tolerance
                -- setting is on) within +/-0.5% (min +/-1 kr) tolerance
                (CASE
                    WHEN kl.amount IS NULL OR pf.norm_amount IS NULL THEN 0
                    WHEN kl.amount = pf.norm_amount THEN 40
                    WHEN $amount_tolerance = 1 AND ABS(kl.amount - pf.norm_amount) <= GREATEST(ABS(kl.amount) * 0.005, 1) THEN 20
                    ELSE 0
                END) AS amount_score,

                -- Date signal (max 25): invoice date 0-45 days before/equal to transdate,
                -- AND within the user's +/-dateRange setting (see below - this is a signal
                -- gate, not a row filter, so a row that only matches on amount still shows
                -- up with date_score=0 rather than being dropped entirely).
                -- Cubic (not linear) decay: stays close to full marks for the first ~2-3
                -- weeks (typical NET-14/NET-30 payment terms), then drops off sharply as
                -- it approaches the 45-day boundary, where it reaches 0.
                (CASE
                    WHEN pf.safe_file_date IS NULL OR kl.transdate IS NULL THEN 0
                    WHEN ABS(kl.transdate - pf.safe_file_date) > $date_range THEN 0
                    WHEN (kl.transdate - pf.safe_file_date) BETWEEN 0 AND 45
                        THEN ROUND((25 * (1 - POWER((kl.transdate - pf.safe_file_date)::numeric / 45, 3)))::numeric, 1)
                    ELSE 0
                END) AS date_score,

                -- Text signal (max 25): built from \$text_score_expr above (trgm or ILIKE fallback)
                ROUND($text_score_expr::numeric, 1) AS text_score,

                -- Invoice-number-in-description bonus (10)
                (CASE
                    WHEN COALESCE(pf.invoice_number, '') <> '' AND kl.beskrivelse ILIKE '%' || pf.invoice_number || '%'
                        THEN 10 ELSE 0
                END) AS invoice_bonus
            FROM kl
            CROSS JOIN pf
            WHERE kl.currency_code = pf.currency_code
                -- Include-already-posted setting: pool_files rows are normally
                -- deleted once attached (insertDoc.php), but that delete is best-effort
                -- (@db_modify), so exclude any pool file a documents row already claims
                -- unless the user opts in.
                AND ($include_posted = 1 OR NOT EXISTS (
                    SELECT 1 FROM documents d WHERE d.filename = pf.filename
                ))
        ) scored
        WHERE (amount_score + date_score + text_score + invoice_bonus) >= 30
        ORDER BY (amount_score + date_score + text_score + invoice_bonus) DESC
    ";

    $kasseQuery = db_select($qtxt, __FILE__ . " linje " . __LINE__);

    $rowIndex = 0;
    $OD = []; //Output Data

    while($row = db_fetch_array($kasseQuery)){
        $row = array_filter($row,
            function ($col) {
                return !is_int($col);
            },
            ARRAY_FILTER_USE_KEY
        );

        $score = round(
            (float) ($row['amount_score'] ?? 0)
            + (float) ($row['date_score'] ?? 0)
            + (float) ($row['text_score'] ?? 0)
            + (float) ($row['invoice_bonus'] ?? 0)
        );
        $row['score'] = min(100, max(0, $score));
        // Green/"high" cutoff set to 60, not the ticket's ~80: acceptance criterion 2
        // (exact amount + matching currency + invoice date 14 days before transdate, no
        // text/invoice-no signal) must score "high" - with the suggested weights
        // (40 amount + 25 date, decayed at 14/45 days) that's 40 + 25*(1-(14/45)^3) ~= 64.
        // A pure fuzzy-text-and-date match with no amount signal (criterion 3) tops out
        // around 25+25=50, so it still lands "medium" with this cutoff.
        $row['precision'] = ($row['score'] >= 60) ? 'high' : 'medium';

        $OD[$rowIndex] = [];
        foreach($row as $key => $value){
            $OD[$rowIndex][$key] = $value;
        }
        ++$rowIndex;
    }

    // Get rid of anything they output
    ob_clean(); // or ob_end_clean();

    echo json_encode($OD);
?>
