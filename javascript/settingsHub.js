// javascript/settingsHub.js
// 20261101 CL/ASR Created: behaviour of the settings hub "C - Fold ud" (systemdata/settings.php; the owner's choice of 1 Nov 2026,
//                 _prototypes/settings_hub/?v=c). The panels go in four columns from a 1500 px wide grid, two below (Virksomhed over
//                 Salg, Finans over Køb & lager), one under 860 px; each column stacks its own panels.
//                 A sub-group row folds open and shut on click, Enter or Space (a button with aria-expanded); several can be open,
//                 the open ones are remembered in localStorage (not while searching), all are closed the first time.
//                 Search filters in place: sub-groups with hits fold open, the word is marked (also across accents), a row found by
//                 an old menu name says so, and single settings from settingsSearch.php are listed under their row.
//                 Enter or Down in the search goes to the first hit, Esc clears it, / jumps to it; ?g=<group> marks that panel.
// 20261101 CL/ASR Review: the code is plain ASCII (the accent range as \u escapes), so it also runs on a latin-1 page, where the browser
//                 reads this file as windows-1252 and the literal marks made the regular expression invalid and stopped the whole hub.
(function () {
	'use strict';
	var root = document.getElementById('sh-hub');
	var grid = document.getElementById('sh-grid');
	var input = document.getElementById('sh-search');
	if (!root) {
		return;
	}
	var cfg = {};
	try {
		cfg = JSON.parse(document.getElementById('sh-cfg').textContent) || {};
	} catch (e) { /* no data: the rows still fold */ }
	var T = cfg.t || {};
	var STORE = cfg.store || 'saldi.settingsHub.open';
	var keyRow = cfg.keyRow || {};
	var live = document.getElementById('sh-live');
	var other = document.getElementById('sh-other');
	var otherList = document.getElementById('sh-other-list');

	function fold(s) {
		s = String(s || '').toLowerCase();
		// escapes, not the marks themselves: on a latin-1 page the browser reads this file as windows-1252
		return s.normalize ? s.normalize('NFKD').replace(/[\u0300-\u036f]/g, '') : s;
	}
	function esc(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	// text with every hit of q (already folded) in <mark>, also where the text has accents q lacks
	function hl(text, q) {
		text = String(text);
		if (!q) {
			return esc(text);
		}
		var f = '', map = [], i, j;
		for (i = 0; i < text.length; i++) {
			var ch = fold(text[i]);
			for (j = 0; j < ch.length; j++) {
				f += ch[j];
				map.push(i);
			}
		}
		var out = '', last = 0, from = 0, idx;
		while ((idx = f.indexOf(q, from)) !== -1) {
			var a = map[idx], b = map[idx + q.length - 1] + 1;
			if (a >= last) {
				out += esc(text.slice(last, a)) + '<mark>' + esc(text.slice(a, b)) + '</mark>';
				last = b;
			}
			from = idx + q.length;
		}
		return out + esc(text.slice(last));
	}
	// a text from the search endpoint as plain text: some of its labels hold HTML entities (&apos;) written for the old pages
	var decoder = document.createElement('textarea');
	function plain(s) {
		decoder.innerHTML = String(s || '');
		return decoder.value;
	}
	function each(list, fn) {
		Array.prototype.forEach.call(list, fn);
	}
	// texts that can be marked: their own words are kept to restore them
	function marker(el) {
		return el ? { el: el, text: el.textContent } : null;
	}
	function mark(m, q) {
		if (m) {
			if (q) {
				m.el.innerHTML = hl(m.text, q);
			} else {
				m.el.textContent = m.text;
			}
		}
	}

	/* ---- the page as data: panels > sub-groups > rows ---- */
	var panels = [];
	if (grid) {
		each(grid.querySelectorAll('.sh-panel'), function (p) {
			panels.push(p);
		});
		panels.sort(function (a, b) {
			return (+a.getAttribute('data-i')) - (+b.getAttribute('data-i'));
		});
	}
	var subsById = {};
	var model = panels.map(function (p) {
		var title = p.querySelector('.sk-panel-t');
		return {
			el: p,
			id: p.id.replace(/^sh-g-/, ''),
			empty: p.querySelector('.sh-empty'),
			label: title ? title.textContent : '',
			subs: Array.prototype.map.call(p.querySelectorAll('.sh-item'), function (it) {
				var s = {
					el: it,
					id: it.getAttribute('data-sub'),
					name: fold((it.querySelector('.sk-list-t') || {}).textContent),
					locked: it.classList.contains('sh-locked'),
					btn: it.querySelector('.sh-btn'),
					marks: Array.prototype.map.call(it.querySelectorAll(':scope > .sk-list-row [data-hl]'), marker),
					rows: Array.prototype.map.call(it.querySelectorAll('.sh-row'), function (li) {
						var old = [];
						try {
							old = JSON.parse(li.getAttribute('data-old') || '[]');
						} catch (e) { /* keep none */ }
						var tx = li.querySelector('.sh-link-tx');
						return {
							el: li,
							id: li.getAttribute('data-row'),
							terms: fold(li.getAttribute('data-terms')),
							old: old,
							tx: tx,
							t: marker(li.querySelector(':scope > .sh-link .sh-link-t')),
							d: marker(li.querySelector(':scope > .sh-link .sh-link-d')),
							deep: li.querySelector(':scope > .sh-deep'),
							pages: Array.prototype.map.call(li.querySelectorAll('.sh-page2'), function (pl) {
								return { el: pl, terms: fold(pl.getAttribute('data-terms')), t: marker(pl.querySelector('.sh-link-t')) };
							}),
							was: null,
							found: []
						};
					})
				};
				s.body = s.btn ? document.getElementById(s.btn.getAttribute('aria-controls')) : null;
				subsById[s.id] = s;
				return s;
			})
		};
	});

	/* ---- open rows, remembered between visits ---- */
	var openSet = {};
	try {
		(JSON.parse(window.localStorage.getItem(STORE) || '[]') || []).forEach(function (id) {
			openSet[id] = true;
		});
	} catch (e) { /* private mode or bad data: all closed */ }
	function saveOpen() {
		try {
			window.localStorage.setItem(STORE, JSON.stringify(Object.keys(openSet)));
		} catch (e) { /* private mode */ }
	}
	function setOpen(s, open) {
		if (!s.btn) {
			return;
		}
		s.btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (s.body) {
			s.body.hidden = !open;
		}
	}

	/* ---- columns: four, two (Virksomhed over Salg, Finans over Køb & lager) or one ---- */
	function layout() {
		if (!grid || !panels.length) {
			return;
		}
		var w = grid.getBoundingClientRect().width;
		if (!w) {
			return;
		}
		var mode = w < 860 ? 'c1' : (w >= 1500 ? 'c4' : 'c2');
		if (mode === grid.getAttribute('data-layout')) {
			return;
		}
		var cols = mode === 'c4' ? panels.map(function (p) { return [p]; })
			: mode === 'c2' ? [[panels[0], panels[2]], [panels[1], panels[3]]] : [panels];
		var active = document.activeElement;
		grid.textContent = '';
		var n = 0;
		cols.forEach(function (c) {
			var col = document.createElement('div');
			col.className = 'sh-col';
			c.forEach(function (p) {
				if (p) {
					col.appendChild(p);
				}
			});
			if (col.firstChild) {
				grid.appendChild(col);
				n++;
			}
		});
		grid.setAttribute('data-layout', mode);
		grid.style.setProperty('--sh-n', String(n));
		if (active && grid.contains(active) && document.activeElement !== active) {
			active.focus({ preventScroll: true });
		}
	}

	/* ---- search ---- */
	var state = { q: '', raw: '', remote: null };

	// "tidligere “Kontoindstillinger”" after a row found by an old name only (not by its own name or text)
	function wasFor(r, q) {
		var name = '';
		if (q && fold(r.t.text + ' ' + r.d.text).indexOf(q) === -1) {
			for (var i = 0; i < r.old.length; i++) {
				if (fold(r.old[i]).indexOf(q) !== -1) {
					name = r.old[i];
					break;
				}
			}
		}
		if (name && !r.was) {
			r.was = document.createElement('em');
			r.was.className = 'sh-was';
			r.tx.appendChild(r.was);
		}
		if (r.was) {
			r.was.hidden = !name;
			r.was.innerHTML = name ? esc(T.was || '\u201c%s\u201d').replace('%s', hl(name, q)) : '';
		}
	}

	// a link one step in under a row: a single setting the search found
	function deepLink(url, label, q) {
		var li = document.createElement('li');
		li.className = 'sh-found';
		li.innerHTML = '<a class="sh-link sh-deep-link" href="' + esc(url) + '"><i class="bx bx-subdirectory-right sh-deep-ic" aria-hidden="true"></i>' +
			'<span class="sh-link-tx"><span class="sh-link-t">' + hl(plain(label), q) + '</span></span><i class="bx bx-chevron-right sk-list-chev" aria-hidden="true"></i></a>';
		return li;
	}

	function apply() {
		var q = state.q;
		var remote = (state.remote && state.remote.q === q) ? state.remote : null;
		var hits = 0;
		model.forEach(function (p) {
			var shown = 0;
			p.subs.forEach(function (s) {
				s.marks.forEach(function (m) { mark(m, q); });
				if (s.locked) {
					s.el.hidden = !!q && s.name.indexOf(q) === -1;
					shown += s.el.hidden ? 0 : 1;
					return;
				}
				// a hit on the sub-group's name shows all its rows; its short text only sums up the rows, so it is not searched
				var subHit = !!q && s.name.indexOf(q) !== -1;
				var n = 0;
				s.rows.forEach(function (r) {
					var deep = 0;
					r.pages.forEach(function (pg) {
						var on = !!q && pg.terms.indexOf(q) !== -1;
						pg.el.hidden = !on;
						mark(pg.t, on ? q : '');
						deep += on ? 1 : 0;
					});
					r.found.forEach(function (li) { li.parentNode.removeChild(li); });
					r.found = [];
					var fields = (remote && remote.fields[r.id]) || [];
					if (fields.length) {
						if (!r.deep) {
							r.deep = document.createElement('ul');
							r.deep.className = 'sh-deep';
							r.el.appendChild(r.deep);
						}
						fields.slice(0, 3).forEach(function (f) {
							var li = deepLink(f.url, f.label, q);
							r.deep.appendChild(li);
							r.found.push(li);
						});
						deep += r.found.length;
					}
					if (r.deep) {
						r.deep.hidden = deep === 0;
					}
					var own = !!q && r.terms.indexOf(q) !== -1;
					var on = !q || subHit || own || deep > 0 || !!(remote && remote.rows[r.id]);
					r.el.hidden = !on;
					mark(r.t, q);
					mark(r.d, q);
					wasFor(r, own ? q : '');
					if (on) {
						n++;
						hits += q ? 1 + deep : 0;
					}
				});
				s.el.hidden = !!q && n === 0;
				setOpen(s, q ? n > 0 : !!openSet[s.id]);
				shown += s.el.hidden ? 0 : 1;
			});
			if (p.empty) {
				p.empty.hidden = !q || shown > 0;
			}
		});
		hits += renderOther(q, remote);
		if (live) {
			live.textContent = !q ? '' : (hits ? (T.count || '%s').replace('%s', hits) : (T.none || ''));
		}
	}

	// hits that are not on the panels: personal settings (and their single settings), Diverse, Tjeklister
	function renderOther(q, remote) {
		if (!other || !otherList) {
			return 0;
		}
		otherList.textContent = '';
		var n = 0;
		if (q) {
			(cfg.leftOut || []).forEach(function (o) {
				var own = fold(o.label + ' | ' + o.terms).indexOf(q) !== -1;
				var fields = (o.key === 'userSettings' && remote) ? remote.personal.slice(0, 5) : [];
				if (!own && !fields.length) {
					return;
				}
				var li = document.createElement('li');
				var hint = (o.reason && o.reason !== T.out) ? '<span class="sh-link-d">' + esc(o.reason) + '</span>' : '';
				li.innerHTML = '<a class="sh-link" href="' + esc(o.url) + '"><span class="sh-link-tx"><span class="sh-link-t">' + hl(o.label, q) + '</span>' + hint + '</span>' +
					'<span class="sk-list-meta"></span><i class="bx bx-chevron-right sk-list-chev" aria-hidden="true"></i></a>';
				if (fields.length) {
					var ul = document.createElement('ul');
					ul.className = 'sh-deep';
					fields.forEach(function (f) { ul.appendChild(deepLink(f.url, f.label, q)); });
					li.appendChild(ul);
				}
				otherList.appendChild(li);
				n += 1 + fields.length;
			});
		}
		other.hidden = n === 0;
		return n;
	}

	// single settings and registry keywords from the search endpoint (as the old hub's dropdown), by the row they belong to
	var timer = 0;
	function lookup() {
		window.clearTimeout(timer);
		var q = state.q, raw = state.raw;
		if (raw.length < 2) {
			return;
		}
		timer = window.setTimeout(function () {
			fetch('settingsSearch.php?search=' + encodeURIComponent(raw), { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (state.q !== q || !data) {
						return;
					}
					var remote = { q: q, rows: {}, fields: {}, personal: [] };
					// a page found by its name or by one search word that holds the whole query; not by words scattered over several
					(data.results || []).forEach(function (e) {
						if (keyRow[e.key] && (e.matchType === 'label' || fold(e.matchedTerm).indexOf(q) !== -1)) {
							remote.rows[keyRow[e.key]] = true;
						}
					});
					(data.fields || []).forEach(function (f) {
						if (f.personal) {
							remote.personal.push(f);
							return;
						}
						var m = /[?&]s=([^&#]+)/.exec(f.url || '');
						var row = m ? keyRow[decodeURIComponent(m[1])] : '';
						if (row) {
							(remote.fields[row] = remote.fields[row] || []).push(f);
						}
					});
					state.remote = remote;
					apply();
				})
				.catch(function () { /* the panels' own hits stay */ });
		}, 180);
	}

	function setQuery(value) {
		state.raw = String(value || '').trim().toLowerCase();
		var q = fold(state.raw);
		if (q === state.q) {
			return;
		}
		state.q = q;
		apply();
		lookup();
	}

	// Enter in the search: a page whose own name has the word, else a page found by its text or old name, else a second page or
	// single setting with the word in its name, else the first link shown
	function firstHit() {
		var q = state.q, best = [null, null, null, null];
		each(root.querySelectorAll('#sh-grid .sh-link, #sh-other .sh-link'), function (a) {
			if (a.offsetParent === null) {
				return;
			}
			var row = a.parentNode.classList.contains('sh-row') ? a.parentNode : null;
			var named = fold((a.querySelector('.sh-link-t') || a).textContent).indexOf(q) !== -1;
			var rank = row ? (named ? 0 : (fold(row.getAttribute('data-terms')).indexOf(q) !== -1 ? 1 : 3)) : (named ? 2 : 3);
			best[rank] = best[rank] || a;
		});
		return best[0] || best[1] || best[2] || best[3];
	}

	/* ---- events ---- */
	if (grid) {
		grid.addEventListener('click', function (e) {
			var btn = e.target.closest('button.sh-btn');
			if (!btn) {
				return;
			}
			var s = subsById[btn.closest('.sh-item').getAttribute('data-sub')];
			var open = btn.getAttribute('aria-expanded') !== 'true';
			setOpen(s, open);
			if (!state.q) {
				if (open) {
					openSet[s.id] = true;
				} else {
					delete openSet[s.id];
				}
				saveOpen();
			}
		});
	}
	if (input) {
		input.addEventListener('input', function () { setQuery(input.value); });
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				if (input.value !== '') {
					e.preventDefault();
					e.stopPropagation();
					input.value = '';
					setQuery('');
				}
			} else if ((e.key === 'Enter' || e.key === 'ArrowDown') && state.q) {
				var first = firstHit();
				if (first) {
					e.preventDefault();
					first.focus();
				}
			}
		});
	}

	var dialog = document.getElementById('sh-dialog'), backdrop = document.getElementById('sh-backdrop');
	var closeBtn = document.getElementById('sh-dialog-close'), opener = null;
	function toggleDialog(open, from) {
		if (!dialog) {
			return;
		}
		dialog.hidden = !open;
		backdrop.hidden = !open;
		if (open) {
			opener = from || null;
			closeBtn.focus();
		} else if (opener) {
			opener.focus();
		}
	}
	each(root.querySelectorAll('[data-keys]'), function (b) {
		b.addEventListener('click', function () { toggleDialog(true, b); });
	});
	if (dialog) {
		backdrop.addEventListener('click', function () { toggleDialog(false); });
		closeBtn.addEventListener('click', function () { toggleDialog(false); });
		dialog.addEventListener('keydown', function (e) {
			if (e.key === 'Tab') {
				e.preventDefault();
				closeBtn.focus();
			}
		});
	}
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && dialog && !dialog.hidden) {
			toggleDialog(false);
			return;
		}
		var el = document.activeElement;
		var typing = el && (/^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName) || el.isContentEditable);
		if (e.key === '/' && !typing && !e.ctrlKey && !e.metaKey && !e.altKey && input && (!dialog || dialog.hidden)) {
			e.preventDefault();
			input.focus();
			input.select();
		}
	});

	/* ---- start: columns, the remembered open rows, a search kept by the browser, ?g= ---- */
	layout();
	apply();
	if (input && input.value) {
		setQuery(input.value);
	}
	if (grid && window.ResizeObserver) {
		var frame = 0;
		new window.ResizeObserver(function () {
			window.cancelAnimationFrame(frame);
			frame = window.requestAnimationFrame(layout);
		}).observe(grid);
	} else {
		window.addEventListener('resize', layout);
	}
	var g = (/[?&]g=([a-z_]+)/.exec(window.location.search) || [])[1];
	var target = g ? document.getElementById('sh-g-' + g) : null;
	if (target) {
		target.classList.add('sh-hit');
		if (target.scrollIntoView) {
			target.scrollIntoView({ block: 'nearest' });
		}
		window.setTimeout(function () { target.classList.remove('sh-hit'); }, 1400);
	}
})();
