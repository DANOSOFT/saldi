// ---- javascript/pageChrome.js --- lap 5.0.0 --- 2026.10.08 ---
// 20261008 Sawaneh The breadcrumb script of includes/stdFunc/pageChrome.php as a static file: the page's head only
//                  carries window.saldiPageChrome, so a page that redirects after online.php stays under the
//                  output buffer (Goods on ssl12 redirected into a blank page). Same behaviour as the inline script.
(function () {
	function send() { if (window.parent && window.parent !== window && window.saldiPageChrome) { window.parent.postMessage(window.saldiPageChrome, window.location.origin); } }
	if (!window.saldiChromeBound) {
		window.saldiChromeBound = true;
		// The page's old Luk/Tilbage through includes/luk.php releases the record it locked: leave the same way.
		var viaLuk = function (href) {
			var c = document.querySelector('a[accesskey="l"]:not([data-keep-in-shell]), a[accesskey="L"]:not([data-keep-in-shell])');
			if (!c || !/(^|\/)luk\.php/.test(c.getAttribute('href') || '')) { return href; }
			try { var u = new URL(c.href), t = new URL(href); u.searchParams.delete('popup'); u.searchParams.set('returside', t.pathname + t.search); return u.href; } catch (x) { return href; }
		};
		window.addEventListener('message', function (e) {
			if (e.origin !== window.location.origin || !e.data || e.source !== window.parent) { return; }
			if (e.data.type === 'saldi:breadcrumb-request') { send(); }
			if (e.data.type === 'saldi:navigate' && typeof e.data.href === 'string') {
				window.parent.postMessage({ type: 'saldi:navigate-ack' }, window.location.origin);
				if (typeof window.saldiNavigate === 'function') { window.saldiNavigate(e.data.href); return; }
				if (window.docChange && !window.confirm(e.data.confirm || '')) { return; }
				window.docChange = false;
				window.location.href = viaLuk(e.data.href);
			}
		});
		// Alt+L goes back through the shell (§3.2); a page that still shows its old Luk/Tilbage keeps that accesskey.
		document.addEventListener('keydown', function (e) {
			if (e.altKey && !e.ctrlKey && !e.metaKey && (e.key === 'l' || e.key === 'L') && !Array.prototype.some.call(document.querySelectorAll('[accesskey="l"], [accesskey="L"]'), function (c) { return c.offsetParent !== null; })) { e.preventDefault(); window.parent.postMessage({ type: 'saldi:back' }, window.location.origin); }
		});
	}
	send();
})();
