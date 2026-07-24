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
 */
class PopupManager {
    popupContainer = null;
    background_dimmer = null;
    style = null;

    onNoResult = [];
    onResult = [];
    onClose = [];

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
            <div id="popup-summary-bar">${this.summaryFn(initialSelected, totalCount)}</div>
            <div id="popup-results">
        `;

        if (!results || results.length === 0) {
            if (this.onNoResult.some(fn => fn() === true)) return;
            html += this.noResultsHtml;
        } else {
            html += '<table class="popup-table"><thead><tr>';

            html += `<th class="popup-checkmark">${this.checkboxHeaderLabel}</th>`;
            this.columns.forEach(
                column => {
                    html += `<th ${column.headerAtt}>${column.display}</th>\n`;
                }
            );

            html += '</tr></thead><tbody>';

            results.forEach(item => {
                html += `<tr class="autocomplete-item">\n`;

                const checked = this.preSelectFn(item) ? 'checked' : '';
                html += `<td><input class='active-checkbox' type='checkbox' ${checked}/></td>\n`;
                this.columns.forEach(
                    column => {
                        html += `<td ${column.columnAtt}>${(typeof column.selector == "function" ? column.selector(item) : item[column.selector]) ?? '' }</td>\n`;
                    }
                );

                html += '</tr>\n';
            });
            html += '</tbody></table>';
        }

        html += `
            </div> <!-- popup-results -->
            <div class="popup-footer">
                <button type="button" id="popup-cancel-btn" class="saldi-button popup-btn-secondary">${this.closeLabel}</button>
                <button type="button" id="popup-exit-call-btn" class="saldi-button popup-btn-primary">${this.exitName}</button>
            </div>
        `;

        const popupMenuCon = this.getPopupContainer();
        popupMenuCon.innerHTML = html;

        // Recomputes the summary bar from the checkboxes actually checked right now -
        // called after every toggle so it always reflects the live selection.
        const updateSummary = () => {
            const info = popupMenuCon.querySelector('#popup-summary-bar');
            if (!info) return;
            const selected = popupMenuCon.querySelectorAll('#popup-results tbody input.active-checkbox:checked').length;
            info.textContent = this.summaryFn(selected, totalCount);
        };

        // Event listeners for results
        popupMenuCon.querySelectorAll('.autocomplete-item').forEach(item => {
            item.addEventListener('mousedown', function (e) {
                const box = item.querySelector('.active-checkbox');
                e.preventDefault();
                e.stopPropagation();
                if (box && e.target != box) {
                    box.checked = !box.checked;
                    updateSummary();
                }
            });
            const box = item.querySelector('.active-checkbox');
            if (box) box.addEventListener('change', updateSummary);
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
                    return Object.fromEntries(this.columnKeys.map((key, i) => [key, tds[i + 1]?.innerHTML ?? '']));
                });
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


        if (results && results.length !== 0) {
            this.onResult.forEach(fn => fn(popupMenuCon));
        }
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
     * Escapes a string for safe insertion as HTML text content.
     * @param {string} text
     * @returns {string}
     */
    static escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
