// ---- javascript/dashboard.js --- ver 5.1.0 --- 2026.11.01 ---
// 20261008 Sawaneh Oversigt (index/dashboard.php) after Adam's prototype_dashboard_tema.html v5: counting numbers,
// 20261009 Sawaneh Prototype v8.13: blocks in the user's order (grip drag with pointer events, Alt+Up/Down, aria-live), one Ring
//                  builder for both rings, the Ubetalte salgsfakturaer card (filter by group, row click opens the invoice).
// 20261009 Sawaneh Axis text at a fixed 0.78rem (--axis per chart), charts drawn after document.fonts.ready (addendum 3 §3.1, §5.1).
//                  sparklines, bar charts (this year, last year, best), the customers-per-hour heatmap, the item-group
//                  ring, tooltips, the "Rediger oversigt" drawer (changes saved at once) and the entry animation.
//                  Data and texts come from window.SALDI_DASH, written by the page.
// 20261101 CL/ASR Prototype D: the bar charts and the heatmap are drawn at the box's real width (12 px text, fixed height) and redrawn when the width changes, the white tooltip of the prototype, the month table built on first use, the due groups filter the invoice list, "Rediger oversigt" opens as the kit's panel with the cards of Nøgletal and Små tal shown.
//                 Counting numbers, sparklines, rings and the entry animation are gone (calm motion, prototype kit).
//                 Months and days after today show "–" in the tooltip and the table instead of 0 and a 100 % drop.
// 20261101 CL/ASR Review: a chart redrawn while hidden (the compare select changed in table view) is redrawn when it shows again, not left stale.
(function () {
	'use strict';
	const D = window.SALDI_DASH || {};
	const T = D.txt || {};
	const root = document.documentElement, $ = (s) => document.querySelector(s), $$ = (s) => [...document.querySelectorAll(s)];
	const h = (n, cls, txt) => { const e = document.createElement(n); if (cls) e.className = cls; if (txt != null) e.textContent = txt; return e; };
	const esc = (s) => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	const locale = D.locale || 'da-DK';
	const da = (v, o) => Number(v).toLocaleString(locale, o);
	const kr = (v) => da(Math.round(v)) + ' kr';
	const kr2 = (v) => da(v, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kr';
	const fmtAxis = (v) => v >= 1e6 ? da(v / 1e6, { maximumFractionDigits: 2 }) + ' ' + (T.mioShort || 'mio.') : v >= 1e3 ? da(v / 1e3, { maximumFractionDigits: 0 }) + ' ' + (T.thousandShort || 't.') : String(Math.round(v));
	const pct = (v) => da(Math.abs(v), { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' %';
	const tpl = (s, ...a) => String(s || '').replace(/%s/g, () => a.shift());
	const remScale = () => (parseFloat(getComputedStyle(root).fontSize) || 16) / 16;

	// Theme from the shell (chip menu) while the page is open.
	window.addEventListener('message', (e) => { if (e.origin === location.origin && e.data && e.data.type === 'saldi:theme' && typeof e.data.theme === 'string') { root.dataset.theme = e.data.theme; } });

	/* ---- tooltip: one small popover for every [data-tip] (bars, heatmap cells, the stacked bar) ---- */
	const tip = $('#tip');
	function tipHtml(title, rows, foot) {
		return '<div class="tt">' + esc(title) + '</div>' + rows.map((r) => '<div class="tr"><i class="db-sw ' + r[0] + '"></i>' + esc(r[1]) + '<b>' + esc(r[2]) + '</b></div>').join('')
			+ (foot ? '<div class="tr ' + foot[0] + '">' + esc(foot[1]) + '</div>' : '');
	}
	function showTip(node) {
		if (!tip) return;
		tip.innerHTML = node.getAttribute('data-tip');
		const r = node.getBoundingClientRect(), w = tip.offsetWidth, th = tip.offsetHeight;
		const x = Math.max(8, Math.min(innerWidth - w - 8, r.left + r.width / 2 - w / 2));
		let y = r.top + (node.classList.contains('db-hit') ? r.height * .35 : 0) - th - 8;
		if (y < 8) y = r.bottom + 8;
		tip.style.left = x + 'px'; tip.style.top = y + 'px'; tip.classList.add('on');
	}
	function hideTip() { if (tip) tip.classList.remove('on'); }
	const tipFor = (e) => { const n = e.target.closest && e.target.closest('[data-tip]'); if (n) showTip(n); else hideTip(); };
	document.addEventListener('mouseover', tipFor);
	document.addEventListener('focusin', tipFor);
	document.addEventListener('scroll', hideTip, true);

	/* ---- charts are drawn at the box's real width (text stays 12 px) and redrawn when the width changes ---- */
	const charts = [];
	/* a forced redraw of a hidden chart (table view, block switched off) is kept for when it shows again */
	function render(c, force) { const w = c.box.clientWidth; if (!w) { if (force) c.w = -1; return; } if (!force && w === c.w) return; c.w = w; c.draw(c.box); }
	const ro = 'ResizeObserver' in window ? new ResizeObserver((es) => es.forEach((e) => { const c = charts.find((x) => x.box === e.target); if (c) render(c); })) : null;
	function chart(box, draw) { const c = { box, draw, w: -1 }; charts.push(c); return c; }
	window.addEventListener('resize', () => charts.forEach((c) => render(c)));
	new MutationObserver(() => charts.forEach((c) => render(c, true))).observe(root, { attributes: true, attributeFilter: ['data-size'] });

	function niceScale(raw) {   // the tightest round axis with 4-5 intervals
		if (!(raw > 0)) raw = 1000;
		let best = null;
		[4, 5].forEach((ticks) => {
			const pow = Math.pow(10, Math.floor(Math.log10(raw / ticks)));
			const step = [1, 2, 2.5, 5, 10].map((m) => m * pow).find((v) => v * ticks >= raw);
			if (!best || step * ticks < best.max) best = { max: step * ticks, ticks };
		});
		return best;
	}
	function topRound(x, y, w, hh, r) {   // a bar with a rounded data end, square on the baseline
		if (!(hh > 0)) return '';
		r = Math.min(r, hh, w / 2);
		return 'M' + x + ',' + (y + hh) + 'V' + (y + r) + 'Q' + x + ',' + y + ' ' + (x + r) + ',' + y + 'H' + (x + w - r) + 'Q' + (x + w) + ',' + y + ' ' + (x + w) + ',' + (y + r) + 'V' + (y + hh) + 'Z';
	}
	/* the periods after today have no figure yet: null (no bar, "–") */
	const toDate = (arr, n) => (arr || []).map((v, i) => (n != null && i >= n ? null : v));
	/* Grouped bars, this year against last year; o: labels, now (null = not yet), last, showLast, H, nowName, lastName, aria */
	function barChart(box, o) {
		const k = remScale(), W = Math.max(280, Math.floor(box.clientWidth)), H = Math.round(o.H * k);
		const m = { t: Math.round(20 * k), r: 4, b: Math.round(22 * k), l: Math.round(50 * k) }, pw = W - m.l - m.r, ph = H - m.t - m.b, base = m.t + ph;
		const n = o.labels.length, slot = pw / n, bw = Math.min(16, Math.floor(slot * (o.showLast ? .28 : .4)));
		const vals = o.now.filter((v) => v != null).concat(o.showLast ? o.last : []).map(Number).filter((v) => isFinite(v));
		const sc = niceScale(Math.max(0, ...vals)), ticks = sc.ticks, max = sc.max;
		let best = -1, bv = 0;
		o.now.forEach((v, i) => { if (v != null && +v > bv) { bv = +v; best = i; } });
		let s = '<svg class="db-chart" width="' + W + '" height="' + H + '" viewBox="0 0 ' + W + ' ' + H + '" role="group" aria-label="' + esc(o.aria) + '">';
		for (let t = 0; t <= ticks; t++) {
			const y = Math.round(base - ph / ticks * t) + .5;
			if (t) s += '<line class="db-gl" x1="' + m.l + '" x2="' + (W - m.r) + '" y1="' + y + '" y2="' + y + '"/>';
			s += '<text x="' + (m.l - 10) + '" y="' + (y + 4) + '" text-anchor="end">' + esc(fmtAxis(max / ticks * t)) + '</text>';
		}
		s += '<line class="db-base" x1="' + m.l + '" x2="' + (W - m.r) + '" y1="' + (base + .5) + '" y2="' + (base + .5) + '"/>';
		o.labels.forEach((lab, i) => {
			const x0 = m.l + slot * i, cx = Math.round(x0 + slot / 2), yet = o.now[i] != null, a = +o.now[i] || 0, b = +o.last[i] || 0;
			const hn = Math.max(0, Math.round(a / max * ph)), hl = Math.max(0, Math.round(b / max * ph));
			const xn = o.showLast ? cx + 1 : cx - bw / 2, xl = cx - bw - 1;
			const rows = [[i === best ? 'sw-best' : 'sw-now', o.nowName, yet ? kr(a) : '–']];
			if (o.showLast) rows.push(['sw-last', o.lastName, kr(b)]);
			const d = yet && o.showLast && b ? (a - b) / Math.abs(b) * 100 : null;
			const foot = d == null ? null : [d >= 0 ? 'db-up' : 'db-down', (d >= 0 ? '▲ ' : '▼ ') + pct(d) + ' ' + (T.vsLast || 'mod sidste år')];
			s += '<g class="db-slot"><g class="db-bars">' + (o.showLast ? '<path class="b-last" d="' + topRound(xl, base - hl, bw, hl, 3) + '"/>' : '')
				+ '<path class="' + (i === best ? 'b-best' : 'b-now') + '" d="' + topRound(xn, base - hn, bw, hn, 3) + '"/></g>'
				+ '<text x="' + cx + '" y="' + (base + Math.round(16 * k)) + '" text-anchor="middle">' + esc(lab) + '</text>'
				+ '<rect class="db-hit" x="' + x0 + '" y="' + m.t + '" width="' + slot + '" height="' + (ph + m.b) + '" tabindex="0" role="img"'
				+ ' aria-label="' + esc(lab + ': ' + o.nowName + ' ' + (yet ? kr(a) : (T.noRevenue || '–')) + (o.showLast ? ', ' + o.lastName + ' ' + kr(b) : '')) + '"'
				+ ' data-tip="' + esc(tipHtml(lab, rows, foot)) + '"/></g>';
		});
		if (best >= 0) {
			const cx = Math.round(m.l + slot * best + slot / 2), bx = o.showLast ? cx + 1 + bw / 2 : cx;
			s += '<text class="db-best" x="' + bx + '" y="' + (base - Math.round(bv / max * ph) - 6) + '" text-anchor="middle">' + esc(fmtAxis(bv)) + '</text>';
		}
		box.innerHTML = s + '</svg>';
	}
	/* Heatmap: weekdays x 24 hours, one blue ramp in 7 steps (--q1 ... --q7), nothing = --q0 */
	function heatmap(box, data) {
		const k = remScale(), days = D.days || [], W = Math.max(260, Math.floor(box.clientWidth));
		const lw = Math.round(36 * k), top = 2, gap = 2, cw = (W - lw) / 24, ch = Math.round(30 * k), H = top + 7 * ch + Math.round(22 * k);
		let max = 0;
		data.forEach((row) => row.forEach((v) => { if (+v > max) max = +v; }));
		let s = '<svg class="db-chart" width="' + W + '" height="' + H + '" viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="' + esc(box.dataset.label || '') + '">';
		data.forEach((row, di) => {
			s += '<text x="0" y="' + (top + di * ch + ch / 2 + 4) + '">' + esc(days[di] || '') + '</text>';
			row.forEach((v, hr) => {
				const q = !(v > 0) || max <= 0 ? 0 : Math.max(1, Math.ceil(v / max * 7));
				const lab = (days[di] || '') + ' ' + (T.at || 'kl.') + ' ' + String(hr).padStart(2, '0') + '–' + String(hr + 1).padStart(2, '0');
				s += '<rect class="db-cellr" x="' + (lw + hr * cw).toFixed(1) + '" y="' + (top + di * ch) + '" width="' + (cw - gap).toFixed(1) + '" height="' + (ch - gap) + '" rx="2"'
					+ ' fill="var(--q' + q + ')" data-tip="' + esc(tipHtml(lab, [['q' + Math.max(q, 1), T.avgCust || 'Gns. kunder pr. time', da(+v || 0, { maximumFractionDigits: 1 })]])) + '"/>';
			});
		});
		[0, 6, 12, 18, 23].forEach((hr) => { s += '<text x="' + (lw + hr * cw + (cw - gap) / 2).toFixed(1) + '" y="' + (top + 7 * ch + Math.round(14 * k)) + '" text-anchor="middle">' + String(hr).padStart(2, '0') + '</text>'; });
		box.innerHTML = s + '</svg>';
	}

	/* ---- Omsætning pr. måned: compare select, "Vis som tabel" ---- */
	const M = D.month || null;
	if (M && $('#monthChart')) {
		let showLast = true;
		const mNow = toDate(M.now, M.toDate);
		const mc = chart($('#monthChart'), (box) => barChart(box, { labels: M.labels, now: mNow, last: M.last, showLast, H: 240, nowName: M.nowLabel, lastName: M.lastLabel,
			aria: ($('[data-block="revgraph"] .db-t') || {}).textContent || '' }));
		const cmp = $('#cmpSel');
		if (cmp) cmp.addEventListener('change', (e) => { showLast = e.target.value === 'both'; const leg = $('#legLast'); if (leg) leg.hidden = !showLast; render(mc, true); });
		const tb = $('#monthTbl'), tog = $('#tblToggle'), view = $('#monthView');
		if (tb && tog && view) {
			tog.addEventListener('click', () => {
				const showT = tb.hidden;
				if (showT && !tb.firstChild) {
					tb.innerHTML = '<table class="dt"><thead><tr><th>' + esc(T.month || 'Måned') + '</th><th class="dt-num">' + esc(M.nowLabel) + '</th><th class="dt-num">' + esc(M.lastLabel) + '</th><th class="dt-num">' + esc(T.change || 'Ændring') + '</th></tr></thead><tbody>'
						+ M.labels.map((mth, i) => {
							const yet = mNow[i] != null, a = +mNow[i] || 0, b = +M.last[i] || 0, d = yet && b ? (a - b) / Math.abs(b) * 100 : null;
							return '<tr><td>' + esc(mth) + '</td><td class="dt-num' + (yet ? '' : ' db-muted') + '">' + (yet ? esc(da(Math.round(a))) : '–') + '</td><td class="dt-num">' + esc(da(Math.round(b))) + '</td>'
								+ '<td class="dt-num ' + (d == null ? 'db-muted' : d >= 0 ? 'db-up' : 'db-down') + '">' + (d == null ? '–' : (d >= 0 ? '▲ ' : '▼ ') + esc(pct(d))) + '</td></tr>';
						}).join('') + '</tbody></table>';
				}
				tb.hidden = !showT; view.hidden = showT;
				tog.textContent = showT ? (T.showGraph || 'Vis som graf') : (T.showTable || 'Vis som tabel');
			});
		}
	}

	/* ---- Omsætning pr. dag: the week select ---- */
	const WK = D.weeks || null;
	if (WK && WK.list && WK.list.length && $('#dayChart')) {
		let w = WK.list[0];
		const dc = chart($('#dayChart'), (box) => barChart(box, { labels: D.days || [], now: w === WK.list[0] ? toDate(w.now, WK.dayToDate) : w.now, last: w.last, showLast: true, H: 176, nowName: tpl(T.week, w.no, w.year), lastName: tpl(T.sameWeek, w.lastYear),
			aria: ($('[data-block="revweek"] .db-t') || {}).textContent || '' }));
		const sel = $('#weekSel');
		if (sel) sel.addEventListener('change', (e) => {
			const f = WK.list.find((x) => String(x.no) + '-' + x.year === e.target.value); if (!f) return; w = f;
			const n = $('#wkNow'), l = $('#wkLast'); if (n) n.textContent = tpl(T.week, w.no, w.year); if (l) l.textContent = tpl(T.sameWeek, w.lastYear);
			render(dc, true);
		});
	}

	/* ---- Kunder pr. time: the period select ---- */
	if (D.heat && $('#heat')) {
		let span = D.heatDefault || '30';
		const box = $('#heat'); box.dataset.label = ($('[data-block="customergraph"] .db-t') || {}).textContent || '';
		const hc = chart(box, (b) => heatmap(b, D.heat[span] || D.heat[Object.keys(D.heat)[0]] || []));
		const hs = $('#heatSel');
		if (hs) hs.addEventListener('change', (e) => { if (D.heat[e.target.value]) { span = e.target.value; render(hc, true); } });
	}

	/* ---- Ubetalte salgsfakturaer: a due group filters the list (again shows all), a row opens its invoice ---- */
	const grpT = $('#unpGrp'), invT = $('#unpTbl');
	if (grpT && invT) {
		let sel = null;
		const setGrp = (g) => {
			sel = sel === g ? null : g;
			let shown = 0;
			invT.querySelectorAll('tbody tr').forEach((tr) => { const ok = (!sel || tr.dataset.g === sel) && shown < 8; tr.hidden = !ok; if (ok) shown++; });
			grpT.classList.toggle('filtering', !!sel);
			grpT.querySelectorAll('tbody tr').forEach((tr) => { tr.classList.toggle('sel', tr.dataset.g === sel); tr.setAttribute('aria-pressed', String(tr.dataset.g === sel)); });
		};
		grpT.addEventListener('click', (e) => { const tr = e.target.closest('tr[data-g]'); if (tr) setGrp(tr.dataset.g); });
		grpT.addEventListener('keydown', (e) => { const tr = e.target.closest('tr[data-g]'); if (tr && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); setGrp(tr.dataset.g); } });
		invT.addEventListener('click', (e) => { if (e.target.closest('a')) return; const tr = e.target.closest('tr[data-href]'); if (tr) location.href = tr.dataset.href; });
		invT.addEventListener('keydown', (e) => { if (e.key !== 'Enter' || e.target.closest('a')) return; const tr = e.target.closest('tr[data-href]'); if (tr) location.href = tr.dataset.href; });
		const U = D.unpaid, names = { late: T.dueLate, warn: T.dueWarn, ok: T.dueOk };
		if (U && U.groups) $$('.db-stack > i[data-g]').forEach((i) => {
			const g = i.dataset.g, G = U.groups[g] || { sum: 0, n: 0 };
			i.setAttribute('data-tip', tipHtml(names[g] || '', [['sw-' + g, T.amount || 'Beløb', kr2(+G.sum)], ['sw-' + g, T.count || 'Antal', String(G.n)]]));
		});
	}

	/* ---- Rediger oversigt: blocks in the user's order (grip: drag with mouse or touch, Alt+Up/Down), switches, saved at once ---- */
	const drawer = $('#drawer'), scrim = $('#scrim'), dBody = $('#drawerBody'), grid = $('#grid');
	const BLOCKS = D.blocks || [], DEFAULT_ORDER = D.defaultOrder || BLOCKS.map((b) => b.id), DEFAULT_OFF = new Set(D.defaultOff || []);
	const dash = Object.assign({}, D.widgets || {}); let hidden = !!D.hidden; let order = (D.order || DEFAULT_ORDER).slice();
	const isOn = (k) => dash[k] !== false, cardOf = (k) => document.querySelector(`[data-widget="${k}"]`), blockEl = (id) => grid && grid.querySelector(`[data-block="${id}"]`);
	function save(fields) {
		const fd = new FormData(); fd.append('csrf_token', D.csrf || ''); Object.keys(fields).forEach((k) => fd.append(k, fields[k]));
		// The answer is JSON after the page's leading "<!doctype html>" line, so it is parsed from the first brace.
		return fetch('dashboard.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then((r) => r.ok ? r.text() : '')
			.then((t) => { const i = t.indexOf('{'); return i < 0 ? null : JSON.parse(t.slice(i)); }).catch(() => null);
	}
	let savedT = 0;
	function markSaved(res) { const m = $('#savedMsg'); if (!m) return; if (!res || !res.ok) { m.classList.remove('on'); return; } m.classList.add('on'); clearTimeout(savedT); savedT = setTimeout(() => m.classList.remove('on'), 1500); }
	function setWidget(k, on) { const c = cardOf(k); dash[k] = on; if (c) c.classList.toggle('gone', !on); }
	function applyOrder(o) { if (!grid) return; const tail = grid.querySelector('.db-pos'); o.map(blockEl).filter(Boolean).forEach((e) => grid.insertBefore(e, tail)); }
	function mkSwitch(on, label, fn) { const b = h('button', 'sw'); b.type = 'button'; b.setAttribute('role', 'switch'); b.setAttribute('aria-checked', on); b.setAttribute('aria-label', label); b.addEventListener('click', (e) => { e.stopPropagation(); const v = b.getAttribute('aria-checked') !== 'true'; b.setAttribute('aria-checked', v); fn(v); }); return b; }
	const spot = (e, on) => e && e.classList.toggle('spot', on);
	function mkRow(title, desc, on, fn, target) {
		const r = h('div', 'trow' + (on ? '' : ' off')), tx = h('div', 'tx'); tx.append(h('b', '', title)); if (desc) tx.append(h('span', '', desc));
		const sw = mkSwitch(on, title, (v) => { r.classList.toggle('off', !v); fn(v); }); r.append(tx, sw); r.addEventListener('click', () => sw.click());
		if (target) { r.addEventListener('pointerenter', () => spot(target(), true)); r.addEventListener('pointerleave', () => spot(target(), false)); }
		return r;
	}
	const ICON = (d) => { const sv = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); sv.setAttribute('viewBox', '0 0 24 24'); sv.setAttribute('aria-hidden', 'true'); sv.innerHTML = '<path d="' + d + '"/>'; return sv; };
	const live = h('div', 'sr'); live.setAttribute('aria-live', 'polite'); document.body.append(live);
	function commitOrder(o, movedId) { order = o; applyOrder(o); save({ action: 'order', order: JSON.stringify(o) }).then(markSaved); buildList(movedId); if (movedId) { const b = BLOCKS.find((x) => x.id === movedId); if (b) live.textContent = tpl(T.movedTo || '%s flyttet til plads %s af %s', b.name, o.indexOf(movedId) + 1, o.length); } }
	function moveBlock(id, dir) { const o = order.slice(), i = o.indexOf(id), j = i + dir; if (i < 0 || j < 0 || j >= o.length) return; o.splice(i, 1); o.splice(j, 0, id); commitOrder(o, id); }
	let drag = null;
	function buildList(focusId) {
		const list = dBody && dBody.querySelector('.blist'); if (!list) return; list.replaceChildren();
		order.forEach((id) => {
			const B = BLOCKS.find((b) => b.id === id); if (!B) return;
			const wrap = h('div', 'brow'); wrap.dataset.id = id;
			const grip = h('button', 'grip'); grip.type = 'button'; grip.title = T.dragHint || ''; grip.setAttribute('aria-label', tpl(T.moveLabel || 'Flyt %s', B.name)); grip.append(ICON('M7 10h4v4H7zm0-6h4v4H7zm0 12h4v4H7zm6-6h4v4h-4zm0-6h4v4h-4zm0 12h4v4h-4z'));
			grip.addEventListener('keydown', (e) => { if (e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) { e.preventDefault(); moveBlock(id, e.key === 'ArrowUp' ? -1 : 1); } });
			grip.addEventListener('click', (e) => e.stopPropagation());
			let row;
			if (B.items) {
				const n0 = B.items.filter(([k]) => isOn(k)).length; row = h('div', 'trow' + (n0 ? '' : ' off')); const tx = h('div', 'tx'); tx.append(h('b', '', B.name), h('span', '', tpl(T.shownOf || '%s af %s vises', n0, B.items.length)));
				const fold = h('button', 'fold'); fold.type = 'button'; fold.setAttribute('aria-label', tpl(T.showCards || 'Vis kort i %s', B.name)); fold.append(ICON('M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z'));
				wrap.classList.add('open'); fold.setAttribute('aria-expanded', 'true');
				fold.onclick = (e) => { e.stopPropagation(); const o = wrap.classList.toggle('open'); fold.setAttribute('aria-expanded', o); }; row.append(tx, fold); row.addEventListener('click', () => fold.click());
				const sub = h('div', 'bsub');
				B.items.forEach(([k, t, d]) => sub.append(mkRow(t, d, isOn(k), (v) => { setWidget(k, v); save({ action: 'widget', key: k, on: v ? '1' : '0' }).then(markSaved); const n = B.items.filter(([kk]) => isOn(kk)).length; tx.lastChild.textContent = tpl(T.shownOf || '%s af %s vises', n, B.items.length); row.classList.toggle('off', !n); }, () => cardOf(k))));
				row.prepend(grip); wrap.append(row, sub);
			} else {
				row = mkRow(B.name, B.desc, isOn(id), (v) => { setWidget(id, v); save({ action: 'widget', key: id, on: v ? '1' : '0' }).then(markSaved); }, () => blockEl(id)); row.prepend(grip); wrap.append(row);
			}
			row.addEventListener('pointerenter', () => spot(blockEl(id), true)); row.addEventListener('pointerleave', () => spot(blockEl(id), false));
			/* drag with the grip: pointer events, so mouse and touch behave the same (HTML5 drag-and-drop has no touch support) */
			grip.addEventListener('pointerdown', (e) => { if (e.button && e.button !== 0) return; e.preventDefault(); drag = { id, y: e.clientY }; wrap.classList.add('dragging'); grip.setPointerCapture(e.pointerId); });
			grip.addEventListener('pointermove', (e) => { if (!drag || drag.id !== id) return; const over = document.elementsFromPoint(e.clientX, e.clientY).find((n) => n.classList && n.classList.contains('brow') && n.dataset.id !== id); list.querySelectorAll('.brow').forEach((x) => x.classList.remove('drop-before', 'drop-after')); drag.target = null; if (!over) return; const r = over.getBoundingClientRect(); drag.before = e.clientY < r.top + r.height / 2; over.classList.add(drag.before ? 'drop-before' : 'drop-after'); drag.target = over.dataset.id; });
			const finish = () => { if (!drag || drag.id !== id) return; const d = drag; drag = null; list.querySelectorAll('.brow').forEach((x) => x.classList.remove('dragging', 'drop-before', 'drop-after')); if (!d.target) return; const o = order.filter((x) => x !== id); o.splice(o.indexOf(d.target) + (d.before ? 0 : 1), 0, id); commitOrder(o, id); };
			grip.addEventListener('pointerup', finish); grip.addEventListener('pointercancel', finish);
			list.append(wrap);
		});
		if (focusId) { const g = list.querySelector(`.brow[data-id="${focusId}"] .grip`); if (g) g.focus({ preventScroll: true }); }
	}
	function setHidden(v) { hidden = v; if (grid) grid.classList.toggle('all-hidden', v); save({ action: 'hide', hidden: v ? '1' : '0' }).then(markSaved); if (window.parent !== window) { try { window.parent.postMessage({ type: 'saldi:dash-hidden', hidden: v }, location.origin); } catch (e) { /* other origin */ } } }
	function buildDrawer() {
		if (!dBody) return; dBody.replaceChildren();
		dBody.append(mkRow(T.showDash || 'Vis oversigten', T.showDashHelp || '', !hidden, (v) => setHidden(!v)));
		const g1 = h('div', 'grp', T.contentOrder || 'Indhold og rækkefølge'); g1.append(h('span', 'tag', T.dragHint || '')); dBody.append(g1);
		dBody.append(h('div', 'blist')); buildList();
		if (D.accounts) {
			const g = h('div', 'grp', T.accounts || 'Omsætningskonti'); g.append(h('span', 'tag', T.companyWide || 'Gælder hele regnskabet')); dBody.append(g);
			const a = h('div', 'acct');
			[[T.fromAcc || 'Fra kontonr.', 'kontomin'], [T.toAcc || 'Til kontonr.', 'kontomaks']].forEach(([l, k]) => { const lab = h('label', '', l), inp = h('input'); inp.inputMode = 'numeric'; inp.value = D.accounts[k] || ''; inp.disabled = !D.accounts.canEdit; inp.addEventListener('change', () => { D.accounts[k] = inp.value; save({ action: 'accounts', kontomin: D.accounts.kontomin, kontomaks: D.accounts.kontomaks }).then((r) => { markSaved(r); if (r && r.reload) location.reload(); }); }); lab.append(inp); a.append(lab); });
			dBody.append(a);
			const hp = h('div', 'help', (T.accountsHelp || '') + ' '); if (D.accounts.guide) { const lk = h('a', 'db-link', T.seeGuide || 'Se guiden'); lk.href = D.accounts.guide; lk.target = '_blank'; lk.rel = 'noopener'; hp.append(lk); } dBody.append(hp);
		}
	}
	function drawerOpen(on) {
		if (!drawer) return; if (on) buildDrawer(); drawer.classList.toggle('open', on); if (scrim) scrim.classList.toggle('open', on); hideTip();
		if (on) setTimeout(() => { const c = $('#drawerClose'); if (c) c.focus({ preventScroll: true }); }, 60); else $$('.spot').forEach((c) => c.classList.remove('spot'));
	}
	window.saldiDashDrawer = drawerOpen;
	window.saldiDashSetHidden = setHidden;
	const dc = $('#drawerClose'), dd = $('#drawerDone'); if (dc) dc.onclick = () => drawerOpen(false); if (dd) dd.onclick = () => drawerOpen(false); if (scrim) scrim.onclick = () => drawerOpen(false);
	if (drawer) drawer.addEventListener('click', (e) => e.stopPropagation());
	const ALL_KEYS = BLOCKS.flatMap((b) => b.items ? b.items.map((i) => i[0]) : [b.id]);
	const rs = $('#dashReset'); if (rs) rs.onclick = () => { ALL_KEYS.forEach((k) => setWidget(k, !DEFAULT_OFF.has(k))); if (hidden) setHidden(false); order = DEFAULT_ORDER.slice(); applyOrder(order); save({ action: 'reset' }).then(markSaved); buildDrawer(); };
	const sd = $('#showDash'); if (sd) sd.onclick = () => setHidden(false);
	document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && drawer && drawer.classList.contains('open')) drawerOpen(false); });
	window.addEventListener('message', (e) => { if (e.origin === location.origin && e.data && e.data.type === 'saldi:dash-edit') drawerOpen(true); });
	Object.keys(dash).forEach((k) => { if (!isOn(k)) setWidget(k, false); }); if (hidden && grid) grid.classList.add('all-hidden');

	/* ---- draw the charts now, again when the web fonts are in, and whenever a chart's width changes ---- */
	charts.forEach((c) => { render(c, true); if (ro) ro.observe(c.box); });
	if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => charts.forEach((c) => render(c, true)));
})();
