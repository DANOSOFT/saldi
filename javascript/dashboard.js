// ---- javascript/dashboard.js --- lap 5.0.0 --- 2026.10.08 ---
// 20261008 Sawaneh Oversigt (index/dashboard.php) after Adam's prototype_dashboard_tema.html v5: counting numbers,
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

	/* ---- ring per item group ---- */
	let donut = null;
	if (D.donut && $('#donut')) {
		donut = (function () {
			const raw = D.donut.items, svg = $('#donut'), wrap = $('#donutWrap'), leg = $('#donutLeg'), cx = 88, cy = 88, R = 78, r = 53, gap = .022, total = D.donut.total || 0;
			const items = raw.map(([name, p, other], i) => {
				const col = other ? 'var(--context)' : `var(--s${(i % 7) + 1})`; const path = svg.appendChild(el('path', { fill: col, tabindex: 0, role: 'img', 'aria-label': `${name} ${p} %` }));
				const li = leg.appendChild(h('li')); li.style.setProperty('--n', i); const sp = h('span'); const dot = h('i'); dot.style.background = col; sp.append(dot, document.createTextNode(name)); li.append(sp, h('b', '', da(p, { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' %'));
				return { name, p, col, path, li };
			});
			const c1 = svg.appendChild(el('text', { class: 'c1', x: cx, y: cy - 1, 'text-anchor': 'middle' }, '0')), c2 = svg.appendChild(el('text', { class: 'c2', x: cx, y: cy + 15, 'text-anchor': 'middle' }, T.total || 'mio. kr i alt'));
			let a0 = -Math.PI / 2; items.forEach((o) => { o.a1 = a0; o.a2 = a0 + o.p / 100 * 2 * Math.PI; a0 = o.a2; });
			function arc(a1, a2) { if (a2 - a1 < gap) return ''; a1 += gap / 2; a2 -= gap / 2; const L = a2 - a1 > Math.PI ? 1 : 0, P = (rr, a) => `${(cx + rr * Math.cos(a)).toFixed(2)},${(cy + rr * Math.sin(a)).toFixed(2)}`; return `M${P(R, a1)} A${R},${R} 0 ${L} 1 ${P(R, a2)} L${P(r, a2)} A${r},${r} 0 ${L} 0 ${P(r, a1)} Z`; }
			const totalTxt = () => da(total / 1e6, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
			function draw(p) { const lim = -Math.PI / 2 + p * 2 * Math.PI; items.forEach((o) => o.path.setAttribute('d', arc(o.a1, Math.min(o.a2, lim)))); c1.textContent = da(total / 1e6 * p, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
			function on(o) { svg.classList.add('hovering'); wrap.classList.add('hovering'); o.path.classList.add('on'); o.li.classList.add('on'); const mid = (o.a1 + o.a2) / 2; o.path.style.transform = `translate(${(Math.cos(mid) * 5).toFixed(1)}px,${(Math.sin(mid) * 5).toFixed(1)}px)`; c1.textContent = da(o.p, { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' %'; c2.textContent = o.name.length > 16 ? o.name.slice(0, 15) + '…' : o.name; }
			function off(o) { svg.classList.remove('hovering'); wrap.classList.remove('hovering'); o.path.classList.remove('on'); o.li.classList.remove('on'); o.path.style.transform = ''; c1.textContent = totalTxt(); c2.textContent = T.total || 'mio. kr i alt'; }
			items.forEach((o) => { interactive(o.path, () => tipBody(o.name, [{ color: o.col, label: T.share || 'Andel', value: da(o.p, { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' %' }, { color: o.col, label: T.revenue || 'Omsætning', value: fmt.mio(total * o.p / 100) }]), { enter: () => on(o), leave: () => off(o) }); o.li.addEventListener('pointerenter', () => on(o)); o.li.addEventListener('pointerleave', () => off(o)); });
			draw(0);
			return { enter() { tween({ dur: 1150, delay: 150, step: (p) => draw(easeOut(p)) }); } };
		})();
	}

	/* ---- Rediger oversigt: drawer with switches, saved at once ---- */
	const drawer = $('#drawer'), scrim = $('#scrim'), dBody = $('#drawerBody'), grid = $('#grid');
	const dash = Object.assign({}, D.widgets || {}); let hidden = !!D.hidden;
	const isOn = (k) => dash[k] !== false, cardOf = (k) => document.querySelector(`[data-widget="${k}"]`);
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
	function mkSwitch(on, label, fn) { const b = h('button', 'sw'); b.type = 'button'; b.setAttribute('role', 'switch'); b.setAttribute('aria-checked', on); b.setAttribute('aria-label', label); b.set = (v) => { b.setAttribute('aria-checked', v); }; b.addEventListener('click', (e) => { e.stopPropagation(); const v = b.getAttribute('aria-checked') !== 'true'; b.set(v); fn(v); }); return b; }
	function mkRow(title, desc, on, fn, key) {
		const r = h('div', 'trow' + (on ? '' : ' off')), tx = h('div', 'tx'); tx.append(h('b', '', title)); if (desc) tx.append(h('span', '', desc));
		const sw = mkSwitch(on, title, (v) => { r.classList.toggle('off', !v); fn(v); }); r.append(tx, sw); r.addEventListener('click', () => sw.click()); r.sw = sw;
		if (key) { r.addEventListener('pointerenter', () => { const c = cardOf(key); c && c.classList.add('spot'); }); r.addEventListener('pointerleave', () => { const c = cardOf(key); c && c.classList.remove('spot'); }); }
		return r;
	}
	function setHidden(v) { hidden = v; grid && grid.classList.toggle('all-hidden', v); save({ action: 'hide', hidden: v ? '1' : '0' }).then(markSaved); if (window.parent !== window) { try { window.parent.postMessage({ type: 'saldi:dash-hidden', hidden: v }, location.origin); } catch (e) {} } }
	function buildDrawer() {
		if (!dBody) return; dBody.replaceChildren();
		dBody.append(mkRow(T.showDash || 'Vis oversigten', T.showDashHelp || '', !hidden, (v) => setHidden(!v)));
		(D.widgetGroups || []).forEach((G) => { dBody.append(h('div', 'grp', G.g)); G.items.forEach(([k, t, d]) => dBody.append(mkRow(t, d, isOn(k), (v) => { setWidget(k, v); save({ action: 'widget', key: k, on: v ? '1' : '0' }).then(markSaved); }, k))); });
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
		if (on) setTimeout(() => { const c = $('#drawerClose'); c && c.focus({ preventScroll: true }); }, 60); else $$('.card.spot').forEach((c) => c.classList.remove('spot'));
	}
	window.saldiDashDrawer = drawerOpen;
	window.saldiDashSetHidden = setHidden;
	const dc = $('#drawerClose'), dd = $('#drawerDone'); dc && (dc.onclick = () => drawerOpen(false)); dd && (dd.onclick = () => drawerOpen(false)); scrim && (scrim.onclick = () => drawerOpen(false));
	drawer && drawer.addEventListener('click', (e) => e.stopPropagation());
	const rs = $('#dashReset'); rs && (rs.onclick = () => { (D.widgetGroups || []).forEach((G) => G.items.forEach(([k]) => { dash[k] = true; setWidget(k, true); })); if (hidden) setHidden(false); save({ action: 'reset' }).then(markSaved); buildDrawer(); });
	const sd = $('#showDash'); sd && (sd.onclick = () => setHidden(false));
	const ed = $('#dashEdit'); ed && (ed.onclick = () => drawerOpen(true));
	document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && drawer && drawer.classList.contains('open')) drawerOpen(false); });
	window.addEventListener('message', (e) => { if (e.origin === location.origin && e.data && e.data.type === 'saldi:dash-edit') drawerOpen(true); });
	Object.keys(dash).forEach((k) => { if (!isOn(k)) setWidget(k, false, false); }); if (hidden) grid && grid.classList.add('all-hidden');

	/* ---- entry: cards slide in staggered, charts start when seen ---- */
	const anim = { month: () => monthChart && monthChart.update(M.now, M.last, { stagger: true }), day: () => dayChart && WK && dayChart.update(WK.list[0].now, WK.list[0].last, { stagger: true }), heat: () => heat && heat.enter(), donut: () => donut && donut.enter() };
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
