// --- javascript/docPoolSwitch.js --- ver 5.0.0 --- 2026-10-09 ---
// Copyright (c) 2026 Danosoft ApS
// 20261003 CL/SZ SD-719 Created: switch document in the pool without reloading the page.
//                The page for the new document is fetched and only the entry fields (#kassebilagTopBar) and the viewer (#rightPanel) are replaced.
//                The list, its loaded pages, scroll position, sort and search stay as they are; the URL is updated with history.replaceState.
//                The scripts inside the replaced parts run again; DOMContentLoaded handlers among them run right after.
//                Other scripts follow through the "poolswitch" event on document.
//                Anything unexpected (an error, a page without the same parts) falls back to loading the page normally.
// 20261006 CL/SZ SD-719 options.keepLineContext: a document opened for the same line leaves the match groups as they are.
// 20261006 CL/SZ SD-719 An element marked data-pool-keep="<id>" stays in front of that element through the switch (the kreditor notice), so the list doesn't jump.
// 20261006 CL/SZ SD-719 The bilag area keeps its height for 1.5 s after the switch while the new document's notes arrive, so the list moves at most once.
// 20261007 CL/SZ SD-719 The page is asked for with X-Pool-Switch, so it doesn't build the first list page this switch doesn't use.
// 20261009 CL/SZ SD-719 Inside the main menu (index/main.php) the menu's address (#...) follows the switch too, so F5 opens the document that is shown, not the first one.
(function () {
    'use strict';

    var REGIONS = ['kassebilagTopBar', 'rightPanel'];
    var inFlight = null;
    var heightHold = null;

    /**
     * Keeps the bilag area at its height while the new document's notes arrive (suggestion, "Fundet via", invoice warning,
     * "Ukendt leverandør"): they come a moment after the switch, so the list below would jump up and back down. It is let
     * go once they are in; a shorter document then moves the list once, to its place.
     */
    function holdHeight(el, height) {
        if (heightHold) clearTimeout(heightHold.timer);
        el.style.minHeight = height + 'px';
        heightHold = { el: el, timer: setTimeout(function () { el.style.minHeight = ''; heightHold = null; }, 1500) };
    }

    /** The journal line's amount and date the new page matches the list against (docPool.php prints them as JSON). */
    function lineContext(html) {
        var sum = html.match(/let totalSum\s*=\s*([^;\n]*);/);
        var dato = html.match(/let targetDate\s*=\s*([^;\n]*);/);
        try {
            return { sum: sum ? JSON.parse(sum[1]) : null, dato: dato ? JSON.parse(dato[1]) : null };
        } catch (e) {
            return null;
        }
    }

    /**
     * Runs the scripts of the replaced parts in document order.
     * A script added from another document does not run by itself, so each is replaced by a fresh copy.
     * Their DOMContentLoaded handlers would never fire on a loaded page, so they are collected and called afterwards.
     */
    function runScripts(roots) {
        var queued = [];
        var original = document.addEventListener;
        document.addEventListener = function (type, handler, options) {
            if (type === 'DOMContentLoaded') {
                queued.push(handler);
                return undefined;
            }
            return original.call(document, type, handler, options);
        };
        try {
            roots.forEach(function (root) {
                root.querySelectorAll('script').forEach(function (old) {
                    var script = document.createElement('script');
                    Array.prototype.forEach.call(old.attributes, function (attr) { script.setAttribute(attr.name, attr.value); });
                    script.text = old.text;
                    old.replaceWith(script);
                });
            });
        } finally {
            document.addEventListener = original;
        }
        queued.forEach(function (handler) {
            try {
                handler.call(document, new Event('DOMContentLoaded'));
            } catch (e) {
                console.error(e);
            }
        });
    }

    /**
     * Shows another document in place.
     * options.remove: file names that left the list (attached or archived) and are taken out of it.
     * options.keepLineContext: the same journal line, so the list keeps matching against its date and amount.
     * Resolves when the new document is shown.
     */
    function poolSwitch(href, options) {
        options = options || {};
        if (inFlight) inFlight.abort();
        var controller = new AbortController();
        inFlight = controller;
        document.body.classList.add('pool-switching');

        // X-Pool-Switch: the page leaves out the first list page it sends with a normal load (SD-719), the list stays
        return fetch(href, { credentials: 'same-origin', signal: controller.signal, headers: { 'X-Pool-Switch': '1' } })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.text();
            })
            .then(function (html) {
                if (inFlight !== controller) return;
                var fresh = new DOMParser().parseFromString(html, 'text/html');
                var parts = REGIONS.map(function (id) {
                    return { id: id, old: document.getElementById(id), fresh: fresh.getElementById(id) };
                });
                // Same parts on both pages, or a normal page load
                var viewer = parts[1];
                if (!viewer.old || !viewer.fresh || parts.some(function (p) { return !!p.old !== !!p.fresh; })) {
                    window.location.href = href;
                    return;
                }
                if (typeof window.closeAccountAutocomplete === 'function') window.closeAccountAutocomplete();

                // A notice that marks itself data-pool-keep="<id>" keeps its place in front of that element: the new document's
                // answer replaces or removes it, so the rows below don't jump up and back down (kreditorFromCvr.js, SD-721)
                var kept = Array.prototype.slice.call(document.querySelectorAll('[data-pool-keep]'));
                var top = parts[0].old;
                var topHeight = top ? top.getBoundingClientRect().height : 0;
                // The elements stay (the panel resizer holds on to #rightPanel); their content is replaced
                var replaced = [];
                parts.forEach(function (p) {
                    if (!p.old) return;
                    p.old.replaceChildren.apply(p.old, Array.prototype.map.call(p.fresh.childNodes, function (node) {
                        return document.importNode(node, true);
                    }));
                    replaced.push(p.old);
                });
                kept.forEach(function (el) {
                    var before = document.getElementById(el.dataset.poolKeep);
                    if (before && before.parentNode && !document.getElementById(el.id)) before.parentNode.insertBefore(el, before);
                });
                if (top && topHeight) holdHeight(top, Math.round(topHeight));
                window.history.replaceState(window.history.state, '', href);
                runScripts(replaced);
                if (typeof window.initAccountAutocomplete === 'function') window.initAccountAutocomplete();

                if (options.remove && options.remove.length && typeof window.poolRemoveFiles === 'function') {
                    window.poolRemoveFiles(options.remove);
                }
                var context = lineContext(html);
                if (context && !options.keepLineContext && typeof window.poolSetLineContext === 'function') window.poolSetLineContext(context.sum, context.dato);
                if (typeof window.poolShowCurrent === 'function') window.poolShowCurrent();
                document.dispatchEvent(new CustomEvent('poolswitch', { detail: { href: href } }));
                // The menu frame (index/main.php) only updates its address on a page load, and this switch doesn't load one.
                // Once, after the poolswitch handlers: they may change the address again (transfer=1 is removed), and two quick writes would make the menu reload the frame.
                try {
                    if (window.parent !== window && typeof window.parent.trigger_iframe_load === 'function') window.parent.trigger_iframe_load();
                } catch (e) {}
            })
            .catch(function (error) {
                if (error && error.name === 'AbortError') return;
                console.error(error);
                window.location.href = href;
            })
            .finally(function () {
                if (inFlight === controller) {
                    inFlight = null;
                    document.body.classList.remove('pool-switching');
                }
            });
    }

    window.poolSwitch = poolSwitch;
})();
