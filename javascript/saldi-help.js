// ---- javascript/saldi-help.js --- ver 5.1.0 --- 2026.11.14 ---
// 20261107 CL/ASR Created: Hjælp variant B in the shell (index/main.php). The topbar's Saldi AI symbol opens one light
//                 panel beside the page in the frame: search/ask, Om siden, Feltet du stod i, Sådan gør du with Vis mig,
//                 Rundvisning (window.SaldiTour in the frame), Tastaturgenveje, Kontakt support and what Saldi AI is told.
//                 Ported from prototype_hjaelp/help/core.js and surface-b.js for the top window + same-origin frame:
//                 every node is built with DOM calls and every text goes in as textContent or through md(), never as
//                 markup. Nothing opens or acts by itself; no polling, no idle blink, no "har noget til dig" in
//                 phase 1. Content comes from window.SaldiHelpContent and index/helpTexts.php, chrome texts from the
//                 host's data-txt. Spec: doc/hjaelp_saldi_ai.md.
// 20261107 CL/ASR Review round 1: focus goes back to a kassekladde lookup field through the page's focusAccountQuietly(),
//                 so closing help no longer opens its account list; Vis mig and Rundvisning close a list that is open and
//                 the ring and callout sit above it; the generic panel on Oversigt is named as the sidebar names it;
//                 a field's Vis mig is labelled with that field's own name; the phone number has +45 for en and no.
// 20261107 CL/ASR Review round 2: search ranks a hit on a task's title or a question above words spread over steps,
//                 breaks ties by the page's own order for its state, and also finds the page's fields, shortcuts and
//                 codes and a question's steps; "kontrolkonto" no longer hits "kontrolsum". Row symbols and old search
//                 hits are no longer kept alive after they leave the panel. An opened task, question or the keys list
//                 scrolls into view.
// 20261108 CL/ASR The page's state is read again when the document pool switches documents in place ("poolswitch").
// 20261108 CL/ASR Ask first (owner's user test): "Hvad kan jeg hjælpe med?" with the field and a send button, then
//                 Tastaturgenveje and Kontakt support; a problem the page shows (page.problem()) with its steps open;
//                 Om siden, Sådan gør du and Rundvisning under "Lær siden at kende", open the first three times a
//                 page is shown, then folded unless the user chose. Spec §18.
// 20261108 CL/ASR Hjælp inside Saldi Assist (spec §19): when the Guide loader says SaldiAssist.helpReady, the Hjælp
//                 button, ? and F1 open the chat (saldi:help:open) instead of the panel; the page help goes to the chat as
//                 saldi:help:state (javascript/saldi-help-state.js), and the chat's Vis mig, Rundvisning and fold choice
//                 come back as saldi:help:action. A chat that lies over the page closes for Vis mig and Rundvisning, and
//                 the strip "Tilbage til hjælpen" opens it again. Without helpReady the panel works as before.
// 20261108 CL/ASR Merge review (spec §19): a mouse end of a tour on its card and the help's own buttons no longer count
//                 as a press in the page or a move away from the field; the way back is not a new view of the page; a
//                 frame that is loading or cannot be read sends a state with route '' and the keys that work everywhere;
//                 a press outside the chat brings it back without focus; focus goes back to a docked chat after Vis mig
//                 or a tour; the chat opened from its own launcher ends a ring;
//                 SALDI_CHAT.isOpen()/layout() are preferred; the topbar button no longer points at the hidden panel;
//                 the panel takes over when a loader that says SaldiAssist.helpConnected === false does not connect within
//                 4 s; the additive fields and actions run, back and seen of spec §19.9. A v1.1 loader (it says
//                 helpConnected) also gets the page's fields in the search, with Vis mig on field:<id>; Vis mig and a
//                 tour step the chat aside with its hide() when it has one, so an open bug card in it stays.
// 20261108 ASR Vis mig audit (spec §21): the callout's button says Ok (new chrome text 8795) instead of Forstået (5148,
//                 which Saldi uses elsewhere and keeps).
// 20261108 CL/ASR Oversigt (spec §20): the page's state is read again when it hides or shows itself in place
//                 ("saldi:dash-hidden" from javascript/dashboard.js).
// 20261108 ASR Saldi AI symbol in Saldi Assist: in merged mode the chat's loader owns the topbar symbol's data-ai-state
//                 and data-here (data-saldi-assist-symbol, topbar.php).
// 20261108 ASR Saldi Assist in the shell (spec §23): with a loader that says SaldiAssist.shellVersion === 1 the topbar's
//                 Hjælp button opens the chat where the user left off (no saldi:help:open, so no page help over a
//                 conversation) and closes it when it is open; ?, F1, field help and the classic cell are unchanged. Every
//                 chat open arms the 4 s watchdog, so a "+ Ny chat" whose chat never connects also falls back to the panel,
//                 and while the panel has taken over "+ Ny chat" is hidden through data-saldi-help-dead.
// 20261109 CL/ASR Variants (spec §24): the page for the frame comes from SaldiHelpState.resolve(), so a route that serves
//                 several pages (systemdata/settingsSection.php?s=<group>.<section>, one page object per settings section)
//                 shows the section's own page and loads its own text group. The page id (the route, and ?<key> for a
//                 variant) keys the fold counters, the order signature and route changes, so another section is another page.
// 20261109 CL/ASR Page questions in the chat (spec §25): the state's labels questions, askMore and related (chrome texts 8825-8827).
// 20261112 CL/ASR A page that redraws in place (page.inPlace, the CRM's tabs, drawer and dialogs) is read again after a
//                 click or its keys Esc, Enter and N in the frame, not only on a load or a change event (spec §22.10).
// 20261113 CL/ASR The CRM's drawer opened after a fetch (the server search's pick) says "crmdrawer" (javascript/crm.js), and the
//                 page is read again then, as on "poolswitch". Typing in a field of an inPlace page (the CRM's search) reads it
//                 again once the typing pauses, and the panel is drawn again only when the page's state changed. A page's
//                 ownWords are not stop words on that page, so "Mine" finds the CRM's view Mine (spec §22.10).
// 20261114 CL/ASR In the shell, Hjælp on an open chat minimises it (SALDI_CHAT.minimize, the conversation is kept) instead
//                 of closing it, and Lær siden at kende is folded on every open (learnOpen false).
(function () {
	'use strict';

	const host = document.getElementById('saldi-help');
	if (!host || window.SaldiHelp) { return; }

	/* ================================================================ settings from the host */
	let TXT = {};
	try { TXT = JSON.parse(host.getAttribute('data-txt') || '{}') || {}; } catch (e) { TXT = {}; }
	const LANG = /^(da|en|no)$/.test(host.getAttribute('data-lang') || '') ? host.getAttribute('data-lang') : 'da';
	const SUPPORT_SEND = host.getAttribute('data-support-send') === '1';
	const RECORDS = host.getAttribute('data-records') === '1';
	const SIGNAL_ON = host.getAttribute('data-signal') === '1';
	const W = 400;                 // panel width (css/saldi-help.css)
	const DOCK_ROOM = 960;         // D1: dock when the page keeps at least this much beside the panel
	const PIN_KEY = 'saldi.help.pinned', OPEN_KEY = 'saldi.help.open';
	const LEAVE_MS = 120;
	const DEFAULT_SUPPORT = { phone: '46 90 22 08', phoneIntl: '+45 46 90 22 08', tel: '+4546902208', mail: 'support@saldi.dk', openHour: 9, closeHour: 16, tz: 'Europe/Copenhagen' };
	// The global keys use the chrome ids of data-txt (spec §4.1); content may hand its own list with the same ids.
	const DEFAULT_GLOBAL_KEYS = [{ keys: '?', id: '8749' }, { keys: 'F1', id: '8750' }, { keys: 'Esc', id: '8751' }, { keys: 'Ctrl+K', id: '8752' }, { keys: 'Alt+L', id: '8753' }];
	// Search vocabulary used only when the content file has none for the language (never shown).
	const DEFAULT_STOP = ['jeg', 'hvordan', 'hvad', 'hvor', 'kan', 'der', 'det', 'den', 'de', 'en', 'et', 'og', 'på', 'til', 'af', 'med', 'om', 'er', 'min', 'mit', 'mine',
		'hvorfor', 'skal', 'ikke', 'saldi', 'får', 'gør', 'man', 'mig', 'du', 'dig', 'har', 'have', 'eller', 'som', 'fra', 'for', 'nu', 'så', 'lige', 'vil', 'gerne'];
	// Hjælp inside Saldi Assist (spec §19): what a saldi:help:action may point at, and the state's debounce
	const HELP_TARGET = /^(task|problem|q|field):[A-Za-z0-9_]{1,24}$|^field$/;   // field:<id>: a field of the search (v1.1)
	const STATE_MS = 150;
	const READY_MS = 4000;   // a loader that says helpConnected === false gets this long to connect before the panel takes over

	/* ================================================================ tiny utils */
	const warned = {};
	function warnOnce(key, msg, err) {
		if (warned[key]) { return; }
		warned[key] = true;
		try { console.warn('saldi-help: ' + msg, err || ''); } catch (e) { /* no console */ }
	}
	function fill(s, args) {
		let i = 0;
		return String(s).replace(/%s/g, function () { return i < args.length ? String(args[i++]) : ''; });
	}
	/** A chrome text from data-txt (spec §8.1), or the id in brackets when it is missing (visible in testing). */
	function t(id) {
		const v = TXT[String(id)];
		if (typeof v === 'string') { return v; }
		warnOnce('txt:' + id, 'missing chrome text ' + id);
		return '[' + id + ']';
	}
	function tf(id) { return fill(t(id), Array.prototype.slice.call(arguments, 1)); }
	let uidN = 0;
	function uid(p) { uidN += 1; return (p || 'shp') + '-' + uidN; }
	function el(tag, attrs, kids) {
		const n = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				const v = attrs[k];
				if (v === null || v === undefined || v === false) { return; }
				if (k === 'class') { n.className = v; } else if (k === 'text') { n.textContent = String(v); } else { n.setAttribute(k, v === true ? '' : String(v)); }
			});
		}
		(kids || []).forEach(function (c) {
			if (c === null || c === undefined || c === false) { return; }
			n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
		});
		return n;
	}
	function clear(n) { while (n && n.firstChild) { n.removeChild(n.firstChild); } }
	/**
	 * The one helper that formats text (spec §1.14): **x** becomes a <strong> whose textContent is x; everything else
	 * is a text node. Nothing is parsed as HTML, so & < > " ' need no escaping. An unbalanced trailing ** stays literal.
	 * With doc, the nodes are built in that (frame) document.
	 */
	function md(text, doc) {
		const d = doc || document;
		const frag = d.createDocumentFragment();
		const parts = String(text === null || text === undefined ? '' : text).split('**');
		const balanced = parts.length % 2 === 1;
		parts.forEach(function (p, i) {
			if (i % 2 === 0) { if (p) { frag.appendChild(d.createTextNode(p)); } return; }
			if (!balanced && i === parts.length - 1) { frag.appendChild(d.createTextNode('**' + p)); return; }
			const s = d.createElement('strong');
			s.textContent = p;
			frag.appendChild(s);
		});
		return frag;
	}
	function mq(q) { try { return window.matchMedia(q).matches; } catch (e) { return false; } }
	function reduced() { return mq('(prefers-reduced-motion: reduce)'); }
	function coarse() { return mq('(pointer: coarse)'); }
	function lGet(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } }
	function lSet(k, v) { try { if (v === null) { window.localStorage.removeItem(k); } else { window.localStorage.setItem(k, v); } } catch (e) { /* private mode */ } }
	function sGet(k) { try { return window.sessionStorage.getItem(k); } catch (e) { return null; } }
	function sSet(k, v) { try { if (v === null) { window.sessionStorage.removeItem(k); } else { window.sessionStorage.setItem(k, v); } } catch (e) { /* private mode */ } }
	/** Mostly under the panel: the rects meet and the middle of a lies within the panel's width (a wide status line is not "behind"). */
	function covered(a, panel) { const cx = (a.left + a.right) / 2; return hit(a, panel) && cx >= panel.left && cx <= panel.right; }
	function hit(a, b, pad) { pad = pad || 0; return !!(a && b) && a.left < b.right + pad && a.right > b.left - pad && a.top < b.bottom + pad && a.bottom > b.top - pad; }
	function norm(s) { return String(s || '').toLowerCase().replace(/[éèê]/g, 'e').replace(/[»«"'?!.,:;()*]/g, ' ').replace(/\s+/g, ' ').trim(); }
	function words(s) { return norm(s).split(' ').filter(function (w) { return w.length >= 2; }); }

	/* ================================================================ icons: path strings only, built with createElementNS */
	const SVGNS = 'http://www.w3.org/2000/svg';
	const ICONS = {
		search: { d: ['M10 18a7.952 7.952 0 0 0 4.897-1.688l4.396 4.396 1.414-1.414-4.396-4.396A7.952 7.952 0 0 0 18 10c0-4.411-3.589-8-8-8s-8 3.589-8 8 3.589 8 8 8zm0-14c3.309 0 6 2.691 6 6s-2.691 6-6 6-6-2.691-6-6 2.691-6 6-6z'] },
		chev: { d: ['M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z'] },
		send: { d: ['m21.426 11.095-17-8A1 1 0 0 0 3.03 4.242l1.212 4.849L12 12l-7.758 2.909-1.212 4.849a.998.998 0 0 0 1.396 1.147l17-8a1 1 0 0 0 0-1.81zM5.481 18.197l.839-3.357L12 12 6.32 9.16l-.839-3.357L18.651 12l-13.17 6.197z'] },
		x: { d: ['m16.192 6.344-4.243 4.242-4.242-4.242-1.414 1.414L10.535 12l-4.242 4.242 1.414 1.414 4.242-4.242 4.243 4.242 1.414-1.414L13.364 12l4.242-4.242z'] },
		mail: { d: ['M20 4H4c-1.103 0-2 .897-2 2v12c0 1.103.897 2 2 2h16c1.103 0 2-.897 2-2V6c0-1.103-.897-2-2-2zm0 2v.511l-8 6.223-8-6.222V6h16zM4 18V9.044l7.386 5.745a.994.994 0 0 0 1.228 0L20 9.044 20.002 18H4z'] },
		phone: { d: ['M17.707 12.293a.999.999 0 0 0-1.414 0l-1.594 1.594c-.739-.22-2.118-.72-2.992-1.594s-1.374-2.253-1.594-2.992l1.594-1.594a.999.999 0 0 0 0-1.414l-4-4a.999.999 0 0 0-1.414 0L3.581 5.005c-.38.38-.594.902-.586 1.435.023 1.424.4 6.37 4.298 10.268s8.844 4.274 10.269 4.298h.028c.528 0 1.027-.208 1.405-.586l2.712-2.712a.999.999 0 0 0 0-1.414l-4-4.001zm-.127 6.712c-1.248-.021-5.518-.356-8.873-3.712-3.366-3.366-3.692-7.651-3.712-8.874L7 4.414 9.586 7 8.293 8.293a1 1 0 0 0-.272.912c.024.115.611 2.842 2.271 4.502s4.387 2.247 4.502 2.271a.991.991 0 0 0 .912-.271L17 14.414 19.586 17l-2.006 2.005z'] },
		tour: { d: ['m21.447 6.105-6-3a1 1 0 0 0-.895 0L9 5.882 3.447 3.105A1 1 0 0 0 2 4v13c0 .379.214.725.553.895l6 3a1 1 0 0 0 .895 0L15 18.118l5.553 2.776a.992.992 0 0 0 .972-.043c.295-.183.475-.504.475-.851V7c0-.379-.214-.725-.553-.895zM10 7.236l4-2v11.528l-4 2V7.236zm-6-.618 4 2v11.764l-4-2V6.618zm16 12.764-4-2V5.236l4 2v12.146z'] },
		keys: { d: ['M20 5H4c-1.103 0-2 .897-2 2v10c0 1.103.897 2 2 2h16c1.103 0 2-.897 2-2V7c0-1.103-.897-2-2-2zM4 17V7h16l.002 10H4z', 'M5 8h2v2H5zm3 0h2v2H8zm3 0h2v2h-2zm3 0h2v2h-2zm3 0h2v2h-2zM5 11h2v2H5zm3 0h2v2H8zm3 0h2v2h-2zm3 0h2v2h-2zm3 0h2v2h-2zM8 14h8v2H8z'] },
		support: { d: ['M12 2C6.486 2 2 6.486 2 12v4.143C2 17.167 2.897 18 4 18h1a1 1 0 0 0 1-1v-5.143a1 1 0 0 0-1-1h-.908C4.648 6.987 7.978 4 12 4s7.352 2.987 7.908 6.857H19a1 1 0 0 0-1 1V18c0 1.103-.897 2-2 2h-2v-1h-4v3h6c2.206 0 4-1.794 4-4 1.103 0 2-.833 2-1.857V12c0-5.514-4.486-10-10-10z'] },
		guide: { d: ['M19.903 8.586a.997.997 0 0 0-.196-.293l-6-6a.997.997 0 0 0-.293-.196c-.03-.014-.062-.022-.094-.033a.991.991 0 0 0-.259-.051C13.04 2.011 13.021 2 13 2H6c-1.103 0-2 .897-2 2v16c0 1.103.897 2 2 2h12c1.103 0 2-.897 2-2V9c0-.021-.011-.04-.013-.062a.952.952 0 0 0-.051-.259c-.01-.032-.019-.063-.033-.093zM16.586 8H14V5.414L16.586 8zM6 20V4h6v5a1 1 0 0 0 1 1h5l.002 10H6z', 'M8 12h8v2H8zm0 4h8v2H8zm0-8h2v2H8z'] },
		back: { stroke: '2', d: ['M14.5 6 8.5 12l6 6'] },
		pin: { stroke: '1.8', d: [['hsb-pin__head', 'M9 3.5h6l-.9 5.6 3.4 3.4v1.9h-11v-1.9l3.4-3.4z'], 'M12 14.4v6.1'] }
	};
	function icon(name, cls) {
		const def = ICONS[name];
		const s = document.createElementNS(SVGNS, 'svg');
		s.setAttribute('viewBox', '0 0 24 24');
		s.setAttribute('width', '16');
		s.setAttribute('height', '16');
		s.setAttribute('aria-hidden', 'true');
		s.setAttribute('focusable', 'false');
		if (cls) { s.setAttribute('class', cls); }
		if (def.stroke) {
			s.setAttribute('fill', 'none');
			s.setAttribute('stroke', 'currentColor');
			s.setAttribute('stroke-width', def.stroke);
			s.setAttribute('stroke-linecap', 'round');
			s.setAttribute('stroke-linejoin', 'round');
		} else {
			s.setAttribute('fill', 'currentColor');
		}
		def.d.forEach(function (p) {
			const path = document.createElementNS(SVGNS, 'path');
			if (Array.isArray(p)) { path.setAttribute('class', p[0]); path.setAttribute('d', p[1]); } else { path.setAttribute('d', p); }
			s.appendChild(path);
		});
		return s;
	}

	/* ================================================================ the frame adapter (spec §1.5) */
	function frame() { return document.getElementById('iframe_a'); }
	function fwin() { try { const f = frame(); return f ? f.contentWindow : null; } catch (e) { return null; } }
	function fdoc() { try { const w = fwin(); return w ? w.document : null; } catch (e) { return null; } }
	/** The frame path relative to the Saldi root, or null when the frame is unreadable or not a Saldi page. */
	function route() {
		try {
			const w = fwin();
			if (!w) { return null; }
			const p = w.location.pathname;                                     // throws SecurityError on another origin
			const root = location.pathname.replace(/\/index\/main\.php$/, '');  // '/asr_hj', '' at the domain root
			return p.indexOf(root + '/') === 0 ? p.slice(root.length + 1) : null;
		} catch (e) { return null; }
	}
	function homeContent() { return document.querySelector('.home-section .home-content'); }
	function inFrame(n) { return !!(n && n.nodeType === 1 && n.ownerDocument && n.ownerDocument !== document); }
	/** A rect of a frame element in top-window coordinates. */
	function rectInTop(n) {
		const r = n.getBoundingClientRect();
		if (!inFrame(n)) { return { left: r.left, top: r.top, right: r.right, bottom: r.bottom, width: r.width, height: r.height }; }
		const f = frame().getBoundingClientRect();
		return { left: r.left + f.left, top: r.top + f.top, right: r.right + f.left, bottom: r.bottom + f.top, width: r.width, height: r.height };
	}
	// Frame nodes come from another realm: nodeType/tagName, never instanceof (spec §3.1).
	function isField(n) {
		if (!n || n.nodeType !== 1) { return false; }
		const tag = n.tagName;
		if (tag === 'SELECT' || tag === 'TEXTAREA') { return true; }
		if (tag !== 'INPUT') { return false; }
		return ['hidden', 'button', 'submit', 'reset', 'image'].indexOf(String(n.type || '').toLowerCase()) < 0;
	}
	function isTyping(n) { return !!(n && n.nodeType === 1 && (/^(INPUT|TEXTAREA|SELECT)$/.test(n.tagName) || n.isContentEditable)); }
	function caretOf(n) {
		try { if (n && typeof n.selectionStart === 'number') { return { start: n.selectionStart, end: n.selectionEnd }; } } catch (e) { /* type=number etc. */ }
		return { start: null, end: null };
	}
	function visibleEl(n) {
		try { return !!(n && n.nodeType === 1 && n.isConnected && Array.prototype.some.call(n.getClientRects(), function (r) { return r.width > 0 && r.height > 0; })); } catch (e) { return false; }
	}

	/* ================================================================ content and help texts (spec §3.2, §3.4) */
	function content() { const c = window.SaldiHelpContent; return c && typeof c === 'object' ? c : null; }
	/** The page object that serves the frame and its variant key (spec §24): the route's page, or the variant it names for
	 *  this document through SaldiHelpState.resolve() (a settings section of settingsSection.php). { page, key } or null.
	 *  Without the state builder (index/main.php always loads it first) a route's own page serves. */
	function resolved(ctx) {
		const c = content();
		if (!c || !c.pages || !ctx || typeof ctx.route !== 'string') { return null; }
		const p = c.pages[ctx.route];
		if (!p || typeof p !== 'object' || typeof p.group !== 'string') { return null; }
		const B = window.SaldiHelpState;
		if (!B || typeof B.resolve !== 'function') { return { page: p, key: '' }; }
		return B.resolve(p, ctx, function (what, e) { warnOnce('variant:' + ctx.route, 'variant() failed on ' + ctx.route, e); });
	}
	function pageFor(ctx) { const r = resolved(ctx); return r ? r.page : null; }
	/** The page's identity for route changes and the fold counters: the route, and ?<key> for a variant (spec §24). */
	function idOf(ctx, r) { return ctx ? ctx.route + (r && r.key ? '?' + r.key : '') : null; }
	function frameId() { const ctx = pageCtx(); return ctx ? idOf(ctx, resolved(ctx)) : null; }
	function supportData() { const c = content(); return Object.assign({}, DEFAULT_SUPPORT, (c && c.support) || {}); }
	/** The phone number as shown: the Danish form for Danish users; with +45 for everyone else, because the Danish form
	 *  reads as a valid domestic number abroad (a Norwegian mobile number is 8 digits from 4). The tel: link has +45 always. */
	function phoneShown(sup) { return LANG === 'da' ? sup.phone : (sup.phoneIntl || sup.phone); }
	const TX = { maps: {}, state: {} };
	/** Fetch help.<group>.* once per shell lifetime; a failure is retried on the next open (spec §2.4). */
	function loadTexts(group) {
		if (!/^[a-z]{2,12}$/.test(group) || TX.state[group] === 'loading' || TX.state[group] === 'ready') { return; }
		TX.state[group] = 'loading';
		fetch('../index/helpTexts.php?group=' + encodeURIComponent(group), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
			.then(function (r) { return r.json(); })
			.then(function (j) {
				if (!j || j.ok !== true || !j.texts || typeof j.texts !== 'object') { throw new Error('no texts'); }
				TX.maps[group] = j.texts;
				TX.state[group] = 'ready';
			})
			.catch(function (e) {
				warnOnce('texts:' + group, 'help texts for ' + group + ' could not be loaded', e);
				TX.state[group] = 'failed';
			})
			.then(function () { if (S.open) { render(); } queueState(); });
	}
	/** A help text (help.<group>.… or help.common.…) for the user's language, %s filled in order, or [key]. */
	function T(key) {
		const args = Array.prototype.slice.call(arguments, 1);
		if (typeof key !== 'string' || key === '') { return ''; }
		const m = /^help\.([a-z0-9]+)\./.exec(key);
		let map = null;
		if (m && m[1] !== 'common') {
			map = TX.maps[m[1]] || null;
		} else {
			map = (P.rawPage && TX.maps[P.rawPage.group]) || null;
			if (!map || typeof map[key] !== 'string') {
				Object.keys(TX.maps).some(function (g) { if (typeof TX.maps[g][key] === 'string') { map = TX.maps[g]; return true; } return false; });
			}
		}
		if (map && typeof map[key] === 'string') { return fill(map[key], args); }
		warnOnce('key:' + key, 'missing help text ' + key);
		return '[' + key + ']';
	}
	/** Msg = Key or [Key, ...args] (spec §3.2). */
	function M(msg) { return Array.isArray(msg) ? T.apply(null, msg) : T(msg); }
	function call(fn, args, dflt, name) {
		if (typeof fn !== 'function') { return dflt; }
		try {
			const v = fn.apply(P.rawPage, args);
			return v === null || v === undefined ? dflt : v;
		} catch (e) {
			warnOnce('call:' + name, name + ' failed', e);
			return dflt;
		}
	}

	/* ================================================================ state */
	const S = {
		open: false, view: 'home', mode: null, pinned: lGet(PIN_KEY) === '1', minimised: false, busy: false,
		lastField: null,        // { el, key, name, start, end } (el null between pagehide and the next bind)
		returnTo: null,         // where Esc/× return focus: { el, start, end }
		lastPage: null,         // the page element the user left last while help was open
		lastDown: null,         // the last press or key in the page: { el, t, press }
		st: null, stDoc: null,  // detect() cached per frame document
		lastRoute: undefined,   // page id of the last frame document seen: its route, and ?<key> for a variant (spec §24)
		fieldFirst: false, fieldMinH: 0, expanded: {}, keysOpen: false, seenRoute: null,
		q: '', sel: -1, notice: null, supportOpts: null,
		ring: null, tour: false, fullRect: null, pos: null,
		engine: null, signal: false, signalKey: '', acked: {}, mood: 'ready',
		leaveT: 0, behind: []
	};
	let P = { ctx: null, page: null, rawPage: null, st: null, texts: 'none', id: null };
	const D = {};
	// Hjælp inside Saldi Assist (spec §19): the last state sent (json) and kept (state), the debounce timer, whether the
	// first state went out after the loader ran (caught), the route last counted as a view in the chat (seen), the way
	// back to the chat while Vis mig or a tour shows with the chat closed (back: { focus, rect }), and a tour that closed the
	// chat and has not said 'start' yet (backTour: the focus to return to, backRect: where the chat stood). fromChat: a ring
	// or tour shown beside a chat that stayed open, so focus goes back into it. dead: the chat did not connect, and the panel
	// works instead (watch: that timer). widgetSeen: the chat says itself when it shows the help (the seen action), so the
	// host stops counting views. obs: the observer of the chat box while the way back is shown.
	const H = { json: null, state: null, timer: 0, caught: false, seen: null, back: null, backTour: null, backRect: null, fromChat: false, dead: false, watch: 0, widgetSeen: false, obs: null };

	/* ================================================================ page context, state and field (spec §1.6) */
	function pageCtx() {
		const r = route();
		if (r === null) { return null; }
		try {
			const win = fwin(), doc = win.document;
			return {
				doc: doc, win: win, url: new URL(win.location.href), route: r, lang: LANG, t: t,
				field: S.lastField && S.lastField.el ? { key: S.lastField.key, el: S.lastField.el } : null
			};
		} catch (e) { return null; }
	}
	function cachedDetect(page, ctx) {
		if (S.st && S.stDoc === ctx.doc) { return S.st; }
		let st;
		try {
			st = page.detect(ctx);
			if (!st || typeof st !== 'object') { throw new Error('detect() returned no state'); }
			st = { state: String(st.state || 'unknown'), flags: st.flags || {}, data: st.data || {} };
		} catch (e) {
			warnOnce('detect:' + ctx.route, 'detect() failed on ' + ctx.route, e);
			st = { state: 'unknown', flags: {}, data: {} };
		}
		S.st = st;
		S.stDoc = ctx.doc;
		return st;
	}
	function dropDetect() { S.st = null; S.stDoc = null; }
	/** P = what the panel shows now. load: start fetching the page's help texts (only on an open). */
	function computeP(load) {
		const ctx = pageCtx();
		const res = ctx ? resolved(ctx) : null;
		const raw = res ? res.page : null;
		let texts = 'none';
		if (raw) {
			texts = TX.state[raw.group] || 'none';
			if (load && (texts === 'none')) { loadTexts(raw.group); texts = TX.state[raw.group] || 'none'; }
		}
		P = { ctx: ctx, rawPage: raw, page: raw && texts === 'ready' ? raw : null, st: null, texts: texts, id: idOf(ctx, res) };
		P.st = raw && ctx ? cachedDetect(raw, ctx) : null;
		return P;
	}
	function fieldKeyFor(n) {
		const page = ctx0Page();
		if (page && typeof page.fieldKey === 'function') {
			try { const k = page.fieldKey(n); if (typeof k === 'string' && k) { return k; } } catch (e) { warnOnce('fieldKey', 'fieldKey() failed', e); }
		}
		const name = n.getAttribute('name') || '';
		let m;
		if ((m = /^search\[[^\]]+\]\[([^\]]+)\]$/.exec(name))) { return 'search:' + m[1]; }
		if (/^rowcount\[/.test(name)) { return 'rowcount'; }
		if (name) { return name.replace(/_?\d+$/, ''); }
		return n.id || null;
	}
	function ctx0Page() { const ctx = pageCtx(); return ctx ? pageFor(ctx) : null; }
	function setLastField(n) {
		const k = caretOf(n);
		S.lastField = { el: n, key: fieldKeyFor(n), name: n.getAttribute('name') || '', start: k.start, end: k.end };
	}
	/** "Feltet du stod i" for the current page: { key, label, text, el } or null. */
	function fieldInfo() {
		const f = S.lastField;
		if (!f || !f.key || !P.page || !P.page.fields) { return null; }
		const def = P.page.fields[f.key];
		if (!def) { return null; }
		return { key: f.key, label: T(def.label), text: T(def.text), el: f.el };
	}

	/* ================================================================ the symbol, the mascot and its states (spec §1.10) */
	const M_ = { top: null, at: null, seats: [], flyer: null, flight: null, avatars: [] };
	function topBtn() { return document.getElementById('topbar-help-btn'); }
	function topSvg() { const s = M_.top; return s ? s.querySelector('svg') : null; }
	/** A copy of the topbar's symbol (cloneNode, never serialised markup) with its own gradient id (spec §1.10). */
	function cloneSymbol() {
		const src = topSvg();
		if (!src) { return null; }
		const svg = src.cloneNode(true);
		const id = 'sa-g-' + uid('c');
		const g = svg.querySelector('linearGradient');
		if (g) { g.setAttribute('id', id); }
		const s = svg.querySelector('.sa-stroke');
		if (s) { s.setAttribute('stroke', 'url(#' + id + ')'); }
		const d = svg.querySelector('.sa-dot');
		if (d) { d.setAttribute('fill', 'url(#' + id + ')'); }
		return svg;
	}
	/** Only the long-lived symbols are kept in M_.avatars (the topbar seat, the panel's head seat and the flyer). */
	function registerAvatar(a) { if (M_.avatars.indexOf(a) < 0) { M_.avatars.push(a); } a.setAttribute('data-ai-state', S.mood); }
	/** A symbol in a disc. The 20 px row symbols are rebuilt on every keystroke and render, so they are not kept in a list
	 *  (that kept every detached results list alive); applyMood() finds them in the panel instead. */
	function avatar(size, seat) {
		const a = el('span', { class: 'ai-avatar' + (size === 20 ? ' ai-avatar--20' : '') + (seat ? ' ai-avatar--light ai-avatar--seat' : ''), 'aria-hidden': 'true' });
		const svg = cloneSymbol();
		if (svg) { a.appendChild(svg); }
		if (seat) { registerAvatar(a); registerSeat(a); } else { a.setAttribute('data-ai-state', S.mood); }
		return a;
	}
	function computeMood() {
		if (S.engine === 'thinking') { return 'thinking'; }
		if (S.engine === 'answering') { return 'answering'; }
		if (S.signal) { return 'signal'; }
		return 'ready';
	}
	function applyMood() {
		const m = computeMood();
		S.mood = m;
		M_.avatars = M_.avatars.filter(function (a) { return a.isConnected || a === M_.top; });
		const all = M_.avatars.concat(Array.prototype.slice.call(host.querySelectorAll('.ai-avatar')));
		// in merged mode the chat's loader owns the topbar symbol's state (data-saldi-assist-symbol, support-sally
		// docs/saldi-ai-symbol.md): thinking, answering and the amber dot of an answer that came while the chat was closed
		const loaderOwns = merged() && M_.top && M_.top.hasAttribute('data-saldi-assist-symbol');
		all.forEach(function (a) { if (loaderOwns && a === M_.top) { return; } if (a.getAttribute('data-ai-state') !== m) { a.setAttribute('data-ai-state', m); } });
		const b = topBtn();
		if (b) { b.setAttribute('aria-label', m === 'signal' ? t('8701') : t('8700')); }
	}
	function ctr(n) {
		if (!visibleEl(n)) { return null; }
		const r = n.getBoundingClientRect();
		return { x: r.left + r.width / 2, y: r.top + r.height / 2, w: r.width };
	}
	function setHere(flying) {
		M_.seats = M_.seats.filter(function (s) { return s === M_.top || s.isConnected; });
		const loaderOwns = merged() && M_.top && M_.top.hasAttribute('data-saldi-assist-symbol');
		M_.seats.forEach(function (s) { if (loaderOwns && s === M_.top) { return; } s.setAttribute('data-here', (!flying && s === M_.at) ? 'true' : 'false'); });
	}
	function registerSeat(a) {
		if (M_.seats.indexOf(a) < 0) { M_.seats.push(a); }
		a.setAttribute('data-here', a === M_.at ? 'true' : 'false');
	}
	function placeFlyer(c) {
		M_.flyer.style.transform = 'translate(' + (c.x - 20).toFixed(1) + 'px, ' + (c.y - 20).toFixed(1) + 'px) scale(' + (c.w / 40).toFixed(4) + ')';
	}
	function flyerHide() { if (M_.flyer) { M_.flyer.setAttribute('data-on', 'false'); M_.flyer.style.removeProperty('--ai-shadow'); } }
	/** 640 ms easeInOutCubic arc, both ends re-measured every frame, then a 320 ms landing pop (prototype core.js fly()). */
	function fly(target, o) {
		o = o || {};
		target = target || M_.top;
		if (!target || !M_.flyer) { return; }
		const mid = M_.flight;
		if (target === M_.at && !mid) { setHere(false); return; }
		const origin = mid ? mid.pos : (o.from || ctr(M_.at));
		if (mid) { cancelAnimationFrame(mid.raf); }
		M_.flight = null;
		const instant = o.instant || reduced() || !origin || !ctr(target);
		M_.at = target;
		if (instant) { flyerHide(); setHere(false); return; }
		setHere(true);
		const f = M_.flight = { raf: 0, pos: origin };
		placeFlyer(origin);
		M_.flyer.setAttribute('data-on', 'true');
		const t0 = performance.now(), dur = 640;
		function step(now) {
			if (M_.flight !== f) { return; }
			const p = Math.min(1, (now - t0) / dur), e = p < 0.5 ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2;
			const a = origin, b = ctr(target);
			if (!b) { M_.flight = null; flyerHide(); M_.at = M_.top; setHere(false); return; }
			const d = Math.hypot(b.x - a.x, b.y - a.y);
			const head = Math.min(a.y, b.y) - Math.max(a.w, b.w) / 2 - 4;
			let amp = Math.min(150, Math.max(36, d * 0.22));
			amp = head >= 24 ? Math.min(amp, head) : -Math.min(24, d * 0.08);
			const lift = amp * 4 * e * (1 - e);
			const w = a.w + (b.w - a.w) * e;
			const y = Math.max(a.y + (b.y - a.y) * e - lift, w / 2 + 4);
			f.pos = { x: a.x + (b.x - a.x) * e, y: y, w: w };
			placeFlyer(f.pos);
			M_.flyer.style.setProperty('--ai-shadow', Math.sin(Math.PI * e).toFixed(3));
			if (p < 1) { f.raf = requestAnimationFrame(step); return; }
			M_.flight = null;
			flyerHide();
			setHere(false);
			if (target.animate && !reduced()) { target.animate([{ transform: 'scale(1.12)' }, { transform: 'scale(1)' }], { duration: 320, easing: 'cubic-bezier(.2,.7,.2,1)' }); }
		}
		f.raf = requestAnimationFrame(step);
	}
	/** "Har noget til dig" (spec §1.10): evaluated only with data-signal="1", which phase 1 never prints (D11). */
	function signalCheck() {
		if (!SIGNAL_ON) { return; }
		const ctx = pageCtx();
		const page = ctx ? pageFor(ctx) : null;
		let on = false, key = '';
		if (page && typeof page.signal === 'function') {
			const st = cachedDetect(page, ctx);
			key = [ctx.route, st.data && st.data.kladdeId ? st.data.kladdeId : '', st.state].join('|');
			try { on = !!page.signal(ctx, st); } catch (e) { warnOnce('signal', 'signal() failed', e); on = false; }
		}
		S.signalKey = key;
		S.signal = on && !S.acked[key];
		applyMood();
	}
	function ackSignal() { if (S.signalKey) { S.acked[S.signalKey] = true; } if (S.signal) { S.signal = false; applyMood(); } }

	/* ================================================================ build the panel once (spec §2.4) */
	function rowButton(o) {
		const b = el('button', { type: 'button', class: 'hc-row' + (o.className ? ' ' + o.className : '') });
		const ic = el('span', { class: 'hc-row__ic', 'aria-hidden': 'true' });
		if (o.icon) { ic.appendChild(o.icon); }
		if (o.icon !== false) { b.appendChild(ic); }
		const txt = el('span', { class: 'hc-row__txt' }, [el('span', { class: 'hc-row__t', text: o.title })]);
		if (o.desc) { txt.appendChild(el('span', { class: 'hc-row__d', text: o.desc })); }
		b.appendChild(txt);
		if (o.chevron !== false) { b.appendChild(icon('chev', 'hc-row__chev')); }
		if (o.onClick) { b.addEventListener('click', o.onClick); }
		return b;
	}
	function build() {
		host.classList.add('saldi-help', 'hs-b', 'hc-root');
		clear(host);

		D.handle = el('button', { type: 'button', class: 'hc-sheet__handle', 'aria-label': t('8773') });
		sheetHandle(D.handle);
		host.appendChild(D.handle);

		D.head = el('header', { class: 'hsb-head' });
		D.seat = avatar(40, true);
		D.head.appendChild(D.seat);
		D.title = el('h2', { class: 'hsb-title', id: 'saldi-help-title' });
		D.head.appendChild(el('div', { class: 'hsb-who' }, [D.title]));
		D.pinTxt = el('span', { class: 'hsb-pin__t' });
		D.pin = el('button', { type: 'button', class: 'hc-btn hc-btn--ghost hsb-pin', 'aria-pressed': 'false' }, [icon('pin'), D.pinTxt]);
		D.pin.addEventListener('click', togglePin);
		D.head.appendChild(D.pin);
		D.close = el('button', { type: 'button', class: 'hc-x', 'aria-label': t('8735'), title: t('8747') }, [icon('x')]);
		D.close.addEventListener('click', function () { close({ restoreFocus: true }); });
		D.head.appendChild(D.close);
		host.appendChild(D.head);

		D.body = el('div', { class: 'hsb-body' });
		// Ask first (spec §18): "Hvad kan jeg hjælpe med?", the field, that anything may be asked, then the page's
		// shortcuts and support one click away. The page's guidance follows under "Lær siden at kende".
		D.ask = el('section', { class: 'hsb-ask', 'aria-labelledby': 'saldi-help-ask-h' });
		D.ask.appendChild(el('h3', { class: 'hc-sec hsb-ask__h', id: 'saldi-help-ask-h', text: t('8711') }));
		D.searchWrap = el('div', { class: 'hsb-search' }, [buildSearch()]);
		D.ask.appendChild(D.searchWrap);
		D.ask.appendChild(el('p', { class: 'hsb-ask__sub', text: t('8789') }));
		const keysId = uid('hsk');
		D.keysBtn = el('button', { type: 'button', class: 'hc-btn hc-btn--ghost hsb-quick__btn', 'aria-expanded': 'false', 'aria-controls': keysId }, [icon('keys'), el('span', { text: t('6024') })]);
		D.keysBtn.addEventListener('click', function () { setKeysOpen(D.keysBox.hidden); if (!D.keysBox.hidden) { reveal(D.keysBox, D.keysBtn); } });
		const supBtn = el('button', { type: 'button', class: 'hc-btn hc-btn--ghost hsb-quick__btn' }, [icon('support'), el('span', { text: t('8714') })]);
		supBtn.addEventListener('click', function (e) { openSupport({ keyboard: e.detail === 0 }); });
		D.ask.appendChild(el('div', { class: 'hsb-quick' }, [D.keysBtn, supBtn]));
		D.keysBox = el('div', { class: 'hsb-keysbox', id: keysId, hidden: true });
		D.ask.appendChild(D.keysBox);
		D.body.appendChild(D.ask);
		D.notice = el('div', { class: 'hsb-notice', role: 'status', hidden: true });
		D.body.appendChild(D.notice);
		// A problem the page shows (page.problem()), with its steps open: the acute help comes before the guidance
		D.problem = el('section', { class: 'hsb-sec hsb-problem', hidden: true }, [el('h3', { class: 'hc-sec hsb-h', text: t('8791') })]);
		D.problemList = el('div', { class: 'hsb-list' });
		D.problem.appendChild(D.problemList);
		D.body.appendChild(D.problem);
		D.field = el('section', { class: 'hsb-sec hsb-field', hidden: true });
		D.body.appendChild(D.field);
		const learnId = uid('hsl');
		D.learn = el('section', { class: 'hsb-learn' });
		D.learnBtn = el('button', { type: 'button', class: 'hsb-learn__btn', 'aria-expanded': 'true', 'aria-controls': learnId }, [el('span', { class: 'hsb-learn__t', text: t('8790') }), icon('chev', 'hsb-learn__chev')]);
		D.learnBtn.addEventListener('click', function () { const open = D.learnBody.hidden; setLearnOpen(open, true); if (open) { reveal(D.learnBody, D.learnBtn); } });
		D.learnBody = el('div', { class: 'hsb-learn__body', id: learnId });
		D.learn.appendChild(D.learnBtn);
		D.learn.appendChild(D.learnBody);
		D.about = el('section', { class: 'hsb-sec hsb-about' }, [el('h3', { class: 'hc-sec hsb-h', text: t('8703') })]);
		D.aboutBody = el('div');
		D.about.appendChild(D.aboutBody);
		D.learnBody.appendChild(D.about);
		D.tasks = el('section', { class: 'hsb-sec hsb-tasks' }, [el('h3', { class: 'hc-sec hsb-h', text: t('8706') })]);
		D.taskList = el('div', { class: 'hsb-list' });
		D.tasks.appendChild(D.taskList);
		D.learnBody.appendChild(D.tasks);
		D.more = el('section', { class: 'hsb-sec hsb-more' });
		D.moreList = el('ul', { class: 'hc-rows hsb-list' });
		D.more.appendChild(D.moreList);
		D.learnBody.appendChild(D.more);
		D.body.appendChild(D.learn);
		D.support = el('section', { class: 'hsb-sec hsb-support hc-hidden' });
		const back = el('button', { type: 'button', class: 'hc-btn hc-btn--ghost hsb-back' }, [icon('back'), t('8728')]);
		back.addEventListener('click', function (e) { setView('home', { keyboard: e.detail === 0 }); });
		D.support.appendChild(back);
		D.supportCard = el('div', { class: 'hsb-support__card' });
		D.support.appendChild(D.supportCard);
		D.body.appendChild(D.support);
		host.appendChild(D.body);

		D.foot = el('footer', { class: 'hsb-foot' });
		D.foot.appendChild(disclosure());
		host.appendChild(D.foot);

		D.strip = el('div', { class: 'hsb-strip' });
		D.stripTxt = el('span', { class: 'hc-strip__txt' });
		const stripBack = el('button', { type: 'button', class: 'hc-btn hc-btn--ghost', text: t('8728') });
		stripBack.addEventListener('click', function () { endRingOrTour(); });
		D.strip.appendChild(el('div', { class: 'hc-strip', role: 'status' }, [D.stripTxt, stripBack]));
		host.appendChild(D.strip);
	}
	function disclosure() {
		const id = uid('hcd');
		const box = el('div', { id: id, hidden: true });
		box.appendChild(el('p', { class: 'hc-disclosure__txt', text: RECORDS ? t('8771') : t('8737') }));
		box.appendChild(el('p', { class: 'hc-disclosure__txt', text: t('8772') }));
		const b = el('button', { type: 'button', class: 'hc-disclosure__btn', 'aria-expanded': 'false', 'aria-controls': id, text: t('8736') });
		b.addEventListener('click', function () { const show = box.hidden; box.hidden = !show; b.setAttribute('aria-expanded', show ? 'true' : 'false'); });
		return el('div', { class: 'hc-disclosure' }, [b, box]);
	}
	/** Tap closes; dragging down more than 80 px closes (bottom sheet only). */
	function sheetHandle(hd) {
		let y0 = null, moved = false;
		hd.addEventListener('click', function () { if (!moved) { close({}); } moved = false; });
		hd.addEventListener('pointerdown', function (e) { y0 = e.clientY; moved = false; try { hd.setPointerCapture(e.pointerId); } catch (x) { /* ignore */ } });
		hd.addEventListener('pointermove', function (e) {
			if (y0 === null) { return; }
			const dy = Math.max(0, e.clientY - y0);
			if (dy > 4) { moved = true; }
			host.style.transform = dy ? 'translateY(' + dy + 'px)' : '';
		});
		hd.addEventListener('pointerup', function (e) {
			if (y0 === null) { return; }
			const dy = e.clientY - y0;
			y0 = null;
			host.style.transform = '';
			if (dy > 80) { moved = true; close({}); }
		});
		hd.addEventListener('pointercancel', function () { y0 = null; host.style.transform = ''; });
	}

	/* ================================================================ rendering */
	function genericLabel() {
		if (!P.ctx) { return ''; }
		// Oversigt posts no breadcrumb, and its <title> is a fixed Danish "Overblik" that is nowhere on screen: use the
		// sidebar entry the user clicked (#dashboard, findtekst 2224 in the user's language), as the page is called there.
		if (P.ctx.route === 'index/dashboard.php') {
			const dash = document.querySelector('#dashboard .link_name');
			const name = dash ? dash.textContent.replace(/\s+/g, ' ').trim() : '';
			if (name) { return name; }
		}
		try { return typeof topbarAssistPage === 'function' ? String(topbarAssistPage().label || '') : ''; } catch (e) { return ''; }
	}
	/** The page's own help registry (page_help(): tour, shortcuts, guide), only for a readable frame. */
	function registry() {
		if (!P.ctx) { return {}; }
		try { return typeof topbarAssistPage === 'function' ? (topbarAssistPage().help || {}) : {}; } catch (e) { return {}; }
	}
	function pageName() {
		if (P.page) { return M(call(P.page.name, [P.ctx, P.st], '', 'name()')); }
		return genericLabel();
	}
	function renderHead() {
		clear(D.title);
		D.title.appendChild(document.createTextNode(t('2564')));
		const name = pageName();
		if (name) {
			D.title.appendChild(document.createTextNode(' '));
			D.title.appendChild(el('span', { class: 'hsb-title__q', text: name }));
		}
	}
	function tourApi() {
		if (!P.ctx) { return null; }
		try { const w = fwin(); return w && w.SaldiTour && typeof w.SaldiTour.count === 'function' ? w.SaldiTour : null; } catch (e) { return null; }
	}
	function tourCount() { const a = tourApi(); if (!a) { return 0; } try { return Number(a.count()) || 0; } catch (e) { return 0; } }
	function render() {
		if (!S.open) { return; }
		computeP(true);
		renderHead();
		if (!S.busy) {
			fillProblem();
			fillField(false);
			fillAbout();
			fillTasks(false);
			fillMore();
			fillKeys();
			fillLearn();
		}
		if (S.view === 'support') { ensureSupportCard(S.supportOpts || {}); S.supportOpts = null; }
		renderNotice();
		applyView();
		setBusyAttr();
		if (S.q.trim()) { renderResults(); }
		layout();
	}
	function applyView() {
		const sup = S.view === 'support', searching = !!S.q.trim();
		D.ask.classList.toggle('hc-hidden', sup);
		D.ask.classList.toggle('is-searching', searching);
		D.notice.classList.toggle('hc-hidden', sup);
		[D.problem, D.field, D.learn].forEach(function (s) { s.classList.toggle('hc-hidden', sup || searching); });
		D.support.classList.toggle('hc-hidden', !sup);
		host.setAttribute('data-view', S.view);
	}
	function setBusyAttr() {
		const loading = !!(P.rawPage && P.texts === 'loading');
		if (S.busy || loading) { host.setAttribute('aria-busy', 'true'); } else { host.removeAttribute('aria-busy'); }
	}
	function fillAbout() {
		clear(D.aboutBody);
		const keys = P.page ? call(P.page.about, [P.ctx, P.st], [], 'about()') : [];
		(Array.isArray(keys) ? keys : []).forEach(function (k) { D.aboutBody.appendChild(el('p', { class: 'hsb-about__t' }, [md(M(k))])); });
		D.about.hidden = !D.aboutBody.firstChild;
	}
	function fillField(keepOld) {
		const f = fieldInfo();
		// focus went to a button while help was open, or the page is reloading: keep the box (prototype fillField(keepOld))
		if ((!f && keepOld && D.field.firstChild) || (S.lastField && !S.lastField.el && D.field.firstChild)) { markBehind(); return; }
		S.behind = S.behind.filter(function (b) { return b.kind !== 'fieldbox'; });
		clear(D.field);
		if (!f) { D.field.hidden = true; return; }
		const box = el('div', { class: 'hc-field' });
		box.appendChild(el('p', { class: 'hc-field__h', text: tf('8704', f.label) }));
		box.appendChild(el('p', { class: 'hc-field__t' }, [md(f.text)]));
		const ask = el('button', { type: 'button', class: 'hc-link', text: t('8705') });
		ask.addEventListener('click', function () { prefill(tf('8756', f.label)); });
		box.appendChild(ask);
		if (f.el) {
			const note = el('p', { class: 'hsb-behind', hidden: true }, [t('8746')]);
			const show = el('button', { type: 'button', class: 'hc-link hsb-behind__show', text: t('8707'), 'aria-label': tf('8708', f.label) });
			show.addEventListener('click', function () { if (S.lastField && S.lastField.el) { showMe(S.lastField.el, { label: f.label, kind: 'field' }); } });
			note.appendChild(show);
			box.appendChild(note);
			S.behind.push({ kind: 'fieldbox', node: f.el, note: note });
		}
		D.field.appendChild(box);
		D.field.hidden = false;
		// After a problem the page shows, or first after F1/? from a field (spec §1.6, §18); before the guidance
		if (S.fieldFirst) { D.body.insertBefore(D.field, D.problem); } else { D.body.insertBefore(D.field, D.learn); }
		lockField();
	}
	/** The field box never shrinks while the panel is open (prototype surface-b.js lockField()). */
	function lockField() {
		const box = D.field.querySelector('.hc-field');
		if (!box || !S.open) { return; }
		box.style.minHeight = '';
		const h = box.getBoundingClientRect().height;
		if (h > S.fieldMinH) { S.fieldMinH = h; }
		if (S.fieldMinH) { box.style.minHeight = S.fieldMinH + 'px'; }
	}
	function resolveShow(show) {
		if (!show || !P.ctx) { return null; }
		let n = null;
		try {
			if (typeof show.target === 'function') {
				n = show.target.call(P.rawPage, P.ctx, P.st);
			} else if (typeof show.target === 'string') {
				n = Array.prototype.find.call(P.ctx.doc.querySelectorAll(show.target), visibleEl) || null;
			}
		} catch (e) { warnOnce('show:' + String(show.target), 'show target failed', e); n = null; }
		return n && n.nodeType === 1 && visibleEl(n) ? n : null;
	}
	/** The Vis mig label. A field target that the page describes in its field help gets that field's own label, so the
	 *  strip and the callout name the field the ring is on (KK4 rings Debet or Kredit, whichever is empty). */
	function showLabel(show, n) {
		if (show.kind === 'field' && n && P.page && P.page.fields) {
			const k = fieldKeyFor(n);
			const def = k ? P.page.fields[k] : null;
			if (def && def.label) { return T(def.label); }
		}
		return T(show.label);
	}
	const BEHIND = { button: '8745', field: '8746', status: '8758', row: '8758' };
	/** An accordion that opened below the visible part of the panel scrolls up into view, without pushing its own row
	 *  out at the top. Only the panel's body moves (review round 2). */
	function reveal(box, head) {
		try {
			const b = D.body.getBoundingClientRect(), r = box.getBoundingClientRect();
			let dy = r.bottom - b.bottom + 8;
			if (head) { dy = Math.min(dy, head.getBoundingClientRect().top - b.top - 8); }
			if (dy > 0) { D.body.scrollBy({ top: dy, behavior: reduced() ? 'auto' : 'smooth' }); }
		} catch (e) { /* ignore */ }
	}
	/** The first visible field of the frame document with this field key (spec §1.6): the field the user stood in when it
	 *  has the key, else the first one on the page (line 1 for the kassekladde's line fields). */
	function fieldEl(key) {
		const ctx = P.ctx;
		if (!ctx || !key) { return null; }
		if (S.lastField && S.lastField.el && S.lastField.key === key && visibleEl(S.lastField.el)) { return S.lastField.el; }
		const esc = function (s) { return window.CSS && CSS.escape ? CSS.escape(s) : String(s).replace(/["\\]/g, '\\$&'); };
		let sel;
		const m = /^search:(.+)$/.exec(key);
		if (m) { sel = '[name^="search["][name$="[' + esc(m[1]) + ']"]'; } else if (key === 'rowcount') { sel = '[name^="rowcount["]'; } else { sel = '[name^="' + esc(key) + '"], #' + esc(key); }
		try {
			return Array.prototype.find.call(ctx.doc.querySelectorAll(sel), function (n) { return isField(n) && fieldKeyFor(n) === key && visibleEl(n); }) || null;
		} catch (e) { return null; }
	}
	/** A task, a prepared question or (in search results) a field as an accordion row with its steps or text and a quiet
	 *  Vis mig (spec §2.4 item 5, D5). o.hit marks a search result, whose Vis mig note the next search drops. */
	function stepItem(kind, id, def, o) {
		o = o || {};
		const isQ = kind === 'q', asField = kind === 'field';
		const title = T(isQ ? def.q : asField ? def.label : def.title);
		const why = isQ || asField ? '' : T(def.why);
		const key = kind + ':' + id;
		const li = el('li', { class: 'hc-taskrow' });
		const stepsId = uid('hss');
		const row = rowButton({ icon: o.icon || false, title: title, desc: why, className: o.rowClass || '' });
		row.setAttribute('aria-expanded', 'false');
		row.setAttribute('aria-controls', stepsId);
		const head = el('div', { class: 'hsb-taskhead' }, [row]);
		li.appendChild(head);
		const box = el('div', { id: stepsId, class: 'hsb-expand', hidden: true });
		if (isQ && def.intro) { box.appendChild(el('p', { class: 'hsb-intro' }, [md(T(def.intro))])); }
		if (asField) {
			box.appendChild(el('p', { class: 'hsb-intro' }, [md(T(def.text))]));
		} else {
			const ol = el('ol', { class: 'hsb-steps' });
			(Array.isArray(def.steps) ? def.steps : []).forEach(function (k) { ol.appendChild(el('li', null, [md(T(k))])); });
			box.appendChild(ol);
		}
		li.appendChild(box);
		const show = asField ? { target: function () { return fieldEl(id); }, label: def.label, kind: 'field' } : (def.show || null);
		const target = resolveShow(show);
		if (target) {
			const kindOf = show.kind || 'button';
			const sm = el('button', { type: 'button', class: 'hsb-showme', 'aria-label': tf('8708', title), text: t('8707') });
			if (S.busy) { sm.disabled = true; }
			sm.addEventListener('click', function () {
				const n = resolveShow(show);
				if (!n) { render(); return; }
				showMe(n, { label: showLabel(show, n), kind: kindOf, note: show.note ? T(show.note) : '' });
			});
			li.classList.add('hsb-task--show');
			head.appendChild(sm);
			const note = el('span', { class: 'hsb-behind', hidden: true, text: t(BEHIND[kindOf] || '8758') });
			row.querySelector('.hc-row__txt').appendChild(note);
			S.behind.push({ kind: o.hit ? 'hit' : (o.behindKind || 'task'), node: target, note: note });
		}
		if (isQ) {
			const acts = el('div', { class: 'hsb-expand__acts' });
			const askQ = el('button', { type: 'button', class: 'hc-link', text: t('8763') });
			askQ.addEventListener('click', function () { ask(title); });
			acts.appendChild(askQ);
			box.appendChild(acts);
		}
		function setOpen(open) {
			row.setAttribute('aria-expanded', open ? 'true' : 'false');
			box.hidden = !open;
			if (o.remember) { if (open) { S.expanded[key] = true; } else { delete S.expanded[key]; } }
		}
		row.addEventListener('click', function () { const open = box.hidden; setOpen(open); if (open) { reveal(box, head); } });
		if ((o.remember && S.expanded[key]) || o.open) { setOpen(true); }
		return li;
	}
	let lastOrder = '';
	/** "Sådan gør du": the first 3 existing task ids of page.order(ctx, st) (D4). */
	function fillTasks(onlyIfChanged) {
		const page = P.page;
		let ids = [];
		const pid = problemId();
		if (page && page.tasks) {
			const raw = call(page.order, [P.ctx, P.st], [], 'order()');
			const seen = {};
			if (pid) { seen[pid] = true; }      // shown in the problem card above
			(Array.isArray(raw) ? raw : []).forEach(function (id) {
				if (ids.length < 3 && !seen[id] && page.tasks[id]) { seen[id] = true; ids.push(id); }
			});
		}
		const sig = (P.id || '') + '|' + pid + '|' + ids.join(',');
		if (onlyIfChanged && sig === lastOrder) { return; }
		lastOrder = sig;
		S.behind = S.behind.filter(function (b) { return b.kind !== 'task'; });
		clear(D.taskList);
		D.tasks.hidden = !ids.length;
		if (!ids.length) { return; }
		const ul = el('ul', { class: 'hc-rows hc-tasks' });
		ids.forEach(function (id) { ul.appendChild(stepItem('task', id, page.tasks[id], { remember: true })); });
		D.taskList.appendChild(ul);
	}
	/** Rundvisning · Vejledning, under "Lær siden at kende" (Tastaturgenveje is in the ask block, spec §18). */
	function fillMore() {
		clear(D.moreList);
		const reg = registry();
		const add = function (n) { if (n) { D.moreList.appendChild(el('li', null, [n])); } };
		const n = tourCount();
		if (n > 0) {
			const tr = rowButton({ icon: icon('tour'), title: tf('8709', n), desc: t('8710'), onClick: function () { startTour(); } });
			tr.setAttribute('data-tour-row', '1');
			if (S.busy) { tr.disabled = true; }
			add(tr);
		} else if (reg.tour && reg.tour !== '#tutorial-help' && reg.tour !== 'saldi:tour') {   // both mean SaldiTour, counted above
			add(rowButton({ icon: icon('tour'), title: t('6455'), onClick: function () { runRegistered('tour'); } }));
		}
		if (reg.guide) { add(rowButton({ icon: icon('guide'), title: t('92'), onClick: function () { runRegistered('guide'); } })); }
		// a page without content asks through the ask block at the top (spec §18), no "Spørg Saldi AI om <side>" row here
		D.more.classList.toggle('hsb-plain', !P.page);
		D.more.hidden = !D.moreList.firstChild;
	}
	function runRegistered(kind) {
		try { if (typeof topbarAssistRun === 'function') { topbarAssistRun(kind); } } catch (e) { warnOnce('run:' + kind, 'topbarAssistRun failed', e); }
	}
	/** The keys that work everywhere: the content file's list, else the shell's (spec §4.1). */
	function globalKeys() {
		const c = content();
		return c && Array.isArray(c.globalShortcuts) && c.globalShortcuts.length ? c.globalShortcuts : DEFAULT_GLOBAL_KEYS;
	}
	/** The page's keys and codes, then the global keys and the page's note (the list under "Tastaturgenveje"). */
	function keysList(reg, id) {
		const ul = el('ul', { class: 'hsb-keylist', id: id });
		const page = P.page;
		const own = page && Array.isArray(page.shortcuts) ? page.shortcuts : [];
		const keyLi = function (text, keys) { return el('li', null, [el('span', null, [md(text)]), el('span', { class: 'hc-kbd', text: keys })]); };
		[['keys', '8774'], ['codes', '8775']].forEach(function (g) {
			const list = own.filter(function (s) { return (s.group || 'keys') === g[0]; });
			if (!list.length) { return; }
			ul.appendChild(el('li', { class: 'hsb-keyhead', text: t(g[1]) }));
			list.forEach(function (s) { ul.appendChild(keyLi(T(s.text), String(s.keys || ''))); });
		});
		if (own.length) { ul.appendChild(el('li', { class: 'hsb-keyhead', text: t('8776') })); }
		globalKeys().forEach(function (s) { ul.appendChild(keyLi(t(s.id), String(s.keys || ''))); });
		if (page && page.shortcutsNote) { ul.appendChild(el('li', { class: 'hsb-keynote' }, [el('span', null, [md(T(page.shortcutsNote))])])); }
		if (reg && reg.shortcuts) {
			const own2 = rowButton({ icon: false, title: t('8754'), onClick: function () { runRegistered('shortcuts'); } });
			ul.appendChild(el('li', null, [own2]));
		}
		return ul;
	}
	/** "Tastaturgenveje" as an expandable row: a search result for "genvej", "tast" … */
	function keysBlock(reg) {
		const wrap = el('div', { class: 'hsb-keys' });
		const listId = uid('hsk');
		const r = rowButton({ icon: icon('keys'), title: t('6024') });
		r.setAttribute('aria-expanded', 'false');
		r.setAttribute('aria-controls', listId);
		const ul = keysList(reg, listId);
		ul.hidden = true;
		r.addEventListener('click', function () { const open = ul.hidden; ul.hidden = !open; r.setAttribute('aria-expanded', open ? 'true' : 'false'); if (open) { reveal(ul, r); } });
		wrap.appendChild(r);
		wrap.appendChild(ul);
		return wrap;
	}
	/** The ask block's "Tastaturgenveje": the list under the button, open until the panel closes (S.keysOpen). */
	function fillKeys() {
		clear(D.keysBox);
		const ul = keysList(registry(), uid('hskl'));
		D.keysBox.appendChild(ul);
		setKeysOpen(S.keysOpen);
	}
	function setKeysOpen(open) {
		S.keysOpen = !!open;
		D.keysBox.hidden = !open;
		D.keysBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
	}
	/** The task the page's problem() names, when it exists: shown first, with its steps open (spec §18). */
	function problemId() {
		const page = P.page;
		if (!page || typeof page.problem !== 'function' || !page.tasks) { return ''; }
		const id = call(page.problem, [P.ctx, P.st], '', 'problem()');
		return typeof id === 'string' && page.tasks[id] ? id : '';
	}
	function fillProblem() {
		const id = problemId();
		S.behind = S.behind.filter(function (b) { return b.kind !== 'problem'; });
		clear(D.problemList);
		D.problem.hidden = !id;
		if (!id) { return; }
		const ul = el('ul', { class: 'hc-rows hc-tasks' });
		ul.appendChild(stepItem('task', id, P.page.tasks[id], { open: true, behindKind: 'problem' }));
		D.problemList.appendChild(ul);
	}
	/** "Lær siden at kende": open the first three times the panel shows a page, then folded, unless the user chose (spec §18). */
	const LEARN_KEY = 'saldi-help.learn.', SEEN_KEY = 'saldi-help.seen.';
	function learnRoute() { return P.ctx ? (P.id || P.ctx.route) : 'generic'; }
	function fillLearn() {
		const r = learnRoute();
		if (S.seenRoute !== r) {
			S.seenRoute = r;
			const n = (parseInt(lGet(SEEN_KEY + r) || '0', 10) || 0) + 1;
			lSet(SEEN_KEY + r, String(Math.min(n, 99)));
		}
		setLearnOpen(learnOpenFor(r), false);
		D.learn.hidden = D.about.hidden && D.tasks.hidden && D.more.hidden;
	}
	/** The user's own choice for the route, else open for the first three views. ahead: views not counted yet (the chat counts
	 *  its view when it shows the help, after the state was built). */
	function learnOpenFor(r, ahead) {
		const choice = lGet(LEARN_KEY + r);
		return choice === '1' ? true : (choice === '0' ? false : (parseInt(lGet(SEEN_KEY + r) || '0', 10) || 0) + (ahead || 0) <= 3);
	}
	function setLearnOpen(open, chosen) {
		D.learnBody.hidden = !open;
		D.learnBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (chosen) { lSet(LEARN_KEY + learnRoute(), open ? '1' : '0'); }
	}
	/** What lies under an overlay panel says so next to its Vis mig (prototype markBehind()). */
	function markBehind() {
		const on = S.open && !S.minimised && S.mode === 'overlay';
		let pr = null;
		if (on) { pr = host.getBoundingClientRect(); }
		S.behind = S.behind.filter(function (b) { return b.note.isConnected; });
		S.behind.forEach(function (b) {
			let under = false;
			if (on && b.node && visibleEl(b.node)) { try { under = covered(rectInTop(b.node), pr); } catch (e) { under = false; } }
			b.note.hidden = !under;
		});
		lockField();
	}
	function renderNotice() {
		clear(D.notice);
		const n = S.notice;
		D.notice.hidden = !n;
		if (!n) { return; }
		D.notice.appendChild(el('p', { text: n.text }));
		if (n.button) {
			const b = el('button', { type: 'button', class: 'hc-btn', text: n.button });
			b.addEventListener('click', function (e) { n.action(e); });
			D.notice.appendChild(b);
		}
	}
	function showNotice(text, o) {
		o = o || {};
		S.notice = text ? { text: text, button: o.button || '', action: o.action || function () { /* none */ } } : null;
		if (S.open) { renderNotice(); }
	}

	/* ================================================================ search / ask (spec §1.11) */
	function buildSearch() {
		const id = 'saldi-help-q';
		D.input = el('input', { type: 'search', class: 'hc-input', id: id, autocomplete: 'off', maxlength: '1000', placeholder: t('8788'), 'aria-controls': 'saldi-help-results' });
		D.send = el('button', { type: 'button', class: 'hc-search__send', 'aria-label': t('8792'), title: t('8792') }, [icon('send')]);
		D.send.addEventListener('click', function () { submitQuery(); });
		D.results = el('div', { class: 'hc-results', id: 'saldi-help-results', hidden: true });
		D.input.addEventListener('input', function () { S.q = D.input.value; S.sel = -1; renderResults(); applyView(); });
		D.input.addEventListener('keydown', onSearchKey);
		D.results.addEventListener('keydown', function (e) {
			if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') { return; }
			if (isTyping(e.target)) { return; }
			const items = resultRows();
			const i = items.indexOf(document.activeElement);
			if (i < 0) { return; }
			e.preventDefault();
			if (e.key === 'ArrowDown' && i < items.length - 1) { items[i + 1].focus(); } else if (e.key === 'ArrowUp') { if (i > 0) { items[i - 1].focus(); } else { D.input.focus(); } }
		});
		const box = el('div', { class: 'hc-search__box' }, [
			el('span', { class: 'hc-search__ic', 'aria-hidden': 'true' }, [icon('search')]),
			el('label', { class: 'hc-sr', for: id, text: t('8702') }),
			D.input,
			D.send
		]);
		return el('div', { class: 'hc-search', role: 'search' }, [box, D.results]);
	}
	function resultRows() { return Array.prototype.filter.call(D.results.querySelectorAll('.hc-row'), visibleEl); }
	function selectRow(i) {
		const rows = resultRows();
		rows.forEach(function (r, k) { if (k === i) { r.setAttribute('aria-selected', 'true'); } else { r.removeAttribute('aria-selected'); } });
		S.sel = i;
		if (i >= 0 && rows[i]) {
			if (!rows[i].id) { rows[i].id = uid('hsr'); }
			D.input.setAttribute('aria-activedescendant', rows[i].id);
			try { rows[i].scrollIntoView({ block: 'nearest' }); } catch (e) { /* ignore */ }
		} else {
			D.input.removeAttribute('aria-activedescendant');
		}
	}
	function onSearchKey(e) {
		if (e.isComposing) { return; }
		if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
			// ↑ ↓ move between the result rows; the caret in the input never moves
			e.preventDefault();
			const rows = resultRows();
			if (!rows.length) { return; }
			let i = S.sel;
			if (e.key === 'ArrowDown') { i = i < rows.length - 1 ? i + 1 : rows.length - 1; } else { i = i > 0 ? i - 1 : -1; }
			selectRow(i);
			return;
		}
		if (e.key === 'Enter') {
			e.preventDefault();
			submitQuery();
		}
	}
	/** Enter in the field or the send button: the selected hit, a person (support), else the question to Saldi AI. */
	function submitQuery() {
		const q = D.input.value.trim();
		if (!q) { try { D.input.focus({ preventScroll: true }); } catch (e) { /* ignore */ } return; }
		const rows = resultRows();
		if (S.sel >= 0 && rows[S.sel]) { rows[S.sel].click(); return; }
		if (contactRoute(q)) { openSupport({ form: true, keyboard: true }); return; }   // a person, not Saldi AI
		const last = D.results.querySelector('.hc-row--ask');
		if (last) { last.click(); } else { ask(q); }
	}
	function prefill(text) {
		D.input.value = text;
		S.q = text;
		S.sel = -1;
		renderResults();
		applyView();
		try { D.input.focus({ preventScroll: true }); D.input.setSelectionRange(text.length, text.length); } catch (e) { /* ignore */ }
	}
	function clearSearch() {
		D.input.value = '';
		S.q = '';
		S.sel = -1;
		D.input.removeAttribute('aria-activedescendant');
		clear(D.results);
		D.results.hidden = true;
		applyView();
	}
	/** The stop words for the page shown: the content's list less the page's ownWords, the words that name something on
	 *  that page (the CRM's view Mine), so they are searched there and stay stop words everywhere else. */
	function stopWords() {
		const c = content();
		const all = (c && c.stopWords && Array.isArray(c.stopWords[LANG])) ? c.stopWords[LANG] : (LANG === 'da' ? DEFAULT_STOP : []);
		const own = P.page && P.page.ownWords && Array.isArray(P.page.ownWords[LANG]) ? P.page.ownWords[LANG] : [];
		return own.length ? all.filter(function (w) { return own.indexOf(w) < 0; }) : all;
	}
	function contactRoute(q) {
		const c = content();
		const list = c && c.contactWords && Array.isArray(c.contactWords[LANG]) ? c.contactWords[LANG] : [];
		return words(q).some(function (w) { return list.indexOf(w) >= 0; });
	}
	/** How well the query word w hits the text word x: 1 the same word, .9 one starts the other, .8 a compound or the same
	 *  stem, 0 no hit. Both are normalised (norm(), which also folds é, so "simuler" finds "Simulér").
	 *  - a 2-letter word ("ny") only as the same word;
	 *  - w starts x ("moms" → "momskoden"): from 4 letters, a 3-letter w with at most 3 more, any w while it is the last
	 *    word being typed (prefix);
	 *  - x starts w ("afstem" → "afstemning", "bogfør" → "bogfører"): when x has at least 5 letters, or 4 with at most 3 more;
	 *  - w ends the compound x ("kladde" → "kassekladde", "konto" → "bankkonto"): from 5 letters;
	 *  - the same stem with an inflection ending ("bogfører" → "bogføres", "kunden" → "kunder"): at least 5 letters in common
	 *    and only an ending such as -e, -r, -t, -en, -er, -et after them, so "kontrolkonto" no longer hits "kontrolsum" or
	 *    "kontrollér", nor "control" "contra".
	 *  "kredit…" and "kreditor…" never hit each other. */
	function wordHit(w, x, prefix) {
		if (!w || !x) { return 0; }
		if (x === w) { return 1; }
		if (w.length < 3 || x.length < 3) { return 0; }
		const xk = /^kreditor/.test(x);
		if ((xk && !/^kredito/.test(w)) || (!xk && /^kreditor/.test(w))) { return 0; }
		if (x.indexOf(w) === 0) { return prefix || w.length >= 4 || x.length - w.length <= 3 ? 0.9 : 0; }
		if (w.indexOf(x) === 0) { return x.length >= 5 || (x.length >= 4 && w.length - x.length <= 3) ? 0.9 : 0; }
		if (w.length >= 5 && x.length > w.length && x.slice(-w.length) === w) { return 0.8; }
		let n = 0;
		const m = Math.min(w.length, x.length);
		while (n < m && w.charAt(n) === x.charAt(n)) { n += 1; }
		const end = /^(e|r|n|s|t|d|en|er|et|es|ne|re|te|de|ed)?$/;
		return n >= 5 && end.test(w.slice(n)) && end.test(x.slice(n)) ? 0.8 : 0;
	}
	/** Score of one candidate. parts = [[text, weight], …]: 3 for what names it (a task's title, a question and its search
	 *  words, a field's label), 2 for what describes it (a task's why and Vis mig label, a question's intro, a shortcut),
	 *  1.5 for a field's text and 1 for steps. Each query word counts with its best hit (weight × wordHit()); when every
	 *  word hits a part of weight 2 or more, the candidate gets 2 more, so a hit on what the thing is beats words spread
	 *  over its steps; and 1 more when the query names all of it (every word of its title, question or label). */
	function rate(parts, ws, o) {
		const toks = parts.map(function (p) { return { xs: norm(p[0]).split(' '), w: p[1] }; });
		let s = 0, all = true;
		ws.forEach(function (w, i) {
			const pre = !!(o.prefix && i === ws.length - 1);
			let best = 0, top = 0;
			toks.forEach(function (p) {
				let h = 0;
				p.xs.forEach(function (x) { const k = wordHit(w, x, pre); if (k > h) { h = k; } });
				if (h) { best = Math.max(best, p.w * h); top = Math.max(top, p.w); }
			});
			s += best;
			if (top < 2) { all = false; }
		});
		// the whole name typed ("Debet" over "D/K (Debet)"): every word of a weight-3 part, other than stop words, is hit
		const named = s > 0 && toks.some(function (p) {
			const xs = p.w === 3 ? p.xs.filter(function (x) { return x.length >= 2 && (o.stop || []).indexOf(x) < 0; }) : [];
			return xs.length > 0 && xs.every(function (x) { return ws.some(function (w) { return wordHit(w, x, false) >= 0.9; }); });
		});
		return s > 0 ? Math.round((s + (all ? 2 : 0) + (named ? 1 : 0)) * 100) / 100 : 0;
	}
	const HIT_KIND = { tour: 0, keys: 1, task: 2, q: 3, field: 4, key: 5 };
	/** Hits from the current page only (spec §1.11 as changed in review round 2): tasks, prepared questions (with their
	 *  steps), the page's fields, its shortcuts and codes, the tour and the keys row; max 3. Equal scores keep the page's
	 *  own order for its state (page.order() and questionOrder(), the field the user stood in), then the declared order. */
	function search(raw) {
		const q = String(raw || '').trim();
		const out = { contact: contactRoute(q), page: [] };
		const page = P.page;
		if (!page || !q) { return out; }
		const stop = stopWords();
		const ws = words(q).filter(function (w) { return stop.indexOf(w) < 0; });
		if (!ws.length) { return out; }
		const o = { prefix: !/\s$/.test(String(raw || '')), stop: stop };
		const add = function (kind, id, sc, rel, n) { if (sc > 0) { out.page.push({ kind: kind, id: id, score: sc, rel: rel, n: n }); } };
		const relOf = function (list, id, n) { const i = Array.isArray(list) ? list.indexOf(id) : -1; return i >= 0 ? i : 50 + n; };
		const steps = function (list, wt, parts) { (Array.isArray(list) ? list : []).forEach(function (k) { parts.push([T(k), wt]); }); return parts; };
		const order = call(page.order, [P.ctx, P.st], [], 'order()');
		Object.keys(page.tasks || {}).forEach(function (id, n) {
			const d = page.tasks[id];
			const parts = [[T(d.title), 3], [T(d.why), 2]];
			if (d.show && d.show.label) { parts.push([T(d.show.label), 2]); }
			add('task', id, rate(steps(d.steps, 1, parts), ws, o), relOf(order, id, n), n);
		});
		const qOrder = call(page.questionOrder, [P.ctx, P.st], [], 'questionOrder()');
		Object.keys(page.questions || {}).forEach(function (id, n) {
			const d = page.questions[id];
			const vocab = d.words && Array.isArray(d.words[LANG]) ? d.words[LANG] : [];
			const parts = [[T(d.q), 3], [vocab.join(' '), 3]];
			if (d.intro) { parts.push([T(d.intro), 2]); }
			add('q', id, rate(steps(d.steps, 1, parts), ws, o), relOf(qOrder, id, n), n);
		});
		const here = S.lastField && S.lastField.key;
		Object.keys(page.fields || {}).forEach(function (key, n) {
			const d = page.fields[key];
			add('field', key, rate([[T(d.label), 3], [T(d.text), 1.5]], ws, o), key === here ? 0 : 60 + n, n);
		});
		(Array.isArray(page.shortcuts) ? page.shortcuts : []).forEach(function (sc, n) {
			add('key', n, rate([[T(sc.text), 2], [String(sc.keys || ''), 2]], ws, o), 70 + n, n);
		});
		if (tourCount() > 0 && ws.some(function (w) { return /^(rundvis|tur|rundt|vis|tour|omvis)/.test(w); })) { add('tour', '', 5, 0, 0); }
		if (ws.some(function (w) { return /^(genvej|tast|shortcut|alt|snarvei|key)/.test(w); })) { add('keys', '', 5, 0, 0); }
		out.page.sort(function (a, b) { return b.score - a.score || a.rel - b.rel || HIT_KIND[a.kind] - HIT_KIND[b.kind] || a.n - b.n; });
		out.page = out.page.slice(0, 3);
		return out;
	}
	/** A shortcut or code of the page found by search: its text and its keys, no action. */
	function keyHit(sc) {
		return el('li', { class: 'hsb-keyhit' }, [
			el('span', { class: 'hc-row__ic', 'aria-hidden': 'true' }, [icon('keys')]),
			el('span', { class: 'hsb-keyhit__t' }, [md(T(sc.text))]),
			el('span', { class: 'hc-kbd', text: String(sc.keys || '') })
		]);
	}
	function renderResults() {
		clear(D.results);
		S.behind = S.behind.filter(function (b) { return b.kind !== 'hit'; });
		const q = S.q.trim();
		S.sel = -1;
		D.input.removeAttribute('aria-activedescendant');
		if (!q) { D.results.hidden = true; return; }
		const r = search(S.q);
		if (r.contact) { D.results.appendChild(el('div', { class: 'hc-contactcard' }, [supportCard({ compact: true })])); }
		if (r.page.length) {
			D.results.appendChild(el('p', { class: 'hc-results__grp', text: t('8748') }));
			const ul = el('ul', { class: 'hc-rows' });
			r.page.forEach(function (it) {
				if (it.kind === 'task') { ul.appendChild(stepItem('task', it.id, P.page.tasks[it.id], { hit: true })); }
				if (it.kind === 'q') { ul.appendChild(stepItem('q', it.id, P.page.questions[it.id], { icon: avatar(20), hit: true })); }
				if (it.kind === 'field') { ul.appendChild(stepItem('field', it.id, P.page.fields[it.id], { hit: true })); }
				if (it.kind === 'key') { ul.appendChild(keyHit(P.page.shortcuts[it.id])); }
				if (it.kind === 'tour') {
					const n = tourCount();
					ul.appendChild(el('li', null, [rowButton({ icon: icon('tour'), title: tf('8709', n), desc: t('8710'), onClick: function () { startTour(); } })]));
				}
				if (it.kind === 'keys') { ul.appendChild(el('li', null, [keysBlock(registry(), true)])); }
			});
			D.results.appendChild(ul);
		}
		// the last row is always the hand-off; the user's text goes in as text (spec §1.14)
		const askRow = rowButton({ icon: avatar(20), title: tf('8712', q), className: 'hc-row--ask', onClick: function () { ask(q); } });
		D.results.appendChild(el('ul', { class: 'hc-rows' }, [el('li', null, [askRow])]));
		D.results.hidden = false;
	}

	/* ================================================================ Spørg Saldi AI (spec §6) */
	function guideState() {
		const a = window.SaldiAssist;
		if (a && a.v2Ready) { return 'ask'; }                                       // Guide v2 listens for saldi:assist:ask
		if (window.SALDI_CHAT && typeof window.SALDI_CHAT.open === 'function') { return 'open'; }
		if (document.getElementById('saldi-chat-launcher')) { return 'open'; }
		return 'none';
	}
	function ask(question) {
		const q = String(question === null || question === undefined ? '' : question).trim();
		const g = guideState();
		if (g === 'none' && document.readyState === 'loading') {
			// the Guide loader is defer: it has run (or failed) by DOMContentLoaded; no timer, no polling
			document.addEventListener('DOMContentLoaded', function () { ask(q); }, { once: true });
			return 'none';
		}
		if (g === 'ask' && q !== '') {
			window.dispatchEvent(new CustomEvent('saldi:assist:ask', { detail: { question: q.slice(0, 1000) } }));
			handedOff(t('8738'), false);
			return 'sent';
		}
		if (g === 'ask' || g === 'open') {
			try { if (window.SaldiAssist && typeof topbarAssistContext === 'function') { window.SaldiAssist.pageContext = topbarAssistContext(); } } catch (e) { /* context is optional */ }
			try { if (typeof topbarOpenAssist === 'function') { topbarOpenAssist(); } } catch (e) { warnOnce('openAssist', 'topbarOpenAssist failed', e); }
			handedOff(q ? t('8739') : '', !!q);
			return 'opened';
		}
		if (!S.open) { open({ source: 'api', focus: false }); }
		showNotice(t('8740'), { button: t('8741'), action: function (e) { openSupport({ question: q, keyboard: !!(e && e.detail === 0) }); } });
		return 'none';
	}
	/** D8: an unpinned panel closes after a hand-off and the notice goes to a polite live region; pinned keeps it in the panel. */
	function handedOff(notice, keepSearch) {
		if (S.open && !S.pinned) {
			close({ restoreFocus: false, keepSearch: keepSearch });
			if (notice) { announce(notice); }
			return;
		}
		if (S.open) { showNotice(notice); } else if (notice) { announce(notice); }
	}
	function announce(text) {
		if (!D.sr) { return; }
		D.sr.textContent = '';
		D.sr.textContent = text;
	}

	/* ================================================================ Hjælp inside Saldi Assist (spec §19) */
	/** Merged mode, read at the time of use (the Guide loader is defer): the loader keeps the contract
	 *  (SaldiAssist.helpReady), the chat exists and the state builder loaded. Otherwise the panel works as before. */
	function merged() {
		const a = window.SaldiAssist, c = window.SALDI_CHAT, b = window.SaldiHelpState;
		return !H.dead && !!(a && a.helpReady === true && c && typeof c.open === 'function' && b && typeof b.build === 'function');
	}
	/** The loader's chat box: visible while the chat is open, data-layout docked|floating|fullscreen. Its own
	 *  SALDI_CHAT.isOpen() and layout() are read first when the loader has them; the box is the fallback. */
	function chatBox() { return document.getElementById('saldi-chat-container'); }
	function chatOpen() {
		const c = window.SALDI_CHAT;
		if (c && typeof c.isOpen === 'function') { try { return !!c.isOpen(); } catch (e) { /* the box below */ } }
		const b = chatBox();
		return !!b && b.style.visibility === 'visible';
	}
	function chatLayout() {
		const c = window.SALDI_CHAT;
		if (c && typeof c.layout === 'function') { try { const l = String(c.layout() || ''); if (l) { return l; } } catch (e) { /* the box below */ } }
		const b = chatBox();
		return b ? String(b.getAttribute('data-layout') || '') : '';
	}
	/** The chat lies over the page (floating or full screen) instead of standing beside it. */
	function chatOver() { return chatOpen() && chatLayout() !== 'docked'; }
	/** The open chat's box in top-window coordinates, or null. */
	function chatRect() {
		const b = chatBox();
		if (!b || !chatOpen()) { return null; }
		try { const r = b.getBoundingClientRect(); return r && r.width > 0 && r.height > 0 ? { left: r.left, top: r.top, right: r.right, bottom: r.bottom } : null; } catch (e) { return null; }
	}
	function focusChat() {
		const b = chatBox();
		const f = b && b.querySelector ? b.querySelector('iframe') : null;
		try { if (f && chatOpen()) { f.focus(); } } catch (e) { /* focus is a convenience */ }
	}
	/** The chat again after a press in the page: no help focus, so the press keeps its focus (as the panel's restore()). */
	function reopenQuiet() {
		pushState(false);
		const c = window.SALDI_CHAT;
		try { if (typeof c.show === 'function') { c.show(); } else { c.open(); } } catch (e) { warnOnce('chatOpen', 'SALDI_CHAT.open failed', e); }
	}
	function minimizeChat() {
		const c = window.SALDI_CHAT;
		if (c && typeof c.minimize === 'function') {
			try { c.minimize(); return; } catch (e) { warnOnce('chatMinimize', 'SALDI_CHAT.minimize failed', e); }
		}
		closeChat();
	}
	function closeChat() {
		try { if (typeof window.SALDI_CHAT.close === 'function') { window.SALDI_CHAT.close(); } } catch (e) { warnOnce('chatClose', 'SALDI_CHAT.close failed', e); }
	}
	/** The chat steps aside for Vis mig or a tour, to come back by the way back: the loader's hide() when it has one (the
	 *  widget is not told it closed, so an open bug card in it stays), else its close(). */
	function hideChat() {
		const c = window.SALDI_CHAT;
		if (c && typeof c.hide === 'function') {
			try { c.hide(); return; } catch (e) { warnOnce('chatHide', 'SALDI_CHAT.hide failed', e); }
		}
		closeChat();
	}
	/** A loader of contract v1.1 says whether its widget connected (SaldiAssist.helpConnected, a boolean); a v1 loader
	 *  does not. Only v1.1 takes field items in the search: a v1 widget refuses a whole state with them. */
	function v11() { const a = window.SaldiAssist; return !!a && typeof a.helpConnected === 'boolean'; }
	function stopTour() {
		const a = tourApiAny();
		try { if (a && typeof a.stop === 'function') { a.stop(); } } catch (e) { /* the page went away */ }
	}
	/** A view of the page in the chat, for "Lær siden at kende" (open the first three views, spec §18). */
	function countView(r) {
		if (H.seen === r) { return; }
		H.seen = r;
		const n = (parseInt(lGet(SEEN_KEY + r) || '0', 10) || 0) + 1;
		lSet(SEEN_KEY + r, String(Math.min(n, 99)));
	}
	function stateLabels() {
		return {
			helpButton: t('8793'), problem: t('8791'), field: t('8704'), learn: t('8790'), about: t('8703'), tasks: t('8706'),
			showMe: t('8707'), showMeFor: t('8708'), tour: t('8709'), tourDesc: t('8710'), shortcuts: t('6024'), fromHelp: t('8748'),
			// spec §19.9, additive
			back: t('8794'), support: t('8714'), call: t('8715'), write: t('8721'), askField: t('8705'), askThis: t('8763'),
			// spec §25, additive: the heading of the page's questions (%s: the state's title), the answer card's links
			questions: t('8825'), askMore: t('8826'), related: t('8827')
		};
	}
	/** The page's registered help that is not a SaldiTour (page_help(): a tour of its own, the guide, its own shortcuts),
	 *  as the panel's rows (fillMore(), keysList()). Spec §19.9. */
	function moreRows() {
		const reg = registry();
		const out = [];
		if (tourCount() <= 0 && reg.tour && reg.tour !== '#tutorial-help' && reg.tour !== 'saldi:tour') { out.push({ kind: 'tour', title: t('6455') }); }
		if (reg.guide) { out.push({ kind: 'guide', title: t('92') }); }
		if (reg.shortcuts) { out.push({ kind: 'shortcuts', title: t('8754') }); }
		return out;
	}
	/** The contact card's facts (supportCard()): the number as shown, the tel: number, the hours, open now, the mail. */
	function supportState() {
		const sup = supportData();
		const os = openStatus(sup);
		return { phone: phoneShown(sup), tel: sup.tel, hours: t('8716'), open: os.open, status: os.text, mail: sup.mail };
	}
	function contactList() {
		const c = content();
		return c && c.contactWords && Array.isArray(c.contactWords[LANG]) ? c.contactWords[LANG] : [];
	}
	/** The HelpState of the page in the frame (contract §5). A frame that cannot be read, or blank (a page that is going
	 *  away), gives route '' with the keys that work everywhere. view: the chat shows it now. */
	function buildState(view, blank) {
		if (!blank) { computeP(true); }   // starts loading the page group's texts; their arrival sends the state again
		const ctx = blank ? null : P.ctx;
		const r = learnRoute();
		if (ctx && !H.widgetSeen && (view || chatOpen())) { countView(r); }
		const f = ctx ? fieldInfo() : null;
		return window.SaldiHelpState.build({
			page: ctx ? P.page : null, ctx: ctx, st: P.st, lang: LANG, text: T, title: ctx && !P.page ? genericLabel() : '',
			field: f ? { key: f.key, label: f.label, text: f.text, canShow: !!(f.el && visibleEl(f.el)), ask: tf('8756', window.SaldiHelpState.plain(f.label)) } : null,
			fields: v11(), fieldTarget: fieldEl,
			tourCount: ctx ? tourCount() : 0, learnOpen: false,   // folded on every Hjælp: the user opens it when needed
			shortcuts: {
				// Esc closes the panel, not the chat, so the chat's list leaves it out
				global: globalKeys().filter(function (s) { return s.keys !== 'Esc'; }).map(function (s) { return { text: t(s.id), keys: String(s.keys || '') }; }),
				titles: { keys: t('8774'), codes: t('8775'), global: t('8776') }
			},
			labels: stateLabels(), stopWords: stopWords(), resolveShow: resolveShow, showLabel: showLabel,
			support: supportState(), contactWords: contactList(), more: ctx ? moreRows() : [],
			disclosure: { title: t('8736'), lines: [RECORDS ? t('8771') : t('8737'), t('8772')] },
			warn: function (what, e) { warnOnce('state:' + what, 'help state: ' + what + ' failed', e); }
		});
	}
	/** Hand the state to the loader when its JSON differs from the last one sent. */
	function sendState(state) {
		const json = JSON.stringify(state);
		if (json === H.json) { return; }
		H.json = json;
		H.state = JSON.parse(json);
		try { window.dispatchEvent(new CustomEvent('saldi:help:state', { detail: JSON.parse(json) })); } catch (e) { warnOnce('stateEvent', 'saldi:help:state failed', e); }
	}
	/** The state now, when it changed (the loader keeps the latest and hands it to the chat). */
	function pushState(view) {
		if (H.timer) { clearTimeout(H.timer); H.timer = 0; }
		if (!merged() || S.busy) { return; }   // a page that is loading sends its state when it is bound
		H.caught = true;
		let state;
		try { state = buildState(!!view, false); } catch (e) { warnOnce('state', 'the help state could not be built', e); return; }
		sendState(state);
	}
	/** The page in the frame is going away: the chat drops its help (and its Vis mig) until the next page is bound. */
	function pushBlank() {
		if (H.timer) { clearTimeout(H.timer); H.timer = 0; }
		if (!merged()) { return; }
		let state;
		try { state = buildState(false, true); } catch (e) { warnOnce('state', 'the help state could not be built', e); return; }
		sendState(state);
	}
	/** Page load, poolswitch, change, a field move, texts loaded: the state again, at most once per 150 ms. No polling. */
	function queueState() {
		if (!merged()) { return; }
		if (H.timer) { clearTimeout(H.timer); }
		H.timer = setTimeout(function () { H.timer = 0; pushState(false); }, STATE_MS);
	}
	/** States sent before the loader listened are lost: the first one goes out again once it has run. The topbar button
	 *  then says what it opens. */
	function catchUp() {
		if (!merged()) { return; }
		setTopbar();
		if (!H.caught) { pushState(false); }
	}
	/** A loader that says helpConnected === false (it connects on the widget's first saldi:ready and then sends
	 *  saldi:help:ready) gets READY_MS from an open; without it the chat is closed and the panel opens instead, until the
	 *  chat connects after all. A loader that does not say (contract v1) is trusted as before. */
	function watchReady() {
		const a = window.SaldiAssist;
		if (H.watch || !a || a.helpConnected !== false) { return; }
		H.watch = setTimeout(function () {
			H.watch = 0;
			const b = window.SaldiAssist;
			if (!b || b.helpConnected !== false || H.dead) { return; }
			warnOnce('chatDead', 'Saldi Assist did not connect within ' + READY_MS + ' ms: the help panel takes over');
			const waiting = chatOpen();
			if (H.back) { endBack(false); }
			H.dead = true;
			markNewChat();
			if (waiting) { closeChat(); }
			setTopbar();
			if (waiting && !S.open) { open({ source: 'api', keyboard: true, focus: 'search' }); }
		}, READY_MS);
	}
	function onHelpReady() {
		if (H.watch) { clearTimeout(H.watch); H.watch = 0; }
		if (!H.dead) { return; }
		H.dead = false;
		markNewChat();
		H.json = null;
		if (merged()) { setTopbar(); pushState(false); }
	}
	/** What every open of the chat by the host does first: the topbar's menus close, a failed text group is retried, the
	 *  signal is acknowledged, the state goes out (no debounce, a view of the page) and the chat gets the page's context. */
	function prepareChatOpen() {
		try { if (typeof topbarCloseAll === 'function') { topbarCloseAll(); } } catch (e) { /* the shell has no topbar */ }
		Object.keys(TX.state).forEach(function (g) { if (TX.state[g] === 'failed') { TX.state[g] = 'none'; } });   // retried on an open
		ackSignal();
		pushState(true);
		try { if (window.SaldiAssist && typeof topbarAssistContext === 'function') { window.SaldiAssist.pageContext = topbarAssistContext(); } } catch (e) { /* context is optional */ }
	}
	/** Open the chat on the page help (contract §2), the state first so the chat has it when it opens. */
	function openChatHelp(focus) {
		prepareChatOpen();
		const s = H.state;
		let f = focus === 'field' || focus === 'problem' ? focus : 'help';
		if ((f === 'field' && !(s && s.field)) || (f === 'problem' && !(s && s.problem))) { f = 'help'; }
		try { window.dispatchEvent(new CustomEvent('saldi:help:open', { detail: { focus: f } })); } catch (e) { warnOnce('openEvent', 'saldi:help:open failed', e); }
		watchReady();
	}
	/** Saldi Assist in the shell (spec §23): a loader that says SaldiAssist.shellVersion === 1 docks the chat under the
	 *  topbar and binds the topbar's "+ Ny chat". There the Hjælp button opens the chat where the user left off. */
	function shellResume() {
		const a = window.SaldiAssist;
		return merged() && !!a && a.shellVersion === 1;
	}
	/** Hjælp in the shell: the chat opens on its live conversation (or its start screen with the page help, when there is
	 *  none), without saldi:help:open, so the page help never covers a conversation. The state still goes out first. */
	function mergedResume() {
		H.seen = null;
		prepareChatOpen();
		try { window.SALDI_CHAT.open(); } catch (e) { warnOnce('chatOpen', 'SALDI_CHAT.open failed', e); }
		watchReady();
	}
	/** The Hjælp button in the shell is a toggle for the chat. A ring or tour shown with the chat stepped aside goes back to
	 *  the card the user came from, as before; a ring left from a docked Vis mig ends first, as mergedOpen() does. */
	function shellToggle() {
		if (H.back) { mergedOpen('help'); return; }
		if (S.ring) { endShowMe({ silent: true, noRestore: true }); }
		// Hjælp on an open chat minimises it (the conversation is kept, Hjælp brings it back); the chat's own close
		// button ends the conversation. A loader without minimize() closes, as before.
		if (chatOpen()) { minimizeChat(); } else { mergedResume(); }
	}
	/** "+ Ny chat" belongs to the loader, which alone writes its hidden. While the chat did not connect and the panel took
	 *  over (H.dead), Saldi hides it with its own attribute (css/topbar.css), so the two never undo each other. */
	function markNewChat() {
		let list = [];
		try { list = Array.prototype.slice.call(document.querySelectorAll('[data-saldi-assist-new]')); } catch (e) { list = []; }
		list.forEach(function (b) {
			if (H.dead && b.getAttribute('data-saldi-assist-new') === 'bound') { b.setAttribute('data-saldi-help-dead', ''); } else { b.removeAttribute('data-saldi-help-dead'); }
		});
	}
	/** The Hjælp button, ? and F1 in merged mode. A ring or tour shown with the chat closed ends through its way back.
	 *  Only this open is a new view of the page; the way back and a failed Vis mig are not. */
	function mergedOpen(focus) {
		if (H.back) { H.back.focus = focus; endRingOrTour(); return; }
		if (S.ring) { endShowMe({ silent: true, noRestore: true }); }
		if (!chatOpen()) { H.seen = null; }
		openChatHelp(focus);
	}
	/** What a showme target names on the page now: the element, its Vis mig label and kind, and where help reopens. */
	function helpTarget(target) {
		computeP(false);
		if (target === 'field') {
			const f = fieldInfo();
			return f && f.el && visibleEl(f.el) ? { el: f.el, label: f.label, kind: 'field', note: '', focus: 'field' } : null;
		}
		const m = /^(task|problem|q|field):(.+)$/.exec(target);
		const page = P.page;
		if (!m || !page) { return null; }
		if (m[1] === 'field') {
			// a field of the search (v1.1): its id maps back to the key as the builder made it
			const x = window.SaldiHelpState.fieldIds(page.fields).filter(function (y) { return y.id === m[2]; })[0];
			const d = x ? page.fields[x.key] : null;
			const n = d ? fieldEl(x.key) : null;
			return n ? { el: n, label: T(d.label), kind: 'field', note: '', focus: 'help' } : null;
		}
		const defs = m[1] === 'q' ? page.questions : page.tasks;
		const def = defs && Object.prototype.hasOwnProperty.call(defs, m[2]) ? defs[m[2]] : null;
		const show = def ? def.show : null;
		const n = resolveShow(show);
		if (!n) { return null; }
		return { el: n, label: showLabel(show, n), kind: show.kind || 'button', note: show.note ? T(show.note) : '', focus: m[1] === 'problem' ? 'problem' : 'help' };
	}
	/** Vis mig from the chat: the panel's ring and callout; a chat over the page closes first and the strip leads back. */
	function mergedShowMe(target) {
		if (S.busy) { return; }
		const h = helpTarget(target);
		if (!h) { queueState(); return; }   // the target went away: the chat gets canShow false
		if (H.back) { endBack(false); }
		const over = chatOver();
		const rect = over ? chatRect() : null;   // where it stood: a press outside it brings it back (endBack())
		if (over) { hideChat(); }
		if (S.tour) { stopTour(); }
		if (!showMe(h.el, { label: h.label, kind: h.kind, note: h.note })) { if (over) { openChatHelp(h.focus); } return; }
		H.fromChat = !over && chatOpen();
		if (over) { showBack(h.label, h.focus, rect); }
	}
	/** Rundvisning from the chat: SaldiTour.start(); a chat over the page closes, and the strip shows once the tour has started. */
	function mergedTour() {
		if (S.busy) { return; }
		computeP(false);
		const a = tourApi();
		if (!a || tourCount() <= 0) { queueState(); return; }
		if (H.back) { endBack(false); }
		const over = chatOver();             // a tour moves over the whole page: any chat over it closes
		H.backRect = over ? chatRect() : null;
		if (over) { hideChat(); }
		if (S.ring) { endShowMe({ silent: true, noRestore: true }); }
		closePageList(fwin());
		let ok = false;
		H.backTour = over ? 'help' : null;   // onTour('start') shows the strip; an earlier tour's 'end' comes first
		H.fromChat = !over && chatOpen();
		try { ok = a.start({ focus: true }) !== false; } catch (e) { warnOnce('tour', 'SaldiTour.start failed', e); }
		if (!ok) { H.backTour = null; H.backRect = null; H.fromChat = false; if (over) { openChatHelp('help'); } }
	}
	/** saldi:help:action from the chat (contract §3); anything else is ignored. No action carries user text. */
	function onHelpAction(d) {
		if (!merged() || !d || typeof d !== 'object') { return; }
		if (d.action === 'showme') {
			if (typeof d.target === 'string' && d.target.length <= 64 && HELP_TARGET.test(d.target)) { mergedShowMe(d.target); }
		} else if (d.action === 'tour') {
			mergedTour();
		} else if (d.action === 'learn' && typeof d.open === 'boolean') {
			computeP(false);
			if (P.ctx) { lSet(LEARN_KEY + learnRoute(), d.open ? '1' : '0'); }
			queueState();
		} else if (d.action === 'run' && typeof d.kind === 'string') {
			mergedRun(d.kind);
		} else if (d.action === 'back') {
			mergedBack();
		} else if (d.action === 'seen') {
			// the chat shows the page help now (start screen or sheet): that is the view, not the host's open (spec §19.9)
			// the chat sends it once per page and opening; only the first may report the view the host counted for this open
			computeP(false);
			if (!P.ctx) { return; }
			if (H.widgetSeen) { H.seen = null; }
			H.widgetSeen = true;
			countView(learnRoute());
		}
	}
	/** run (spec §19.9): the page's own tour, guide or shortcuts, as the panel's rows; a chat over the page closes first. */
	function mergedRun(kind) {
		if (S.busy) { return; }
		computeP(false);
		if (!moreRows().some(function (m) { return m.kind === kind; })) { queueState(); return; }
		if (H.back) { endBack(false); }
		if (S.ring) { endShowMe({ silent: true, noRestore: true }); }
		if (chatOver()) { closeChat(); }
		runRegistered(kind);
	}
	/** back (spec §19.9, Esc in the chat after Hjælp, ? or F1): back to the field the user stood in, with its caret; a chat
	 *  over the page closes. As the panel's Esc (8751). */
	function mergedBack() {
		const f = S.lastField;
		if (chatOver()) { closeChat(); }
		if (f && f.el && visibleEl(f.el)) { refocus({ el: f.el, start: f.start, end: f.end }); }
	}
	/** The user opened the chat themselves (its launcher) while a ring or tour showed with it closed: those end quietly. */
	function chatReopened() {
		if (!H.back || !chatOpen()) { return; }
		if (S.ring) { endShowMe({ silent: true, noRestore: true }); }
		endBack(false);
		if (S.tour) { stopTour(); }
	}
	/** While the way back is shown: the loader's saldi:chat:open when it has one, else its box's style (visibility). */
	function watchChat(on) {
		if (H.obs) { try { H.obs.disconnect(); } catch (e) { /* ignore */ } H.obs = null; }
		const b = chatBox();
		if (!on || !b || typeof window.MutationObserver !== 'function') { return; }
		try {
			H.obs = new window.MutationObserver(chatReopened);
			H.obs.observe(b, { attributes: true, attributeFilter: ['style'] });
		} catch (e) { H.obs = null; }
	}
	/** The panel's strip alone, "Viser: <label> · Tilbage til hjælpen", while the chat is closed for a ring or a tour.
	 *  rect: where the chat stood, so a press outside it brings it back (backAgain()). */
	function showBack(label, focus, rect) {
		if (S.open) { return; }   // the panel has its own strip
		cancelLeave();
		H.back = { focus: focus || 'help', rect: rect || null };
		S.mode = mq('(max-width: 600px)') ? 'sheet' : 'overlay';
		host.setAttribute('data-mode', S.mode);
		host.removeAttribute('role');
		host.removeAttribute('aria-modal');
		stripLabel(label);
		host.classList.add('is-min');
		host.hidden = false;
		setPos(S.mode === 'sheet' ? 't' : 'tr');
		motionCheck();
		schedule();
		watchChat(true);
	}
	function backLayout() {
		if (!H.back || S.open) { return; }
		const m = mq('(max-width: 600px)') ? 'sheet' : 'overlay';
		if (m !== S.mode) { S.mode = m; host.setAttribute('data-mode', m); setPos(m === 'sheet' ? 't' : 'tr'); }
	}
	/** The strip goes; reopen: back to the chat, on the card the user came from. pressed: a press in the page ended it, and
	 *  the chat comes back without focus unless the press was under it or into a field (the panel's restore(), §1.8). */
	function endBack(reopen, pressed) {
		const b = H.back;
		if (!b) { return; }
		H.back = null;
		watchChat(false);
		if (!S.open) { finishClose(); S.pos = null; }
		motionCheck();
		if (reopen) { openChatHelp(b.focus); } else if (pressed && pressAside(b.rect)) { reopenQuiet(); }
	}
	/** How a ring ended: by the callout, the strip, Esc or the API (back to the chat), or by a press, a reload or a new ring. */
	function backAgain(o) { return !(o.silent || o.noAside || o.pressed); }
	/** The last press in the page lay outside where the chat stood, and not in a field the user now types in. */
	function pressAside(rect) {
		const d = S.lastDown;
		const n = d && d.press && Date.now() - d.t < 800 ? d.el : null;
		if (!rect || !n || n.nodeType !== 1 || isTyping(n) || !visibleEl(n)) { return false; }
		try { return !hit(rectInTop(n), rect); } catch (e) { return false; }
	}

	/* ================================================================ support (spec §1.9) */
	function copenhagenNow(tz) {
		let parts = {};
		try {
			new Intl.DateTimeFormat('en-GB', { timeZone: tz, weekday: 'short', hour: '2-digit', minute: '2-digit', hour12: false })
				.formatToParts(new Date()).forEach(function (p) { parts[p.type] = p.value; });
		} catch (e) {
			const d = new Date();
			parts = { weekday: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][d.getDay()], hour: String(d.getHours()), minute: String(d.getMinutes()) };
		}
		return { day: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(parts.weekday), h: parseInt(parts.hour, 10) % 24 };
	}
	/** Open or closed in Europe/Copenhagen (public holidays are not known). Never green: blue when open, grey when closed. */
	function openStatus(sup) {
		const n = copenhagenNow(sup.tz), o = sup.openHour, c = sup.closeHour, weekday = n.day >= 1 && n.day <= 5;
		if (weekday && n.h >= o && n.h < c) { return { open: true, text: tf('8717', c) }; }
		if (weekday && n.h < o) { return { open: false, text: tf('8718', o) }; }
		if ((n.day >= 1 && n.day <= 4) || n.day === 0) { return { open: false, text: tf('8719', o) }; }
		return { open: false, text: tf('8720', o) };
	}
	/** The frame's address without inframe=1: href to show, path to send (it must start with /). */
	function pageAddress() {
		if (route() === null) { return { href: '', path: '' }; }
		try {
			const u = new URL(fwin().location.href);
			u.searchParams.delete('inframe');
			return { href: u.href, path: (u.pathname + u.search).slice(0, 500) };
		} catch (e) { return { href: '', path: '' }; }
	}
	function currentLabel() { try { return pageName() || ''; } catch (e) { return ''; } }
	function mailtoHref(mail, question) {
		const a = pageAddress(), label = currentLabel();
		const subject = 'Saldi ' + t('2564') + (label ? ': ' + label : '');
		const lines = [];
		if (question) { lines.push(question, ''); }
		if (a.href) { lines.push(t('8724') + ': ' + a.href); }
		return 'mailto:' + mail + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(lines.join('\r\n'));
	}
	function supportLine(kids) { return el('div', { class: 'hc-support__line' }, kids); }
	/** The contact card: phone, opening status, mail, and "Skriv til os" (a form when a recipient is configured, else a mail link, D9). */
	function supportCard(o) {
		o = o || {};
		const sup = supportData();
		const card = el('div', { class: 'hc-support' });
		if (!o.compact) { card.appendChild(el('h3', { class: 'hc-sec', text: t('8714') })); }
		card.appendChild(supportLine([icon('phone'), el('a', { href: 'tel:' + sup.tel, text: tf('8715', phoneShown(sup)) }), el('span', { class: 'hc-muted', text: '· ' + t('8716') })]));
		const os = openStatus(sup);
		card.appendChild(el('div', { class: 'hc-open', 'data-open': os.open ? 'true' : 'false' }, [el('span', { class: 'hc-open__dot', 'aria-hidden': 'true' }), os.text]));
		if (!SUPPORT_SEND) {
			const question = o.question || '';
			const a = el('a', { href: mailtoHref(sup.mail, question), text: tf('8757', sup.mail) });
			a.addEventListener('click', function () { a.setAttribute('href', mailtoHref(sup.mail, question)); });
			card.appendChild(supportLine([icon('mail'), a]));
			return card;
		}
		if (!o.compact) { card.appendChild(supportLine([icon('mail'), el('a', { href: 'mailto:' + sup.mail, text: sup.mail })])); }
		const formId = uid('hcs');
		const write = el('button', { type: 'button', class: 'hc-btn', 'aria-expanded': 'false', 'aria-controls': formId, text: t('8721') });
		const writeWrap = el('div', { class: 'hc-support__write' }, [write]);
		card.appendChild(writeWrap);
		if (o.compact) {
			write.addEventListener('click', function (e) { openSupport({ form: true, keyboard: e.detail === 0 }); });
			return card;
		}
		const taId = formId + '-m';
		const ta = el('textarea', { class: 'hc-ta', id: taId, rows: '3', maxlength: '2000' });
		if (o.question) { ta.value = o.question; }
		const addr = el('small');
		const chk = el('input', { type: 'checkbox', checked: true });
		const send = el('button', { type: 'button', class: 'hc-btn hc-btn--primary', text: t('8725') });
		const err = el('p', { class: 'hc-form__err', role: 'status', hidden: true, text: t('8727') });
		const form = el('div', { class: 'hc-form', id: formId, hidden: true }, [
			el('label', { class: 'hc-lbl', for: taId, text: t('8722') }),
			ta,
			el('fieldset', { class: 'hc-attach' }, [
				el('legend', { text: t('8723') }),
				el('label', { class: 'hc-check' }, [chk, el('span', null, [t('8724'), addr])])
			]),
			el('div', null, [send]),
			err
		]);
		const receipt = el('p', { class: 'hc-receipt', role: 'status', hidden: true, text: t('8726') });
		card.appendChild(form);
		card.appendChild(receipt);
		D.supportAddr = addr;
		D.supportTa = ta;
		D.supportForm = form;
		D.supportWrite = write;
		refreshAddress();
		function openForm() { form.hidden = false; write.setAttribute('aria-expanded', 'true'); writeWrap.hidden = true; }
		write.addEventListener('click', function () { openForm(); try { ta.focus({ preventScroll: true }); } catch (e) { /* ignore */ } });
		send.addEventListener('click', function () {
			const msg = ta.value.trim();
			if (!msg) { ta.focus(); return; }
			send.disabled = true;
			err.hidden = true;
			const body = new URLSearchParams();
			body.set('message', msg.slice(0, 2000));
			const a = pageAddress();
			if (chk.checked && a.path) { body.set('page', a.path); }
			body.set('page_label', currentLabel().slice(0, 120));
			fetch('../index/helpSupport.php', {
				method: 'POST', credentials: 'same-origin',
				headers: { 'X-CSRF-Token': String(window.SALDI_CSRF || ''), 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', Accept: 'application/json' },
				body: body.toString()
			})
				.then(function (r) { return r.json().catch(function () { return null; }); })
				.then(function (j) {
					if (!j || j.ok !== true) { throw new Error(j && j.error ? j.error : 'send'); }
					form.hidden = true;
					receipt.hidden = false;
					receipt.setAttribute('tabindex', '-1');
					try { receipt.focus({ preventScroll: true }); } catch (e) { /* ignore */ }
				})
				.catch(function () { send.disabled = false; err.hidden = false; });
		});
		if (o.form || o.question) { openForm(); }
		return card;
	}
	function refreshAddress() {
		if (!D.supportAddr || !D.supportAddr.isConnected) { return; }
		D.supportAddr.textContent = pageAddress().href;
	}
	/** The support sub-page keeps its card (and a typed draft) unless something new must go in it (prototype fillSupport()). */
	function ensureSupportCard(o) {
		o = o || {};
		const card = D.supportCard.querySelector('.hc-support');
		const sent = card && card.querySelector('.hc-receipt:not([hidden])');
		if (card && !sent && !o.question) {
			if (o.form && D.supportForm && D.supportForm.hidden && D.supportWrite) { D.supportWrite.click(); }
			refreshAddress();
			return;
		}
		clear(D.supportCard);
		D.supportCard.appendChild(supportCard({ question: o.question || '', form: !!o.form }));
	}
	function openSupport(o) {
		o = o || {};
		if (!S.open) { open({ source: 'api', view: 'support', support: o, keyboard: !!o.keyboard }); return; }
		setView('support', { support: o, keyboard: !!o.keyboard });
	}
	function setView(view, o) {
		o = o || {};
		if (S.minimised) { endRingOrTour(); }
		S.view = view;
		if (view === 'support') { ensureSupportCard(o.support || {}); }
		applyView();
		D.body.scrollTop = 0;
		focusInto({ keyboard: o.keyboard });
	}

	/* ================================================================ open / close / minimise (spec §1.12, §3.1) */
	function computeReturn(o) {
		const f = S.lastField;
		if (f && f.el && f.el.isConnected) {
			const d = f.el.ownerDocument;
			if (d.activeElement === f.el) { const k = caretOf(f.el); f.start = k.start; f.end = k.end; }
			return { el: f.el, start: f.start, end: f.end };
		}
		if (document.activeElement === frame()) {
			const d = fdoc();
			const a = d ? d.activeElement : null;
			if (a && a.nodeType === 1 && a !== d.body && a !== d.documentElement) { const k = caretOf(a); return { el: a, start: k.start, end: k.end }; }
		}
		return { el: o.trigger || topBtn(), start: null, end: null };
	}
	function refocus(r) {
		if (!r || !r.el || !r.el.isConnected) { return false; }
		try {
			const w = inFrame(r.el) ? r.el.ownerDocument.defaultView : null;
			if (w) { w.focus(); }
			// The kassekladde's account lookup opens its list on every focus that is not a keyboard move
			// (javascript/accountAutocomplete.js), so a plain focus() here would open it by itself. The page's own
			// focusAccountQuietly() focuses the field as a Tab into it does; it scrolls only when the field is out of view.
			if (w && r.el.tagName === 'INPUT' && typeof w.focusAccountQuietly === 'function') { w.focusAccountQuietly(r.el); } else { r.el.focus({ preventScroll: true }); }
		} catch (e) { return false; }
		if (r.start !== null && r.start !== undefined && typeof r.el.setSelectionRange === 'function') {
			try { r.el.setSelectionRange(r.start, r.end); } catch (e) { /* not a text field */ }
		}
		return true;
	}
	function restoreFocus() {
		const back = S.lastPage && visibleEl(S.lastPage.el) ? S.lastPage : S.returnTo;
		S.returnTo = null;
		if (!refocus(back)) { const b = topBtn(); if (b) { try { b.focus({ preventScroll: true }); } catch (e) { /* ignore */ } } }
	}
	function focusInto(o) {
		o = o || {};
		if (o.focus === false || !S.open) { return; }
		let target = null;
		if (S.view === 'support') {
			// the message field, else "Skriv til os", else the mail link, else the first control of the card
			['textarea', '.hc-support__write button', 'a[href^="mailto:"]', 'button, a[href]'].some(function (sel) {
				target = Array.prototype.find.call(D.supportCard.querySelectorAll(sel), visibleEl) || null;
				return !!target;
			});
		} else if (coarse() && !o.keyboard) {
			target = host;     // no on-screen keyboard on a touch open
		} else {
			target = D.input;
		}
		if (!target) { target = host; }
		try { target.focus({ preventScroll: true }); } catch (e) { /* ignore */ }
		if (target === D.input && D.input.value) { try { D.input.setSelectionRange(D.input.value.length, D.input.value.length); } catch (e) { /* ignore */ } }
	}
	function setTopbar() {
		const b = topBtn();
		if (!b) { return; }
		if (merged() && !S.open) {
			// the button opens the chat (spec §19): it controls no panel here, and nothing is pinned. A loader that says
			// whether the chat is open (isOpen(), with saldi:chat:open and saldi:chat:close) lets it point at the chat;
			// without that the open state would go stale, so the button says nothing about it.
			const c = window.SALDI_CHAT;
			if (c && typeof c.isOpen === 'function' && chatBox()) {
				b.setAttribute('aria-controls', 'saldi-chat-container');
				b.setAttribute('aria-expanded', chatOpen() ? 'true' : 'false');
			} else {
				b.removeAttribute('aria-controls');
				b.removeAttribute('aria-expanded');
			}
			b.setAttribute('aria-haspopup', 'dialog');
			b.setAttribute('title', t('2564') + ' (?)');
			return;
		}
		b.setAttribute('aria-controls', 'saldi-help');
		b.removeAttribute('aria-haspopup');
		b.setAttribute('aria-expanded', S.open ? 'true' : 'false');
		b.setAttribute('title', (S.pinned ? t('8755') : t('2564')) + ' (?)');
	}
	function cancelLeave() {
		if (!S.leaveT) { return; }
		clearTimeout(S.leaveT);
		S.leaveT = 0;
		host.classList.remove('hsb-leave');
	}
	function finishClose() {
		S.leaveT = 0;
		host.hidden = true;
		host.classList.remove('hsb-leave', 'hsb-enter', 'is-min');
		host.removeAttribute('data-pos');
		host.removeAttribute('data-mode');
		host.removeAttribute('role');
		host.removeAttribute('aria-modal');
		host.style.transform = '';
		S.mode = null;
	}
	function open(opts) {
		const o = opts || {};
		try { if (typeof topbarCloseAll === 'function') { topbarCloseAll(); } } catch (e) { /* the shell has no topbar */ }
		if (S.ring && !o.keepShowMe) { endShowMe({ silent: true }); }
		if (S.minimised) { restore({ noAside: true }); }
		const was = S.open;
		if (!was) {
			cancelLeave();
			Object.keys(TX.state).forEach(function (g) { if (TX.state[g] === 'failed') { TX.state[g] = 'none'; } });
			S.returnTo = computeReturn(o);
			S.lastPage = null;
			S.fieldMinH = 0;
			S.expanded = {};
			S.keysOpen = false;
			S.seenRoute = null;
			S.notice = null;
			S.view = 'home';
			lastOrder = '';
			S.fieldFirst = !!o.fromField && !!(S.lastField && S.lastField.el);   // F1 from a field: its help comes first
			S.open = true;
			host.hidden = false;
			if (!reduced() && !o.noAnim) { host.classList.remove('hsb-enter'); void host.offsetWidth; host.classList.add('hsb-enter'); }
			observeLayout(true);
		}
		if (o.view) { S.view = o.view; }
		if (o.view === 'support') { S.supportOpts = o.support || {}; }
		ackSignal();
		render();
		setTopbar();
		if (!was) { fly(D.seat, { instant: !!o.keyboard || !!o.noAnim }); }
		if (typeof o.prefill === 'string') { prefill(o.prefill); }
		focusInto({ keyboard: !!o.keyboard, focus: o.focus === false ? false : (typeof o.prefill === 'string' ? false : o.focus) });
		sSet(OPEN_KEY, S.pinned ? '1' : null);
	}
	function close(opts) {
		const o = opts || {};
		if (!S.open) { return; }
		if (S.ring) { endShowMe({ silent: true, noRestore: true }); }
		const from = ctr(M_.at);
		S.open = false;
		S.minimised = false;
		S.fullRect = null;
		S.notice = null;
		unbindMotion();
		observeLayout(false);
		host.classList.remove('is-min');
		host.removeAttribute('data-pos');
		if (!o.keepSearch) { clearSearch(); }
		if (!o.instant && !reduced() && !host.hidden) {
			host.classList.remove('hsb-enter');
			host.classList.add('hsb-leave');
			S.leaveT = setTimeout(finishClose, LEAVE_MS);
		} else {
			cancelLeave();
			finishClose();
		}
		setTopbar();
		fly(M_.top, { from: from, instant: !!o.instant });
		sSet(OPEN_KEY, null);
		if (o.restoreFocus !== false) { restoreFocus(); }
	}
	function toggle(o) {
		if (!S.open && o && o.source === 'topbar' && shellResume()) { shellToggle(); return; }   // Hjælp in the shell (spec §23)
		if (!S.open && merged()) { mergedOpen('help'); return; }   // the topbar button and the classic Hjælp cell (spec §19)
		if (S.open && !S.minimised) { close(o); } else if (S.open && S.minimised) { endRingOrTour(); } else { open(o); }
	}
	function stripLabel(label) {
		clear(D.stripTxt);
		const parts = t('8729').split('%s');
		D.stripTxt.appendChild(document.createTextNode(parts[0] || ''));
		D.stripTxt.appendChild(el('b', { text: label }));
		if (parts.length > 1) { D.stripTxt.appendChild(document.createTextNode(parts.slice(1).join(''))); }
	}
	function setPos(p) { S.pos = p; host.setAttribute('data-pos', p); }
	/** While Vis mig or a tour is shown, the panel shrinks to the 44 px strip (spec §1.8). */
	function minimise(label) {
		if (!S.open) { return; }
		if (!S.minimised) { S.fullRect = host.getBoundingClientRect(); }
		S.minimised = true;
		stripLabel(label);
		host.classList.add('is-min');
		setPos(S.mode === 'sheet' ? 't' : 'tr');
		fly(M_.top, { instant: true });
		motionCheck();
		schedule();
	}
	/** Where focus (or the last press) is in the page, when the full panel would cover it again (prototype pageSpotUnderPanel()). */
	function pageSpotUnderPanel() {
		if (S.mode === 'docked' || !S.fullRect) { return null; }
		const d = fdoc();
		if (!d) { return null; }
		let n = null;
		if (document.activeElement === frame() && isField(d.activeElement)) { n = d.activeElement; }
		if (!n && S.lastDown && Date.now() - S.lastDown.t < 800 && S.lastDown.el && S.lastDown.el.ownerDocument === d) { n = S.lastDown.el; }
		if (!n || !visibleEl(n)) { return null; }
		let r;
		try { r = rectInTop(n); } catch (e) { return null; }
		if (!hit(r, S.fullRect)) { return null; }
		const k = caretOf(n);
		return { el: n, start: k.start, end: k.end };
	}
	function restore(o) {
		o = o || {};
		if (!S.minimised) { return; }
		S.minimised = false;
		host.classList.remove('is-min');
		host.removeAttribute('data-pos');
		motionCheck();
		const keep = o.noAside ? null : pageSpotUnderPanel();
		S.fullRect = null;
		if (keep) {
			// the user is now in a page spot the panel would cover: help steps aside, the caret stays
			close({ restoreFocus: false, instant: true });
			refocus(keep);
			return;
		}
		render();
		fly(D.seat, { instant: true });
	}
	function endRingOrTour() {
		if (S.ring) { endShowMe({ restoreScroll: false }); return; }
		if (S.tour) {
			const a = tourApiAny();
			if (a && typeof a.stop === 'function') { try { a.stop(); } catch (e) { /* ignore */ } } else { S.tour = false; if (H.back) { endBack(true); } else { restore(); } }
			return;
		}
		if (H.back) { endBack(true); return; }
		restore();
	}
	function tourApiAny() { try { const w = fwin(); return w && w.SaldiTour ? w.SaldiTour : null; } catch (e) { return null; } }
	/** The user asked for a ring: a lookup list the page left open (the kassekladde's account list) would lie next to it.
	 *  closeAccountAutocomplete() only hides that transient list (javascript/accountAutocomplete.js). */
	function closePageList(w) {
		try { if (w && typeof w.closeAccountAutocomplete === 'function') { w.closeAccountAutocomplete(); } } catch (e) { /* ignore */ }
	}
	function startTour() {
		const a = tourApi();
		if (!a || S.busy) { return; }
		closePageList(fwin());
		try { a.start({ focus: true }); } catch (e) { warnOnce('tour', 'SaldiTour.start failed', e); }
	}

	/* ================================================================ docking (spec §1.4) */
	function decide() {
		if (mq('(max-width: 600px)')) { return 'sheet'; }
		const hc = homeContent();
		if (!hc) { return 'overlay'; }
		return hc.clientWidth - W >= DOCK_ROOM ? 'docked' : 'overlay';
	}
	function layout() {
		if (!S.open) { return; }
		const mode = decide();
		if (mode !== S.mode) {
			S.mode = mode;
			host.setAttribute('data-mode', mode);
			if (mode === 'docked') { host.setAttribute('role', 'region'); host.removeAttribute('aria-modal'); } else { host.setAttribute('role', 'dialog'); host.setAttribute('aria-modal', 'false'); }
			if (S.minimised) { setPos(mode === 'sheet' ? 't' : 'tr'); }
		}
		renderPin();
		if (!S.minimised) { markBehind(); }
	}
	function renderPin() {
		D.pin.setAttribute('aria-pressed', S.pinned ? 'true' : 'false');
		D.pinTxt.textContent = S.pinned ? t('8731') : t('8730');
		D.pin.setAttribute('title', S.pinned ? (S.mode === 'overlay' ? t('8734') : t('8733')) : t('8732'));
		setTopbar();
	}
	function togglePin() {
		S.pinned = !S.pinned;
		lSet(PIN_KEY, S.pinned ? '1' : null);
		sSet(OPEN_KEY, S.pinned && S.open ? '1' : null);
		renderPin();
	}
	let layoutRO = null;
	function observeLayout(on) {
		if (!window.ResizeObserver) { return; }
		if (!on) { if (layoutRO) { layoutRO.disconnect(); layoutRO = null; } return; }
		if (layoutRO) { return; }
		const hc = homeContent();
		if (!hc) { return; }
		layoutRO = new ResizeObserver(function () { layout(); if (S.ring || S.minimised) { schedule(); } });
		layoutRO.observe(hc);
	}

	/* ================================================================ Vis mig, drawn inside the frame (spec §1.7) */
	const LAYER_CSS = '#saldi-help-ring{position:fixed;z-index:100000;display:none;box-sizing:border-box;margin:0;padding:0;pointer-events:none;border:2px solid #4FA0EE;border-radius:6px;box-shadow:0 0 0 3px rgba(79,160,238,.18)}\n' +
		'#saldi-help-ring[data-on="true"]{display:block}\n' +
		'#saldi-help-callout{position:fixed;z-index:100010;display:none;box-sizing:border-box;width:320px;max-width:calc(100vw - 16px);margin:0;padding:12px 14px;background:#fff;color:#0b1a3a;border:1px solid #e4e8ef;border-radius:8px;box-shadow:0 10px 34px rgba(16,30,60,.16);font:13px/1.45 Inter,"Segoe UI",system-ui,-apple-system,Arial,sans-serif;text-align:left;letter-spacing:normal;text-transform:none}\n' +
		'#saldi-help-callout[data-on="true"]{display:block}\n' +
		'#saldi-help-callout *{box-sizing:border-box;font-family:inherit}\n' +
		'#saldi-help-callout .shc-head{display:flex;align-items:center;gap:8px;margin:0 0 6px}\n' +
		'#saldi-help-callout .shc-step{flex:1 1 auto;font-size:12px;font-weight:600;color:#5b6b85}\n' +
		'#saldi-help-callout .shc-x{width:28px;height:28px;padding:0;border:0;border-radius:4px;background:none;color:#5b6b85;font-size:18px;line-height:1;cursor:pointer}\n' +
		'#saldi-help-callout .shc-x:hover{background:#eef2f8;color:#0b1a3a}\n' +
		'#saldi-help-callout p{margin:0}\n' +
		'#saldi-help-callout .shc-foot{display:flex;justify-content:flex-end;margin-top:12px}\n' +
		'#saldi-help-callout .shc-ok{height:28px;padding:0 12px;border:1px solid #114691;border-radius:4px;background:#114691;color:#fff;font-size:12.5px;font-weight:500;cursor:pointer}\n' +
		'#saldi-help-callout .shc-ok:hover{background:#0e3a78}\n' +
		'#saldi-help-callout button:focus-visible{outline:2px solid #1B68BD;outline-offset:2px}\n' +
		'@media print{#saldi-help-ring,#saldi-help-callout{display:none!important}}\n';
	/** The ring and the callout, injected once per frame document (they die with it). */
	function ensureLayer(doc) {
		if (!doc.getElementById('saldi-help-layer-style')) {
			const st = doc.createElement('style');
			st.id = 'saldi-help-layer-style';
			st.textContent = LAYER_CSS;
			(doc.head || doc.documentElement).appendChild(st);
		}
		let ring = doc.getElementById('saldi-help-ring');
		if (!ring) {
			ring = doc.createElement('div');
			ring.id = 'saldi-help-ring';
			ring.setAttribute('aria-hidden', 'true');
			doc.body.appendChild(ring);
		}
		let card = doc.getElementById('saldi-help-callout');
		if (!card) {
			card = doc.createElement('div');
			card.id = 'saldi-help-callout';
			card.setAttribute('role', 'dialog');
			card.setAttribute('aria-modal', 'false');
			const head = doc.createElement('div');
			head.className = 'shc-head';
			const step = doc.createElement('span');
			step.className = 'shc-step';
			const x = doc.createElement('button');
			x.type = 'button';
			x.className = 'shc-x';
			x.textContent = '×';
			head.appendChild(step);
			head.appendChild(x);
			const p = doc.createElement('p');
			p.className = 'shc-text';
			const foot = doc.createElement('div');
			foot.className = 'shc-foot';
			const ok = doc.createElement('button');
			ok.type = 'button';
			ok.className = 'shc-ok';
			foot.appendChild(ok);
			card.appendChild(head);
			card.appendChild(p);
			card.appendChild(foot);
			doc.body.appendChild(card);
			x.addEventListener('click', function () { endShowMe({ restoreScroll: true }); });
			ok.addEventListener('click', function () { endShowMe({ restoreScroll: true }); });
		}
		return { ring: ring, card: card };
	}
	/** The visible, clipped rect of a frame element in the frame's viewport (port of tutorial.php visibleRect()). */
	function visibleRect(n, win) {
		const doc = n.ownerDocument;
		const r = n.getBoundingClientRect();
		let top = r.top, left = r.left, right = r.right, bottom = r.bottom;
		for (let p = n.parentElement; p && p !== doc.body && p !== doc.documentElement; p = p.parentElement) {
			const cs = win.getComputedStyle(p);
			if (cs.overflowX === 'visible' && cs.overflowY === 'visible') { continue; }
			const b = p.getBoundingClientRect();
			const bl = b.left + p.clientLeft, bt = b.top + p.clientTop;
			top = Math.max(top, bt);
			left = Math.max(left, bl);
			right = Math.min(right, bl + p.clientWidth);
			bottom = Math.min(bottom, bt + p.clientHeight);
		}
		top = Math.max(top, 0);
		left = Math.max(left, 0);
		right = Math.min(right, win.innerWidth);
		bottom = Math.min(bottom, win.innerHeight);
		return (top < bottom && left < right) ? { top: top, left: left, right: right, bottom: bottom, width: right - left, height: bottom - top } : null;
	}
	function rememberScroll(n) {
		const saved = new Map();
		const doc = n.ownerDocument;
		for (let p = n.parentElement; p; p = p.parentElement) {
			if (!saved.has(p) && (p.scrollHeight > p.clientHeight || p.scrollWidth > p.clientWidth)) { saved.set(p, [p.scrollLeft, p.scrollTop]); }
		}
		const se = doc.scrollingElement;
		if (se && !saved.has(se)) { saved.set(se, [se.scrollLeft, se.scrollTop]); }
		return saved;
	}
	function restoreScroll(saved) {
		if (!saved) { return; }
		saved.forEach(function (pos, n) { try { n.scrollLeft = pos[0]; n.scrollTop = pos[1]; } catch (e) { /* gone */ } });
	}
	/** Below the target (14 px gap), else above, else beside; 8 px inside the frame's viewport (prototype placeCard()). */
	function placeCard(R, rect) {
		const card = R.card, vw = R.win.innerWidth, vh = R.win.innerHeight, m = 8, gap = 14;
		const cw = card.offsetWidth || 320, ch = card.offsetHeight || 120;
		let top, left;
		if (!rect) {
			top = parseFloat(card.style.top) || m;
			left = parseFloat(card.style.left) || m;
		} else if (rect.bottom + gap + ch <= vh - m) {
			top = rect.bottom + gap;
			left = rect.left;
		} else if (rect.top - gap - ch >= m) {
			top = rect.top - gap - ch;
			left = rect.left;
		} else {
			top = rect.top;
			left = rect.right + gap + cw <= vw - m ? rect.right + gap : rect.left - gap - cw;
		}
		top = Math.max(m, Math.min(vh - ch - m, top));
		left = Math.max(m, Math.min(vw - cw - m, left));
		card.style.top = Math.round(top) + 'px';
		card.style.left = Math.round(left) + 'px';
	}
	function place() {
		const R = S.ring;
		if (!R) { return; }
		let rect = null;
		try { rect = R.node.isConnected ? visibleRect(R.node, R.win) : null; } catch (e) { rect = null; }
		if (rect) {
			R.ring.style.left = Math.round(rect.left - 6) + 'px';
			R.ring.style.top = Math.round(rect.top - 6) + 'px';
			R.ring.style.width = Math.round(rect.width + 12) + 'px';
			R.ring.style.height = Math.round(rect.height + 12) + 'px';
			R.ring.setAttribute('data-on', 'true');
		} else {
			R.ring.setAttribute('data-on', 'false');
		}
		placeCard(R, rect);
	}
	const KIND_TEXT = { button: '8742', field: '8743', status: '8744', row: '8759' };
	function showMe(target, opts) {
		const o = opts || {};
		const doc = fdoc();
		if (!doc || !doc.body || route() === null) { return false; }
		let n = null;
		if (typeof target === 'string') {
			try { n = Array.prototype.find.call(doc.querySelectorAll(target), visibleEl) || null; } catch (e) { n = null; }
		} else if (target && target.nodeType === 1 && target.ownerDocument === doc) {
			n = target;
		}
		if (!n || !visibleEl(n)) { return false; }
		if (S.ring) { endShowMe({ silent: true, noRestore: true }); }
		const L = ensureLayer(doc);
		const win = doc.defaultView;
		closePageList(win);
		const label = String(o.label || '');
		const kind = KIND_TEXT[o.kind] ? o.kind : 'button';
		const step = L.card.querySelector('.shc-step'), text = L.card.querySelector('.shc-text');
		const x = L.card.querySelector('.shc-x'), ok = L.card.querySelector('.shc-ok');
		step.textContent = t('8707') + ' · ' + label;
		clear(text);
		text.appendChild(md(o.note ? o.note : t(KIND_TEXT[kind]), doc));
		x.setAttribute('aria-label', t('8747'));
		x.setAttribute('title', t('8747'));
		ok.textContent = t('8795');
		L.card.setAttribute('aria-label', tf('8708', label));
		const saved = rememberScroll(n);
		try { n.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: reduced() ? 'auto' : 'smooth' }); } catch (e) { try { n.scrollIntoView(); } catch (e2) { /* ignore */ } }
		S.ring = { doc: doc, win: win, node: n, ring: L.ring, card: L.card, saved: saved, label: label };
		L.card.setAttribute('data-on', 'true');
		place();
		if (S.open) { minimise(label); }
		motionCheck();
		if (o.focus !== false) {
			try { win.focus(); ok.focus({ preventScroll: true }); } catch (e) { /* ignore */ }
		}
		return true;
	}
	function endShowMe(opts) {
		const o = opts || {};
		const R = S.ring;
		if (!R) {
			if (S.minimised && !S.tour && !o.noRestore) { restore(); }
			if (H.back && !S.tour && !o.noRestore) { endBack(backAgain(o), !!o.pressed); }
			return;
		}
		// focus goes back into help only when it was in help (the callout, the strip, nowhere); a user who clicked
		// into the page keeps their place
		let fromHelp = false;
		try {
			const ae = document.activeElement;
			if (!ae || ae === document.body || host.contains(ae)) { fromHelp = true; } else if (ae === frame()) {
				const fa = R.doc.activeElement;
				fromHelp = !fa || fa === R.doc.body || R.card.contains(fa);
			}
		} catch (e) { fromHelp = false; }
		S.ring = null;
		try { R.ring.setAttribute('data-on', 'false'); R.card.setAttribute('data-on', 'false'); } catch (e) { /* the document is gone */ }
		if (o.restoreScroll) { restoreScroll(R.saved); }
		motionCheck();
		if (S.minimised && !S.tour && !o.noRestore) {
			restore({ noAside: !!o.noAside });
			if (!o.silent && fromHelp && S.open) { focusInto({ keyboard: true }); }
		}
		// the chat was closed for the ring: Ok, ×, Esc and the strip go back to it; a press in the page brings it
		// back only outside where it stood, without focus
		if (H.back && !S.tour && !o.noRestore) { endBack(backAgain(o), !!o.pressed); }
		// the chat stayed open beside the ring: Ok, × and Esc put focus back into it, as they put it back into the
		// panel; a press in the page, a page change or a new ring leave focus where it is
		if (H.fromChat) {
			H.fromChat = false;
			if (!o.silent && !o.pressed && !o.noAside && fromHelp) { focusChat(); }
		}
	}

	/* ================================================================ the strip keeps clear of the rings and cards (spec §1.8) */
	function shownRects(doc, sels) {
		const out = [];
		sels.forEach(function (sel) {
			let list;
			try { list = doc.querySelectorAll(sel); } catch (e) { return; }
			Array.prototype.forEach.call(list, function (n) {
				if (!visibleEl(n)) { return; }
				try { out.push(rectInTop(n)); } catch (e) { /* ignore */ }
			});
		});
		return out;
	}
	function stripRect(pos) {
		if (S.mode === 'sheet') {
			const iw = window.innerWidth, ih = window.innerHeight;
			let tb = 44;
			const bar = document.getElementById('topbar');
			if (bar) { tb = bar.getBoundingClientRect().bottom; }
			return pos === 't' ? { left: 8, top: tb + 8, right: iw - 8, bottom: tb + 52 } : { left: 8, top: ih - 52, right: iw - 8, bottom: ih - 8 };
		}
		const hc = homeContent().getBoundingClientRect();
		const w = Math.min(W, hc.width);
		if (pos === 'tl') { return { left: hc.left, top: hc.top, right: hc.left + w, bottom: hc.top + 44 }; }
		if (pos === 'br') { return { left: hc.right - w, top: hc.bottom - 44, right: hc.right, bottom: hc.bottom }; }
		return { left: hc.right - w, top: hc.top, right: hc.right, bottom: hc.top + 44 };
	}
	function avoid() {
		if (!((S.open && S.minimised) || H.back) || S.mode === 'docked' || !homeContent()) { return; }
		const doc = fdoc();
		if (!doc || route() === null) { return; }
		const must = shownRects(doc, ['#saldi-help-ring[data-on="true"]', '#saldi-help-callout[data-on="true"]', '#tutorial-ring', '#tutorial-tooltip']);
		const soft = shownRects(doc, ['input[type=submit][accesskey]', 'input[name="bilagsmatch"]']);
		const order = S.mode === 'sheet' ? ['t', 'b'] : ['tr', 'tl', 'br'];
		const clearOf = function (p, list) { const r = stripRect(p); return !list.some(function (x) { return hit(r, x, 4); }); };
		if (S.pos && clearOf(S.pos, must) && clearOf(S.pos, soft)) { return; }
		let best = null;
		order.some(function (p) { if (clearOf(p, must) && clearOf(p, soft)) { best = p; return true; } return false; });
		if (!best) { order.some(function (p) { if (clearOf(p, must)) { best = p; return true; } return false; }); }
		if (best && best !== S.pos) { setPos(best); }
	}
	/* One coalesced frame callback for place() and avoid(), scheduled only by events (frame scroll/resize, ResizeObserver,
	   saldi:tour); nothing runs while nothing moves. The tour card moves in the frame's own frame callback after a scroll,
	   and the top window's callbacks run first, so avoid() reads it once more one frame later. */
	let rafId = 0;
	function schedule() {
		if (rafId) { return; }
		rafId = requestAnimationFrame(function () {
			rafId = 0;
			if (S.ring) { place(); }
			if (S.minimised || H.back) {
				avoid();
				if (S.tour) { requestAnimationFrame(avoid); }
			}
		});
	}
	const MOT = { win: null, ro: null, node: null };
	function bindMotion() {
		const w = fwin();
		if (MOT.win !== w) {
			unbindMotion();
			MOT.win = w;
			try { w.addEventListener('scroll', schedule, true); w.addEventListener('resize', schedule); } catch (e) { /* unreadable */ }
		}
		const node = S.ring ? S.ring.node : null;
		if (window.ResizeObserver && MOT.node !== node) {
			if (MOT.ro) { MOT.ro.disconnect(); }
			MOT.ro = new ResizeObserver(schedule);
			MOT.node = node;
			try { if (node) { MOT.ro.observe(node); } } catch (e) { /* ignore */ }
		}
	}
	function unbindMotion() {
		if (MOT.win) { try { MOT.win.removeEventListener('scroll', schedule, true); MOT.win.removeEventListener('resize', schedule); } catch (e) { /* gone */ } }
		if (MOT.ro) { MOT.ro.disconnect(); }
		MOT.win = null;
		MOT.ro = null;
		MOT.node = null;
	}
	function motionCheck() { if (S.ring || (S.open && S.minimised) || H.back) { bindMotion(); } else { unbindMotion(); } }

	/* ================================================================ binding into the frame (spec §1.5) */
	function whenParsed(doc, fn) {
		if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', fn, { once: true }); } else { fn(); }
	}
	function setBusy(b) {
		S.busy = b;
		setBusyAttr();
		Array.prototype.forEach.call(host.querySelectorAll('.hsb-showme, .hsb-behind__show, [data-tour-row]'), function (n) { n.disabled = b; });
	}
	/** A new frame document (or an unreadable one, r = null): D6 closes an unpinned panel on another route. r is the page id
	 *  (frameId()), so another variant of the same route, such as another settings section, is another page (spec §24). */
	function noteRoute(r) {
		const prev = S.lastRoute;
		S.lastRoute = r;
		if (prev === undefined || prev === r) { return false; }
		if (S.ring) { endShowMe({ silent: true, noAside: true }); }
		if (S.open && !S.pinned) { close({ restoreFocus: false }); return true; }
		S.lastField = null;
		S.lastPage = null;
		S.expanded = {};
		S.fieldMinH = 0;
		clear(D.field);
		D.field.hidden = true;
		return true;
	}
	/** Look the field up again by its name after a reload of the same route (spec §1.5.2). */
	function reattachField(doc) {
		const f = S.lastField;
		if (!f || f.el || !f.name) { return; }
		let n = null;
		try {
			const form = doc.forms && doc.forms.kassekladde;
			n = form && form.elements ? form.elements.namedItem(f.name) : null;
			if (n && n.nodeType !== 1) { n = n[0] || null; }
			if (!n) { n = doc.getElementsByName(f.name)[0] || null; }
		} catch (e) { n = null; }
		if (n && isField(n)) { f.el = n; }
	}
	function bindFrame(doc) {
		if (!doc || doc.__saldiHelpBound) { return; }
		if (route() === null || doc !== fdoc()) { return; }
		doc.__saldiHelpBound = true;
		doc.addEventListener('keydown', function (e) { onKey(e, true); });
		const acted = function (e) {
			if (!e.isTrusted) { return; }
			S.lastDown = { el: e.target, t: Date.now(), press: e.type === 'pointerdown' };
			if (!doc.__saldiHelpUserActed) {
				doc.__saldiHelpUserActed = true;
				// adopt the field the page focused by itself only when the user types in it or clicks into it (S11, S11b)
				const a = doc.activeElement;
				if (isField(a) && (e.type === 'keydown' || e.target === a)) { setLastField(a); }
			}
			if (e.type === 'pointerdown' && S.ring && S.ring.doc === doc) {
				const tg = e.target;
				const onCell = tg && tg.closest && tg.closest('#tutorial-help');
				if (!S.ring.card.contains(tg) && !onCell) { endShowMe({ pressed: true }); }   // D2: the press still does what it does
			}
		};
		doc.addEventListener('pointerdown', acted, true);
		doc.addEventListener('keydown', acted, true);
		doc.addEventListener('focusin', function (e) {
			const tg = e.target;
			if (isField(tg)) {
				if (!doc.__saldiHelpUserActed) { return; }   // the page's own focus on load does not count
				const was = S.lastField ? S.lastField.key : null;
				setLastField(tg);
				onFieldMoved();
				// the state depends on the field's key, not on the row: tabbing down a column builds it once
				if (S.lastField.key !== was) { queueState(); }
			} else if (tg && tg.nodeType === 1 && tg.matches && tg.matches('button, a[href], [tabindex]') && !tg.closest('#tutorial-help')) {
				// help that is not shown forgets the field; the chat keeps it while it is open, as the panel does, and so do
				// the help's own buttons in the page (the callout's Ok, the tour card) while the chat is closed for them
				const ours = !!(tg.closest && tg.closest('#saldi-help-callout, #tutorial-tooltip'));
				if (!S.open && !(merged() && (chatOpen() || ours))) { S.lastField = null; queueState(); }
			}
		}, true);
		const caret = function (e) {
			const f = S.lastField;
			if (f && e.target === f.el) { const k = caretOf(f.el); f.start = k.start; f.end = k.end; }
		};
		doc.addEventListener('focusout', function (e) {
			caret(e);
			const tg = e.target;
			if (S.open && tg && tg.nodeType === 1 && tg !== doc.body && !(tg.closest && tg.closest('#saldi-help-callout, #tutorial-tooltip'))) { const k = caretOf(tg); S.lastPage = { el: tg, start: k.start, end: k.end }; }
		}, true);
		doc.addEventListener('keyup', caret, true);
		doc.addEventListener('mouseup', caret, true);
		doc.addEventListener('change', function () { dropDetect(); queueState(); }, true);
		// A page that redraws in place without a load or a change event (page.inPlace: debitor/crm.php's tabs, drawer and
		// dialogs, javascript/crm.js) is read again after a click or one of its keys, once the page's own handler has run.
		// Capture, because crm.js stops some clicks at the button (#newBtn) before they reach the document.
		const reread = function () {
			dropDetect();
			if (S.open && !S.minimised) { render(); }
			queueState();
		};
		const again = function () {
			setTimeout(function () {
				if (fdoc() !== doc) { return; }
				const p = ctx0Page();
				if (!p || !p.inPlace) { return; }
				reread();
			}, 0);
		};
		doc.addEventListener('click', again, true);
		doc.addEventListener('keyup', function (e) { if (/^(Escape|Enter|n|N)$/.test(e.key || '')) { again(); } }, true);
		// Typing in a field of an inPlace page (the CRM's search #q filters every tab as the user types, with no change event
		// until the field is left): read again once the typing pauses, and draw the panel again only when the state changed.
		let typing = 0;
		doc.addEventListener('input', function () {
			if (typing) { clearTimeout(typing); }
			typing = setTimeout(function () {
				typing = 0;
				if (fdoc() !== doc) { return; }
				const ctx = pageCtx();
				const p = ctx ? pageFor(ctx) : null;
				if (!p || !p.inPlace) { return; }
				const was = S.st && S.stDoc === doc ? JSON.stringify(S.st) : '';
				dropDetect();
				if (JSON.stringify(cachedDetect(p, ctx)) === was) { return; }
				if (S.open && !S.minimised) { render(); }
				pushState(false);   // already debounced here: no second wait in queueState()
			}, STATE_MS);
		}, true);
		// The document pool (includes/documents.php) shows the next document without a page load and says so with
		// "poolswitch" on its document (javascript/docPoolSwitch.js:151), so the page's state is read again then. The CRM
		// says "crmdrawer" when its drawer has opened (javascript/crm.js openDrawer()), also after a fetch that follows no
		// click or key (the server search's pick, openOutside()).
		doc.addEventListener('poolswitch', reread, true);
		doc.addEventListener('crmdrawer', reread, true);
		doc.addEventListener('click', function (e) {
			const tg = e.target;
			const cell = tg && tg.closest ? tg.closest('#tutorial-help') : null;
			if (cell) {
				// the classic blue Hjælp cell opens the panel (D3); runs before tutorial.php's bubble listener
				e.preventDefault();
				e.stopPropagation();
				toggle({ source: 'classic', trigger: cell.querySelector('button') || cell, keyboard: e.detail === 0 });
				return;
			}
			if (S.ring && S.ring.doc === doc && S.ring.node.contains(tg)) { endShowMe({ pressed: true }); }
		}, true);
		// pagehide is fired at the window, not the document
		try { doc.defaultView.addEventListener('pagehide', function () { onPageHide(doc); }); } catch (e) { /* no window */ }
		const changed = noteRoute(frameId());
		dropDetect();
		whenParsed(doc, function () {
			if (fdoc() !== doc) { return; }
			reattachField(doc);
			dropDetect();
			if (S.busy) { setBusy(false); }
			if (S.open && (changed || !S.minimised)) { render(); }
			signalCheck();
			queueState();
		});
	}
	function onPageHide(doc) {
		setBusy(true);
		if (S.ring && S.ring.doc === doc) { endShowMe({ silent: true, noAside: true }); }
		if (S.tour) { S.tour = false; if (S.minimised) { restore({ noAside: true }); } }
		H.backTour = null;
		H.backRect = null;
		H.fromChat = false;
		if (H.back) { endBack(false); }   // the page went away under the ring or tour: no way back to show
		pushBlank();                      // and its help with it: the chat shows no Vis mig of a page that is gone
		dropDetect();
		if (S.lastField && S.lastField.el && S.lastField.el.ownerDocument === doc) {
			const f = S.lastField;
			S.lastField = { el: null, key: f.key, name: f.name, start: f.start, end: f.end };
		}
		if (S.lastPage && S.lastPage.el && S.lastPage.el.ownerDocument === doc) { S.lastPage = null; }
		if (S.returnTo && S.returnTo.el && S.returnTo.el.ownerDocument === doc) { S.returnTo = null; }
		unbindMotion();
	}
	function onFrameLoad() {
		const r = route();
		if (r === null) {
			// another origin, about:blank or an error page: no bind, generic panel (spec §1.5.1)
			noteRoute(null);
			dropDetect();
			setBusy(false);
			if (S.open) { render(); }
			queueState();
			return;
		}
		const doc = fdoc();
		if (doc && !doc.__saldiHelpBound) {
			bindFrame(doc);       // a page without a breadcrumb: bound (and rendered) now
		} else {
			dropDetect();
			if (S.busy) { reattachField(doc); setBusy(false); }
			if (S.open && !S.minimised) { render(); }
			signalCheck();
			queueState();
		}
		if (r === 'finans/kladdeliste.php') {
			// kladdeliste injects its filter toggle 300 ms after DOMContentLoaded: one re-check, not a poll
			setTimeout(function () { if (fdoc() === doc) { dropDetect(); if (S.open && !S.minimised) { render(); } queueState(); } }, 400);
		}
	}
	function onFieldMoved() {
		if (!S.open || S.minimised || S.view !== 'home' || !P.page) { return; }
		// only page.order (KK4 depends on the field) and the field box, never detect() (spec §1.6)
		computeP(false);
		fillField(true);
		fillTasks(true);
		markBehind();
	}

	/* ================================================================ keyboard (spec §1.12) */
	function onKey(e, fromFrame) {
		const k = e.key;
		if (k === 'Escape' || k === 'Esc') { onEsc(e, fromFrame); return; }
		if (k === 'F1') { e.preventDefault(); onF1(e, fromFrame); return; }
		if (k === '?' && !e.ctrlKey && !e.altKey && !e.metaKey) {
			if (e.defaultPrevented || isTyping(e.target)) { return; }
			e.preventDefault();
			if (!S.open) { if (merged()) { mergedOpen('help'); } else { open({ source: 'key', keyboard: true, focus: 'search' }); } return; }
			if (S.minimised) { endRingOrTour(); }
			if (S.view !== 'home') { setView('home', { keyboard: true }); } else { focusInto({ keyboard: true }); }
		}
	}
	function onF1(e, fromFrame) {
		const tg = e.target;
		const fieldTarget = fromFrame && isField(tg);
		if (fieldTarget) { setLastField(tg); }
		if (!S.open && merged()) { mergedOpen(fieldTarget ? 'field' : 'help'); return; }   // the field's card when it has help
		if (!S.open) { open({ source: 'key', keyboard: true, focus: 'search', fromField: fieldTarget }); return; }
		if (S.minimised) { endRingOrTour(); }
		if (fieldTarget) {
			// that field's help first, and Esc returns to it with its caret
			const kk = caretOf(tg);
			S.returnTo = { el: tg, start: kk.start, end: kk.end };
			S.lastPage = S.returnTo;
			S.fieldFirst = true;
			S.view = 'home';
			render();
		}
		focusInto({ keyboard: true });
	}
	function onEsc(e, fromFrame) {
		if (e.defaultPrevented) { return; }                                                                   // 1
		if (document.querySelector('.topbar-pop.open, .topbar-search.open')) { return; }                       // 2
		if (!fromFrame && document.activeElement === D.input && D.input.value) { e.preventDefault(); clearSearch(); return; }   // 3
		if (S.ring) { e.preventDefault(); endShowMe({ restoreScroll: true }); return; }                        // 4
		if (S.tour) {
			if (!fromFrame) { const a = tourApiAny(); if (a && typeof a.stop === 'function') { try { a.stop(); } catch (x) { /* ignore */ } } e.preventDefault(); }
			return;                                                                                            // the tour card handles its own Esc in the frame
		}
		if (!S.open || S.minimised) { return; }
		if (!fromFrame && host.contains(document.activeElement)) { e.preventDefault(); close({ keyboard: true }); return; }   // 5
		if (fromFrame) {
			if ((S.mode === 'overlay' || S.mode === 'sheet') && !S.pinned) { close({ restoreFocus: false, keyboard: true }); }   // 6 (the page still gets its Esc)
			return;                                                                                            // 7 docked or pinned: nothing
		}
		e.preventDefault();
		close({ restoreFocus: false, keyboard: true });
	}

	/* ================================================================ messages from the frame (spec §1.5, §3.3) */
	function onTour(d) {
		if (d.state === 'start') {
			S.tour = true;
			if (S.ring) { endShowMe({ silent: true, noRestore: true }); }
			if (S.open) { minimise(t('8760')); } else if (H.backTour) { showBack(t('8760'), H.backTour, H.backRect); } else if (H.back) { stripLabel(t('8760')); }
			H.backTour = null;
			H.backRect = null;
			motionCheck();
			schedule();
		} else if (d.state === 'step') {
			schedule();
		} else if (d.state === 'end') {
			const was = S.tour;   // false when pagehide already ended it
			S.tour = false;
			// A tour started from the panel has no frame element to give focus back to (the frame's activeElement
			// was its body while focus was in the panel), so ×, Færdig or Esc leave focus nowhere. Put the caret back
			// in the page field the user left when help opened (S13); otherwise focus goes back into help, as for
			// Vis mig (§1.7). A press in the page ends the tour with focus where the user put it: nothing to do.
			const pressed = !!(S.lastDown && S.lastDown.press && Date.now() - S.lastDown.t < 800);
			// A mouse ×, Færdig or Næste on the last step is a press on the tour card, not in the page; the card's Spørg button
			// is followed by its own saldi:help-ask (helpAsk()).
			const de = pressed ? S.lastDown.el : null;
			const onCard = !!(de && de.closest && de.closest('#tutorial-tooltip'));
			const away = pressed && !onCard;
			const card = onCard && !de.closest('#tutorial-ask');
			// the chat was closed for the tour: ×, Færdig, Esc and the strip go back to it; a press in the page brings it back
			// only outside where it stood, without focus
			if (H.back && !S.ring) { endBack(was && (!pressed || card), away); motionCheck(); return; }
			if (H.fromChat && !S.ring) {
				// the chat stayed open beside the tour: focus that the tour left nowhere goes back into it
				H.fromChat = false;
				if (was && (!pressed || card) && tourFocusLost()) { focusChat(); }
			}
			const r = S.returnTo;
			const back = was && !pressed && tourFocusLost() === 'frame' && r && r.el && isField(r.el) && r.el.ownerDocument === fdoc() && visibleEl(r.el) ? r : null;
			if (back) { refocus(back); }   // before restore(): an overlay panel that would cover the field steps aside (§1.8)
			if (S.minimised && !S.ring) { restore(); }
			if (was && !pressed && !back && S.open && !S.minimised && tourFocusLost()) { focusInto({ keyboard: true }); }
			motionCheck();
		}
	}
	/** Focus is nowhere after a tour: 'frame' (on the frame's body or the hidden card), 'shell' (on the shell's body or a control of help that
	 *  is hidden now, such as the strip's button), else ''. */
	function tourFocusLost() {
		try {
			const ae = document.activeElement;
			if (ae === frame()) {
				const d = fdoc();
				const fa = d ? d.activeElement : null;
				// the card's own button may still be the activeElement although the card is hidden now
				return !fa || fa === d.body || fa === d.documentElement || !visibleEl(fa) || !!(fa.closest && fa.closest('#tutorial-tooltip')) ? 'frame' : '';
			}
			if (!ae || ae === document.body || (host.contains(ae) && !visibleEl(ae))) { return 'shell'; }
			return '';
		} catch (e) { return ''; }
	}
	function helpAsk(text) {
		const q = tf('8756', String(text || '').replace(/\s+/g, ' ').trim().slice(0, 200));
		if (!S.open && merged()) {
			// the tour card's "Spørg Saldi AI om dette": the tour ends, as in the panel, and the chat gets the question
			if (H.back) { endBack(false); }
			if (S.ring) { endShowMe({ silent: true, noRestore: true }); }
			if (S.tour) { stopTour(); }
			ask(q);
			return;
		}
		if (!S.open) { open({ source: 'api', keyboard: true, prefill: q }); return; }
		if (S.minimised) { endRingOrTour(); }
		if (S.view !== 'home') { S.view = 'home'; applyView(); }
		prefill(q);
	}

	/* ================================================================ boot */
	function boot() {
		M_.top = (function () { const b = topBtn(); return b ? b.querySelector('.ai-avatar') : null; })();
		M_.at = M_.top;
		if (M_.top) { registerSeat(M_.top); registerAvatar(M_.top); }
		build();
		const fa = el('span', { class: 'ai-avatar', 'aria-hidden': 'true' });
		const fsvg = cloneSymbol();
		if (fsvg) { fa.appendChild(fsvg); }
		registerAvatar(fa);
		M_.flyer = el('div', { class: 'saldi-help-flyer', 'aria-hidden': 'true', 'data-on': 'false' }, [fa]);
		document.body.appendChild(M_.flyer);
		D.sr = el('span', { class: 'hc-sr', role: 'status', 'aria-live': 'polite' });
		document.body.appendChild(D.sr);

		document.addEventListener('keydown', function (e) { if (e.key !== 'Escape' && e.key !== 'Esc') { onKey(e, false); } });
		window.addEventListener('keydown', function (e) { if (e.key === 'Escape' || e.key === 'Esc') { onEsc(e, false); } }, true);
		window.addEventListener('message', function (e) {
			const f = frame();
			if (!f || e.origin !== location.origin || !e.data || e.source !== f.contentWindow) { return; }
			const d = e.data;
			if (d.type === 'saldi:breadcrumb') {
				const doc = fdoc();
				if (doc && !doc.__saldiHelpBound) { bindFrame(doc); }
				if (S.open && !P.page && !S.busy) { computeP(false); renderHead(); fillMore(); }
				queueState();   // the generic title comes from the breadcrumb
			} else if (d.type === 'saldi:help-open') {
				toggle({ source: 'classic' });
			} else if (d.type === 'saldi:help-ask') {
				helpAsk(typeof d.text === 'string' ? d.text : '');
			} else if (d.type === 'saldi:tour') {
				onTour(d);
			} else if (d.type === 'saldi:dash-hidden') {
				// Oversigt hides or shows itself in place, without a load or a change event (javascript/dashboard.js
				// setHidden()): its state is read again, so the help follows (spec §20)
				dropDetect();
				if (S.open && !S.minimised) { render(); }
				queueState();
			}
		});
		const f = frame();
		if (f) { f.addEventListener('load', onFrameLoad); }
		window.addEventListener('resize', function () { if (S.open) { layout(); } backLayout(); if (S.ring || S.minimised || H.back) { schedule(); } });
		// Hjælp inside Saldi Assist (spec §19): the chat's Vis mig, Rundvisning and fold choice
		window.addEventListener('saldi:help:action', function (e) { onHelpAction(e ? e.detail : null); });
		window.addEventListener('saldi:help:ready', onHelpReady);   // spec §19.9: the loader connected to the widget
		// spec §19.9: the chat opened (any way). In the shell (§23) every open is watched, also "+ Ny chat", which is the loader's
		window.addEventListener('saldi:chat:open', function () { chatReopened(); if (merged()) { setTopbar(); } if (shellResume()) { watchReady(); } });
		window.addEventListener('saldi:chat:close', function () { if (merged()) { setTopbar(); } });
		if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', catchUp, { once: true }); } else { catchUp(); }
		window.addEventListener('load', catchUp, { once: true });

		applyMood();
		renderPin();
		const doc = fdoc();
		if (doc && route() !== null && doc.readyState !== 'loading') { bindFrame(doc); }
		// a pinned panel that was open in this tab opens again after a shell reload, without flight and focus (spec §10).
		// With the Guide v2 loader in the page, only after it ran (defer) and only when the chat does not take the help over.
		if (S.pinned && sGet(OPEN_KEY) === '1') {
			const reopen = function () { if (!merged() && !S.open) { open({ source: 'api', keyboard: true, focus: false, noAnim: true }); } };
			if (document.readyState === 'loading' && document.querySelector('script[data-assistant="v2"]')) { document.addEventListener('DOMContentLoaded', reopen, { once: true }); } else { reopen(); }
		}
	}

	window.SaldiHelp = {
		enabled: true,
		version: 1,
		open: function (o) { open(o || { source: 'api' }); },
		close: function (o) { close(o || {}); },
		toggle: function (o) { toggle(o || { source: 'api' }); },
		isOpen: function () { return S.open; },
		refresh: function () { dropDetect(); if (S.open) { render(); } },
		showMe: function (target, o) { return showMe(target, o || {}); },
		endShowMe: function () { endShowMe({ restoreScroll: true }); },
		ask: function (q) { return ask(q); },
		setEngineState: function (s) { S.engine = s === 'thinking' || s === 'answering' ? s : null; applyMood(); },
		state: function () {
			const ctx = pageCtx();
			const res = ctx ? resolved(ctx) : null;
			const raw = res ? res.page : null;
			const st = raw ? cachedDetect(raw, ctx) : null;
			return {
				open: S.open, view: S.open ? S.view : null, mode: S.open ? S.mode : null, pinned: S.pinned, minimised: S.minimised, busy: S.busy,
				route: ctx ? ctx.route : null, pageId: idOf(ctx, res), group: raw ? raw.group : null,
				page: !!(S.open ? P.page : (raw && TX.state[raw.group] === 'ready')),
				texts: raw ? (TX.state[raw.group] || 'none') : 'none',
				pageState: st ? st.state : null, flags: st ? Object.assign({}, st.flags) : {},
				mood: S.mood, lastField: S.lastField ? S.lastField.key : null, ring: !!S.ring, tour: !!S.tour, guide: guideState(),
				merged: merged(), back: !!H.back, dead: H.dead
			};
		},
		/** The last HelpState sent to the chat (spec §19), null before the first one; a test hook. */
		helpState: function () { return H.state; }
	};
	boot();
})();
