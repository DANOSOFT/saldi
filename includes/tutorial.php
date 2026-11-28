<?php
// 20260902 CL/NTR Tutorial buttons are now type="button" so they no longer submit an enclosing
//                 form (e.g. kassekladde.php, whose form never closes because its </form> is
//                 printed inside a table cell) and reload the page on Next/Prev/Finish/Skip.
// 20261002 CL/LOE SST-852 Scroll each step's target into view and keep the tooltip inside the viewport
// 20261107 CL/ASR "Ro på": no auto-start and no seen rows; a ring instead of the dark overlay; Esc, ×, Færdig or a press outside the card ends the tour; window.SaldiTour for the help panel; one delegated click listener for the Hjælp cell, which opens the help panel in the shell and starts the tour in place elsewhere (never a reload).
// 20261107 CL/ASR Review: the ring and the card sit above the page's own layers (the kassekladde's account list is fixed at z-index 99999), and a tour gives focus back to a lookup field through focusAccountQuietly(), so its account list does not open by itself.
ob_start();

if (!function_exists('tutorial_utf8')) {
    /** A text in the database encoding (findtekst()) as UTF-8, so json_encode() never fails on a Latin-1 tenant. */
    function tutorial_utf8($s)
    {
        $s = (string) $s;
        return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    }
}

/**
 * Prints the tour of a page: a ring around the target and a small card, nothing else.
 *
 * The tour never starts by itself. It starts from window.SaldiTour.start() (the help panel's
 * "Rundvisning af siden", the old Assist menu) or from the page's blue Hjælp cell (#tutorial-help)
 * when the help panel is not there. Nothing is read from or written to the tutorials table.
 *
 * @param string $id    The tour id (window.SaldiTour.id); an id starting with 'book-' waits for renderComplete.
 * @param array  $steps array('selector' => CSS selector, 'content' => text (may hold <br>), 'first' => true to ring only the first match)
 */
function create_tutorial($id, $steps)
{
    global $sprog_id;

    // One tour per page: a second call would print the same ids again.
    static $printed = false;
    if ($printed) {
        return;
    }
    $printed = true;

    $list = array();
    foreach ((array) $steps as $step) {
        if (!is_array($step) || !isset($step['selector'])) {
            continue;
        }
        $list[] = array(
            'selector' => (string) $step['selector'],
            'content' => tutorial_utf8(isset($step['content']) ? (string) $step['content'] : ''),
            'first' => !empty($step['first']),
        );
    }
    $closeText = findtekst('8762|Luk rundvisningen', $sprog_id);
    // The texts below are put into the page with textContent, so entities are decoded here.
    $plain = function ($s) {
        return html_entity_decode(tutorial_utf8($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    };
    $txt = array(
        'tour' => $plain(findtekst('8760|Rundvisning', $sprog_id)),
        'of' => $plain(findtekst('8761|%s af %s', $sprog_id)),
        'ask' => $plain(findtekst('8763|Spørg Saldi AI om dette', $sprog_id)),
        'close' => $plain($closeText),
    );
    // ASCII-only JSON (no JSON_UNESCAPED_UNICODE), so it is safe in a UTF-8 and in a Latin-1 page.
    $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $stepsJson = json_encode($list, $flags);
    $txtJson = json_encode($txt, $flags);
    $idJson = json_encode(tutorial_utf8((string) $id), $flags);
    if ($stepsJson === false) {
        $stepsJson = '[]';
    }
    if ($txtJson === false) {
        $txtJson = '{}';
    }
    if ($idJson === false) {
        $idJson = '""';
    }
    // The page is printed in the database encoding, as findtekst() returns it.
    $closeAttr = htmlspecialchars((string) $closeText, ENT_QUOTES, mb_check_encoding((string) $closeText, 'UTF-8') ? 'UTF-8' : 'ISO-8859-1', false);

    ?>
    <div id="tutorial-ring" aria-hidden="true"></div>
    <div id="tutorial-tooltip" role="dialog" aria-modal="false" aria-labelledby="tutorial-step">
        <div id="tutorial-header">
            <span id="tutorial-step"></span>
            <button type="button" id="tutorial-skip" aria-label="<?php echo $closeAttr; ?>" title="<?php echo $closeAttr; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 -960 960 960" fill="#5b6b85" aria-hidden="true" focusable="false">
                    <path d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z" />
                </svg>
            </button>
        </div>
        <div id="tutorial-content"></div>
        <div id="tutorial-controls">
            <button type="button" id="tutorial-ask" hidden></button>
            <span class="tutorial-grow"></span>
            <button type="button" id="tutorial-prev"><?php echo findtekst('2598|Forrige', $sprog_id);?></button>
            <button type="button" id="tutorial-next"><?php echo findtekst('1200|Næste', $sprog_id);?></button>
            <button type="button" id="tutorial-finish"><?php echo findtekst('2599|Færdig', $sprog_id);?></button>
        </div>
    </div>

    <style>
        /* Classic pages have no tokens and no kit fonts, so every value is explicit and scoped by id.
           Above the page's own layers: the kassekladde's account list is fixed at z-index 99999 (accountAutocomplete.js). */
        #tutorial-ring { position: fixed; z-index: 100000; display: none; left: 0; top: 0; width: 0; height: 0; box-sizing: border-box; margin: 0; padding: 0; pointer-events: none; border: 2px solid #4FA0EE; border-radius: 6px; box-shadow: 0 0 0 3px rgba(79, 160, 238, .18); }
        #tutorial-ring[data-on="true"] { display: block; }
        #tutorial-tooltip { position: fixed; z-index: 100010; display: none; left: 8px; top: 8px; box-sizing: border-box; width: 320px; max-width: calc(100vw - 16px); margin: 0; padding: 12px 14px; background: #fff; color: #0b1a3a; border: 1px solid #e4e8ef; border-radius: 8px; box-shadow: 0 10px 34px rgba(16, 30, 60, .16); font: 13px/1.45 Inter, "Segoe UI", system-ui, -apple-system, Arial, sans-serif; text-align: left; letter-spacing: normal; text-transform: none; white-space: normal; }
        #tutorial-tooltip[data-on="true"] { display: block; }
        #tutorial-tooltip * { box-sizing: border-box; font-family: inherit; }
        #tutorial-tooltip [hidden] { display: none !important; }
        #tutorial-header { display: flex; align-items: center; gap: 8px; margin: 0 0 6px; padding: 0; background: none; color: inherit; }
        #tutorial-step { flex: 1 1 auto; font-size: 12px; font-weight: 600; line-height: 1.3; color: #5b6b85; }
        #tutorial-skip { display: inline-flex; align-items: center; justify-content: center; flex: none; width: 28px; height: 28px; margin: 0; padding: 0; border: 0; border-radius: 4px; background: none; cursor: pointer; }
        #tutorial-skip:hover { background: #eef2f8; }
        #tutorial-skip svg { display: block; width: 18px; height: 18px; }
        #tutorial-content { margin: 0; padding: 0; font-size: 13px; line-height: 1.45; color: #0b1a3a; }
        #tutorial-controls { display: grid; grid-template-columns: 1fr auto auto; gap: 8px; align-items: center; margin: 12px 0 0; padding: 0; }
        #tutorial-controls .tutorial-grow { display: none; }
        #tutorial-ask { grid-column: 1 / -1; justify-self: start; height: auto; margin: 0; padding: 0; border: 0; background: none; color: #1b68bd; font-size: 13px; line-height: 1.45; text-decoration: underline; text-underline-offset: 2px; cursor: pointer; }
        #tutorial-ask:hover { color: #0b1a3a; }
        #tutorial-prev, #tutorial-next, #tutorial-finish { height: 28px; margin: 0; padding: 0 12px; border: 1px solid #c6d2e4; border-radius: 4px; background: #fff; color: #0b1a3a; font-size: 12.5px; font-weight: 500; line-height: 1; white-space: nowrap; cursor: pointer; }
        #tutorial-prev { grid-column: 2; }
        #tutorial-prev:hover { background: #eef2f8; border-color: #a9b8cf; }
        #tutorial-next, #tutorial-finish { grid-column: 3; background: #114691; border-color: #114691; color: #fff; }
        #tutorial-next:hover, #tutorial-finish:hover { background: #0e3a78; border-color: #0e3a78; }
        #tutorial-tooltip button:focus-visible { outline: 2px solid #1B68BD; outline-offset: 2px; }
        @media print { #tutorial-ring, #tutorial-tooltip { display: none !important; } }
    </style>

    <script>
    (function () {
        'use strict';
        if (window.SaldiTour) { return; } // one tour per page
        var steps = <?php echo $stepsJson; ?>, id = <?php echo $idJson; ?>, T = <?php echo $txtJson; ?>;
        var ready = String(id).indexOf('book-') !== 0;
        if (!ready) { document.addEventListener('renderComplete', function () { ready = true; }); }

        var ring = document.getElementById('tutorial-ring');
        var card = document.getElementById('tutorial-tooltip');
        var stepLabel = document.getElementById('tutorial-step');
        var content = document.getElementById('tutorial-content');
        var askBtn = document.getElementById('tutorial-ask');
        var prevBtn = document.getElementById('tutorial-prev');
        var nextBtn = document.getElementById('tutorial-next');
        var finishBtn = document.getElementById('tutorial-finish');
        var skipBtn = document.getElementById('tutorial-skip');
        if (!ring || !card || !stepLabel || !content || !askBtn || !prevBtn || !nextBtn || !finishBtn || !skipBtn) { return; }

        function tx(key) { return T && typeof T[key] === 'string' ? T[key] : ''; }
        /* "%s af %s" → "2 af 4"; every %s is replaced in order */
        function fmt(s) {
            var args = arguments, i = 1;
            return String(s).replace(/%s/g, function () { return i < args.length ? String(args[i++]) : ''; });
        }
        askBtn.textContent = tx('ask');

        function els(s) {
            try {
                return s.first ? [document.querySelector(s.selector)].filter(Boolean) : Array.prototype.slice.call(document.querySelectorAll(s.selector));
            } catch (e) { return []; } // an invalid selector (e.g. :has() in an old browser) is a step without a target
        }
        function visible(el) { return Array.prototype.some.call(el.getClientRects(), function (r) { return r.width > 0 && r.height > 0; }); }
        /* Steps whose target is visible now: a hidden column, an empty status or a missing button gives no step */
        function available() { return ready ? steps.filter(function (s) { return els(s).some(visible); }) : []; }
        /* The help panel in the shell (main.php), only when it is switched on for this tenant */
        function shell() {
            try { return window.parent !== window && window.parent.SaldiHelp && window.parent.SaldiHelp.enabled ? window.parent : null; } catch (e) { return null; }
        }
        function tell(msg) {
            if (window.parent !== window) {
                try { window.parent.postMessage(msg, window.location.origin); } catch (e) { /* nothing listens */ }
            }
        }
        /* The step text as plain text, for "Spørg Saldi AI om dette". DOMParser builds an inert document:
           no script runs and no image loads. */
        function plain(html) {
            try {
                var doc = new DOMParser().parseFromString(String(html), 'text/html');
                Array.prototype.forEach.call(doc.body.querySelectorAll('br'), function (br) { br.parentNode.replaceChild(doc.createTextNode(' '), br); });
                return (doc.body.textContent || '').replace(/\s+/g, ' ').trim();
            } catch (e) { return ''; }
        }
        /* The viewport without the page's scrollbars (in quirks mode the body reports it) */
        function viewport() {
            var se = document.scrollingElement || document.documentElement;
            return { w: (se && se.clientWidth) || window.innerWidth, h: (se && se.clientHeight) || window.innerHeight };
        }
        /* A press on a scrollbar is not "outside the card": the user is only scrolling */
        function onScrollbar(e) {
            var t = e.target, vp = viewport();
            if (e.clientX >= vp.w || e.clientY >= vp.h) { return true; }
            if (!t || t.nodeType !== 1 || t === document.body || t === document.documentElement) { return false; }
            return (t.clientWidth > 0 && t.scrollHeight > t.clientHeight && e.offsetX >= t.clientWidth)
                || (t.clientHeight > 0 && t.scrollWidth > t.clientWidth && e.offsetY >= t.clientHeight);
        }

        var run = null;

        class Tutorial {
            constructor(list, opts) {
                this.steps = list;
                this.opts = opts || {};
                this.i = 0;
                this.running = false;
                this.shown = false;
                this.placed = false;
                this.prevFocus = null;
                this.prevSel = null;
                // Scroll positions to restore when the tour ends with ×, Færdig, Esc or stop()
                this.savedScroll = new Map();
                this.repositionFrame = 0;
                this.onKeydown = this.handleKeydown.bind(this);
                this.onPointerdown = this.handlePointerdown.bind(this);
                // One position() per frame however many scroll or resize events arrive
                this.onScrollOrResize = () => {
                    if (this.repositionFrame) { return; }
                    this.repositionFrame = requestAnimationFrame(() => {
                        this.repositionFrame = 0;
                        this.position();
                    });
                };
            }

            start(index, focus) {
                const ae = document.activeElement;
                this.prevFocus = ae && ae !== document.body && ae !== document.documentElement && !card.contains(ae) ? ae : null;
                this.prevSel = null;
                if (this.prevFocus) {
                    try {
                        if (typeof ae.selectionStart === 'number') { this.prevSel = [ae.selectionStart, ae.selectionEnd]; }
                    } catch (e) { this.prevSel = null; }
                }
                this.running = true;
                // Capture phase: the card's keys are handled (and defaultPrevented) before the page's
                // and the help panel's own listeners on the document see them
                document.addEventListener('keydown', this.onKeydown, true);
                document.addEventListener('pointerdown', this.onPointerdown, true);
                // Capture phase, so scrolling inside a container (e.g. the kassekladde grid) is seen too
                window.addEventListener('scroll', this.onScrollOrResize, true);
                window.addEventListener('resize', this.onScrollOrResize);
                this.showStep(Math.max(0, Math.min(index || 0, this.steps.length - 1)));
                if (focus) { this.focusButton(); }
            }

            focusButton() {
                const b = nextBtn.hidden ? finishBtn : nextBtn;
                try { b.focus({ preventScroll: true }); } catch (e) { b.focus(); }
            }

            handleKeydown(event) {
                if (!this.running || event.defaultPrevented) { return; }
                if (event.key === 'Escape' || event.key === 'Esc') {
                    event.preventDefault();
                    this.end({ restore: true });
                    return;
                }
                if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') { return; }
                if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) { return; }
                const t = event.target;
                if (t && t.nodeType === 1 && (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) || t.isContentEditable)) { return; }
                const to = this.i + (event.key === 'ArrowLeft' ? -1 : 1);
                if (to < 0 || to >= this.steps.length) { return; }
                event.preventDefault();
                this.showStep(to);
            }

            handlePointerdown(event) {
                if (!this.running) { return; }
                const t = event.target;
                if (t && t.nodeType === 1 && card.contains(t)) { return; }
                if (onScrollbar(event)) { return; }
                // A press on the Hjælp cell is left to its click, which ends the tour (below, or the help panel's toggle)
                if (t && t.nodeType === 1 && t.closest && t.closest('#tutorial-help')) { return; }
                // The press still does what it does; the user moved on, so focus and scroll stay where they are
                this.end({ restore: false });
            }

            showStep(index) {
                const n = this.steps.length;
                if (!this.running || index < 0 || index >= n) { return; }
                const first = !this.shown;
                this.shown = true;
                this.i = index;
                const step = this.steps[index];

                // Bring the target into view only when part of it is hidden (a long kassekladde scrolls its
                // own grid); scrollIntoView also scrolls nested containers, not just the window.
                const targets = els(step).filter(visible);
                if (targets.length && !this.fullyVisible(targets[0])) {
                    this.rememberScroll(targets[0]);
                    targets[0].scrollIntoView({ block: 'center', inline: 'nearest' });
                }

                const where = fmt(tx('of'), index + 1, n);
                stepLabel.textContent = tx('tour') + ' · ' + where;
                card.setAttribute('aria-label', tx('tour') + ', ' + where);
                // The step texts are tekster rows that carry <br> (kassekladde.php), so they stay HTML
                content.innerHTML = step.content;

                const ae = document.activeElement;
                prevBtn.hidden = index === 0;
                nextBtn.hidden = index === n - 1;
                finishBtn.hidden = index !== n - 1;
                askBtn.hidden = !shell();
                card.setAttribute('data-on', 'true');
                this.position();
                // A focused button that was just hidden would drop focus to the body
                if (ae && card.contains(ae) && ae.hidden) {
                    const b = !nextBtn.hidden ? nextBtn : finishBtn;
                    try { b.focus({ preventScroll: true }); } catch (e) { b.focus(); }
                }
                if (!first) { tell({ type: 'saldi:tour', id: id, state: 'step', step: index + 1, of: n }); }
            }

            next() {
                if (this.i < this.steps.length - 1) { this.showStep(this.i + 1); } else { this.end({ restore: true }); }
            }

            ask() {
                const step = this.steps[this.i];
                const text = step ? plain(step.content).slice(0, 200) : '';
                this.end({ restore: true });
                tell({ type: 'saldi:help-ask', text: text });
            }

            // Ring the visible part of the step's targets and place the card. The ring and the card are
            // position: fixed, so everything is in viewport coordinates (no scroll offsets).
            position() {
                if (!this.running) { return; }
                const step = this.steps[this.i];
                let top = Infinity, left = Infinity, right = -Infinity, bottom = -Infinity;
                // Combined box of the visible part of all elements of the step, each clipped by its
                // scrolling ancestors and the viewport
                (step ? els(step) : []).forEach((element) => {
                    const rect = this.visibleRect(element, 0);
                    if (!rect) { return; }
                    top = Math.min(top, rect.top);
                    left = Math.min(left, rect.left);
                    right = Math.max(right, rect.right);
                    bottom = Math.max(bottom, rect.bottom);
                });
                if (top < bottom && left < right) {
                    // 4 px between the target and the ring's 2 px border
                    ring.style.left = Math.round(left - 6) + 'px';
                    ring.style.top = Math.round(top - 6) + 'px';
                    ring.style.width = Math.round(right - left + 12) + 'px';
                    ring.style.height = Math.round(bottom - top + 12) + 'px';
                    ring.setAttribute('data-on', 'true');
                    this.placeCard({ top: top, left: left, right: right, bottom: bottom });
                } else {
                    // Nothing of the target is visible: no ring, the card stays where it was
                    ring.setAttribute('data-on', 'false');
                    this.clampCard();
                }
            }

            // Below the target, else above, else beside it, always 8 px inside the viewport
            placeCard(rect) {
                const vp = viewport(), margin = 8, gap = 14;
                const cw = card.offsetWidth || 320, ch = card.offsetHeight || 140;
                let top, left = rect.left;
                if (rect.bottom + gap + ch <= vp.h - margin) {
                    top = rect.bottom + gap;
                } else if (rect.top - gap - ch >= margin) {
                    top = rect.top - gap - ch;
                } else {
                    top = rect.top;
                    if (rect.right + gap + cw <= vp.w - margin) { left = rect.right + gap; }
                    else if (rect.left - gap - cw >= margin) { left = rect.left - gap - cw; }
                }
                this.moveCard(top, left);
            }

            clampCard() {
                const vp = viewport();
                if (!this.placed) {
                    this.moveCard(vp.h, 8); // first step without a visible target: bottom left
                    return;
                }
                this.moveCard(parseFloat(card.style.top) || 8, parseFloat(card.style.left) || 8);
            }

            moveCard(top, left) {
                const vp = viewport(), margin = 8;
                const cw = card.offsetWidth || 320, ch = card.offsetHeight || 140;
                top = Math.max(margin, Math.min(top, vp.h - ch - margin));
                left = Math.max(margin, Math.min(left, vp.w - cw - margin));
                card.style.top = Math.round(top) + 'px';
                card.style.left = Math.round(left) + 'px';
                this.placed = true;
            }

            fullyVisible(element) {
                const r = element.getBoundingClientRect(), v = this.visibleRect(element, 0);
                return !!v && v.top <= r.top + 1 && v.left <= r.left + 1 && v.right >= r.right - 1 && v.bottom >= r.bottom - 1;
            }

            // The element's rect (grown by padding), cut down to the client box of every ancestor
            // that clips its content and to the viewport; null when nothing of it is visible.
            // body and html are left out: their scrolling is the viewport's, which is applied last.
            visibleRect(element, padding) {
                const rect = element.getBoundingClientRect();
                let top = rect.top - padding, left = rect.left - padding;
                let right = rect.right + padding, bottom = rect.bottom + padding;

                for (let el = element.parentElement; el && el !== document.body && el !== document.documentElement; el = el.parentElement) {
                    const style = getComputedStyle(el);
                    if (style.overflowX === 'visible' && style.overflowY === 'visible') { continue; }
                    // Client box: inside the borders, without the scrollbars
                    const box = el.getBoundingClientRect();
                    const boxLeft = box.left + el.clientLeft;
                    const boxTop = box.top + el.clientTop;
                    top = Math.max(top, boxTop);
                    left = Math.max(left, boxLeft);
                    right = Math.min(right, boxLeft + el.clientWidth);
                    bottom = Math.min(bottom, boxTop + el.clientHeight);
                }

                top = Math.max(top, 0);
                left = Math.max(left, 0);
                right = Math.min(right, window.innerWidth);
                bottom = Math.min(bottom, window.innerHeight);

                return (top < bottom && left < right) ? { top: top, left: left, right: right, bottom: bottom } : null;
            }

            // Remember the original scroll position of every scrollable ancestor (incl. the page)
            // before the tour scrolls it, so end() can put the user back where they were
            rememberScroll(element) {
                for (let el = element.parentElement; el; el = el.parentElement) {
                    if (!this.savedScroll.has(el) && (el.scrollHeight > el.clientHeight || el.scrollWidth > el.clientWidth)) {
                        this.savedScroll.set(el, [el.scrollLeft, el.scrollTop]);
                    }
                }
            }

            restoreScroll() {
                this.savedScroll.forEach((pos, el) => {
                    el.scrollLeft = pos[0];
                    el.scrollTop = pos[1];
                });
                this.savedScroll.clear();
            }

            // restore: true for ×, Færdig, Esc and stop() (focus, caret and scroll go back);
            // false for a press outside the card (the user moved on)
            end(opts) {
                if (!this.running) { return; }
                this.running = false;
                const restore = !!(opts && opts.restore);
                document.removeEventListener('keydown', this.onKeydown, true);
                document.removeEventListener('pointerdown', this.onPointerdown, true);
                window.removeEventListener('scroll', this.onScrollOrResize, true);
                window.removeEventListener('resize', this.onScrollOrResize);
                if (this.repositionFrame) {
                    cancelAnimationFrame(this.repositionFrame);
                    this.repositionFrame = 0;
                }
                ring.setAttribute('data-on', 'false');
                card.setAttribute('data-on', 'false');
                content.textContent = '';
                if (restore) {
                    this.restoreScroll();
                    const el = this.prevFocus;
                    if (el && el.isConnected && typeof el.focus === 'function') {
                        try {
                            // A kassekladde lookup field opens its account list on a plain focus(); the page's
                            // focusAccountQuietly() focuses it as a Tab into it does (javascript/accountAutocomplete.js)
                            if (el.tagName === 'INPUT' && typeof window.focusAccountQuietly === 'function') { window.focusAccountQuietly(el); } else { el.focus({ preventScroll: true }); }
                        } catch (e) { /* not focusable any more */ }
                        if (this.prevSel) {
                            try { el.setSelectionRange(this.prevSel[0], this.prevSel[1]); } catch (e) { /* no caret in this field */ }
                        }
                    }
                } else {
                    this.savedScroll.clear();
                }
                this.prevFocus = null;
                this.prevSel = null;
                if (this.opts.onEnd) { this.opts.onEnd(); }
            }
        }

        // The card's buttons; bound once, they act on the running tour
        card.addEventListener('click', function (e) {
            const b = e.target && e.target.closest ? e.target.closest('button') : null;
            if (!b || !run) { return; }
            if (b === nextBtn) { run.next(); }
            else if (b === prevBtn) { run.showStep(run.i - 1); }
            else if (b === finishBtn || b === skipBtn) { run.end({ restore: true }); }
            else if (b === askBtn) { run.ask(); }
        });

        window.SaldiTour = {
            id: id,
            count: function () { return available().length; },
            active: function () { return !!run; },
            start: function (o) {
                o = o || {};
                const list = available();
                if (!list.length) { return false; }
                if (run) { run.end({ restore: false }); }
                const index = Math.max(0, Math.min(parseInt(o.step, 10) || 0, list.length - 1));
                run = new Tutorial(list, { onEnd: function () { run = null; tell({ type: 'saldi:tour', id: id, state: 'end' }); } });
                run.start(index, o.focus !== false);
                tell({ type: 'saldi:tour', id: id, state: 'start', step: index + 1, of: list.length });
                return true;
            },
            stop: function () { if (run) { run.end({ restore: true }); } }
        };

        // The page goes away (an Enter-save on the kassekladde): tell the shell the tour is over
        window.addEventListener('pagehide', function () { if (run) { run.end({ restore: false }); } });

        // One delegated listener: the cell may be printed before or after this script (debitor/ordre.php and
        // kreditor/ordre.php print the tour first). Bubble phase, so the help panel's capture listener in the
        // shell (javascript/saldi-help.js) wins once it has bound to this page. The cell's <button> has no
        // type, so the click is always prevented: never a form submit, never a reload.
        document.addEventListener('click', function (e) {
            const c = e.target && e.target.closest ? e.target.closest('#tutorial-help') : null;
            if (!c) { return; }
            e.preventDefault();
            if (shell()) {
                tell({ type: 'saldi:help-open', source: 'classic' });
                return;
            }
            // No help panel (a tenant without it, or the page outside the shell): the cell starts the tour
            // in place, and a second click ends it
            if (run) { window.SaldiTour.stop(); } else { window.SaldiTour.start({ focus: true }); }
        });
    })();
    </script>
    <?php
}
?>
