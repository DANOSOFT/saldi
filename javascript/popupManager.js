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

// 20260507 NTR - A generalised popup manager that can be reused in the future for other popup functions.
// 20260702 NTR - Added onClose event to allow for cleanup when the popup is closed.
//              - Moved the confirmed exit button to the footer of the popup instead of header.
// 20260720 Sawaneh Added optional isRowChecked predicate so callers can control
//                each row's initial checkbox state instead of always checking all.
// 20260721 CL/SZ - Added an options object (closeLabel, preSelectFn, summaryFn, noResultsHtml)
//                   for the Bilagsmatch redesign; footer summary now recomputes live as rows
//                   are toggled instead of showing a static count. Removed a leftover debug
//                   console.log from the constructor.
// 20260721 CL/SZ - Second pass to actually match the Bilagsmatch mockup, not just its column
//                   set: the header close button is now an icon-only "x" (was rendering the
//                   closeLabel text, e.g. "Annullér", in the header - the mockup only has an
//                   "x" there), the summary line moved from the footer to directly under the
//                   header (mockup shows it above the table, not in the footer), and the
//                   footer now renders BOTH a secondary cancel button (closeLabel) and the
//                   primary exit button side by side (was exit-only, with cancel stranded in
//                   the header) - both cancel affordances close without calling exitCall.
// 20260811 CL/SZ - Added backward-compatible options for the Bilagsmatch settings
//                   panel/Match-badge rework (see invoicematch_settings.md): summaryExtraHtml
//                   (inline control next to the summary text, e.g. a settings toggle button)
//                   and toolbarHtml (a block rendered below the summary bar, e.g. the
//                   settings panel itself) both default to () => '' so existing popups
//                   render nothing new; hideCheckboxColumn visually hides the leftmost
//                   checkbox column via display:none while keeping the input in the DOM for
//                   exitCall/summary logic (default false); disableExitWhenEmpty greys out
//                   the primary exit button while zero rows are checked (default false); and
//                   a "row-selected" class is now kept in sync on each <tr> as its checkbox
//                   toggles, so callers can style other cells (e.g. a badge) to reflect
//                   selection without their own change listener; and footerTextFn adds a
//                   live-updating text span in the footer, next to Cancel/exit.
// 20260811 CL/SZ - Fixed onResult being skipped whenever a render had zero results: once
//                   Bilagsmatch could re-render itself (a settings change narrowing results
//                   to 0), that dropped ALL of onResult's wiring - not just row-hover, but
//                   its settings-panel toggle/chip/checkbox listeners too - leaving the
//                   panel dead until the popup was closed and reopened. Now runs always;
//                   the one caller's handler already no-ops safely over an empty row set.
// 20260811 CL/SZ - Added confirmExit option: called with the selected rows right before
//                   exitCall, returning false aborts without exiting (default () => true,
//                   unconditional as before). Lets a caller add a "some of these aren't
//                   certain, continue anyway?" check without PopupManager itself knowing
//                   anything about what "certain" means for that caller's data.
// 20260813 CL/SZ - CodeRabbit review fixes: escapeHtml() used a textContent/innerHTML
//                   round-trip that escapes &/</> but leaves ' and " untouched - exactly
//                   the characters that break out of a quoted attribute, which is the
//                   main reason to call this helper. Rewrote it as explicit entity
//                   replacement covering both quote types. Also moved the initial
//                   updateSummary() call out from inside the selectAllHeader-only
//                   if-block so it always runs: a caller supplying isRowChecked can render
//                   a different checked state than preSelectFn, and without this the
//                   initial summary/footer text/disableExitWhenEmpty state only got
//                   corrected for callers that also set selectAllHeader.

/**
 * Describes a single column in a PopupManager table.
 * @param {string} display - Header label shown in the table.
 * @param {string|function} selector - Either a key to look up on each result row, or a
 *   function(row) that returns the cell's HTML content.
 * @param {string} [headerAtt=''] - Extra HTML attributes on the <th> element, e.g. "style='width:80px;'".
 * @param {string} [columnAtt=''] - Extra HTML attributes on each <td> element.
 * @param {string|null} [key=null] - Key used when building the result object passed to exitCall.
 *   Defaults to the first word of display.
 */
class ColumnInfo {
    constructor(display, selector, headerAtt = '', columnAtt = '', key = null) {
        this.display = display;
        this.selector = selector;
        this.headerAtt = headerAtt ?? '';
        this.columnAtt = columnAtt ?? '';
        this.key = key ?? display.split(' ')[0];
    }
    static fromPositionalArray(arr) {
        if(arr != null && typeof arr[Symbol.iterator] !== 'function') {
            console.log('Error: object must be iteratable, something went wrong when it was initiated. Object: ' + JSON.stringify(arr));
            return null;
        }
        return arr.map((a) => new ColumnInfo(...a));
    }
}

/**
 * Renders a modal popup table of results and lets the user select rows.
 * Clicking the exit button collects all checked rows as plain objects and
 * passes them to exitCall, then closes the popup. Clicking Close discards
 * the selection and closes without calling exitCall.
 *
 * @param {ColumnInfo[]} columns - Column definitions for the result table.
 * @param {Object|null} popupStyle - CSS-in-JS overrides for the popup container.
 *   Merged on top of the defaults (positioned, fixed size, drop shadow).
 * @param {function(Object[]): void} exitCall - Called with the array of selected
 *   row objects when the exit button is clicked. Each object is keyed by
 *   ColumnInfo.key.
 * @param {string} exitName - Label shown on the primary exit/confirm button (footer).
 * @param {Object|null} [background_dimmer_style] - CSS-in-JS overrides for the
 *   semi-transparent background overlay.
 * @param {Object} [options] - Optional behavior overrides (all backward-compatible):
 *   - closeLabel {string}: accessible label/tooltip on the header "x" button, and the
 *     visible label on the footer's secondary cancel button (default "Close"). Both
 *     close without calling exitCall.
 *   - preSelectFn {function(Object): boolean}: given a result row, returns whether its
 *     checkbox should start checked (default: always checked, the original behavior).
 *   - summaryFn {function(number selectedCount, number totalCount): string}: text shown
 *     in a bar directly under the header, recomputed live whenever a row is toggled
 *     (default: the original static "Viser {total} resultater").
 *   - noResultsHtml {string}: markup shown instead of the table when there are no results.
 *   - checkboxHeaderLabel {string}: header text above the selection checkbox column
 *     (default "Add.", the original hardcoded text).
 *   - summaryExtraHtml {function(): string}: re-evaluated on every popup() call and
 *     inserted inline next to the summary text inside #popup-summary-bar (default: () => '',
 *     renders nothing). Use this for a control that belongs on the summary row itself
 *     (e.g. a settings toggle button).
 *   - toolbarHtml {function(): string}: re-evaluated on every popup() call and inserted
 *     directly after the summary bar (default: () => '', renders nothing). Use this for
 *     caller-specific controls (e.g. a settings panel) that need to reflect state that can
 *     change between renders (like a re-fetch after a setting changes).
 *   - hideCheckboxColumn {boolean}: visually hides the leftmost selection checkbox column
 *     (default false, column stays visible as today). The checkbox input itself still
 *     renders (hidden) since exitCall/summary/row-selection logic all read it - use this
 *     when the caller renders its own selection affordance elsewhere in the row instead.
 *   - disableExitWhenEmpty {boolean}: greys out the primary exit/confirm button while zero
 *     rows are checked, re-evaluated live on every toggle (default false, unchanged
 *     behavior - the button stays enabled regardless of selection count).
 *   - footerTextFn {function(number selectedCount, number totalCount): string}: text shown
 *     in the footer, to the left of the cancel/exit buttons, recomputed live like summaryFn
 *     (default: () => '', renders nothing - the footer looks exactly as it does today).
 *   - selectAllHeader {boolean}: renders an actual checkbox in the header checkbox column
 *     (replacing checkboxHeaderLabel's plain text) that checks/unchecks every row at once,
 *     and stays in sync (checked/indeterminate/unchecked) as rows are toggled individually
 *     (default false, checkboxHeaderLabel's text renders as before).
 *   - confirmExit {function(Object[]): boolean}: called with the same selected-row array
 *     exitCall is about to receive, right before it's called. Returning false aborts -
 *     neither exitCall nor closeDropdown runs, so the popup stays open exactly as it was
 *     (default: () => true, exits unconditionally as today). Use this for a "some of these
 *     aren't certain matches, continue anyway?" confirmation rather than blocking selection
 *     itself.
 */
class PopupManager {
    popupContainer = null;
    background_dimmer = null;
    style = null;

    onNoResult = [];
    onResult = [];
    onClose = [];

    /**
     * Optional predicate deciding each row's initial checkbox state.
     * @type {?function(Object): boolean} - function(row) returning whether the
     *   row starts checked. When null, every row starts checked.
     */
    isRowChecked = null;

    constructor(columns, popupStyle = null, exitCall, exitName, background_dimmer_style = null, options = {}){
        this.columns = columns;
        this.columnKeys = columns.map(col => col.key);
        this.popupStyle = Object.assign({
            position: 'absolute',
            top: '50px',
            left: 'calc(50vw - 25%)',
            width: 'auto',
            maxHeight: 'calc(100vh - 100px)',
            background: '#eeeef0',
            borderTop: '2px solid #aaa',
            borderLeft: '2px solid #aaa',
            borderBottom: '2px solid #333',
            borderRight: '2px solid #333',
            borderRadius: '4px',
            boxShadow: '0 4px 20px #000',
            zIndex: '20',
            padding: '5px',
            overflow: 'auto',
        }, popupStyle);

        this.background_dimmer_style = Object.assign({
            position: 'absolute',
            width: '100vw',
            height: '100vh',
            top: '0',
            left: '0',
            opacity: '0.5',
            background: 'DarkGrey',
            zIndex: '10',
        }, background_dimmer_style);
        this.exitCall = exitCall;
        this.exitName = exitName;

        this.closeLabel = options.closeLabel ?? 'Close';
        this.preSelectFn = options.preSelectFn ?? (() => true);
        this.summaryFn = options.summaryFn ?? ((selectedCount, totalCount) => `Viser ${totalCount} resultater`);
        this.noResultsHtml = options.noResultsHtml ?? '<div class="popup-no-results">Ingen resultater fundet</div>';
        this.checkboxHeaderLabel = options.checkboxHeaderLabel ?? 'Add.';
        this.summaryExtraHtml = options.summaryExtraHtml ?? (() => '');
        this.toolbarHtml = options.toolbarHtml ?? (() => '');
        this.hideCheckboxColumn = options.hideCheckboxColumn ?? false;
        this.disableExitWhenEmpty = options.disableExitWhenEmpty ?? false;
        this.footerTextFn = options.footerTextFn ?? (() => '');
        this.selectAllHeader = options.selectAllHeader ?? false;
        this.confirmExit = options.confirmExit ?? (() => true);
    }

    convert_js_to_css(jsObject){
        return Object.entries(jsObject)
                .map(([k, v]) => `${k.replace(/([A-Z])/g, '-$1').toLowerCase()}: ${v}`) // Convert from javascript to css language
                .join(';\n\t');
    }
    
    /** @returns {HTMLElement} The popup container div, creating it on first call. */
    getPopupContainer() {
        if (!Boolean(this.popupContainer)) {
            this.popupContainer = document.createElement('div');
            this.popupContainer.id = 'popup-container';
            document.body.appendChild(this.popupContainer);
            this.background_dimmer = document.createElement('div');
            this.background_dimmer.id = 'background-dimmer';
            document.body.appendChild(this.background_dimmer);
            this.style = document.createElement('style');
            this.style.innerHTML = `
                #background-dimmer {\n${this.convert_js_to_css(this.background_dimmer_style)}\n}
                #popup-container {\n${this.convert_js_to_css(this.popupStyle)}\n}
                `;
            document.body.appendChild(this.style);
        }
        return this.popupContainer;
    }

    /**
     * Opens the popup and renders results as a selectable table.
     * @param {Object[]} results - Array of data rows to display.
     * @param {string} title - Heading shown at the top of the popup.
     */
    popup(results, title) {

        const totalCount = results ? results.length : 0;
        const initialSelected = results ? results.filter(item => this.preSelectFn(item)).length : 0;

        let html = `
            <div id="popup-header">
                <span id="popup-header-title">${title}</span>
                <button type="button" id="popup-close-btn" class="popup-close-x" title="${this.closeLabel}" aria-label="${this.closeLabel}">&times;</button>
            </div>
            <div id="popup-summary-bar">
                <span id="popup-summary-text">${this.summaryFn(initialSelected, totalCount)}</span>
                ${this.summaryExtraHtml()}
            </div>
            ${this.toolbarHtml()}
            <div id="popup-results">
        `;

        if (!results || results.length === 0) {
            if (this.onNoResult.some(fn => fn() === true)) return;
            html += this.noResultsHtml;
        } else {
            html += '<table class="popup-table"><thead><tr>';

            const checkboxColStyle = this.hideCheckboxColumn ? "style='display:none;'" : '';
            const headerCheckboxContent = this.selectAllHeader
                ? `<input type='checkbox' id='popup-select-all-checkbox'/>`
                : this.checkboxHeaderLabel;
            html += `<th class="popup-checkmark" ${checkboxColStyle}>${headerCheckboxContent}</th>`;
            this.columns.forEach(
                column => {
                    html += `<th ${column.headerAtt}>${column.display}</th>\n`;
                }
            );

            html += '</tr></thead><tbody>';

            results.forEach(item => {
                const checked = (typeof this.isRowChecked === 'function') ? this.isRowChecked(item) : (typeof this.preSelectFn === 'function')  ? this.preSelectFn(item) : true;
                html += `<tr class="autocomplete-item${checked ? ' row-selected' : ''}">\n`;

                html += `<td ${checkboxColStyle}><input class='active-checkbox' type='checkbox' ${checked ? 'checked' : ''}/></td>\n`;
                this.columns.forEach(
                    column => {
                        // A function selector returns markup the caller built on purpose
                        // (icons, badges, links) and is trusted as-is - same as before. A
                        // plain string selector is a raw data lookup (item[key]) with no
                        // caller code in between to escape it, so it goes through
                        // escapeHtml() here instead: without this, a pool file/journal
                        // line field containing HTML (subject, description, account -
                        // all can carry AI-extraction or upload-derived text) rendered
                        // straight into the DOM and executed on render, no click needed.
                        html += (typeof column.selector == "function")
                            ? `<td ${column.columnAtt}>${column.selector(item) ?? ''}</td>\n`
                            : `<td ${column.columnAtt}>${PopupManager.escapeHtml(item[column.selector])}</td>\n`;
                    }
                );

                html += '</tr>\n';
            });
            html += '</tbody></table>';
        }

        html += `
            </div> <!-- popup-results -->
            <div class="popup-footer">
                <span id="popup-footer-text">${this.footerTextFn(initialSelected, totalCount)}</span>
                <button type="button" id="popup-cancel-btn" class="saldi-button popup-btn-secondary">${this.closeLabel}</button>
                <button type="button" id="popup-exit-call-btn" class="saldi-button popup-btn-primary" ${(this.disableExitWhenEmpty && initialSelected === 0) ? 'disabled' : ''}>${this.exitName}</button>
            </div>
        `;

        const popupMenuCon = this.getPopupContainer();
        popupMenuCon.innerHTML = html;

        // Recomputes the summary bar from the checkboxes actually checked right now -
        // called after every toggle so it always reflects the live selection.
        const allBoxes = () => Array.from(popupMenuCon.querySelectorAll('#popup-results tbody input.active-checkbox'));
        const selectAllBox = popupMenuCon.querySelector('#popup-select-all-checkbox');
        const updateSummary = () => {
            const boxes = allBoxes();
            const selected = boxes.filter(b => b.checked).length;
            const info = popupMenuCon.querySelector('#popup-summary-text');
            if (info) info.textContent = this.summaryFn(selected, totalCount);
            const footerText = popupMenuCon.querySelector('#popup-footer-text');
            if (footerText) footerText.textContent = this.footerTextFn(selected, totalCount);
            if (this.disableExitWhenEmpty) {
                const exitBtn = popupMenuCon.querySelector('#popup-exit-call-btn');
                if (exitBtn) exitBtn.disabled = (selected === 0);
            }
            if (selectAllBox) {
                selectAllBox.checked = boxes.length > 0 && selected === boxes.length;
                selectAllBox.indeterminate = selected > 0 && selected < boxes.length;
            }
        };

        if (selectAllBox) {
            selectAllBox.addEventListener('change', function () {
                allBoxes().forEach(box => {
                    box.checked = selectAllBox.checked;
                    const row = box.closest('tr');
                    if (row) row.classList.toggle('row-selected', box.checked);
                });
                updateSummary();
            });
        }
        // Recomputes summary/footer text and the exit button's disabled state from the
        // checkboxes as actually rendered, rather than trusting initialSelected (computed
        // above from preSelectFn) - a caller supplying isRowChecked can render a different
        // checked state than preSelectFn would, which previously only got corrected when
        // selectAllHeader was also enabled (this call lived inside that if-block).
        updateSummary();

        // Event listeners for results
        popupMenuCon.querySelectorAll('.autocomplete-item').forEach(item => {
            item.addEventListener('mousedown', function (e) {
                const box = item.querySelector('.active-checkbox');
                e.preventDefault();
                e.stopPropagation();
                if (box && e.target != box) {
                    box.checked = !box.checked;
                    item.classList.toggle('row-selected', box.checked);
                    updateSummary();
                }
            });
            const box = item.querySelector('.active-checkbox');
            if (box) box.addEventListener('change', function () {
                item.classList.toggle('row-selected', box.checked);
                updateSummary();
            });
        });

        // finish button
        popupMenuCon.querySelector('#popup-exit-call-btn').addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const resultArr = Array.from(this.popupContainer.querySelectorAll("#popup-results tbody tr"))
                .filter(row => {
                    const checkbox = row.querySelector('input.active-checkbox');
                    return checkbox && checkbox.checked;
                })
                .map(row => {
                    const tds = row.querySelectorAll("td");
                    return Object.fromEntries(this.columnKeys.map((key, i) => {
                        const cell = tds[i + 1];
                        if (!cell) return [key, ''];
                        // Function-selector cells hold caller-built markup callers may
                        // depend on (e.g. Bilagsmatch's confirmExit reads a CSS class name
                        // out of the Match badge's HTML) - innerHTML, unchanged. Plain
                        // string-selector cells are now escapeHtml()'d text (see the render
                        // loop above), so innerHTML would hand back HTML entities instead of
                        // the real value (an attach call keyed on an entity-encoded filename
                        // would never find the actual file) - textContent decodes correctly.
                        const isMarkup = typeof this.columns[i].selector === 'function';
                        return [key, (isMarkup ? cell.innerHTML : cell.textContent) ?? ''];
                    }));
                });
            if (!this.confirmExit(resultArr)) return;
            this.exitCall(resultArr);
            this.closeDropdown();
        }.bind(this));

        // Close button (header "x") and footer cancel button both discard without
        // calling exitCall.
        const closeWithoutSaving = function (e) {
            e.preventDefault();
            e.stopPropagation();
            this.closeDropdown();
        }.bind(this);
        popupMenuCon.querySelector('#popup-close-btn').addEventListener('click', closeWithoutSaving);
        popupMenuCon.querySelector('#popup-cancel-btn').addEventListener('click', closeWithoutSaving);


        // Always fire, even with zero results: onResult isn't just for row-level wiring
        // (e.g. Bilagsmatch's settings panel lives here too) - skipping it on a "0 found"
        // render used to leave that toolbar UI dead until the popup was closed and reopened.
        this.onResult.forEach(fn => fn(popupMenuCon));
    }

    /** Removes the popup and background dimmer from the DOM and resets internal state. */
    closeDropdown() {
        this.onClose.forEach(fn => fn(this.getPopupContainer()));
        if (this.popupContainer) {
            this.popupContainer.outerHTML = "";
            this.popupContainer = null;
            this.background_dimmer.outerHTML = "";
            this.background_dimmer = null;
            this.style.outerHTML = "";
            this.style = null;
        }
    }

    /**
     * Escapes a string for safe insertion as HTML text content OR inside a quoted
     * attribute value (single or double). The textContent/innerHTML round-trip used
     * previously only escapes &/</> - it leaves ' and " untouched, which is exactly what
     * breaks out of a quoted attribute, so this escapes both explicitly instead.
     * @param {string} text
     * @returns {string}
     */
    static escapeHtml(text) {
        // Not `!text` - that would also blank out a legitimate 0 or false cell value.
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
}
