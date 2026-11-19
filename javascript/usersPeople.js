// javascript/usersPeople.js
// 20261102 CL/ASR Created: Brugere & ansatte in systemdata/usersRoles.php (owner, 2 Nov 2026: one list of everyone, one card with the tabs
//                 Stamdata and Login og rolle, one button "Tilføj person"; markup from includes/settings/people.php).
//                 - A click anywhere on a list row opens the person's card (links, buttons, tick boxes and labels keep their own click).
//                 - The card's tabs switch in place (fane= in the address); Stamdata loads the employee drawer of Indstillinger in its
//                   iframe the first time it is shown. A page in the iframe without the embedded drawer (the login page after the session
//                   expired) reloads the whole window.
//                 - Messages from that iframe ({type: 'saldi:stamdata'}, only from the iframe and this origin): saved (the card's title takes
//                   the saved name), close (Annullér or Esc in the iframe), gone (the employee was deleted: back to the list).
//                 - Unsaved changes in Stamdata or in Login og rolle: closing the card, Esc, another row or link, and a form of the card
//                   ask first (the kit dialog); "Luk uden at gemme" clears the iframe's flag, so the browser does not ask again.
//                 - "Tilføj person": login or not, employed here or not; an employee is added through the employee section's add action,
//                   a login only opens the new-login form. Nothing is sent by mail from here.
// 20261102 CL/ASR Review: add=1 in the address (a search hit for Tilføj person) opens that dialog on the list, when the page has its button
//                 (a user who may add); add=1 then leaves the address, so a reload does not open it again.
(function () {
	'use strict';
	var cfg = window.UR_PEOPLE || {};
	var drawer = document.getElementById('ur-drawer');
	var frame = document.getElementById('ur-stam-frame');
	var userForm = document.getElementById('ur-user-form');
	var guard = document.getElementById('ur-pguard');
	var loginDirty = false;

	// ------------------------------------------------------------ unsaved changes
	function stamDirty() {
		try { return !!(frame && frame.contentWindow && frame.contentWindow.docChange); } catch (e) { return false; }
	}
	function dirty() { return stamDirty() || loginDirty; }
	function discard() {
		loginDirty = false;
		try { if (frame && frame.contentWindow) { frame.contentWindow.docChange = false; } } catch (e) { /* gone */ }
	}
	var pendingGo = null;
	function ask(then) {
		if (!guard) { if (window.confirm(cfg.discardBody || '')) { discard(); then(); } return; }
		pendingGo = then;
		document.getElementById('ur-pguard-t').textContent = cfg.discardTitle || '';
		document.getElementById('ur-pguard-b').textContent = cfg.discardBody || '';
		var yes = guard.querySelector('[data-pguard-yes]');
		yes.textContent = cfg.discardVerb || 'OK';
		guard.hidden = false;
		guard.querySelector('[data-pguard-no]').focus();
	}
	function closeGuard() { guard.hidden = true; pendingGo = null; }
	if (guard) {
		guard.addEventListener('click', function (e) {
			if (e.target.closest('[data-pguard-yes]')) {
				var then = pendingGo;
				closeGuard();
				discard();
				if (then) { then(); }
			} else if (e.target === guard || e.target.closest('[data-pguard-no]')) {
				closeGuard();
			}
		});
	}
	function go(href) {
		if (dirty()) { ask(function () { window.location.href = href; }); return; }
		window.location.href = href;
	}
	if (userForm) {
		userForm.addEventListener('input', function () { loginDirty = true; });
		userForm.addEventListener('change', function () { loginDirty = true; });
	}
	// A form of the card (Gem, Luk bruger, Genåbn ...) reloads the page: unsaved Stamdata changes are asked about first.
	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (!drawer || !stamDirty() || form.id === 'ur-padd-form') { return; }
		e.preventDefault();
		var submitter = e.submitter || null;
		ask(function () { if (form.requestSubmit) { form.requestSubmit(submitter); } else { form.submit(); } });
	}, true);
	if (userForm) {
		userForm.addEventListener('submit', function () { loginDirty = false; });
	}
	// Links of the page (the X, the scrim, Annullér, the menu, Tilbage) while something is unsaved.
	document.addEventListener('click', function (e) {
		var a = e.target.closest('a[href]');
		if (!a || !drawer || !dirty() || e.defaultPrevented || a.target === '_blank' || (a.getAttribute('href') || '').charAt(0) === '#') { return; }
		e.preventDefault();
		e.stopPropagation();
		ask(function () { window.location.href = a.href; });
	}, true);
	// Esc: the open dialog first; on the card with unsaved changes it asks before usersRoles.php closes the card.
	window.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') { return; }
		if (guard && !guard.hidden) { e.stopImmediatePropagation(); closeGuard(); return; }
		var add = document.getElementById('ur-padd');
		if (add && !add.hidden) { e.stopImmediatePropagation(); add.hidden = true; return; }
		var dlg = document.getElementById('ur-dlg');
		if (dlg && !dlg.hidden) { return; }
		if (drawer && dirty()) {
			e.stopImmediatePropagation();
			e.preventDefault();
			ask(function () { window.location.href = drawer.dataset.close; });
		}
	}, true);

	// ------------------------------------------------------------ the list: a click on a row opens the card
	var tbody = document.querySelector('#ur-users tbody');
	if (tbody) {
		tbody.addEventListener('click', function (e) {
			var tr = e.target.closest('tr[data-href]');
			if (!tr || e.target.closest('a, button, input, select, textarea, label')) { return; }
			go(tr.getAttribute('data-href'));
		});
	}

	// ------------------------------------------------------------ the card's tabs
	function setFane(fane, focus) {
		if (!drawer || !drawer.hasAttribute('data-person')) { return; }
		drawer.setAttribute('data-fane', fane);
		Array.prototype.forEach.call(drawer.querySelectorAll('.ur-ptabs [data-fane]'), function (b) {
			b.setAttribute('aria-selected', b.getAttribute('data-fane') === fane ? 'true' : 'false');
		});
		if (fane === 'stamdata' && frame && !frame.getAttribute('src')) {
			frame.setAttribute('src', frame.getAttribute('data-src'));
		}
		if (window.history && window.history.replaceState) {
			var url = new URL(window.location.href);
			url.searchParams.set('fane', fane);
			['msg', 'field'].forEach(function (k) { url.searchParams.delete(k); });
			window.history.replaceState(null, '', url.pathname + url.search);
		}
		if (focus) {
			var tab = drawer.querySelector('.ur-ptabs [data-fane="' + fane + '"]');
			if (tab) { tab.focus(); }
		}
	}
	if (drawer && drawer.hasAttribute('data-person')) {
		drawer.addEventListener('click', function (e) {
			var tab = e.target.closest('.ur-ptabs [data-fane]');
			if (tab) { setFane(tab.getAttribute('data-fane'), false); return; }
			if (e.target.closest('[data-plink]')) {
				setFane('login', false);
				var pick = document.getElementById('ur-ansat');
				if (pick) { pick.focus(); }
			}
		});
		var tabs = drawer.querySelector('.ur-ptabs');
		if (tabs) {
			tabs.addEventListener('keydown', function (e) {
				if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
					e.preventDefault();
					setFane(drawer.getAttribute('data-fane') === 'stamdata' ? 'login' : 'stamdata', true);
				}
			});
		}
		if (drawer.getAttribute('data-fane') === 'stamdata' && frame) {
			frame.setAttribute('src', frame.getAttribute('data-src'));
		}
	}

	// ------------------------------------------------------------ the Stamdata iframe
	if (frame) {
		frame.addEventListener('load', function () {
			var doc = null;
			try { doc = frame.contentDocument; } catch (e) { doc = null; }
			if (!frame.getAttribute('src')) { return; }
			if (!doc || !doc.querySelector('.st-embed')) {
				// not the embedded employee drawer (the session ended and the login page came): the whole window logs in again
				window.top.location.reload();
			}
		});
		window.addEventListener('message', function (e) {
			if (e.origin !== window.location.origin || !frame.contentWindow || e.source !== frame.contentWindow) { return; }
			var d = e.data || {};
			if (d.type !== 'saldi:stamdata') { return; }
			if (d.event === 'saved' && typeof d.name === 'string' && d.name !== '') {
				var title = document.getElementById('ur-drawer-title');
				if (title && title.firstChild && title.firstChild.nodeType === 3) { title.firstChild.nodeValue = d.name; }
			} else if (d.event === 'close') {
				go(drawer.dataset.close);
			} else if (d.event === 'gone') {
				discard();
				window.location.href = drawer.dataset.close;
			}
		});
	}

	// ------------------------------------------------------------ Tilføj person
	var add = document.getElementById('ur-padd');
	var addForm = document.getElementById('ur-padd-form');
	if (add && addForm) {
		var whenLogin = addForm.querySelector('[data-when="login"]');
		var whenCard = addForm.querySelector('[data-when="stamkort"]');
		var value = function (name) { var r = addForm.querySelector('input[name="' + name + '"]:checked'); return r ? r.value : ''; };
		var refresh = function () {
			var login = value('p_login');
			if (whenLogin) { whenLogin.hidden = (login === 'no' || login === ''); }
			// a record when there is no login, or a login for someone employed here
			var card = !!whenCard && (login === 'no' || (!!whenLogin && value('p_emp') === 'yes'));
			if (whenCard) {
				whenCard.hidden = !card;
				Array.prototype.forEach.call(whenCard.querySelectorAll('input'), function (i) { i.disabled = !card; });
			}
		};
		addForm.addEventListener('change', refresh);
		refresh();
		Array.prototype.forEach.call(document.querySelectorAll('[data-padd]'), function (b) {
			b.addEventListener('click', function () {
				add.hidden = false;
				var first = addForm.querySelector('input[name="p_login"]:checked') || addForm.querySelector('input');
				if (first) { first.focus(); }
			});
		});
		add.addEventListener('click', function (e) {
			if (e.target === add || e.target.closest('[data-padd-close]')) { add.hidden = true; }
		});
		if (/[?&]add=1(?:&|$)/.test(window.location.search) && !document.querySelector('#ur-drawer:not([hidden])')) {
			var addBtn = document.querySelector('[data-padd]');
			if (addBtn) { addBtn.click(); }
			try {
				var here = new URL(window.location.href);
				here.searchParams.delete('add');
				window.history.replaceState(window.history.state, '', here.toString());
			} catch (e) { /* an old browser keeps add=1 */ }
		}
		addForm.addEventListener('submit', function (e) {
			var login = value('p_login');
			var card = !!whenCard && !whenCard.hidden;
			if (login !== 'no' && !card) {
				// a login only (revisor, bogholder, someone from outside): usersRoles.php's new-login form, nothing is posted here
				e.preventDefault();
				window.location.href = addForm.getAttribute(login === 'classic' ? 'data-classic' : 'data-invite');
				return;
			}
			addForm.elements.then.value = (login === 'invite' || login === 'classic') ? login : '';
		});
	}
})();
