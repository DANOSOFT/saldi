// ---- javascript/dashboard.js --- lap 5.0.0 --- 2026.10.08 ---
// 20261008 Sawaneh Oversigt (index/dashboard.php) after Adam's prototype_dashboard_tema.html v5: counting numbers,
// 20261009 Sawaneh Prototype v8.13: blocks in the user's order (grip drag with pointer events, Alt+Up/Down, aria-live), one Ring
//                  builder for both rings, the Ubetalte salgsfakturaer card (filter by group, row click opens the invoice).
// 20261009 Sawaneh Axis text at a fixed 0.78rem (--axis per chart), charts drawn after document.fonts.ready (addendum 3 §3.1, §5.1).
//                  sparklines, bar charts (this year, last year, best), the customers-per-hour heatmap, the item-group
//                  ring, tooltips, the "Rediger oversigt" drawer (changes saved at once) and the entry animation.
//                  Data and texts come from window.SALDI_DASH, written by the page.
(function () {
	'use strict';
	const D = window.SALDI_DASH || {};
	const T = D.txt || {};
	const root = document.documentElement, $ = (s) => document.querySelector(s), $$ = (s) => [...document.querySelectorAll(s)];
	const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
	const NS = 'http://www.w3.org/2000/svg';
	const el = (n, a = {}, txt) => { const e = document.createElementNS(NS, n); for (const k in a) e.setAttribute(k, a[k]); if (txt != null) e.textContent = txt; return e; };
	const h = (n, cls, txt) => { const e = document.createElement(n); if (cls) e.className = cls; if (txt != null) e.textContent = txt; return e; };
	const easeOut = (t) => 1 - Math.pow(1 - t, 4), clamp = (v, a, b) => Math.max(a, Math.min(b, v));
	const locale = D.locale || 'da-DK';
	function tween({ dur = 800, delay = 0, step, done }) {
		if (reduced) { step(1); done && done(); return; }
		const t0 = performance.now() + delay;
		(function f(now) { const p = (now - t0) / dur; if (p < 0) return requestAnimationFrame(f); step(Math.min(1, p)); p < 1 ? requestAnimationFrame(f) : done && done(); })(performance.now());
	}
	const da = (v, o) => v.toLocaleString(locale, o);
	const kr = (v) => da(Math.round(v)) + ' kr';
	const fmt = { kr: (v) => da(Math.round(v)) + ' kr', mio: (v) => da(v / 1e6, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + (T.mio || 'mio. kr'), int: (v) => da(Math.round(v)) };
	const fmtAxis = (v) => v >= 1e6 ? da(v / 1e6, { maximumFractionDigits: 2 }) + ' ' + (T.mioShort || 'mio.') : v >= 1e3 ? da(v / 1e3, { maximumFractionDigits: 0 }) + ' ' + (T.thousandShort || 't.') : String(Math.round(v));
	const pct = (v) => da(Math.abs(v), { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' %';
	const tpl = (s, ...a) => String(s || '').replace(/%s/g, () => a.shift());
	function niceMax(raw, ticks = 4) { if (raw <= 0) return ticks; const pow = 10 ** Math.floor(Math.log10(raw / ticks)); const step = [1, 2, 2.5, 5, 10].map((m) => m * pow).find((v) => v * ticks >= raw); return step * ticks; }

	// Theme from the shell (chip menu) while the page is open.
	window.addEventListener('message', (e) => { if (e.origin === location.origin && e.data && e.data.type === 'saldi:theme' && typeof e.data.theme === 'string') { root.dataset.theme = e.data.theme; } });

	/* ---- tooltip ---- */
	const tip = $('#tip'); let tx = 0, ty = 0, cx = 0, cy = 0, tipOn = false, tipRaf = 0;
	function tipBody(title, rows, foot) {
		const f = document.createDocumentFragment(); f.append(h('div', 't', title));
		rows.forEach((r) => { const d = h('div', 'r'); const k = h('span', 'k'); k.style.background = r.color; d.append(k, h('span', 'n', r.label), h('span', 'v', r.value)); f.append(d); });
		if (foot) { const d = h('div', 'f', foot.text); if (foot.color) d.style.color = foot.color; f.append(d); }
		return f;
	}
	function tipLoop() { cx += (tx - cx) * (reduced ? 1 : .28); cy += (ty - cy) * (reduced ? 1 : .28); tip.style.transform = `translate(${cx.toFixed(1)}px,${cy.toFixed(1)}px)`; if (tipOn && (Math.abs(tx - cx) > .3 || Math.abs(ty - cy) > .3)) tipRaf = requestAnimationFrame(tipLoop); else tipRaf = 0; }
	function tipAt(x, y) { if (!tip) return; const w = tip.offsetWidth, hh = tip.offsetHeight; tx = clamp(x - w / 2, 8, innerWidth - w - 8); ty = y - hh - 16; if (ty < 8) ty = y + 20; if (!tipOn) { cx = tx; cy = ty + 6; } tipOn = true; tip.classList.add('on'); if (!tipRaf) tipRaf = requestAnimationFrame(tipLoop); }
	function tipShow(content, x, y) { if (!tip) return; tip.replaceChildren(content); tipAt(x, y); }
	function tipHide() { tipOn = false; tip && tip.classList.remove('on'); }
	function interactive(node, content, { enter, leave } = {}) {
		node.addEventListener('pointerenter', (e) => { enter && enter(); tipShow(content(), e.clientX, e.clientY); });
		node.addEventListener('pointermove', (e) => tipAt(e.clientX, e.clientY));
		node.addEventListener('pointerleave', () => { leave && leave(); tipHide(); });
		node.addEventListener('focus', () => { enter && enter(); const r = node.getBoundingClientRect(); tipShow(content(), r.left + r.width / 2, r.top + r.height * .25); });
		node.addEventListener('blur', () => { leave && leave(); tipHide(); });
	}

	/* ---- numbers counting up, sparklines ---- */
	function countUp(n) { const to = +n.dataset.count, f = fmt[n.dataset.fmt] || fmt.int; tween({ dur: 1100, delay: 120, step: (p) => n.textContent = f(to * easeOut(p)) }); }
	$$('[data-spark]').forEach((s) => {
		const d = s.dataset.spark.split(',').map(Number).filter((v) => !isNaN(v)); if (d.length < 2) return;
		const w = 100, hh = 40, mx = Math.max(...d), mn = Math.min(...d);
		const pts = d.map((v, i) => [5 + i * (w - 10) / (d.length - 1), hh - 6 - (v - mn) / (mx - mn || 1) * (hh - 14)]);
		let L = `M${pts[0][0]},${pts[0][1]}`;
		for (let i = 0; i < pts.length - 1; i++) { const p0 = pts[i - 1] || pts[i], p1 = pts[i], p2 = pts[i + 1], p3 = pts[i + 2] || p2; L += ` C${p1[0] + (p2[0] - p0[0]) / 6},${p1[1] + (p2[1] - p0[1]) / 6} ${p2[0] - (p3[0] - p1[0]) / 6},${p2[1] - (p3[1] - p1[1]) / 6} ${p2[0]},${p2[1]}`; }
		const last = pts[pts.length - 1];
		s.append(el('path', { class: 'a', d: L + ` L${last[0]},${hh} L${pts[0][0]},${hh} Z` }));
		const line = el('path', { class: 'l', d: L }); s.append(line);
		s.append(el('circle', { class: 'halo', cx: last[0], cy: last[1], r: 3.5 }), el('circle', { class: 'dot', cx: last[0], cy: last[1], r: 3.5 }));
		try { s.style.setProperty('--len', Math.ceil(line.getTotalLength()) + 2); } catch (e) { s.style.setProperty('--len', 300); }
	});

	/* ---- bar chart: this year blue, last year context, best purple ---- */
	function BarChart(svg, labels, { nowLabel = T.now || 'I år', lastLabel = T.last || 'Sidste år', ticks = 4 } = {}) {
		const [, , W, H] = svg.getAttribute('viewBox').split(' ').map(Number), m = { t: 20, r: 8, b: 30, l: 54 }, pw = W - m.l - m.r, ph = H - m.t - m.b, base = m.t + ph, n = labels.length, slotW = pw / n, bw = Math.min(20, slotW * .3);
		const id = 'clip-' + svg.id; svg.appendChild(el('defs')).appendChild(el('clipPath', { id })).appendChild(el('rect', { x: m.l, y: 0, width: pw, height: base }));
		const g = svg.appendChild(el('g', { class: 'gridl' })), tickTxt = [];
		for (let i = 0; i <= ticks; i++) { const y = base - ph / ticks * i; if (i) g.append(el('line', { x1: m.l, x2: W - m.r, y1: y, y2: y })); tickTxt.push(g.appendChild(el('text', { x: m.l - 10, y: y + 4, 'text-anchor': 'end' }, '0'))); }
		const slots = labels.map((lab, i) => {
			const x0 = m.l + slotW * i, cx = x0 + slotW / 2, s = svg.appendChild(el('g', { class: 'slot' }));
			s.append(el('rect', { class: 'band', x: x0 + 2, y: m.t - 8, width: slotW - 4, height: ph + 8, rx: 8 }));
			const bars = s.appendChild(el('g', { 'clip-path': `url(#${id})` }));
			const last = bars.appendChild(el('rect', { class: 'bar last', x: cx - bw - 1, width: bw, rx: 4, y: base, height: 0 }));
			const now = bars.appendChild(el('rect', { class: 'bar now', x: cx + 1, width: bw, rx: 4, y: base, height: 0 }));
			s.append(el('text', { x: cx, y: H - 10, 'text-anchor': 'middle' }, lab));
			const hit = s.appendChild(el('rect', { class: 'hit', x: x0, y: 0, width: slotW, height: H, tabindex: 0, role: 'img' }));
			return { s, last, now, hit, cx };
		});
		svg.append(el('line', { class: 'base', x1: m.l, x2: W - m.r, y1: base, y2: base }));
		const best = svg.appendChild(el('text', { class: 'best', 'text-anchor': 'middle' }));
		let cur = { now: labels.map(() => 0), last: labels.map(() => 0), max: 1 }, tgt = cur, showLast = true;
		const setBar = (r, v, max) => { const hh = Math.max(0, v / max * ph); r.setAttribute('y', base - hh); r.setAttribute('height', hh > 0 ? hh + 6 : 0); };
		function paint(st) { slots.forEach((o, i) => { setBar(o.now, st.now[i], st.max); setBar(o.last, st.last[i], st.max); }); }
		function markBest() {
			const bi = tgt.now.indexOf(Math.max(...tgt.now)); slots.forEach((o, i) => o.now.setAttribute('class', 'bar ' + (i === bi && tgt.now[bi] > 0 ? 'acc' : 'now')));
			const o = slots[bi]; if (!o) return; best.textContent = fmtAxis(tgt.now[bi]); best.setAttribute('x', o.cx + 1 + bw / 2); best.setAttribute('y', base - tgt.now[bi] / tgt.max * ph - 7);
		}
		slots.forEach((o, i) => interactive(o.hit, () => {
			const a = tgt.now[i], b = tgt.last[i], rows = [{ color: 'var(--s1)', label: nowLabel, value: kr(a) }];
			if (showLast) rows.push({ color: 'var(--context)', label: lastLabel, value: kr(b) });
			const d = b ? ((a - b) / Math.abs(b) * 100) : null;
			return tipBody(labels[i], rows, showLast && d != null ? { text: (d >= 0 ? '▲ ' : '▼ ') + pct(d) + ' ' + (T.vsLast || 'mod sidste år'), color: d >= 0 ? 'var(--good)' : 'var(--bad)' } : null);
		}, { enter() { svg.classList.add('hovering'); o.s.classList.add('on'); }, leave() { svg.classList.remove('hovering'); o.s.classList.remove('on'); } }));
		return {
			update(now, last, { stagger = false } = {}) {
				const from = cur, max = niceMax(Math.max(0, ...now, ...(showLast ? last : [0])) * 1.1, ticks); tgt = { now, last: showLast ? last : last.map(() => 0), max };
				const shown = tgt.last; tickTxt.forEach((t, i) => t.textContent = fmtAxis(max / ticks * i)); best.classList.remove('show');
				const s = .045, span = 1 - (n - 1) * s;
				tween({ dur: stagger ? 1150 : 620, step: (p) => { const st = { max: from.max + (max - from.max) * easeOut(p), now: [], last: [] }; for (let i = 0; i < n; i++) { const q = easeOut(stagger ? clamp((p - i * s) / span, 0, 1) : p); st.now[i] = from.now[i] + (now[i] - from.now[i]) * q; st.last[i] = from.last[i] + (shown[i] - from.last[i]) * q; } paint(st); cur = st; },
					done() { cur = { now: [...now], last: [...shown], max }; tgt.last = last; markBest(); best.classList.add('show'); } });
				markBest();
			},
			toggleLast(on, now, last) { showLast = on; this.update(now, last); },
			setLabel(t) { nowLabel = t; }
		};
	}
	function refetch(svg, fn) { svg.classList.add('loading'); setTimeout(() => { svg.classList.remove('loading'); fn(); }, reduced ? 0 : 220); }

	/* ---- revenue per month ---- */
	const M = D.month || null; let monthChart = null;
	if (M && $('#monthChart')) {
		monthChart = BarChart($('#monthChart'), M.labels, { nowLabel: M.nowLabel, lastLabel: M.lastLabel, ticks: 5 });
		const cmp = $('#cmpSel');
		cmp && cmp.addEventListener('change', (e) => { const on = e.target.value === 'both'; const leg = $('#legLast'); if (leg) leg.style.opacity = on ? 1 : .35; refetch($('#monthChart'), () => monthChart.toggleLast(on, M.now, M.last)); });
		const tb = $('#monthTbl');
		if (tb) {
			const thead = tb.createTHead().insertRow(); [T.month || 'Måned', M.nowLabel, M.lastLabel, T.change || 'Ændring'].forEach((t) => thead.append(h('th', '', t))); const body = tb.createTBody();
			M.labels.forEach((mth, i) => { const r = body.insertRow(), b = M.last[i], d = b ? (M.now[i] - b) / Math.abs(b) * 100 : null; [mth, kr(M.now[i]), kr(b)].forEach((t) => r.insertCell().textContent = t); const c = r.insertCell(); c.textContent = d == null ? '–' : (d >= 0 ? '▲ ' : '▼ ') + pct(d); c.style.color = d == null ? '' : d >= 0 ? 'var(--good)' : 'var(--bad)'; });
			const tog = $('#tblToggle');
			tog && (tog.onclick = (e) => { const showT = tb.classList.contains('off'); tb.classList.toggle('off', !showT); $('#monthView').classList.toggle('off', showT); e.target.textContent = showT ? (T.showGraph || 'Vis som graf') : (T.showTable || 'Vis som tabel'); });
		}
	}

	/* ---- revenue per day (week) ---- */
	const WK = D.weeks || null; let dayChart = null;
	if (WK && WK.list && WK.list.length && $('#dayChart')) {
		const first = WK.list[0];
		dayChart = BarChart($('#dayChart'), D.days || [], { nowLabel: tpl(T.week, first.no, first.year), lastLabel: tpl(T.sameWeek, first.lastYear) });
		const sel = $('#weekSel');
		sel && sel.addEventListener('change', (e) => { const w = WK.list.find((x) => String(x.no) + '-' + x.year === e.target.value); if (!w) return; const lab = tpl(T.week, w.no, w.year); const n = $('#wkNow'); if (n) n.textContent = lab; dayChart.setLabel(lab); refetch($('#dayChart'), () => dayChart.update(w.now, w.last)); });
	}

	/* ---- heatmap ---- */
	let heat = null;
	if (D.heat && $('#heat')) {
		heat = (function () {
			const svg = $('#heat'), m = { l: 34, t: 6, r: 4, b: 22 }, W = 330, H = 262, cw = (W - m.l - m.r) / 24, ch = (H - m.t - m.b) / 7, cells = [], dl = [], days = D.days || [];
			let data = D.heat[D.heatDefault || '30'] || D.heat[Object.keys(D.heat)[0]];
			days.forEach((d, di) => {
				dl.push(svg.appendChild(el('text', { class: 'dlab', x: m.l - 7, y: m.t + di * ch + ch / 2 + 4, 'text-anchor': 'end' }, d)));
				for (let hr = 0; hr < 24; hr++) { const c = svg.appendChild(el('rect', { class: 'cell', x: m.l + hr * cw, y: m.t + di * ch, width: cw, height: ch, rx: 4, tabindex: -1 })); c.style.setProperty('--d', di + hr); cells.push({ c, di, hr }); }
			});
			[0, 4, 8, 12, 16, 20].forEach((hr) => svg.append(el('text', { x: m.l + hr * cw + cw / 2, y: H - 5, 'text-anchor': 'middle' }, String(hr).padStart(2, '0'))));
			const ring = svg.appendChild(el('rect', { class: 'ring', x: 1, y: 1, width: cw - 2, height: ch - 2, rx: 4 }));
			function paint() { const max = Math.max(0, ...data.flat()); cells.forEach(({ c, di, hr }) => { const v = data[di][hr], q = v <= 0 || max <= 0 ? 0 : Math.max(1, Math.ceil(v / max * 7)); c.style.fill = `var(--q${q})`; }); }
			cells.forEach(({ c, di, hr }) => interactive(c, () => tipBody(`${days[di]} ${T.at || 'kl.'} ${String(hr).padStart(2, '0')}–${String(hr + 1).padStart(2, '0')}`, [{ color: 'var(--q5)', label: T.avgCust || 'Gns. kunder pr. time', value: da(data[di][hr], { maximumFractionDigits: 1 }) }]),
				{ enter() { ring.style.transform = `translate(${m.l + hr * cw}px,${m.t + di * ch}px)`; ring.classList.add('on'); dl[di].classList.add('on'); }, leave() { ring.classList.remove('on'); dl[di].classList.remove('on'); } }));
			paint();
			return { enter() { svg.classList.add('in'); setTimeout(() => cells.forEach((o) => { o.c.style.animation = 'none'; o.c.style.opacity = 1; }), reduced ? 0 : 1400); }, set(k) { if (D.heat[k]) { data = D.heat[k]; paint(); } } };
		})();
		const hs = $('#heatSel'); hs && hs.addEventListener('change', (e) => heat.set(e.target.value));
	}

	/* ---- rings: one builder (addendum 3 §5.4: stroke about 22 % of the radius, 2 px gaps in the surface colour) ---- */
	function Ring(svg, items, { total = 0, centre, centreSub, onPick } = {}) {
		const cx = 88, cy = 88, R = 78, r = Math.round(R * 0.78), gap = .03;
		let a0 = -Math.PI / 2; items.forEach((o) => { o.a1 = a0; o.a2 = a0 + (total > 0 ? o.v / total : 0) * 2 * Math.PI; a0 = o.a2; o.path = svg.appendChild(el('path', { fill: o.col, tabindex: 0, role: 'img', 'aria-label': o.aria || o.name })); });
		const c1 = svg.appendChild(el('text', { class: 'c1', x: cx, y: cy - 1, 'text-anchor': 'middle' }, '0')), c2 = svg.appendChild(el('text', { class: 'c2', x: cx, y: cy + 15, 'text-anchor': 'middle' }, centreSub || ''));
		function arc(a1, a2) { if (a2 - a1 < gap) return ''; a1 += gap / 2; a2 -= gap / 2; const L = a2 - a1 > Math.PI ? 1 : 0, P = (rr, a) => `${(cx + rr * Math.cos(a)).toFixed(2)},${(cy + rr * Math.sin(a)).toFixed(2)}`; return `M${P(R, a1)} A${R},${R} 0 ${L} 1 ${P(R, a2)} L${P(r, a2)} A${r},${r} 0 ${L} 0 ${P(r, a1)} Z`; }
		function draw(p) { const lim = -Math.PI / 2 + p * 2 * Math.PI; items.forEach((o) => o.path.setAttribute('d', arc(o.a1, Math.min(o.a2, lim)))); c1.textContent = centre(p); }
		function on(o) { svg.classList.add('hovering'); o.path.classList.add('on'); const mid = (o.a1 + o.a2) / 2; o.path.style.transform = `translate(${(Math.cos(mid) * 5).toFixed(1)}px,${(Math.sin(mid) * 5).toFixed(1)}px)`; if (o.hoverCentre) { c1.textContent = o.hoverCentre[0]; c2.textContent = o.hoverCentre[1]; } }
		function off(o) { svg.classList.remove('hovering'); o.path.classList.remove('on'); o.path.style.transform = ''; c1.textContent = centre(1); c2.textContent = centreSub || ''; }
		items.forEach((o) => { interactive(o.path, () => tipBody(o.name, o.rows), { enter: () => on(o), leave: () => off(o) }); if (onPick) o.path.addEventListener('click', () => onPick(o)); });
		draw(0);
		return { enter() { tween({ dur: 1150, delay: 150, step: (p) => draw(easeOut(p)) }); }, on, off, items };
	}

	/* ---- ring per item group ---- */
	let donut = null;
	if (D.donut && $('#donut')) {
		const wrap = $('#donutWrap'), leg = $('#donutLeg'), total = D.donut.total || 0;
		const items = D.donut.items.map(([name, p, other], i) => ({ name, p, v: p, col: other ? 'var(--context)' : `var(--s${(i % 7) + 1})`, aria: `${name} ${p} %`,
			rows: [{ color: other ? 'var(--context)' : `var(--s${(i % 7) + 1})`, label: T.share || 'Andel', value: da(p, { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' %' }, { color: other ? 'var(--context)' : `var(--s${(i % 7) + 1})`, label: T.revenue || 'Omsætning', value: fmt.mio(total * p / 100) }],
			hoverCentre: [da(p, { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' %', name.length > 16 ? name.slice(0, 15) + '…' : name] }));
		donut = Ring($('#donut'), items, { total: 100, centre: (p) => da(total / 1e6 * p, { minimumFractionDigits: 2, maximumFractionDigits: 2 }), centreSub: T.total || 'mio. kr i alt' });
		items.forEach((o, i) => { const li = leg.appendChild(h('li')); li.style.setProperty('--n', i); const sp = h('span'); const dot = h('i'); dot.style.background = o.col; sp.append(dot, document.createTextNode(o.name)); li.append(sp, h('b', '', da(o.p, { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' %')); o.li = li;
			li.addEventListener('pointerenter', () => { donut.on(o); wrap.classList.add('hovering'); li.classList.add('on'); }); li.addEventListener('pointerleave', () => { donut.off(o); wrap.classList.remove('hovering'); li.classList.remove('on'); }); });
	}

	/* ---- Ubetalte salgsfakturaer (§5.9, addendum 3 §6): ring over due groups, legend, list, filter by group ---- */
	let unpaid = null;
	if (D.unpaid && $('#unpDonut')) {
		const U = D.unpaid, leg = $('#unpLeg'), tb = $('#unpTbl tbody'), foot = $('#unpFoot');
		const kr2 = (v) => da(v, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kr';
		const nInv = (n) => n === 1 ? (T.invoice || '1 faktura') : tpl(T.invoices || '%s fakturaer', n);
		const G = [{ k: 'late', name: T.dueLate || 'Forfalden over 15 dage', col: 'var(--due-late)' }, { k: 'warn', name: T.dueWarn || 'Forfalden 1–15 dage', col: 'var(--due-warn)' }, { k: 'ok', name: T.dueOk || 'Ikke forfalden', col: 'var(--due-ok)' }];
		G.forEach((g) => { g.sum = U.groups[g.k] ? +U.groups[g.k].sum : 0; g.n = U.groups[g.k] ? +U.groups[g.k].n : 0; g.v = g.sum; g.aria = `${g.name} ${kr2(g.sum)}`; g.rows = [{ color: g.col, label: T.amount || 'Beløb', value: kr2(g.sum) }, { color: g.col, label: nInv(2).replace(/^\d+\s*/, ''), value: String(g.n) }]; });
		let sel = null;
		const ringItems = ['ok', 'warn', 'late'].map((k) => G.find((g) => g.k === k)).filter((g) => g.sum > 0);
		unpaid = Ring($('#unpDonut'), ringItems, { total: U.total, centre: (p) => da(U.total * p, { minimumFractionDigits: 2, maximumFractionDigits: 2 }), centreSub: T.krTotal || 'kr i alt', onPick: (g) => filter(g) });
		G.forEach((g) => { const li = leg.appendChild(h('li')); const dot = h('i'); dot.style.background = g.col; const t = h('div'); t.append(h('span', 'n', g.name)); const b = h('b', '', kr2(g.sum)); b.append(' ', h('small', '', '· ' + nInv(g.n))); t.append(b); li.append(dot, t); g.li = li;
			li.addEventListener('click', () => filter(g)); li.addEventListener('pointerenter', () => { if (g.path && !sel) unpaid.on(g); }); li.addEventListener('pointerleave', () => { if (g.path && !sel) unpaid.off(g); }); });
		const fmtDate = (d) => { const [y, m, dd] = String(d).split('-'); return `${dd}.${m}.${String(y).slice(2)}`; };
		const ageTxt = (o) => o.over > 0 ? (o.over === 1 ? (T.dayOver || '1 dag over') : tpl(T.daysOver || '%s dage over', o.over)) : o.over === 0 ? (T.dueToday || 'forfalder i dag') : (-o.over === 1 ? (T.inDay || 'om 1 dag') : tpl(T.inDays || 'om %s dage', -o.over));
		const rows = (U.list || []).map((o) => { const tr = tb.insertRow(); const c0 = tr.insertCell(), dot = h('span', 'dot'); dot.style.background = G.find((g) => g.k === o.g).col; c0.append(dot, fmtDate(o.due), h('span', 'age', ageTxt(o)));
			tr.insertCell().textContent = o.nr; tr.insertCell().textContent = o.kunde; tr.insertCell().textContent = da(+o.amount, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
			tr.title = tpl(T.openInvoice || 'Åbn %s', o.nr || o.kunde); tr.tabIndex = 0; const go = () => { if (o.href) location.href = o.href; }; tr.addEventListener('click', go); tr.addEventListener('keydown', (e) => { if (e.key === 'Enter') go(); }); return { o, tr }; });
		function apply() { let shown = 0; rows.forEach(({ o, tr }) => { const ok = (!sel || o.g === sel.k) && shown < 8; tr.classList.toggle('hide', !ok); if (ok) shown++; }); leg.classList.toggle('filtering', !!sel); G.forEach((g) => { g.li.classList.toggle('on', g === sel); if (g.path) g.path.style.opacity = !sel || g === sel ? 1 : .3; }); }
		function filter(g) { sel = sel === g ? null : g; apply(); }
		if (U.unapplied && +U.unapplied !== 0) { foot.append(h('span', '', tpl(T.unapplied || '%s', kr2(+U.unapplied).replace('-', '−')))); const ub = h('a', 'link', T.settle || 'Udlign'); ub.href = (D.links && D.links.settle) || '#'; foot.append(ub); }
		apply();
	}

	/* ---- Rediger oversigt: blocks in the user's order (grip: drag with mouse or touch, Alt+Up/Down), switches, saved at once ---- */
	const drawer = $('#drawer'), scrim = $('#scrim'), dBody = $('#drawerBody'), grid = $('#grid');
	const BLOCKS = D.blocks || [], DEFAULT_ORDER = D.defaultOrder || BLOCKS.map((b) => b.id), DEFAULT_OFF = new Set(D.defaultOff || []);
	const dash = Object.assign({}, D.widgets || {}); let hidden = !!D.hidden; let order = (D.order || DEFAULT_ORDER).slice();
	const isOn = (k) => dash[k] !== false, cardOf = (k) => document.querySelector(`[data-widget="${k}"]`), blockEl = (id) => grid && grid.querySelector(`[data-block="${id}"]`);
	function save(fields) {
		const fd = new FormData(); fd.append('csrf_token', D.csrf || ''); Object.keys(fields).forEach((k) => fd.append(k, fields[k]));
		return fetch('dashboard.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then((r) => r.ok ? r.json() : null).catch(() => null);
	}
	let savedT = 0;
	function markSaved() { const m = $('#savedMsg'); if (!m) return; m.classList.add('on'); clearTimeout(savedT); savedT = setTimeout(() => m.classList.remove('on'), 1500); }
	function setWidget(k, on, animate = true) {
		const c = cardOf(k); dash[k] = on; if (!c) return; clearTimeout(c._t);
		if (on) { c.classList.remove('gone', 'leaving'); if (animate && c.classList.contains('in')) { c.classList.remove('arriving'); void c.offsetWidth; c.classList.add('arriving'); } }
		else if (animate && !reduced) { c.classList.add('leaving'); c._t = setTimeout(() => { c.classList.add('gone'); c.classList.remove('leaving'); }, 220); }
		else c.classList.add('gone');
	}
	/* blocks into the new order; FLIP glide from the old place to the new */
	function applyOrder(o, animate = true) {
		if (!grid) return; const els = o.map(blockEl).filter(Boolean), first = new Map(els.map((e) => [e, e.getBoundingClientRect()]));
		els.forEach((e) => grid.insertBefore(e, grid.querySelector('.krow:not([data-block])')));
		if (!animate || reduced) return;
		els.forEach((e) => { const a = first.get(e), b = e.getBoundingClientRect(), dx = a.left - b.left, dy = a.top - b.top; if (dx || dy) e.animate([{ transform: `translate(${dx}px,${dy}px)` }, { transform: 'none' }], { duration: 320, easing: 'cubic-bezier(.2,.8,.2,1)' }); });
	}
	function mkSwitch(on, label, fn) { const b = h('button', 'sw'); b.type = 'button'; b.setAttribute('role', 'switch'); b.setAttribute('aria-checked', on); b.setAttribute('aria-label', label); b.set = (v) => { b.setAttribute('aria-checked', v); }; b.addEventListener('click', (e) => { e.stopPropagation(); const v = b.getAttribute('aria-checked') !== 'true'; b.set(v); fn(v); }); return b; }
	const spot = (e, on) => e && e.classList.toggle('spot', on);
	function mkRow(title, desc, on, fn, target) {
		const r = h('div', 'trow' + (on ? '' : ' off')), tx = h('div', 'tx'); tx.append(h('b', '', title)); if (desc) tx.append(h('span', '', desc));
		const sw = mkSwitch(on, title, (v) => { r.classList.toggle('off', !v); fn(v); }); r.append(tx, sw); r.addEventListener('click', () => sw.click()); r.sw = sw;
		if (target) { r.addEventListener('pointerenter', () => spot(target(), true)); r.addEventListener('pointerleave', () => spot(target(), false)); }
		return r;
	}
	const SVG = (d) => { const sv = el('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2.2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }); sv.innerHTML = d; return sv; };
	const live = h('div', 'sr'); live.setAttribute('aria-live', 'polite'); document.body.append(live);
	function commitOrder(o, movedId) { order = o; applyOrder(o); save({ action: 'order', order: JSON.stringify(o) }).then(markSaved); buildList(movedId); if (movedId) { const b = BLOCKS.find((x) => x.id === movedId); if (b) live.textContent = tpl(T.movedTo || '%s flyttet til plads %s af %s', b.name, o.indexOf(movedId) + 1, o.length); } }
	function moveBlock(id, dir) { const o = order.slice(), i = o.indexOf(id), j = i + dir; if (i < 0 || j < 0 || j >= o.length) return; o.splice(i, 1); o.splice(j, 0, id); commitOrder(o, id); }
	let drag = null;
	function buildList(focusId) {
		const list = dBody && dBody.querySelector('.blist'); if (!list) return; list.replaceChildren();
		order.forEach((id) => {
			const B = BLOCKS.find((b) => b.id === id); if (!B) return;
			const wrap = h('div', 'brow'); wrap.dataset.id = id;
			const grip = h('button', 'grip'); grip.type = 'button'; grip.title = T.dragHint || ''; grip.setAttribute('aria-label', tpl(T.moveLabel || 'Flyt %s', B.name)); grip.append(SVG('<circle cx="9" cy="6" r="1.2"/><circle cx="15" cy="6" r="1.2"/><circle cx="9" cy="12" r="1.2"/><circle cx="15" cy="12" r="1.2"/><circle cx="9" cy="18" r="1.2"/><circle cx="15" cy="18" r="1.2"/>'));
			grip.addEventListener('keydown', (e) => { if (e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) { e.preventDefault(); moveBlock(id, e.key === 'ArrowUp' ? -1 : 1); } });
			grip.addEventListener('click', (e) => e.stopPropagation());
			let row;
			if (B.items) {
				const n0 = B.items.filter(([k]) => isOn(k)).length; row = h('div', 'trow' + (n0 ? '' : ' off')); const tx = h('div', 'tx'); tx.append(h('b', '', B.name), h('span', '', tpl(T.shownOf || '%s af %s vises', n0, B.items.length)));
				const fold = h('button', 'fold'); fold.type = 'button'; fold.setAttribute('aria-label', tpl(T.showCards || 'Vis kort i %s', B.name)); fold.setAttribute('aria-expanded', 'false'); fold.append(SVG('<path d="m9 6 6 6-6 6"/>'));
				fold.onclick = (e) => { e.stopPropagation(); const o = wrap.classList.toggle('open'); fold.setAttribute('aria-expanded', o); }; row.append(tx, fold); row.addEventListener('click', () => fold.click());
				const sub = h('div', 'bsub'), inner = h('div');
				B.items.forEach(([k, t, d]) => inner.append(mkRow(t, d, isOn(k), (v) => { setWidget(k, v); save({ action: 'widget', key: k, on: v ? '1' : '0' }).then(markSaved); const n = B.items.filter(([kk]) => isOn(kk)).length; tx.lastChild.textContent = tpl(T.shownOf || '%s af %s vises', n, B.items.length); row.classList.toggle('off', !n); }, () => cardOf(k))));
				sub.append(inner); row.prepend(grip); wrap.append(row, sub);
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
		if (focusId) { const g = list.querySelector(`.brow[data-id="${focusId}"] .grip`); g && g.focus({ preventScroll: true }); }
	}
	function setHidden(v) { hidden = v; grid && grid.classList.toggle('all-hidden', v); save({ action: 'hide', hidden: v ? '1' : '0' }).then(markSaved); if (window.parent !== window) { try { window.parent.postMessage({ type: 'saldi:dash-hidden', hidden: v }, location.origin); } catch (e) {} } }
	function buildDrawer() {
		if (!dBody) return; dBody.replaceChildren();
		dBody.append(mkRow(T.showDash || 'Vis oversigten', T.showDashHelp || '', !hidden, (v) => setHidden(!v)));
		const g1 = h('div', 'grp', T.contentOrder || 'Indhold og rækkefølge'); g1.append(h('span', 'tag', T.dragHint || '')); dBody.append(g1);
		dBody.append(h('div', 'blist')); buildList();
		if (D.accounts) {
			const g = h('div', 'grp', T.accounts || 'Omsætningskonti'); g.append(h('span', 'tag', T.companyWide || 'Gælder hele regnskabet')); dBody.append(g);
			const a = h('div', 'acct');
			[[T.fromAcc || 'Fra kontonr.', 'kontomin'], [T.toAcc || 'Til kontonr.', 'kontomaks']].forEach(([l, k]) => { const lab = h('label', '', l), inp = h('input'); inp.inputMode = 'numeric'; inp.value = D.accounts[k] || ''; inp.disabled = !D.accounts.canEdit; inp.addEventListener('change', () => { D.accounts[k] = inp.value; save({ action: 'accounts', kontomin: D.accounts.kontomin, kontomaks: D.accounts.kontomaks }).then((r) => { markSaved(); if (r && r.reload) location.reload(); }); }); lab.append(inp); a.append(lab); });
			dBody.append(a);
			const hp = h('div', 'help', (T.accountsHelp || '') + ' '); if (D.accounts.guide) { const lk = h('a', 'link', T.seeGuide || 'Se guiden'); lk.href = D.accounts.guide; lk.target = '_blank'; lk.rel = 'noopener'; hp.append(lk); } dBody.append(hp);
		}
	}
	function drawerOpen(on) {
		if (!drawer) return; if (on) buildDrawer(); drawer.classList.toggle('open', on); scrim && scrim.classList.toggle('open', on);
		if (on) setTimeout(() => { const c = $('#drawerClose'); c && c.focus({ preventScroll: true }); }, 60); else $$('.spot').forEach((c) => c.classList.remove('spot'));
	}
	window.saldiDashDrawer = drawerOpen;
	window.saldiDashSetHidden = setHidden;
	const dc = $('#drawerClose'), dd = $('#drawerDone'); dc && (dc.onclick = () => drawerOpen(false)); dd && (dd.onclick = () => drawerOpen(false)); scrim && (scrim.onclick = () => drawerOpen(false));
	drawer && drawer.addEventListener('click', (e) => e.stopPropagation());
	const ALL_KEYS = BLOCKS.flatMap((b) => b.items ? b.items.map((i) => i[0]) : [b.id]);
	const rs = $('#dashReset'); rs && (rs.onclick = () => { ALL_KEYS.forEach((k) => setWidget(k, !DEFAULT_OFF.has(k))); if (hidden) setHidden(false); order = DEFAULT_ORDER.slice(); applyOrder(order); save({ action: 'reset' }).then(markSaved); buildDrawer(); });
	const sd = $('#showDash'); sd && (sd.onclick = () => setHidden(false));
	const ed = $('#dashEdit'); ed && (ed.onclick = () => drawerOpen(true));
	document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && drawer && drawer.classList.contains('open')) drawerOpen(false); });
	window.addEventListener('message', (e) => { if (e.origin === location.origin && e.data && e.data.type === 'saldi:dash-edit') drawerOpen(true); });
	Object.keys(dash).forEach((k) => { if (!isOn(k)) setWidget(k, false, false); }); if (hidden) grid && grid.classList.add('all-hidden');

	/* ---- entry: cards slide in staggered, charts start when seen ---- */
	const anim = { month: () => monthChart && monthChart.update(M.now, M.last, { stagger: true }), day: () => dayChart && WK && dayChart.update(WK.list[0].now, WK.list[0].last, { stagger: true }), heat: () => heat && heat.enter(), donut: () => donut && donut.enter(), unpaid: () => unpaid && unpaid.enter() };
	/* Axis text at a fixed size (addendum 3 §5.1): SVG text scales with the viewBox, so --axis is set per chart to 0.78rem
	   divided by the chart's scale; redone on resize and when the text-size choice changes. */
	const fitAxisText = () => { const rem = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
		$$('svg.chart[viewBox]').forEach((svg) => { const W = +svg.getAttribute('viewBox').split(' ')[2], w = svg.clientWidth || W; svg.style.setProperty('--axis', (0.78 * rem / (w / W)).toFixed(2) + 'px'); }); };
	window.addEventListener('resize', fitAxisText);
	new MutationObserver(fitAxisText).observe(document.documentElement, { attributes: true, attributeFilter: ['data-size', 'data-theme'] });
	const cards = $$('.card');
	const io = new IntersectionObserver((es) => { let k = 0; es.forEach((e) => { if (!e.isIntersecting) return; const c = e.target; io.unobserve(c); c.style.setProperty('--i', k++); c.classList.add('in'); const wait = reduced ? 0 : (k - 1) * 60; setTimeout(() => { c.querySelectorAll('[data-count]').forEach(countUp); const a = anim[c.dataset.anim]; a && a(); }, wait + 120); setTimeout(() => c.classList.add('ready'), wait + 800); }); }, { threshold: .12 });
	/* Charts draw only once the web fonts are in (addendum 3 §3.1), so axis text never sticks to the system font. */
	const start = () => { fitAxisText(); cards.forEach((c) => io.observe(c)); setTimeout(fitAxisText, 600); };
	(document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve()).then(start, start);
})();
