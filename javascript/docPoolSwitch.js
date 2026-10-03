// --- javascript/docPoolSwitch.js --- ver 5.0.0 --- 2026-10-03 ---
// Copyright (c) 2026 Danosoft ApS
// 20261003 CL/SZ SD-719 Created: switch document in the pool without reloading the page.
//                The page for the new document is fetched and only the entry fields (#kassebilagTopBar) and the viewer (#rightPanel) are replaced.
//                The list, its loaded pages, scroll position, sort and search stay as they are; the URL is updated with history.replaceState.
//                The scripts inside the replaced parts run again; DOMContentLoaded handlers among them run right after.
//                Other scripts follow through the "poolswitch" event on document.
//                Anything unexpected (an error, a page without the same parts) falls back to loading the page normally.
(function () {
    'use strict';

    var REGIONS = ['kassebilagTopBar', 'rightPanel'];
    var inFlight = null;

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
     * Resolves when the new document is shown.
     */
    function poolSwitch(href, options) {
        options = options || {};
        if (inFlight) inFlight.abort();
        var controller = new AbortController();
        inFlight = controller;
        document.body.classList.add('pool-switching');

        return fetch(href, { credentials: 'same-origin', signal: controller.signal })
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

                // The elements stay (the panel resizer holds on to #rightPanel); their content is replaced
                var replaced = [];
                parts.forEach(function (p) {
                    if (!p.old) return;
                    p.old.replaceChildren.apply(p.old, Array.prototype.map.call(p.fresh.childNodes, function (node) {
                        return document.importNode(node, true);
                    }));
                    replaced.push(p.old);
                });
                window.history.replaceState(window.history.state, '', href);
                runScripts(replaced);
                if (typeof window.initAccountAutocomplete === 'function') window.initAccountAutocomplete();

                if (options.remove && options.remove.length && typeof window.poolRemoveFiles === 'function') {
                    window.poolRemoveFiles(options.remove);
                }
                var context = lineContext(html);
                if (context && typeof window.poolSetLineContext === 'function') window.poolSetLineContext(context.sum, context.dato);
                if (typeof window.poolShowCurrent === 'function') window.poolShowCurrent();
                document.dispatchEvent(new CustomEvent('poolswitch', { detail: { href: href } }));
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
