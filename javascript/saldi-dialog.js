// ---- javascript/saldi-dialog.js --- ver 5.1.1 --- 2026.11.19 ---
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// Saldi's own message box in place of the browser's alert() and confirm() boxes, in the look of the error box
// (includes/errorReport.php): a white card on a dimmed page, the text, OK (and Annullér for a question).
// Linked in the head of every page includes/online.php prints (not the POS pages) and in the shell (index/main.php),
// with its texts and the user's hidden questions in window.SALDI_DIALOG_CFG.
//
// alert(): shown at once and the page goes on, as alert() only informs. Several wait in turn. A message still open
// when the page moves on (alert(); location = ...) is shown again on the next page in the same tab, and window.close()
// waits until the open messages are read.
// confirm(): a browser box stops the script until it is answered; this one cannot. When confirm() is asked inside a
// click, a submit or a change the user made (return confirm(...) on a button, a link or a form, which is how Saldi
// asks), it answers false, so the action stops, and shows the question; OK does the same click, submit or change again,
// and then confirm() answers true for that text. Asked anywhere else (on load, in a timer) the browser's box is used.
// Messages a cancelled question would have shown at once (if (!confirm(..)) alert(..)) are shown only after Annullér.
// prompt(): the browser's box asks. When the action that asked a question had asked prompt() before it (a new name,
// then "Omdøb .. til <name>?"), OK does that action again with the answers given then: a prompt() in the event OK sends
// again that asks the same question (text and default value), in the same order, gets its answer back instead of asking
// again; any other prompt(), one in an event the page sends itself meanwhile, and every one once the answers run out,
// asks the browser's box. A form OK sends again by its button's click (a browser without requestSubmit(), or a button
// that is no longer the form's) gets no answers: each prompt() in its submit asks the browser's box again.
// "Vis ikke igen": a question whose button, link or form has data-confirm-key="<key>" gets the box; once ticked and
// answered OK, the user is not asked again (settings var_grp 'dialogs', user_id; index/dialogPref.php) and the
// action goes through at once. Only for questions that inform (the invoice date), never for deleting.
//
// 20261114 ASR Created (owner, 14 Nov 2026: the browser boxes do not match the new design, and customers find Saldi has many clicks).
// 20261114 CL/ASR Vis ikke igen has a tip: Personlige indstillinger, Skjulte spørgsmål shows the question again.
// 20261116 CL/ASR While a question is open, a click the browser makes on the page under it (an accesskey, the Enter that submits a form) is cancelled, so the answer decides.
// 20261116 CL/ASR OK on a question gives the action done again the answers prompt() got before the question in the same action, each only to the same question in the same order and only in the event OK sends again, so the serial number's new name is not asked twice.
// 20261117 CL/ASR prompt() converts its text and default value once, as the browser does, and gives the browser's box the converted values (a Symbol gets the browser's own error); a question is told by both as a pair, so no other text and default value match it, and the event OK sends is the one the box makes (a click, a change) or the trusted submit of the form from its button while OK sends it, so an event a listener of the page sends first does not get the answers, and a window listener the page added before the box gets its own answer back in that submit too.
// 20261119 CL/ASR A form OK sends again by its button's click (no requestSubmit(), or requestSubmit() refuses the button) gets no saved prompt() answers and the browser's box asks again, since its submit cannot be told from one a click the page sends at the same button starts, which took the answer.

(function (w, d) {
	'use strict';
	if (w.SaldiDialog) return;
	var CFG = w.SALDI_DIALOG_CFG || {};
	var T = CFG.t || {};
	var txt = function (k, fallback) { return T[k] ? String(T[k]) : fallback; };
	var hidden = {};
	(CFG.hidden || []).forEach(function (k) { hidden[String(k)] = true; });
	var nativeAlert = w.alert, nativeConfirm = w.confirm, nativePrompt = w.prompt, nativeClose = w.close;
	var CARRY = 'saldi_dialog_carry';
	var queue = [], current = null, closeWanted = false;
	var approved = {};          // question text -> time OK was pressed (the replayed action asks again and gets true)
	var replaying = false;      // the replayed event is not trusted; questions inside it may still use the box
	var heldAlerts = null;      // alerts raised right after a question answered false provisionally
	var prompted = null;        // [{ ev, asked, answer }]: what prompt() answered in the events of this task, until it ends
	var promptReplies = null;   // while the action is done again after OK: [{ asked, answer }] its question took along
	var promptEvent = null;     // the event OK sends to do that action again: only a prompt() in it gets those answers
	var submitForm = null;      // while requestSubmit() does a submit again after OK: the form and its button (submitter);
	var submitBy = null;        // the trusted submit at that form from that button is the event OK sends (found in prompt())

	// ---- look (the error box's card, scrim, buttons) ---------------------------------------------------------------
	var CSS = '#sdlg-root,#sdlg-root *{box-sizing:border-box}'
		+ '#sdlg-root{position:fixed;top:0;right:0;bottom:0;left:0;z-index:2147482000;margin:0;padding:0;font:400 14px/1.45 Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#0b1a3a;text-align:left;letter-spacing:normal;text-transform:none;-webkit-font-smoothing:antialiased}'
		+ '#sdlg-root .sdlg-scrim{position:absolute;top:0;right:0;bottom:0;left:0;background:rgba(11,26,58,.42)}'
		+ '#sdlg-root .sdlg-wrap{position:absolute;top:0;right:0;bottom:0;left:0;overflow-y:auto;display:flex;align-items:flex-start;justify-content:center;padding:14vh 16px 24px}'
		+ '#sdlg-root .sdlg-card{position:relative;width:100%;max-width:440px;margin:0;padding:20px 22px 16px;background:#fff;border:1px solid #d8dbe0;border-radius:8px;box-shadow:0 12px 32px rgba(11,26,58,.18),0 2px 6px rgba(11,26,58,.08);outline:0}'
		+ '#sdlg-root .sdlg-body{display:flex;align-items:flex-start;gap:12px}'
		+ '#sdlg-root .sdlg-ic{flex:0 0 32px;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#e8f0fe;color:#114691}'
		+ '#sdlg-root .sdlg-ic svg{display:block}'
		+ '#sdlg-root .sdlg-msg{flex:1 1 auto;min-width:0;margin:5px 0 0;padding:0;font-size:14px;line-height:1.5;color:#22324f;white-space:pre-line;overflow-wrap:anywhere}'
		+ '#sdlg-root .sdlg-foot{display:flex;align-items:center;justify-content:flex-end;gap:8px;margin:18px 0 0;padding:14px 0 0;border-top:1px solid #eef1f6}'
		+ '#sdlg-root .sdlg-dont{display:flex;align-items:center;gap:7px;margin:0 auto 0 0;padding:0;font-size:13px;font-weight:400;line-height:1.3;color:#5b6b85;cursor:pointer;-webkit-user-select:none;user-select:none}'
		+ '#sdlg-root .sdlg-dont input{width:15px;height:15px;margin:0;accent-color:#114691;cursor:pointer}'
		+ '#sdlg-root .sdlg-btn{display:inline-flex;align-items:center;justify-content:center;height:32px;min-width:72px;margin:0;padding:0 14px;border:1px solid #c6d2e4;border-radius:6px;background:#fff;color:#0b1a3a;font-family:inherit;font-size:13.5px;font-weight:500;line-height:1;text-decoration:none;white-space:nowrap;cursor:pointer;box-shadow:none}'
		+ '#sdlg-root .sdlg-btn:hover{background:#eef2f8;border-color:#a9b8cf}'
		+ '#sdlg-root .sdlg-btn:focus{outline:0}'
		+ '#sdlg-root .sdlg-btn:focus-visible{box-shadow:0 0 0 2px rgba(79,160,238,.45)}'
		+ '#sdlg-root .sdlg-primary{background:#114691;border-color:#114691;color:#fff}'
		+ '#sdlg-root .sdlg-primary:hover{background:#0f3f83;border-color:#0f3f83;color:#fff}'
		+ '@media (max-width:420px){#sdlg-root .sdlg-wrap{padding:16px 12px}#sdlg-root .sdlg-card{padding:18px 16px 14px}#sdlg-root .sdlg-foot{flex-wrap:wrap}#sdlg-root .sdlg-btn{flex:1 1 0}}';
	var ICON_INFO = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/></svg>';
	var ICON_ASK = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M9.6 9.2a2.5 2.5 0 0 1 4.8.9c0 1.7-2.4 2.2-2.4 3.6"/><path d="M12 17h.01"/></svg>';

	function addStyle() {
		if (d.getElementById('sdlg-style')) return;
		var s = d.createElement('style'); s.id = 'sdlg-style'; s.textContent = CSS;
		(d.head || d.documentElement).appendChild(s);
	}
	// The text as the browser box would show it, with HTML entities (&aelig;) written as letters.
	function plain(msg) {
		var s = (msg === undefined || msg === null) ? '' : String(msg);
		if (/&(#\d+|#x[0-9a-f]+|[a-z]+\d*);/i.test(s)) { var ta = d.createElement('textarea'); ta.innerHTML = s; s = ta.value; }
		return s;
	}

	// ---- the box --------------------------------------------------------------------------------------------------
	function show(item) {
		queue.push(item);
		if (!current) next();
	}
	function next() {
		current = queue.shift() || null;
		if (!current) {
			if (closeWanted) { closeWanted = false; nativeClose.call(w); }
			return;
		}
		if (!d.body) { d.addEventListener('DOMContentLoaded', function () { var it = current; current = null; queue.unshift(it); next(); }); return; }
		render(current);
	}
	function render(item) {
		addStyle();
		var ask = item.kind === 'confirm';
		var root = d.createElement('div'); root.id = 'sdlg-root';
		root.innerHTML = '<div class="sdlg-scrim"></div><div class="sdlg-wrap"><div class="sdlg-card" role="' + (ask ? 'alertdialog' : 'dialog')
			+ '" aria-modal="true" aria-describedby="sdlg-msg" tabindex="-1"><div class="sdlg-body"><span class="sdlg-ic">' + (ask ? ICON_ASK : ICON_INFO)
			+ '</span><p class="sdlg-msg" id="sdlg-msg"></p></div><div class="sdlg-foot"></div></div></div>';
		root.querySelector('.sdlg-msg').textContent = item.msg;
		var foot = root.querySelector('.sdlg-foot');
		var dont = null;
		if (ask && item.key) {
			var lab = d.createElement('label'); lab.className = 'sdlg-dont'; lab.title = txt('dontShowTip', '');
			dont = d.createElement('input'); dont.type = 'checkbox';
			lab.appendChild(dont); lab.appendChild(d.createTextNode(txt('dontShow', 'Vis ikke igen')));
			foot.appendChild(lab);
		}
		var cancel = null;
		if (ask) {
			cancel = d.createElement('button'); cancel.type = 'button'; cancel.className = 'sdlg-btn'; cancel.textContent = txt('cancel', 'Annullér');
			foot.appendChild(cancel);
		}
		var ok = d.createElement('button'); ok.type = 'button'; ok.className = 'sdlg-btn sdlg-primary'; ok.textContent = txt('ok', 'OK');
		foot.appendChild(ok);
		item.root = root;
		item.before = d.activeElement;
		d.body.appendChild(root);
		var answer = function (yes) { finish(yes, !!(dont && dont.checked)); };
		ok.addEventListener('click', function () { answer(true); });
		if (cancel) cancel.addEventListener('click', function () { answer(false); });
		item.keys = function (e) {
			if (!current || current !== item) return;
			if (e.key === 'Escape') { e.preventDefault(); answer(!ask); }
			else if (e.key === 'Enter' && e.target !== dont && e.target !== cancel) { e.preventDefault(); answer(true); }
			else if (e.key === 'Tab') {
				// keep the focus inside the box
				var f = [dont, cancel, ok].filter(Boolean), i = f.indexOf(d.activeElement);
				e.preventDefault(); f[(i + (e.shiftKey ? f.length - 1 : 1) + f.length) % f.length].focus();
			}
			// the page's own key handlers (Enter in a form, the scanner) do not get the keys while the box is open; an
			// accesskey acts on the key press after this keydown, and the click handler below cancels the click it makes
			if (!root.contains(e.target) || e.key === 'Enter' || e.key === 'Escape') e.stopPropagation();
		};
		w.addEventListener('keydown', item.keys, true);
		setTimeout(function () { try { ok.focus(); } catch (e) {} }, 0);
	}
	function finish(yes, dont) {
		var item = current;
		if (!item) return;
		w.removeEventListener('keydown', item.keys, true);
		if (item.root && item.root.parentNode) item.root.parentNode.removeChild(item.root);
		current = null;
		try { if (item.before && item.before.focus && d.contains(item.before)) item.before.focus(); } catch (e) {}
		// done() may show a message itself (the alerts a cancelled question held back): only move on when nothing is open
		try { if (item.done) item.done(yes, dont); } finally { if (!current) next(); }
	}
	// While a question is open, or waits behind a message, a click the browser itself makes on the page under the box
	// (an accesskey, or the Enter that submits a form straight after the change that asked) is cancelled, so the answer
	// decides, as with the browser's box. Clicks a script makes (el.click(), the action done again after OK) go through,
	// and with only a message open the page goes on, as described for alert() at the top of this file.
	w.addEventListener('click', function (e) {
		if (!e.isTrusted || !current || !current.root || current.root.contains(e.target)) return;
		if (current.kind !== 'confirm' && !queue.some(function (it) { return it.kind === 'confirm'; })) return;
		e.preventDefault();
		e.stopImmediatePropagation();
	}, true);

	// ---- alert() ----------------------------------------------------------------------------------------------------
	w.alert = function (msg) {
		var m = plain(msg);
		if (heldAlerts) { heldAlerts.push(m); return; }
		show({ kind: 'alert', msg: m });
	};

	// ---- prompt() ---------------------------------------------------------------------------------------------------
	// The browser's box asks, except while OK does an action again: then a prompt() in the event OK sends (the click,
	// submit or change done again) that asks the same question (text and default value) as the next answer that action
	// got before its question gets that answer, so the answers come back in the same order and only to their own question.
	// A prompt() that asks anything else, one the action skips this time or one in a branch it did not take, asks the
	// browser's box, as do all once the answers run out. A form OK sends again by its button's click (without
	// requestSubmit(), or when it refuses the button) gets no answers: each prompt() in it asks the browser's box.
	// Each answer is noted with its question and the event it was given in (one the user made, or the action done
	// again), so a question asked later in that event can take that event's answers along (confirm() below). A prompt()
	// in an event the page sends itself (el.click(), dispatchEvent) is not the user's action, so it is not noted; while
	// OK does the action again such an event is not the one OK sent, so its prompt() asks the browser's box again, also
	// when it asks the same question as the action's own prompt().
	// The text and the default value are converted once, as the browser converts them (a missing or undefined one is
	// '', null is 'null', an object's toString() runs once), and the browser's box gets them converted. A Symbol cannot
	// be converted: then the browser's own prompt() gets them as given and throws its own error, after converting the text
	// before it, so nothing is converted twice. The question is the two as a JSON pair, so a text and default value split
	// differently is another question.
	function questionOf(args) {
		var text = args[0] === undefined ? '' : ''.concat(args[0]);
		var value = args[1] === undefined ? '' : ''.concat(args[1]);
		return { text: text, value: value, asked: JSON.stringify([text, value]) };
	}
	function notePrompt(asked, answer) {
		var ev = w.event;
		if (ev && (ev.isTrusted || replaying)) {
			if (!prompted) {
				var list = prompted = [];
				setTimeout(function () { if (prompted === list) prompted = null; }, 0);
			}
			prompted.push({ ev: ev, asked: asked, answer: answer });
		}
		return answer;
	}
	w.prompt = function () {
		if (typeof arguments[0] === 'symbol' || typeof arguments[1] === 'symbol') return nativePrompt.apply(w, arguments);
		var q = questionOf(arguments), ev = w.event;
		// the submit OK sends is found here, from the event itself, so every listener of it finds it, also one the page
		// added at the window before the box (replayOf() below says why no other submit matches)
		if (!promptEvent && submitForm && ev && ev.type === 'submit' && ev.isTrusted && ev.target === submitForm
			&& (!('submitter' in ev) || ev.submitter === submitBy)) promptEvent = ev;
		if (promptReplies && promptReplies.length && promptReplies[0].asked === q.asked && promptEvent && ev === promptEvent) return notePrompt(q.asked, promptReplies.shift().answer);
		return notePrompt(q.asked, nativePrompt.call(w, q.text, q.value));
	};

	// ---- confirm() --------------------------------------------------------------------------------------------------
	function keyOf(ev) {
		var t = ev && (ev.type === 'submit' && ev.submitter ? ev.submitter : ev.target);
		var el = (t && t.closest) ? t.closest('[data-confirm-key]') : null;
		if (!el && ev && ev.type === 'submit' && ev.target && ev.target.closest) el = ev.target.closest('[data-confirm-key]');
		return el ? String(el.getAttribute('data-confirm-key')) : '';
	}
	// The click el.click() sends: a PointerEvent without a pointer (pointerId -1) where the browser has them, else a MouseEvent.
	function clickEvent() {
		var init = { bubbles: true, cancelable: true, composed: true, view: w };
		if (typeof w.PointerEvent !== 'function') return new MouseEvent('click', init);
		init.pointerId = -1;
		return new PointerEvent('click', init);
	}
	// How to do the user's action again after OK, or null when it cannot be done again (then the browser's box asks).
	// It sets promptEvent to the event OK sends (for a submit requestSubmit() sends, submitForm, and prompt() takes the
	// submit from it), so only a prompt() in that very event gets the action's answers; a submit the button's click sends
	// gets none.
	function replayOf(ev) {
		if (!ev || !(ev.isTrusted || replaying)) return null;
		var t = ev.target;
		if (!t || t.nodeType !== 1) return null;
		if (ev.type === 'submit' && t.tagName === 'FORM') {
			var sub = ev.submitter || null;
			return function () {
				if (!t.isConnected) return;
				// requestSubmit() fires the submit event itself, trusted, and while it checks the fields and fires it the
				// browser starts no other submit of this form (the HTML standard's "firing submission events": a
				// requestSubmit() or a submit button's click in a listener is ignored), so the trusted submit at the form
				// while it runs is the one OK sends; an event the page sends itself (dispatchEvent) is not trusted. A browser
				// without that rule would let a listener's requestSubmit() of the same form fire a second one inside the
				// first, and a prompt() in it would be taken for one in the submit OK sends.
				// Without requestSubmit(), or when it refuses the button (no longer this form's submit button), the
				// button's click sends the form as before, and no submit is taken for the one OK sends: each prompt()
				// in it asks the browser's box again, as it did before the box kept answers. That click's submit cannot
				// be told from one a listener of the click starts by sending a click of its own at the same button: both
				// are trusted, at this form and from this button, and the listener's comes first, so it would take the
				// answers and the submit OK sends would ask the browser. t.submit() fires no submit event.
				if (t.requestSubmit) {
					submitForm = t; submitBy = sub;
					try { if (sub) t.requestSubmit(sub); else t.requestSubmit(); return; } catch (e) {}
					finally { submitForm = submitBy = null; }
				}
				if (sub && sub.click) sub.click(); else t.submit();
			};
		}
		if (ev.type === 'click') {
			return function () {
				// as el.click(): nothing for an element taken off the page or a control disabled meanwhile (a disabled
				// fieldset is no control: it gets its click), else its click with what the browser does on it (a checkbox
				// or radio set, a link followed, a form sent by its submit button, a label's control clicked); the event
				// is made here, so it is the event OK sends and not one a listener of the page sends at the same element first
				if (!t.isConnected || (t.matches && t.matches(':disabled') && t.tagName !== 'FIELDSET')) return;
				promptEvent = clickEvent();
				t.dispatchEvent(promptEvent);
			};
		}
		if (ev.type === 'change' && ('value' in t)) {
			var val = t.value, chk = t.checked;
			return function () {
				if (!t.isConnected) return;
				t.value = val; if (typeof chk === 'boolean') t.checked = chk;   // as the user left it, before a cancel branch undid it
				promptEvent = new Event('change', { bubbles: true });
				t.dispatchEvent(promptEvent);
			};
		}
		return null;
	}
	function remember(key) {
		hidden[key] = true;
		if (!CFG.prefUrl) return;
		try {
			fetch(CFG.prefUrl, { method: 'POST', keepalive: true, credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': String(CFG.csrf || '') },
				body: JSON.stringify({ key: key, hide: true }) });
		} catch (e) {}
	}
	w.confirm = function (msg) {
		var m = plain(msg);
		var at = approved[m];
		if (at && Date.now() - at < 5000) return true;
		var ev = w.event;
		var key = keyOf(ev);
		if (key && hidden[key]) return true;
		var replay = replayOf(ev);
		if (!replay) return nativeConfirm.call(w, msg);
		var held = heldAlerts = [];
		setTimeout(function () { if (heldAlerts === held) heldAlerts = null; }, 0);
		// the answers prompt() gave earlier in this event go with the question, for the action done again after OK
		var answers = [];
		if (prompted) prompted.forEach(function (p) { if (p.ev === ev) answers.push({ asked: p.asked, answer: p.answer }); });
		show({ kind: 'confirm', msg: m, key: key, done: function (yes, dont) {
			var replies = answers;
			answers = [];
			if (!yes) { held.forEach(function (a) { show({ kind: 'alert', msg: a }); }); return; }
			if (dont && key) remember(key);
			approved[m] = Date.now();
			setTimeout(function () { if (approved[m] && Date.now() - approved[m] >= 5000) delete approved[m]; }, 5100);
			replaying = true;
			promptReplies = replies;
			try { replay(); } finally { replaying = false; promptReplies = null; promptEvent = null; }
		} });
		return false;
	};

	// ---- a message still open when the page moves on, and window.close() ----------------------------------------------
	w.close = function () {
		if (current || queue.length) { closeWanted = true; return; }
		return nativeClose.call(w);
	};
	w.addEventListener('pagehide', function () {
		var msgs = [];
		if (current && current.kind === 'alert') msgs.push(current.msg);
		queue.forEach(function (it) { if (it.kind === 'alert') msgs.push(it.msg); });
		if (!msgs.length) return;
		try { sessionStorage.setItem(CARRY, JSON.stringify({ at: Date.now(), msgs: msgs })); } catch (e) {}
	});
	function carried() {
		var c = null;
		try { c = JSON.parse(sessionStorage.getItem(CARRY) || 'null'); sessionStorage.removeItem(CARRY); } catch (e) {}
		if (c && c.msgs && Date.now() - c.at < 20000) c.msgs.forEach(function (m) { show({ kind: 'alert', msg: String(m) }); });
	}
	if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', carried); else carried();

	w.SaldiDialog = {
		alert: function (msg) { return new Promise(function (res) { show({ kind: 'alert', msg: plain(msg), done: function () { res(); } }); }); },
		confirm: function (msg, opts) {
			var key = (opts && opts.key) ? String(opts.key) : '';
			if (key && hidden[key]) return Promise.resolve(true);
			return new Promise(function (res) { show({ kind: 'confirm', msg: plain(msg), key: key, done: function (yes, dont) { if (yes && dont && key) remember(key); res(!!yes); } }); });
		},
		nativeAlert: nativeAlert,
		nativeConfirm: nativeConfirm
	};
})(window, document);
