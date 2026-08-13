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
// 20260507 NTR - Added batch invoice matching (this)
// 20260513 PK - Changed padding from 20px to 8px
// 20260720 Sawaneh Pre-check a row only when both amount and date match, via the
//                new popupManager isRowChecked predicate.
// 20260721 CL/SZ - Rewrote the popup per the new design: Type/Dato/Bilag/Tekst/Beløb/Konto/
//                   Modkonto/Valuta/Præcision/link-icon columns, a live "{n} valgt · {m}
//                   fundet" summary, high-confidence rows pre-selected, Annullér/OK footer,
//                   and a "0 fundet" empty state. All labels now go through findtekst()
//                   (fixes the "Reciever" typo by not carrying that column over). Removed a
//                   leftover console.log from hidePreview().
// 20260721 CL/SZ - AttachAll() fix: it was firing one parallel request per selected row,
//                   which races on insertDoc.php's rename() when one pool file matches
//                   several lines (e.g. an invoice split across journal lines). Restored
//                   filename-grouped requests with "targetSourceIds" for sibling lines -
//                   insertDoc.php already supports this (see includes/docsIncludes/insertDoc.php),
//                   it just wasn't being used correctly.
// 20260721 CL/SZ - Second pass on the popup to match the mockup exactly, not just its column
//                   set: header close button is now an icon-only "x" (popupManager.js was
//                   rendering the closeLabel text there instead), the "{n} valgt · {m} fundet"
//                   summary now sits directly under the header instead of in the footer, and
//                   the footer now shows both Annullér (outline) and OK (system button color
//                   via $buttonStyle) instead of OK only - was hardcoded red/green
//                   (#F44336/#4CAF50), matching neither the mockup nor this app's button
//                   convention.
// 20260729 CL/SZ - Renumbered the Bilagsmatch tekst_id block from 5032-5047 to 5040-5055:
//                   upstream/master independently claimed 5032-5039 for unrelated GS1/POS
//                   strings after this branch's merge-base, so keeping the old numbers would
//                   collide once the branches meet. See importfiler/tekster.csv.
// 20260811 CL/SZ - Added the settings panel (date range / amount tolerance / include-posted)
//                   and reworked the Præcision icon column into a clickable Match badge per
//                   invoicematch_settings.md: the badge (not the checkbox column, now hidden)
//                   is the primary selection affordance, matching how the whole row already
//                   toggled via PopupManager's row-click handler. New tekst_id block
//                   5056-5066 (5040-5055 already fully used, see note above); reused id 122
//                   for "Indstillinger" since it already exists. Also added the footer result
//                   count and renamed the primary button from OK to "Match valgte", disabled
//                   while nothing is checked (both via new backward-compatible PopupManager
//                   options - see javascript/popupManager.js history).
// 20260811 CL/SZ - Follow-up polish after live-testing against the bookkeeper's mockup:
//                   restored the checkbox column (the badge is an ADDITIONAL selection
//                   affordance, not a replacement - the mockup keeps both), added the
//                   settings-panel title row, made the type/link icons and the settings
//                   button use the account's actual $buttonColor instead of a hardcoded
//                   blue that didn't match it, stopped the lookup/link icon clicks from
//                   also toggling row selection as a side effect, and added a loading state
//                   (bmSetLoading) + a bmRequestSeq guard around bmRefetch() so a settings
//                   change re-fetch gives visible feedback and a later request can't get
//                   clobbered by an earlier one resolving after it.
// 20260811 CL/SZ - Added a confirmation before attaching a selection that includes a
//                   non-green (not a 100% match) row, via PopupManager's new confirmExit
//                   hook - a mistaken attach is far more likely for those than for an exact
//                   match, and the badge's CSS class (not its translated text) is what gets
//                   checked, so this works the same in all three languages.
// 20260812 CL/SZ - Fixed per client feedback (Mr. Rude, 20260812): the badge tooltip showed
//                   the aggregate row.score, which could read "60%" on a row badged "100 %
//                   match" (that badge only needs total score >=60 with an exact amount,
//                   not a literal 100) - looked like a contradiction. Tooltip now shows the
//                   amount/date signals separately (each normalised to its own max) instead,
//                   reusing the existing Beløb/Dato column labels rather than new strings.
//                   That breakdown then surfaced a real bug it had been masking: the green
//                   badge checked row.precision (amount+date+text+invoice-number combined),
//                   so an exact amount plus a strong text/invoice signal alone could show
//                   "100 % match" with a 0 % date signal. Now checks date_score > 0
//                   specifically, so green genuinely means both signals matched.
// 20260812 CL/SZ - Added the pinned document preview (kravsspecifikation popup fast.pdf):
//                   clicking the Type-column attachment icon opens a persistent, draggable,
//                   resizable window (bmOpenPinnedPreview/bmClosePinnedPreview), separate
//                   from and suppressing the existing hover preview while open. Reuses the
//                   browser's own PDF viewer via <embed> (zoom/page-nav/rotate come from
//                   the browser, not a new component library, per NFR-3) and adds minimal
//                   custom zoom controls only for images, which have no native viewer.
//                   Title bar uses the account's own $buttonColor, not the literal purple
//                   NFR-3 asked for - confirmed with the team, no purple convention exists
//                   anywhere else in this app; that line appears to be spillover from the
//                   other spec's bookkeeper mockup. Known gap: NFR-6 (rights-checked file
//                   access) is not addressed - this reuses the same un-authenticated static
//                   path the old hover preview already used, which is a pre-existing,
//                   app-wide gap (also in includes/documents.php, docPool.php), not
//                   something this feature introduces or fixes.
// 20260812 CL/SZ - Per client feedback: retired the old hover preview entirely (showPreview/
//                   hidePreview/movePreview and the #previewPopup markup are gone) - the
//                   pinned preview is now the only way to view an attachment, opened by
//                   clicking the Type-column icon. Its tooltip text now reads "Click to see
//                   attachment" (findtekst 5068) instead of "Click to open the document".
//                   Default position is now centered in the viewport instead of anchored
//                   beside the dialog, which also removes the earlier "beside the dialog"
//                   placement that could overlap it in a way some users read as the window
//                   letting clicks through to the table underneath.
// 20260812 CL/SZ - Per client feedback: the loading/error/unsupported states rendered as
//                   plain white text on the same flat dark-gray canvas used to frame an
//                   actual PDF/image, which read as a broken screen rather than a
//                   deliberate state. bmRenderPinnedStatus() now switches the body to a
//                   light card (loading spinner, red warning icon for errors, a neutral
//                   document glyph for unsupported formats) - the dark canvas is reserved
//                   for when a document is actually being shown.
// 20260812 CL/SZ - The attachment icon and the row it sits in had the same pointer cursor
//                   and no visual difference, so nothing signalled "click this exact 20px
//                   icon to preview" versus "click anywhere else in the row to select it" -
//                   a tooltip alone isn't discoverable until the user already stops on it.
//                   Gave the icon a button-like chip (background + border, darkens on
//                   hover/focus) so it reads as its own control at a glance, independent of
//                   the tooltip text.
?>

<?php
$bm_title          = findtekst('5051|Bilagsmatch', $sprog_id);
$bm_col_type       = findtekst('5048|Type', $sprog_id);
$bm_col_dato       = findtekst('5052|Dato', $sprog_id);
$bm_col_bilag      = findtekst('5053|Bilag', $sprog_id);
$bm_col_tekst      = findtekst('5054|Tekst', $sprog_id);
$bm_col_beloeb     = findtekst('5055|Beløb', $sprog_id);
$bm_col_konto      = findtekst('5040|Konto', $sprog_id);
$bm_col_modkonto   = findtekst('5041|Modkonto', $sprog_id);
$bm_col_valuta     = findtekst('5042|Valuta', $sprog_id);
$bm_cancel         = findtekst('5044|Annullér', $sprog_id);
$bm_summary_tpl    = findtekst('5045|$n match valgt · $m fundet', $sprog_id);
$bm_empty_title    = findtekst('5046|0 fundet', $sprog_id);
$bm_empty_message  = findtekst('5047|Ingen forslag til match fundet for denne kladde.', $sprog_id);
$bm_link_tooltip   = findtekst('5049|Klik for at forhåndsvise/åbne dokumentet', $sprog_id);
$bm_lookup_tooltip = findtekst('5050|Slå konto op', $sprog_id);

// Settings panel + Match badge labels (invoicematch_settings.md). 122 already exists for
// "Indstillinger" - reused rather than allocating a duplicate id.
$bm_settings          = findtekst('122|Indstillinger', $sprog_id);
$bm_col_daterange     = findtekst('5059|Datointerval:', $sprog_id);
$bm_date_hint         = findtekst('5060|dage omkring bilagsdatoen', $sprog_id);
$bm_col_amounttol     = findtekst('5061|Beløbstolerance:', $sprog_id);
$bm_chk_amount_tol    = findtekst('5062|Tillad afvigelse på 0,5 %', $sprog_id);
$bm_chk_include_posted = findtekst('5063|Medtag allerede bogførte bilag', $sprog_id);
$bm_col_match         = findtekst('5064|Match', $sprog_id);
$bm_badge_full        = findtekst('5065|100 % match', $sprog_id);
$bm_badge_date        = findtekst('5066|Dato matcher', $sprog_id);
$bm_badge_amount      = findtekst('5067|Beløb matcher', $sprog_id);
$bm_footer_count_tpl  = findtekst('5068|Viser $m resultater', $sprog_id);
$bm_match_selected    = findtekst('5069|Match valgte', $sprog_id);
$bm_confirm_uncertain = findtekst('5070|Nogle af de valgte matches er ikke 100 % sikre. Vil du fortsætte?', $sprog_id);

// Pinned document preview (kravsspecifikation popup fast.pdf). Reused existing ids for
// "Luk"/Close (122... no, 2172), "Download" (2708) and "Prøv igen"/Try again (3303) rather
// than duplicating them.
$bm_pin_open_tooltip  = findtekst('5071|Klik for at se bilaget', $sprog_id);
$bm_pin_zoom_out      = findtekst('5072|Zoom ud', $sprog_id);
$bm_pin_zoom_in       = findtekst('5073|Zoom ind', $sprog_id);
$bm_pin_fit_width     = findtekst('5074|Tilpas til bredde', $sprog_id);
$bm_pin_open_tab      = findtekst('5075|Åbn i ny fane', $sprog_id);
$bm_pin_unsupported   = findtekst('5076|Formatet kan ikke forhåndsvises', $sprog_id);
$bm_pin_load_error    = findtekst('5077|Kunne ikke indlæse dokumentet', $sprog_id);
$bm_pin_close         = findtekst('2172|Luk', $sprog_id);
$bm_pin_download      = findtekst('2708|Download', $sprog_id);
$bm_pin_retry         = findtekst('3303|Prøv igen', $sprog_id);
$bm_pin_loading       = findtekst('3277|Indlæser', $sprog_id);
?>

<script src='../javascript/popupManager.js'></script>
<script type='text/javascript'>
    // Settings panel state (invoicematch_settings.md, open question 2: session-only, the
    // cheap first version - not persisted per-user in the db). panelOpen is remembered too
    // so a user who opens the panel once keeps seeing it for the rest of the session.
    const BM_SETTINGS_KEY = 'bilagsmatchSettings';
    const BM_DATE_RANGE_OPTIONS = [0, 1, 3, 7, 14];

    function bmLoadSettings() {
        const defaults = { dateRange: 3, amountTolerance: 1, includePosted: 0, panelOpen: false };
        try {
            const raw = sessionStorage.getItem(BM_SETTINGS_KEY);
            return raw ? Object.assign(defaults, JSON.parse(raw)) : defaults;
        } catch (e) {
            return defaults;
        }
    }

    function bmSaveSettings() {
        try { sessionStorage.setItem(BM_SETTINGS_KEY, JSON.stringify(bmSettings)); } catch (e) { /* private-browsing storage denial etc. - settings just won't persist */ }
    }

    let bmSettings = bmLoadSettings();

    // paper.png/clip.png in ikoner/ both contain the same unrelated shield glyph, not a
    // document or link icon - inlined SVGs here instead of pointing Type/link at those.
    // encodeURIComponent leaves single quotes unescaped, so the SVG markup below uses
    // double quotes for its own attributes - single quotes here would prematurely close
    // the (single-quoted) src='...' attribute the <img> tags are rendered with.
    const typeIcon   = 'data:image/svg+xml;utf8,' + encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="<?= $buttonColor ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v4a1 1 0 0 0 1 1h4"/></svg>`
    );
    // opslag.png is a document+magnifier combo icon that reads as clutter at 20px -
    // a plain magnifying glass (inline SVG, same reasoning as typeIcon/linkIcon above)
    // matches the mockup's lookup icon more clearly.
    const lookupIcon = 'data:image/svg+xml;utf8,' + encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#5a6b8c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M20 20l-4.5-4.5"/></svg>`
    );
    const linkIcon   = 'data:image/svg+xml;utf8,' + encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="<?= $buttonColor ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 14a5 5 0 0 1 0-7l3-3a5 5 0 0 1 7 7l-1.5 1.5"/><path d="M14 10a5 5 0 0 1 0 7l-3 3a5 5 0 0 1-7-7l1.5 1.5"/></svg>`
    );

    function bmFormatAmount(row) {
        var n = parseFloat(row.amount);
        if (isNaN(n)) return '';
        return n.toLocaleString('da-DK', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    /**
     * Replaces the old Præcision icon column: a colored text badge naming *why* the row
     * matched, doubling as the row's primary selection affordance (row-selected styling is
     * applied via CSS from the "row-selected" class PopupManager keeps on the <tr> - see
     * .bilags-match-badge rules below). Green requires the amount AND date signals
     * specifically (not row.precision, which also folds in text-similarity/invoice-number
     * weights unrelated to what this badge claims - an exact amount plus a strong text/
     * invoice match alone can cross precision:high with zero date contribution, which
     * would wrongly show "100 % match" on a row whose date didn't match at all). date_score
     * is 0 whenever the pool file's date fell outside the user's +/-dateRange setting (see
     * fetchbilagsmatch.php), so ">0" here means "date matches" in exactly that sense.
     */
    function bmMatchBadge(row) {
        const amountScore = parseFloat(row.amount_score) || 0;
        const dateScore = parseFloat(row.date_score) || 0;

        let cssClass, label;
        if (amountScore >= 40 && dateScore > 0) {
            cssClass = 'bilags-match-green';
            label = <?= json_encode($bm_badge_full) ?>;
        } else if (amountScore >= dateScore) {
            cssClass = 'bilags-match-yellow';
            label = <?= json_encode($bm_badge_amount) ?>;
        } else {
            cssClass = 'bilags-match-yellow';
            label = <?= json_encode($bm_badge_date) ?>;
        }

        // Tooltip shows the amount/date signals separately (each normalised to their own
        // max: amount_score/40, date_score/25) instead of the old aggregate row.score,
        // which mixed in the text-similarity and invoice-number weights the user never
        // sees - that could show e.g. "60%" on a row badged "100 % match" (exact amount +
        // precision:high only needs a *total* >=60, not a full 100), reading as a
        // contradiction. Per-signal numbers instead confirm the badge rather than
        // seeming to dispute it.
        const amountPct = Math.round((amountScore / 40) * 100);
        const datePct = Math.round((dateScore / 25) * 100);
        const tooltip = `<?= $bm_col_beloeb ?>: ${amountPct} % · <?= $bm_col_dato ?>: ${datePct} %`;

        return `<span class="bilags-match-badge ${cssClass}" title="${tooltip}">`
            + `<span class="bilags-match-check">&#10003;</span> ${label}</span>`;
    }

    /** Settings-toggle button, rendered inline on the summary row (PopupManager's summaryExtraHtml). */
    function bmRenderSettingsToggle() {
        return `<button type="button" id="bm-settings-toggle" class="saldi-button popup-btn-secondary bm-settings-toggle">`
            + `&#9881; <?= $bm_settings ?></button>`;
    }

    /**
     * Settings panel, rendered below the summary row (PopupManager's toolbarHtml).
     * Re-evaluated on every popup()/re-fetch call so chip/checkbox state always reflects
     * the current bmSettings - visibility itself is toggled directly via the DOM afterwards
     * (see the click handler below) rather than by re-rendering, so opening/closing the
     * panel never disturbs the row checkboxes the user has already toggled.
     */
    function bmRenderSettingsPanel() {
        const chips = BM_DATE_RANGE_OPTIONS.map(n => {
            const active = bmSettings.dateRange === n ? ' active' : '';
            return `<button type="button" class="bm-chip${active}" data-range="${n}">&plusmn;${n}</button>`;
        }).join('');

        return `
            <div id="bm-settings-panel" class="bm-settings-panel" style="display:${bmSettings.panelOpen ? 'block' : 'none'}">
                <div class="bm-settings-title"><?= $bm_settings ?></div>
                <div class="bm-settings-row">
                    <span class="bm-settings-label"><?= $bm_col_daterange ?></span>
                    <span class="bm-chip-group">${chips}</span>
                    <span class="bm-settings-hint"><?= $bm_date_hint ?></span>
                </div>
                <div class="bm-settings-row">
                    <span class="bm-settings-label"><?= $bm_col_amounttol ?></span>
                    <label class="bm-settings-checkbox"><input type="checkbox" id="bm-amount-tolerance" ${bmSettings.amountTolerance ? 'checked' : ''}/> <?= $bm_chk_amount_tol ?></label>
                    <label class="bm-settings-checkbox"><input type="checkbox" id="bm-include-posted" ${bmSettings.includePosted ? 'checked' : ''}/> <?= $bm_chk_include_posted ?></label>
                </div>
            </div>
        `;
    }

    const columns = ColumnInfo.fromPositionalArray([
        ["ka. id - hidden", "kasse_id", "style='display:none;'", "style='display:none;'", 'kasse_id'],
        ["pf. id - hidden", "pf_id", "style='display:none;'", "style='display:none;'", 'pf_id'],
        ["kl. id - hidden", "kladde_id", "style='display:none;'", "style='display:none;'", 'kladde_id'],
        ["filename - hidden", "filename", "style='display:none;'", "class='bilags-filename' style='display:none;'", 'filename'],
        ["<?= $bm_col_type ?>", (row) => `<img src='${typeIcon}' title='${row.filename ?? ''}&#10;<?= $bm_pin_open_tooltip ?>' class='bilags-type-icon' tabindex='0' role='button' aria-label='<?= $bm_pin_open_tooltip ?>' data-filename='${row.filename ?? ''}' data-bilag='${row.bilag ?? ''}'/>`, "class='bilags-type'", "class='bilags-type'", 'type'],
        ["<?= $bm_col_dato ?>", "file_date", "class='bilags-date'", null, 'dato'],
        ["<?= $bm_col_bilag ?>", "bilag", "class='bilags-bilag'", null, 'bilag'],
        ["<?= $bm_col_tekst ?>", "description", "class='bilags-description'", "class='bilags-description'", 'tekst'],
        ["<?= $bm_col_beloeb ?>", (row) => bmFormatAmount(row), "class='bilags-amount'", null, 'beloeb'],
        ["<?= $bm_col_konto ?>", "konto", "class='bilags-konto'", null, 'konto'],
        ["<?= $bm_col_modkonto ?>", (row) => `${row.modkonto ?? ''} <img src='${lookupIcon}' title='<?= $bm_lookup_tooltip ?>' class='bilags-lookup-icon'/>`, "class='bilags-modkonto'", null, 'modkonto'],
        ["<?= $bm_col_valuta ?>", "currency", "class='bilags-valuta'", null, 'valuta'],
        ["<?= $bm_col_match ?>", (row) => bmMatchBadge(row), "class='bilags-match'", "class='bilags-match'", 'match'],
        ["", (row) => `<a href='#' class='bilags-link' title='<?= $bm_link_tooltip ?>' onclick='return false;'><img src='${linkIcon}'/></a>`, "class='bilags-linkcol'", "class='bilags-linkcol'", 'link'],
    ]);

    // PopupManager's shared default dialog style is a light-gray background (#eeeef0)
    // with thick beveled borders - override just for this popup to match the Bilagsmatch
    // mockup's clean white dialog, without touching that shared default for other popups.
    const popupStyle = {
        background: '#ffffff',
        border: 'none',
        borderRadius: '10px',
        boxShadow: '0 12px 40px rgba(0,0,0,0.25)',
        padding: '20px 24px',
        width: '960px',
        maxWidth: '95vw',
        // Default position:absolute anchors to the DOCUMENT (scrolls with the page) -
        // fixed anchors to the actual viewport instead, so this floats in view correctly
        // regardless of how far down the page you were scrolled when you opened it.
        position: 'fixed',
        // Horizontally centered (self-correcting for the actual rendered width via
        // translateX, not a fixed-pixel calc() that only centers at exactly 960px).
        // Floats near the top of the viewport rather than vertically centered.
        left: '50%',
        top: '40px',
        transform: 'translateX(-50%)',
        // Default is `overflow: auto` on the whole dialog, which scrolls the header and
        // summary bar along with the table. Make this a flex column instead so only
        // #popup-results (the table) scrolls, keeping header/summary/footer pinned.
        display: 'flex',
        flexDirection: 'column',
        overflow: 'hidden',
    };

    /**
     * Attaches selected rows via includes/documents.php, grouping rows that share the
     * same pool file into a single request.
     *
     * insertDoc.php (included via documents.php -> docPool()) moves the pool file's
     * physical file on disk with a single rename() per file, then fans the resulting
     * documents-table row out to sibling lines via the "targetSourceIds" POST param -
     * firing one parallel request per row for a file matched to several lines (e.g. one
     * invoice split across multiple journal lines) races that same rename() and only the
     * first request's attach survives. Group by filename and pass the rest as
     * targetSourceIds instead, one request per distinct file.
     */
    async function AttachAll(resultRows){
        const groups = new Map();
        for (const row of resultRows) {
            if (!groups.has(row.filename)) groups.set(row.filename, []);
            groups.get(row.filename).push(row);
        }

        for (const [filename, rows] of groups) {
            const primary = rows[0];
            const otherIds = rows.slice(1).map(r => r.kasse_id).join(',');
            const body = new URLSearchParams({
                source:     'kassekladde',
                kladde_id:  primary.kladde_id,
                sourceId:   primary.kasse_id,
                openPool:   '1',
                insertFile: '1',
                poolFiles:  filename,
            });
            if (otherIds) body.set('targetSourceIds', otherIds);
            await fetch(`../includes/documents.php`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    body.toString(),
            });
        }
        location.replace(location.pathname + location.search);
    }

    const dimmerStyle = {
        // Default position:absolute anchors the dimmer to the document, not the
        // viewport - if the page is taller than one screen (it is here, via the sticky
        // footer) and you're scrolled down, the dimmer doesn't reach the bottom of what
        // you're actually looking at. Fixed always covers the full visible viewport.
        position: 'fixed',
    };

    const popuper = new PopupManager(columns, popupStyle, AttachAll, <?= json_encode($bm_match_selected) ?>, dimmerStyle, {
        closeLabel: <?= json_encode($bm_cancel) ?>,
        checkboxHeaderLabel: '',
        selectAllHeader: true,
        disableExitWhenEmpty: true,
        preSelectFn: (row) => row.precision === 'high',
        summaryFn: (selected, total) => <?= json_encode($bm_summary_tpl) ?>.replace('$n', selected).replace('$m', total),
        summaryExtraHtml: () => bmRenderSettingsToggle(),
        toolbarHtml: () => bmRenderSettingsPanel(),
        footerTextFn: (selected, total) => <?= json_encode($bm_footer_count_tpl) ?>.replace('$m', total),
        // Green (100% match) rows go through without asking. Anything else selected
        // (a yellow "Dato matcher"/"Beløb matcher" row) is the case most likely to be a
        // mistaken attach, so ask for confirmation before actually matching - checking the
        // CSS class rather than the badge text keeps this language-independent.
        confirmExit: (resultArr) => {
            const hasUncertain = resultArr.some(row => !(row.match ?? '').includes('bilags-match-green'));
            return !hasUncertain || confirm(<?= json_encode($bm_confirm_uncertain) ?>);
        },
        noResultsHtml: `<div class="popup-no-results">
            <div class="popup-no-results-title"><?= $bm_empty_title ?></div>
            <div class="popup-no-results-message"><?= $bm_empty_message ?></div>
        </div>`,
    });

    popuper.onResult.push(function(container){
        // The lookup (account search), link (document) and type (attachment) icons are
        // their own affordances, not a selection toggle - without this, clicking any of
        // them also flipped the row's checkbox as a surprising side effect of
        // PopupManager's click-anywhere-in-the-row selection handling (which only skips
        // the toggle when the click target IS the checkbox itself).
        container.querySelectorAll('.bilags-lookup-icon, .bilags-link, .bilags-type-icon').forEach(el => {
            el.addEventListener('mousedown', function(e) { e.stopPropagation(); });
        });
        // FR-1/FR-11 (kravsspecifikation popup fast.pdf): clicking the attachment icon
        // opens the pinned preview, reading the filename/bilag stashed on the icon itself
        // at render time (bmMatchBadge's row data isn't available here - the DOM is).
        // NFR-4: also Enter/Space, since the icon is a real tabindex='0' control now, not
        // just a mouse target.
        container.querySelectorAll('.bilags-type-icon').forEach(el => {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                bmOpenPinnedPreview(el.dataset.filename, el.dataset.bilag, el);
            });
            el.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    bmOpenPinnedPreview(el.dataset.filename, el.dataset.bilag, el);
                }
            });
        });
        // Click-outside-to-close: scoped to this popup only (not a PopupManager-wide
        // default, since other popups in the app share that component). Discards without
        // calling exitCall, same as the header x / footer Annullér.
        document.querySelector("#background-dimmer").addEventListener('mousedown', function(e) {
            if (bmPinnedOpen) bmClosePinnedPreview();
            popuper.closeDropdown();
        });

        // Settings panel wiring (re-attached on every render, including re-fetches -
        // the panel/toggle markup itself is rebuilt fresh each time by summaryExtraHtml/
        // toolbarHtml). Opening/closing the panel is a plain style toggle, not a re-render,
        // so it never disturbs row checkboxes the user has already changed.
        const settingsToggle = container.querySelector('#bm-settings-toggle');
        if (settingsToggle) {
            settingsToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                bmSettings.panelOpen = !bmSettings.panelOpen;
                bmSaveSettings();
                const panel = container.querySelector('#bm-settings-panel');
                if (panel) panel.style.display = bmSettings.panelOpen ? 'block' : 'none';
            });
        }

        container.querySelectorAll('.bm-chip').forEach(chip => {
            chip.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                bmSettings.dateRange = Number(chip.dataset.range);
                bmSaveSettings();
                bmRefetch();
            });
        });

        const amountToleranceBox = container.querySelector('#bm-amount-tolerance');
        if (amountToleranceBox) {
            amountToleranceBox.addEventListener('change', function() {
                bmSettings.amountTolerance = amountToleranceBox.checked ? 1 : 0;
                bmSaveSettings();
                bmRefetch();
            });
        }

        const includePostedBox = container.querySelector('#bm-include-posted');
        if (includePostedBox) {
            includePostedBox.addEventListener('change', function() {
                bmSettings.includePosted = includePostedBox.checked ? 1 : 0;
                bmSaveSettings();
                bmRefetch();
            });
        }
    });

    popuper.onClose.push(function(container){
        // Open question 3 (kravsspecifikation popup fast.pdf, unresolved in the spec):
        // closing the dialog also closes the pinned preview, rather than leaving an
        // orphaned floating window with no parent context.
        if (bmPinnedOpen) bmClosePinnedPreview();
    });

    let bmKladdeId = null;

    // Bumped on every bmRefetch() call; a response only gets applied if its own seq is
    // still the latest by the time it resolves. Without this, firing two settings changes
    // in quick succession (e.g. clicking two date-range chips before the first request
    // returns) let whichever response arrived last "win", even if it wasn't the last one
    // requested - the visible chip/checkbox state could end up not matching the rows shown.
    let bmRequestSeq = 0;

    /**
     * Dims the results table and disables the settings panel's own controls while a
     * setting-change re-fetch is in flight, so the user gets visible feedback instead of
     * the table just silently sitting there, and can't stack up further requests (the
     * bmRequestSeq guard above would keep them correct anyway, but blocking them here
     * avoids the confusion of clicking a chip that then appears to do nothing).
     */
    function bmSetLoading(loading){
        const container = popuper.getPopupContainer();
        const results = container.querySelector('#popup-results');
        if (results) results.classList.toggle('bm-loading', loading);
        const panel = container.querySelector('#bm-settings-panel');
        if (panel) {
            panel.classList.toggle('bm-loading', loading);
            panel.querySelectorAll('button, input').forEach(el => { el.disabled = loading; });
        }
    }

    // Re-fetches with the current settings and re-renders. Called on initial open and
    // whenever a setting that affects the SQL (date range / amount tolerance / include
    // posted) changes - selections are naturally reset to the preSelectFn default since
    // this is a fresh popup() render over fresh data, matching the settings panel spec.
    function bmRefetch(){
        const seq = ++bmRequestSeq;
        bmSetLoading(true);
        const params = new URLSearchParams({
            kladde_id:        bmKladdeId,
            dateRange:        bmSettings.dateRange,
            amountTolerance:  bmSettings.amountTolerance,
            includePosted:    bmSettings.includePosted,
        });
        fetch(`./kassekladde_includes/fetchbilagsmatch.php?${params.toString()}`)
            .then(res => res.json())
            .then(data => {
                if (seq !== bmRequestSeq) return; // superseded by a later request - drop it
                popuper.popup(data, <?= json_encode($bm_title) ?>);
            })
            .finally(() => {
                if (seq === bmRequestSeq) bmSetLoading(false);
            });
    }

    function openPopup(){
        let params = new URLSearchParams(document.location.search);
        bmKladdeId = params.get("kladde_id");
        bmRefetch();
    }
</script>
<style>
    #popup-results tbody tr:nth-child(even) {
        background: #dce6f5;
    }
    #popup-results tbody tr:nth-child(odd) {
        background: #e8f0fa;
    }
    #popup-results tbody tr {
        overflow: hidden;
    }

    /* Horizontal separation between rows only - never vertical lines between columns.
       (border-collapse so the per-cell border-bottom below renders as one clean line
       per row instead of doubled/gapped borders between adjacent cells.) */
    #popup-results .popup-table {
        border-collapse: collapse;
        width: 100%;
    }
    #popup-results .popup-table td, #popup-results .popup-table th {
        border-left: none;
        border-right: none;
    }
    #popup-results .popup-table tbody td {
        border-bottom: 1px solid #dbe3ee;
    }

    /* Mockup left-aligns text-column headers; only the icon-only columns (Type,
       the trailing link column) are centered. */
    #popup-results thead tr th {
        text-align: left;
    }
    #popup-results thead tr th.bilags-type,
    #popup-results thead tr th.bilags-linkcol {
        text-align: center;
    }

    /* Whole row is clickable (PopupManager toggles the row's checkbox on any click that
       isn't on the checkbox itself), so signal that with a pointer cursor everywhere in it. */
    #popup-results .autocomplete-item {
        cursor: pointer;
    }

    .saldi-button {
        <?= $buttonStyle ?>
        cursor: pointer;
        padding: 6px 20px;
        font-size: 14px;
    }
    /* Match valgte is greyed out while zero rows are checked (disableExitWhenEmpty) - custom
       background-color from $buttonStyle above would otherwise mask the native disabled look. */
    .saldi-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Footer: result count text + Annullér (outline) + Match valgte (system button color,
       via .saldi-button/$buttonStyle) */
    .popup-footer {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 10px;
        padding: 10px 4px 4px;
        flex: 0 0 auto;
    }

    .popup-btn-secondary {
        background-color: #ffffff;
        color: #333;
        border: 1px solid #b8bec8;
    }

    /* Header: title centered across the dialog, small square "x" close button pinned
       to the top-right corner (matches the mockup, not a flex space-between layout). */
    #popup-header {
        position: relative;
        padding: 4px 0 12px;
        margin-bottom: 8px;
        border-bottom: 1px solid #e0e0e0;
        flex: 0 0 auto;
    }

    #popup-header-title {
        display: block;
        text-align: center;
        font-size: 28px;
        font-weight: 700;
    }

    .popup-close-x {
        position: absolute;
        top: 0;
        right: 0;
        width: 28px;
        height: 28px;
        line-height: 1;
        padding: 0;
        border: 1px solid #b8bec8;
        border-radius: 4px;
        background: #ffffff;
        color: #333;
        font-size: 18px;
        cursor: pointer;
    }
    .popup-close-x:hover {
        background: #f0f0f0;
    }

    #popup-summary-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        font-weight: bold;
        padding: 4px 2px 8px;
        flex: 0 0 auto;
    }

    /* Blue outline, not the grey .popup-btn-secondary look Annullér uses - matches the
       mockup's "Settings" button (white background, blue border + text). */
    .bm-settings-toggle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        font-weight: normal;
        background-color: #ffffff;
        color: <?= $buttonColor ?>;
        border: 1px solid <?= $buttonColor ?>;
    }

    .bm-settings-panel {
        background: #f3f6fb;
        border: 1px solid #dbe3ee;
        border-radius: 8px;
        padding: 10px 14px;
        margin: 0 0 8px;
        flex: 0 0 auto;
    }
    .bm-settings-title {
        font-weight: bold;
        color: <?= $buttonColor ?>;
        padding: 2px 0 6px;
    }
    .bm-settings-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding: 4px 0;
    }
    .bm-settings-label {
        font-weight: bold;
        min-width: 110px;
    }
    .bm-settings-hint {
        color: #666;
        font-size: 12px;
    }
    .bm-chip-group {
        display: flex;
        gap: 6px;
    }
    .bm-chip {
        background: #ffffff;
        color: #333;
        border: 1px solid #b8bec8;
        border-radius: 999px;
        padding: 4px 12px;
        font-size: 13px;
        cursor: pointer;
    }
    .bm-chip.active {
        <?= $buttonStyle ?>
        border-color: transparent;
    }
    .bm-settings-checkbox {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        font-weight: normal;
        cursor: pointer;
    }

    /* Match badge: replaces the old Præcision icon column and doubles as the row's
       selection affordance (row-selected is a class PopupManager keeps on the <tr>). */
    .bilags-match-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 999px;
        border: 1px solid transparent;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
        cursor: pointer;
    }
    .bilags-match-green {
        background: #d7f0dd;
        color: #1e7a3a;
    }
    .bilags-match-yellow {
        background: #fdf0cd;
        color: #9a7412;
    }
    .bilags-match-check {
        display: none;
    }
    /* Selected: badge gets a ring in its own color plus a checkmark; unselected: badge
       dims slightly so the checked rows visibly stand out from the unchecked ones. */
    .autocomplete-item.row-selected .bilags-match-badge {
        border-color: currentColor;
        box-shadow: 0 0 0 1px currentColor inset;
    }
    .autocomplete-item.row-selected .bilags-match-check {
        display: inline;
    }
    .autocomplete-item:not(.row-selected) .bilags-match-badge {
        opacity: 0.6;
    }

    /* Footer is justify-content:flex-end (see .popup-footer above), so this simply joins
       the two buttons as a right-aligned group: "Viser {m} resultater · Annullér · Match valgte". */
    #popup-footer-text {
        color: #666;
        font-size: 13px;
    }

    /* Only this element scrolls now (the outer dialog is overflow:hidden) - header,
       summary bar and footer stay pinned in view. */
    #popup-results {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
    }

    /* Visible feedback while a settings-change re-fetch is in flight (bmSetLoading) -
       dims the table and blocks interaction with it and the settings panel's own controls
       instead of the table just silently sitting there until the new data arrives. */
    #popup-results.bm-loading, #bm-settings-panel.bm-loading {
        opacity: 0.5;
        pointer-events: none;
        transition: opacity 0.15s ease;
    }

    .popup-no-results{
        width: 50vw;
        text-align: center;
        padding: 30px 10px;
    }
    .popup-no-results-title {
        font-size: 20px;
        font-weight: bold;
        margin-bottom: 8px;
    }
    .popup-no-results-message {
        color: #666;
    }

    .bilags-type-icon, .bilags-lookup-icon, .bilags-linkcol img {
        width: 20px;
    }
    .bilags-linkcol a, .bilags-lookup-icon, .bilags-type-icon {
        cursor: pointer;
    }
    /* The row itself is also clickable (toggles the checkbox), with the same pointer
       cursor - nothing distinguished "click this exact 20px icon to preview" from "click
       anywhere else in the row to select it". A button-like chip (background + border)
       reads as its own control at a glance, the way an attachment icon in a table row
       usually does, instead of relying on a tooltip the user has to stop and read first. */
    .bilags-type-icon {
        padding: 3px;
        border-radius: 6px;
        background: #eef2f8;
        box-shadow: inset 0 0 0 1px #d3dbe6;
        transition: background-color 0.12s ease, box-shadow 0.12s ease;
    }
    .bilags-type-icon:hover, .bilags-type-icon:focus-visible {
        background: #dde8f7;
        box-shadow: inset 0 0 0 1px #aec2de;
        outline: none;
    }
    #popup-results .popup-table th, #popup-results .popup-table td {
        padding: 4px 12px;
        line-height: 1.3;
    }
    #popup-results .popup-table td {
        white-space: nowrap;
    }
    #popup-results .popup-table td.bilags-description {
        white-space: normal;
    }
    /* Headers like "Voucher no." / "Contra account" were wrapping to 2 lines at the
       widths below, doubling every row's height across 13 rows - keep them on one
       line and let the table's auto layout widen the column instead. */
    #popup-results .popup-table th {
        white-space: nowrap;
    }
    .popup-checkmark th{
        width: 40px;
    }
    #popup-select-all-checkbox {
        cursor: pointer;
    }
    .bilags-type th{
        width: 60px;
    }
    .bilags-date th{
        width: 90px;
    }
    .bilags-bilag th{
        width: 80px;
    }
    .bilags-description th{
        width: 200px;
    }
    .bilags-amount th{
        width: 110px;
    }
    .bilags-konto th, .bilags-modkonto th{
        width: 100px;
    }
    .bilags-valuta th{
        width: 70px;
    }
    .bilags-match th{
        width: 130px;
    }
</style>

<script>
	// True while the pinned preview window is open - set by bmOpenPinnedPreview(), cleared
	// by bmClosePinnedPreview(). Used by the Esc handler and the background-dimmer click.
	var bmPinnedOpen = false;

    <?php

    if (file_exists('../owncloud')) $docFolder = '../owncloud';
    elseif (file_exists('../bilag')) $docFolder = '../bilag';
    elseif (file_exists('../documents')) $docFolder = '../documents';
    else $docFolder = '../bilag'; // Default fallback

    $puljeFolder = "$docFolder/$db/pulje/";
    ?>

	// Pinned document preview labels (kravsspecifikation popup fast.pdf) - declared once
	// here rather than inline in each template string below.
	var BM_PIN_LOADING = <?= json_encode($bm_pin_loading) ?>;
	var BM_PIN_LOAD_ERROR = <?= json_encode($bm_pin_load_error) ?>;
	var BM_PIN_UNSUPPORTED = <?= json_encode($bm_pin_unsupported) ?>;
	var BM_PIN_RETRY = <?= json_encode($bm_pin_retry) ?>;
	var BM_PIN_DOWNLOAD = <?= json_encode($bm_pin_download) ?>;
	var BM_PIN_OPEN_TAB = <?= json_encode($bm_pin_open_tab) ?>;
	var BM_PIN_CLOSE = <?= json_encode($bm_pin_close) ?>;
	var BM_PIN_ZOOM_OUT = <?= json_encode($bm_pin_zoom_out) ?>;
	var BM_PIN_ZOOM_IN = <?= json_encode($bm_pin_zoom_in) ?>;
	var BM_PIN_FIT_WIDTH = <?= json_encode($bm_pin_fit_width) ?>;
	var BM_COL_BILAG = <?= json_encode($bm_col_bilag) ?>;

	// ==================== Pinned document preview ====================
	// kravsspecifikation popup fast.pdf. Lives outside #popup-container (created once,
	// appended directly to <body>) so PopupManager's popup() re-renders (e.g. a settings
	// change re-fetch) never touch or destroy it while it's open.
	var BM_PINNED_POS_KEY = 'bilagsmatchPinnedPos';
	var bmPinnedTriggerEl = null;
	var bmPinnedWired = false;
	var bmPinnedCurrentFilename = null;

	function bmPinnedEl(id) {
		return document.getElementById(id);
	}

	function bmLoadPinnedRect() {
		try {
			var raw = sessionStorage.getItem(BM_PINNED_POS_KEY);
			return raw ? JSON.parse(raw) : null;
		} catch (e) {
			return null;
		}
	}

	function bmSavePinnedRect(rect) {
		try { sessionStorage.setItem(BM_PINNED_POS_KEY, JSON.stringify(rect)); } catch (e) { /* private browsing etc. - just won't persist */ }
	}

	// Default: centered in the viewport, shrunk to fit on small screens. Simpler and more
	// predictable than the earlier "beside the dialog" placement, which some users read as
	// the window floating loosely over the table rather than a deliberate, focused overlay.
	function bmDefaultPinnedRect() {
		var margin = 16, width = 460, height = 620;
		width = Math.min(width, window.innerWidth - margin * 2);
		height = Math.min(height, window.innerHeight - margin * 2);
		var left = Math.max(margin, (window.innerWidth - width) / 2);
		var top = Math.max(margin, (window.innerHeight - height) / 2);
		return { left: left, top: top, width: width, height: height };
	}

	function bmApplyPinnedRect(rect) {
		var win = bmPinnedEl('bmPinnedPreview');
		if (!win) return;
		win.style.left = rect.left + 'px';
		win.style.top = rect.top + 'px';
		win.style.width = rect.width + 'px';
		win.style.height = rect.height + 'px';
	}

	function bmCurrentPinnedRect() {
		var rect = bmPinnedEl('bmPinnedPreview').getBoundingClientRect();
		return { left: rect.left, top: rect.top, width: rect.width, height: rect.height };
	}

	// Drag (title bar) + resize (corner handle), wired once - the window itself is a single
	// persistent DOM node, never recreated (FR-5: at most one pinned preview at a time).
	function bmWirePinnedWindow() {
		if (bmPinnedWired) return;
		bmPinnedWired = true;
		var win = bmPinnedEl('bmPinnedPreview');
		var titlebar = bmPinnedEl('bmPinnedTitlebar');
		var handle = bmPinnedEl('bmPinnedResizeHandle');
		var dragging = null;
		var resizing = null;

		titlebar.addEventListener('mousedown', function (e) {
			if (e.target.closest('.bm-pinned-toolbar')) return;
			var rect = bmCurrentPinnedRect();
			dragging = { startX: e.clientX, startY: e.clientY, startLeft: rect.left, startTop: rect.top };
			e.preventDefault();
		});

		handle.addEventListener('mousedown', function (e) {
			var rect = bmCurrentPinnedRect();
			resizing = { startX: e.clientX, startY: e.clientY, startWidth: rect.width, startHeight: rect.height };
			e.preventDefault();
			e.stopPropagation();
		});

		document.addEventListener('mousemove', function (e) {
			if (dragging) {
				var rect = bmCurrentPinnedRect();
				var left = Math.max(4, Math.min(dragging.startLeft + (e.clientX - dragging.startX), window.innerWidth - rect.width - 4));
				var top = Math.max(4, Math.min(dragging.startTop + (e.clientY - dragging.startY), window.innerHeight - rect.height - 4));
				win.style.left = left + 'px';
				win.style.top = top + 'px';
			}
			if (resizing) {
				var curRect = bmCurrentPinnedRect();
				var width = Math.max(300, Math.min(resizing.startWidth + (e.clientX - resizing.startX), window.innerWidth - curRect.left - 4));
				var height = Math.max(260, Math.min(resizing.startHeight + (e.clientY - resizing.startY), window.innerHeight - curRect.top - 4));
				win.style.width = width + 'px';
				win.style.height = height + 'px';
			}
		});

		document.addEventListener('mouseup', function () {
			if (dragging || resizing) bmSavePinnedRect(bmCurrentPinnedRect());
			dragging = null;
			resizing = null;
		});
	}

	// Images have no native zoom UI, unlike the <embed>'d PDF viewer below, so they get
	// their own minimal zoom controls (CSS width, scaled off whatever's currently
	// rendered - no separate zoom-level state to track).
	function bmPinnedZoomImage(factor) {
		var img = bmPinnedEl('bmPinnedImage');
		if (!img) return;
		var currentWidth = img.getBoundingClientRect().width;
		img.style.width = Math.max(60, Math.min(currentWidth * factor, 4000)) + 'px';
		img.style.height = 'auto';
	}

	function bmPinnedFitWidth() {
		var img = bmPinnedEl('bmPinnedImage');
		if (!img) return;
		img.style.width = '100%';
		img.style.height = 'auto';
	}

	// mode 'image' shows the zoom/fit controls; anything else hides them - a PDF's own
	// native viewer (see bmRenderPinnedContent) already has zoom/page controls of its own,
	// per the plan's "reuse the browser's native PDF viewer, don't build one" decision
	// (NFR-3 rules out adding a new component library like pdf.js).
	function bmSetPinnedToolbarMode(mode) {
		var zoomControls = bmPinnedEl('bmPinnedZoomControls');
		if (zoomControls) zoomControls.style.display = (mode === 'image') ? 'inline-flex' : 'none';
	}

	// Small inline icons (no icon-library dependency, per NFR-3) for the three "nothing to
	// show yet" states. Loading gets a spinner instead of static text - a flat gray canvas
	// with just a word on it read as broken, not busy.
	var BM_PIN_ICON_ERROR = '<svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="#c0392b" stroke-width="1.6" stroke-linecap="round"><circle cx="12" cy="12" r="9.2"/><line x1="12" y1="7.2" x2="12" y2="13.2"/><circle cx="12" cy="16.4" r="0.6" fill="#c0392b" stroke="none"/></svg>';
	var BM_PIN_ICON_UNSUPPORTED = '<svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="#8a94a6" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3.5h7l3.5 3.5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1z"/><path d="M14 3.5v3.5a1 1 0 0 0 1 1h3.5"/></svg>';

	// Renders a centered message card (icon + text + optional action) in the body, and
	// switches the body to a light background - the dark canvas below is meant to frame an
	// actual document, not sit behind an error message.
	function bmRenderPinnedStatus(kind, message, actionHtml) {
		var body = bmPinnedEl('bmPinnedBody');
		if (!body) return;
		body.classList.add('bm-pinned-body-status');
		var icon = kind === 'loading' ? '<div class="bm-pinned-spinner"></div>'
			: kind === 'error' ? BM_PIN_ICON_ERROR
			: BM_PIN_ICON_UNSUPPORTED;
		body.innerHTML = '<div class="bm-pinned-status">' + icon
			+ '<div class="bm-pinned-status-message">' + message + '</div>'
			+ (actionHtml || '') + '</div>';
	}

	function bmRenderPinnedContent(filename) {
		var body = bmPinnedEl('bmPinnedBody');
		if (!body) return;
		bmSetPinnedToolbarMode('none');
		bmRenderPinnedStatus('loading', BM_PIN_LOADING);
		if (!filename) {
			bmRenderPinnedStatus('error', BM_PIN_LOAD_ERROR);
			return;
		}
		var filepath = "<?php echo $puljeFolder; ?>" + filename;
		var ext = filepath.split('.').pop().toLowerCase();

		// Probe existence first - a missing pool file would otherwise render the server's
		// raw 404 HTML page inside the embed/img instead of a clean message (FR-22).
		fetch(filepath, { method: 'HEAD' }).then(function (res) {
			if (bmPinnedCurrentFilename !== filename) return; // superseded by another row's click meanwhile
			if (!res.ok) throw new Error('not ok');
			body.classList.remove('bm-pinned-body-status');
			if (ext === 'pdf') {
				// #view=FitH is the standard PDF "open parameters" fragment for fit-to-
				// width (FR-13) - the browser's own PDF viewer (not a new component
				// library) renders scroll/zoom/page-nav/rotate itself (FR-12, FR-14).
				body.innerHTML = '<embed src="' + filepath + '#view=FitH" type="application/pdf" class="bm-pinned-embed">';
			} else if (['jpg', 'jpeg', 'png', 'gif'].indexOf(ext) !== -1) {
				bmSetPinnedToolbarMode('image');
				body.innerHTML = '<img id="bmPinnedImage" class="bm-pinned-image" src="' + filepath + '">';
			} else {
				bmRenderPinnedStatus('unsupported', BM_PIN_UNSUPPORTED,
					'<a href="' + filepath + '" download class="saldi-button popup-btn-secondary">' + BM_PIN_DOWNLOAD + '</a>');
			}
		}).catch(function () {
			if (bmPinnedCurrentFilename !== filename) return;
			bmRenderPinnedStatus('error', BM_PIN_LOAD_ERROR,
				'<button type="button" id="bmPinnedRetry" class="saldi-button popup-btn-secondary">' + BM_PIN_RETRY + '</button>');
			var retryBtn = bmPinnedEl('bmPinnedRetry');
			if (retryBtn) retryBtn.addEventListener('click', function () { bmRenderPinnedContent(filename); });
		});
	}

	// FR-1/FR-5/FR-6: opens (or replaces the content of) the single pinned preview window.
	function bmOpenPinnedPreview(filename, bilag, triggerEl) {
		if (!filename) return; // FR-23: no attachment, nothing to open
		bmPinnedOpen = true;
		bmPinnedTriggerEl = triggerEl || null;
		bmPinnedCurrentFilename = filename;

		var win = bmPinnedEl('bmPinnedPreview');
		var filepath = "<?php echo $puljeFolder; ?>" + filename;
		bmPinnedEl('bmPinnedTitle').textContent = filename + (bilag ? ' · ' + BM_COL_BILAG + ' ' + bilag : '');
		// FR-15: consistent open-in-tab/download affordances regardless of file type,
		// rather than relying on the PDF viewer's own (image types have none at all).
		bmPinnedEl('bmPinnedOpenTab').href = filepath;
		bmPinnedEl('bmPinnedDownload').href = filepath;
		win.style.display = 'flex';
		bmWirePinnedWindow();
		bmApplyPinnedRect(bmLoadPinnedRect() || bmDefaultPinnedRect());
		bmRenderPinnedContent(filename);

		// NFR-4: focus moves into the window on open (its close button - a sensible,
		// always-present default landing spot).
		var closeBtn = bmPinnedEl('bmPinnedClose');
		if (closeBtn) closeBtn.focus();
	}

	// FR-3/FR-4: close button and Esc (see keydown listener below) both call this. Returns
	// the dialog exactly as it was - nothing here touches #popup-results, so scroll
	// position and row selection are untouched by construction (FR-4, FR-18).
	function bmClosePinnedPreview() {
		var win = bmPinnedEl('bmPinnedPreview');
		if (win) win.style.display = 'none';
		bmPinnedOpen = false;
		bmPinnedCurrentFilename = null;
		// NFR-4: focus returns to the attachment icon that opened it.
		if (bmPinnedTriggerEl && document.contains(bmPinnedTriggerEl)) bmPinnedTriggerEl.focus();
		bmPinnedTriggerEl = null;
	}

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && bmPinnedOpen) bmClosePinnedPreview();
	});
</script>

<style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 8px; } /* #20260513 */
    .header { background: <?= $buttonColor ?>; color: <?= $buttonTxtColor ?>; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
    .header h2 { margin: 0; }
    .doc-list { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .doc-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid #eee; position: relative; cursor: pointer; }
    .doc-item:hover { background: #f0f7ff; }
    .doc-info { flex: 1; }
    .doc-name { font-weight: bold; color: #333; }
    .doc-meta { font-size: 12px; color: #666; margin-top: 4px; }
    .link-btn { background: <?= $buttonColor ?>; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 13px; flex-shrink: 0; }
    .link-btn:hover { opacity: 0.9; }
    .empty-msg { padding: 40px; text-align: center; color: #666; }
    .search-box { margin-bottom: 15px; }
    .search-box input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; }

    /* Pinned document preview (kravsspecifikation popup fast.pdf) - the only attachment
       preview now (the old hover preview was retired); z-index above both the Bilagsmatch
       dialog (20) and its dimmer (10), and pointer-events is explicit rather than assumed,
       so nothing behind it can ever receive a click or hover meant for this window. */
    .bm-pinned-preview {
        position: fixed;
        display: flex;
        flex-direction: column;
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 12px 40px rgba(0,0,0,0.3);
        z-index: 1001;
        overflow: hidden;
        font-family: Arial, sans-serif;
        pointer-events: auto;
    }
    .bm-pinned-titlebar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 8px 10px;
        background: <?= $buttonColor ?>;
        color: #ffffff;
        cursor: move;
        flex: 0 0 auto;
    }
    .bm-pinned-title {
        font-size: 13px;
        font-weight: bold;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .bm-pinned-toolbar {
        display: flex;
        align-items: center;
        gap: 4px;
        flex: 0 0 auto;
    }
    .bm-pinned-toolbar button, .bm-pinned-toolbar a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        border: none;
        border-radius: 4px;
        background: rgba(255,255,255,0.15);
        color: #ffffff;
        cursor: pointer;
        text-decoration: none;
        font-size: 15px;
        line-height: 1;
    }
    .bm-pinned-toolbar button:hover, .bm-pinned-toolbar a:hover {
        background: rgba(255,255,255,0.3);
    }
    .bm-pinned-zoom-controls {
        display: inline-flex;
        gap: 4px;
    }
    .bm-pinned-body {
        flex: 1 1 auto;
        overflow: auto;
        background: #525659;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        min-height: 0;
    }
    /* The dark canvas above frames an actual document; while there's nothing to frame yet
       (loading/error/unsupported), a flat dark panel with just a line of white text read as
       a broken/blank screen rather than a deliberate state, so this switches to a light
       card-style message instead. */
    .bm-pinned-body.bm-pinned-body-status {
        background: #eef0f4;
        align-items: center;
    }
    .bm-pinned-embed {
        width: 100%;
        height: 100%;
    }
    .bm-pinned-image {
        width: 100%;
        height: auto;
        display: block;
        /* .bm-pinned-body is a flex container - without this, flex-shrink's default of 1
           silently shrinks any zoomed-in width back down to fit the container, so zooming
           in appeared to do nothing while zooming out (never exceeding the container)
           looked fine. */
        flex-shrink: 0;
    }
    .bm-pinned-status {
        margin: auto;
        padding: 28px 32px;
        text-align: center;
        color: #4a5568;
        font-size: 14px;
        max-width: 300px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 14px;
    }
    .bm-pinned-status-message {
        line-height: 1.5;
    }
    .bm-pinned-spinner {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: 3px solid #d7dae1;
        border-top-color: <?= $buttonColor ?>;
        animation: bm-pinned-spin 0.8s linear infinite;
    }
    @keyframes bm-pinned-spin {
        to { transform: rotate(360deg); }
    }
    .bm-pinned-resize-handle {
        position: absolute;
        right: 0;
        bottom: 0;
        width: 16px;
        height: 16px;
        cursor: nwse-resize;
        background: linear-gradient(135deg, transparent 50%, #999 50%);
    }
</style>

<!-- Pinned document preview (kravsspecifikation popup fast.pdf) - created once, hidden by
     default, shown/positioned/filled in by bmOpenPinnedPreview(). Lives outside
     #popup-container so PopupManager re-renders never touch it. -->
<div id='bmPinnedPreview' class='bm-pinned-preview' style='display:none;' role='dialog' aria-label='<?= $bm_pin_open_tooltip ?>'>
    <div id='bmPinnedTitlebar' class='bm-pinned-titlebar'>
        <span id='bmPinnedTitle' class='bm-pinned-title'></span>
        <div class='bm-pinned-toolbar'>
            <span id='bmPinnedZoomControls' class='bm-pinned-zoom-controls' style='display:none;'>
                <button type='button' onclick='bmPinnedZoomImage(0.8)' title='<?= $bm_pin_zoom_out ?>'>&minus;</button>
                <button type='button' onclick='bmPinnedZoomImage(1.25)' title='<?= $bm_pin_zoom_in ?>'>+</button>
                <button type='button' onclick='bmPinnedFitWidth()' title='<?= $bm_pin_fit_width ?>'>&#10021;</button>
            </span>
            <a id='bmPinnedOpenTab' href='#' target='_blank' rel='noopener' title='<?= $bm_pin_open_tab ?>'>&#8599;</a>
            <a id='bmPinnedDownload' href='#' download title='<?= $bm_pin_download ?>'>&#11015;</a>
            <button type='button' id='bmPinnedClose' onclick='bmClosePinnedPreview()' title='<?= $bm_pin_close ?>' aria-label='<?= $bm_pin_close ?>'>&times;</button>
        </div>
    </div>
    <div id='bmPinnedBody' class='bm-pinned-body'></div>
    <div id='bmPinnedResizeHandle' class='bm-pinned-resize-handle'></div>
</div>
