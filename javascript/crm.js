// --- javascript/crm.js --- lap 5.0.0 --- 2026.10.20 ---
// 20261020 ASR CRM redesign (debitor/crm.php) after prototype_crm.html v1.1. Draws the data the page embeds as JSON
//              (#crm-data) and posts every change to crmApi.php, which answers with the whole dataset again.
//              Every change can be undone from the message at the bottom (the inverse action is posted).
//              Automatic help is only offered, never done: a follow-up after a status move, the customer's status
//              after a won deal, closing the previous follow-up when a new entry is saved (pre-ticked, can be unticked).
(function () {
'use strict';

/* ================= data ================= */
let DATA = JSON.parse(document.getElementById('crm-data').textContent);
let TODAY, ME, C = [], H = [], DEALS = [], ST = {}, ST_ORDER = [], GROUPS = [], EMP = {}, EXTRA = {};
const PAL = ['var(--s1)', 'var(--s7)', 'var(--s3)', 'var(--s2)', 'var(--s4)', 'var(--s6)'];
const OFF = '#b6c0d0';

function load(d) {
	Object.assign(DATA, d);
	TODAY = fromKey(DATA.today); ME = DATA.me;
	ST = {}; ST_ORDER = [];
	DATA.statuses.forEach(s => { ST[s.id] = {n: s.n, c: OFF}; ST_ORDER.push(s.id); });
	GROUPS = DATA.groups;
	const lost = new Set(GROUPS.map(g => g.lost).filter(Boolean)), won = new Set(GROUPS.map(g => g.won).filter(Boolean));
	ST_ORDER.forEach(id => {
		if (lost.has(id)) return;
		const gi = GROUPS.findIndex(g => g.s.includes(id));
		ST[id].c = gi >= 0 ? PAL[gi % PAL.length] : won.has(id) ? 'var(--s7)' : OFF;
	});
	C = DATA.customers; H = DATA.hist; DEALS = DATA.deals;
	EMP = {}; DATA.emps.forEach(e => { EMP[e.id] = e; });
}

/* ================= hjælpere ================= */
const $ = s => document.querySelector(s);
const key = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
const fromKey = k => { const [y, m, d] = String(k).split('-').map(Number); return new Date(y, m - 1, d); };
const D = n => { const d = new Date(TODAY); d.setDate(d.getDate() + n); return d; };
const diff = k => Math.round((fromKey(k) - TODAY) / 864e5);
// Texts: the Danish text is the key; DATA.txt holds the user's language from tekster.csv (ids 7300-, see
// crm_text_ids() in includes/crmFunctions.php). '%s' is filled in order.
const T = DATA.txt || {};
const t = (k, ...a) => { let s = (T[k] != null && T[k] !== '') ? T[k] : k; a.forEach(v => { s = s.replace('%s', () => String(v)); }); return s; };
// Day and month names from the browser in the user's language (sprog_id 1 da, 2 en, 3 no)
const LOC = ({1: 'da-DK', 2: 'en-GB', 3: 'nb-NO'})[DATA.lang] || 'da-DK';
const nm = (o, d) => new Intl.DateTimeFormat(LOC, o).format(d).replace(/\.$/, '');
const WD = [0, 1, 2, 3, 4, 5, 6].map(i => nm({weekday: 'short'}, new Date(2026, 0, 4 + i)));   // 4 Jan 2026 is a Sunday
const WDL = [0, 1, 2, 3, 4, 5, 6].map(i => nm({weekday: 'long'}, new Date(2026, 0, 4 + i)));
const MO = [...Array(12).keys()].map(m => nm({month: 'short'}, new Date(2026, m, 1)));
const MOL = [...Array(12).keys()].map(m => nm({month: 'long'}, new Date(2026, m, 1)));
const cap = s => s.charAt(0).toUpperCase() + s.slice(1);
const DOT = LOC.indexOf('en') === 0 ? '' : '.';   // 17. okt (da/no) / 17 Oct (en)
const fmt = k => { const d = fromKey(k); return `${WD[d.getDay()]} ${d.getDate()}${DOT} ${MO[d.getMonth()]}`; };
const fmtY = k => { if (!k) return ''; const d = fromKey(k); return `${d.getDate()}${DOT} ${MO[d.getMonth()]} ${d.getFullYear()}`; };
const rel = k => { const n = diff(k); if (n === 0) return t('i dag'); if (n === 1) return t('i morgen'); if (n === -1) return t('i går');
	if (n < 0) return t('for %s dage siden', -n); return n < 300 ? fmt(k) : fmtY(k); };
const num = n => Math.round(n).toLocaleString(LOC);
const kr = n => t('%s kr', num(n));
const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
const cust = id => C.find(c => c.id === id) || EXTRA[id];
const stName = id => (ST[id] || {n: id || t('Ingen status')}).n;
const stColor = id => (ST[id] || {c: OFF}).c;
const empName = id => id && id === ME ? t('Dig') : (EMP[id] ? EMP[id].n : '');
const empFirst = id => id && id === ME ? t('Dig') : (EMP[id] ? EMP[id].n.split(' ')[0] : '');
const empLow = id => id && id === ME ? t('dig') : empName(id);   // inside a sentence
const ppl = (c, id) => { const p = c && c.ppl.find(p => p.id === id); return p ? p.n : ''; };
const isLost = id => GROUPS.some(g => g.lost === id);
const isWon = id => GROUPS.some(g => g.won === id);

/* ================= tilstand ================= */
const S = {tab: 'follow', mine: localStorage.getItem('crm-mine') === '1', grp: 'all', q: '', stF: 'all', calMode: 'month', calY: 0, calM: 0,
	weekStart: null, showDone: false, drawer: null, dtab: 'tl', bgrp: null};

/* The page's place (tab, board group, open customer and its tab) lives in the query string, so a back link or the
   browser's Back button lands where the user was. A returside without '?' would lose to the session's nav stack
   (nav_back_url() prefers a stacked crm.php?inframe=1), so links back here always carry a query. */
const QP0 = new URLSearchParams(location.search);
function placeParams(over) {
	const p = new URLSearchParams();
	['inframe', 'popup'].forEach(k => { if (QP0.get(k)) p.set(k, QP0.get(k)); });
	const st = Object.assign({tab: S.tab, bgrp: S.bgrp, drawer: S.drawer, dtab: S.dtab}, over || {});
	p.set('tab', st.tab);
	if (st.tab === 'pipe' && st.bgrp) p.set('bgrp', st.bgrp);
	if (st.drawer) { p.set('drawer', st.drawer); if (st.dtab && st.dtab !== 'tl') p.set('dtab', st.dtab); }
	return p.toString();
}
let placeTimer = null, placeSent = '';
// Tell the server where the page is (its navigation stack feeds the shell's came-from chip). Debounced; flushed
// before a link leaves the page (see the click handler on outgoing links).
function sendPlace() {
	clearTimeout(placeTimer);
	const q = placeParams();
	if (q === placeSent || !DATA.api) return Promise.resolve();
	placeSent = q;
	return fetch(DATA.api, {method: 'POST', credentials: 'same-origin', keepalive: true,
		headers: {'Content-Type': 'application/json', 'X-CSRF-Token': DATA.csrf}, body: JSON.stringify({action: 'place', q})}).catch(() => {});
}
function syncUrl() {
	try { history.replaceState(null, '', 'crm.php?' + placeParams()); } catch (e) { /* file:// test pages */ }
	clearTimeout(placeTimer); placeTimer = setTimeout(sendPlace, 400);
}
const backTo = (cid, dtab) => encodeURIComponent('crm.php?' + placeParams({drawer: cid, dtab: dtab || 'deals'}));

const ICON = {
	note: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg>',
	opkald: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>',
	mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
	moede: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18.5 14a6.5 6.5 0 0 1 3 6"/></svg>',
	status: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="5" height="16" rx="1.5"/><rect x="10" y="4" width="5" height="11" rx="1.5"/><rect x="17" y="4" width="4" height="7" rx="1.5"/></svg>',
	deal: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>'
};
const TYPE_N = {note: t('Note'), opkald: t('Opkald'), mail: t('Mail'), moede: t('Møde'), status: t('Status'), deal: t('Handel')};
const COMPOSE_TYPES = ['opkald', 'mail', 'moede', 'note'];
const SV = {
	check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>',
	clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
	edit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16z"/></svg>',
	trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>',
	x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>',
	plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
	ext: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>',
	clip: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 11-8.5 8.5a5 5 0 0 1-7-7L14 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 7"/></svg>',
	left: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m15 6-6 6 6 6"/></svg>',
	right: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg>',
	search: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>'
};

/* ================= server ================= */
let busy = false;
async function api(action, payload) {
	busy = true;
	try {
		const res = await fetch(DATA.api, {method: 'POST', credentials: 'same-origin',
			headers: {'Content-Type': 'application/json', 'X-CSRF-Token': DATA.csrf}, body: JSON.stringify(Object.assign({action}, payload || {}))});
		let j = null;
		try { j = await res.json(); } catch (e) { j = {ok: false, error: 'http ' + res.status}; }
		if (!j.ok) { toast(t('Kunne ikke gemme (%s). Prøv igen.', j.error || res.status)); throw new Error(j.error); }
		if (j.data) { load(j.data); renderAll(); if (S.drawer) renderDrawer(); }
		return j.result;
	} finally { busy = false; }
}

/* ================= filtre ================= */
function custOk(c) {
	if (!c) return false;
	if (S.grp !== 'all') { const g = GROUPS.find(g => String(g.id) === String(S.grp)); if (g && !g.s.includes(c.st)) return false; }
	return true;
}
function match(h) {
	if (!S.q) return true;
	const c = cust(h.cid), q = S.q.toLowerCase();
	return (c.n + ' ' + c.nr + ' ' + ppl(c, h.to) + ' ' + h.note).toLowerCase().includes(q);
}
function custMatch(c) {
	if (!S.q) return true;
	const q = S.q.toLowerCase();
	return (c.n + ' ' + c.nr + ' ' + c.by + ' ' + c.ppl.map(p => p.n).join(' ')).toLowerCase().includes(q);
}
const follows = incDone => H.filter(h => h.due && (incDone || !h.done) && custOk(cust(h.cid)) && (!S.mine || h.by === ME) && match(h));
const openFor = cid => H.filter(h => h.cid === cid && h.due && !h.done).sort((a, b) => a.due.localeCompare(b.due));
const lastContact = cid => H.filter(h => h.cid === cid && h.type !== 'status' && h.type !== 'deal').map(h => h.date).sort().pop();

/* ================= faner ================= */
function renderTabs() {
	const open = follows(false), late = open.filter(h => diff(h.due) < 0).length;
	const custs = C.filter(c => custOk(c) && (!S.mine || c.own === ME) && custMatch(c)).length;
	const tabs = [
		['follow', t('Opfølgning'), `<span class="n ${late ? 'late' : ''}">${open.length}${late ? ' · ' + t('%s forsinket', late) : ''}</span>`],
		['pipe', t('Pipeline'), ''],
		['cal', t('Kalender'), ''],
		['cust', t('Kunder'), `<span class="n">${custs}</span>`],
		['act', t('Aktivitet'), '']
	];
	$('#tabs').innerHTML = tabs.map(([id, n, x]) => `<button class="tab" role="tab" aria-selected="${S.tab === id}" data-tab="${id}">${n}${x}</button>`).join('');
}

/* ================= 1. opfølgning ================= */
function bucket(k) {
	const n = diff(k);
	if (n < 0) return 0; if (n === 0) return 1; if (n === 1) return 2;
	const toSun = (7 - TODAY.getDay()) % 7;
	if (n <= toSun) return 3; if (n <= toSun + 7) return 4; return 5;
}
const BUCKETS = [t('Forsinket'), t('I dag'), t('I morgen'), t('Resten af ugen'), t('Næste uge'), t('Senere')];
const FCOLS = '28px minmax(170px,1.25fr) minmax(220px,2.2fr) 130px 118px 104px 150px';
function followRow(h, done) {
	const c = cust(h.cid), n = diff(h.due);
	const cls = done ? '' : n < 0 ? 'late' : n === 0 ? 'today' : '';
	return `<div class="row body-row ${done ? 'done-row' : ''}" style="--cols:${FCOLS}" tabindex="0" data-open="${c.id}" data-h="${h.id}">
		${DATA.canWrite ? `<button class="tick ${h.done ? 'on' : ''}" data-tick="${h.id}" title="${h.done ? t('Markér som ikke gjort') : t('Markér som gjort')}" aria-label="${t('Gjort')}">${SV.check}</button>` : '<span></span>'}
		<span class="cust"><b>${esc(c.n)}</b>${h.to ? `<small>${esc(ppl(c, h.to))}</small>` : ''}</span>
		<span class="note">${esc(h.note)}</span>
		<span class="dot c2" style="--c:${stColor(c.st)}">${esc(stName(c.st))}</span>
		<span class="who ${h.by === ME ? 'me' : ''}">${esc(empName(h.by))}</span>
		<span class="due ${cls}" title="${fmtY(h.due)}">${n < -1 ? fmt(h.due) : rel(h.due)}</span>
		<span class="acts">${done || !DATA.canWrite ? '' : `<button class="mini" data-snooze="${h.id}">${SV.clock}${t('Udsæt')}</button>`}<button class="mini" data-open="${c.id}">${t('Åbn')}</button></span>
	</div>`;
}
function renderFollow() {
	const list = follows(false).sort((a, b) => a.due.localeCompare(b.due) || a.cid - b.cid);
	let html = `<div class="row thead" style="--cols:${FCOLS}"><span></span><span>${t('Kunde · kontakt')}</span><span>${t('Opfølgning')}</span><span>${t('Status')}</span><span>${t('Ansvarlig')}</span><span>${t('Dato')}</span><span></span></div>`;
	if (!list.length) html += `<div class="empty"><b>${t('Ingen åbne opfølgninger')}</b>${S.q || S.mine || S.grp !== 'all' ? t('Prøv at fjerne filtrene.') : t('Opret en med »Ny opfølgning« eller fra en kunde.')}</div>`;
	BUCKETS.forEach((b, i) => {
		const rows = list.filter(h => bucket(h.due) === i);
		if (rows.length) html += `<div class="grp ${i === 0 ? 'late' : ''}">${b}<span class="n">${rows.length}</span></div>` + rows.map(h => followRow(h, false)).join('');
	});
	const done = follows(true).filter(h => h.done && diff(h.due) >= -14).sort((a, b) => b.due.localeCompare(a.due));
	if (done.length) {
		html += `<button class="showdone" id="showDone">${S.showDone ? t('Skjul %s gjort de sidste 14 dage', done.length) : t('Vis %s gjort de sidste 14 dage', done.length)}</button>`;
		if (S.showDone) html += `<div class="grp">${t('Gjort')}<span class="n">${done.length}</span></div>` + done.map(h => followRow(h, true)).join('');
	}
	$('#v-follow').innerHTML = html;
}

/* ================= 2. pipeline ================= */
// One board per status group (Salg, Kunder, Support): the cards are the customers, a drag changes the customer's
// status. A customer's open deals are shown on the card (amount and count) and are kept in the customer drawer,
// so there is one board per group and no separate deal board (Adam, 20/10).
function boardGroup() {
	if (!GROUPS.length) return {id: 0, n: t('Alle statusser'), s: ST_ORDER.slice(), won: '', lost: ''};
	return GROUPS.find(g => String(g.id) === String(S.bgrp)) || GROUPS[0];
}
const ageDays = k => k ? -diff(k) : 0;
const ageTxt = a => a < 1 ? t('ny') : a > 365 ? t('%s år', Math.floor(a / 365)) : t('%s d', a);
const openDeals = cid => DEALS.filter(d => d.cid === cid && d.state === 'open');
function cardSort(a, b) {
	const da = openFor(a.id)[0], db = openFor(b.id)[0];
	if (da && db) return da.due.localeCompare(db.due);
	if (da) return -1; if (db) return 1;
	return a.n.localeCompare(b.n, LOC);
}
function custCard(c) {
	const nx = openFor(c.id)[0], n = nx ? diff(nx.due) : null, age = ageDays(c.stSince);
	const deals = openDeals(c.id), sum = deals.reduce((a, d) => a + d.v, 0);
	return `<button class="card" draggable="${DATA.canWrite}" data-card="${c.id}" data-open="${c.id}">
		<div class="l1"><b>${esc(c.n)}</b>${sum ? `<span class="val" title="${deals.map(d => esc(d.t)).join(', ')}">${num(sum)}</span>` : ''}</div>
		<div class="l2">${deals.length ? (deals.length === 1 ? esc(deals[0].t) : t('%s åbne handler', deals.length)) : c.ppl.length ? esc(c.ppl[0].n) + (c.ppl.length > 1 ? ` +${c.ppl.length - 1}` : '') : esc(c.by || c.nr)}</div>
		<div class="l3">${nx ? `<span class="due ${n < 0 ? 'late' : n === 0 ? 'today' : ''}" title="${esc(nx.note)}">${n < -1 ? fmt(nx.due) : rel(nx.due)}</span>` : `<span class="nofu">${t('Ingen opfølgning')}</span>`}
			<span>${esc(empFirst(c.own))}</span>
			<span class="age ${age > 14 ? 'stale' : ''}" title="${esc(t('I %s siden %s', stName(c.st), fmtY(c.stSince)))}">${ageTxt(age)}</span></div>
	</button>`;
}
function renderPipe() {
	const g = boardGroup();
	if (!g.s.length) {
		$('#v-pipe').innerHTML = `<div class="empty"><b>${t('Ingen statusser at vise')}</b>${t('Kundestatusser oprettes på debitorkortet (feltet Status). Statusgrupper sættes op under tandhjulet.')}</div>`;
		return;
	}
	const base = C.filter(c => g.s.includes(c.st) && (!S.mine || c.own === ME) && custMatch(c));
	const late = base.filter(c => { const nx = openFor(c.id)[0]; return nx && diff(nx.due) < 0; }).length;
	const none = base.filter(c => !openFor(c.id).length).length;
	const dealSum = base.reduce((a, c) => a + openDeals(c.id).reduce((x, d) => x + d.v, 0), 0);
	const sum = t('%s kunder', `<b>${base.length}</b>`) + (dealSum ? ' · ' + t('åbne handler %s', `<b>${kr(dealSum)}</b>`) : '') + (late ? ` · <span class="late">${t('%s med forsinket opfølgning', late)}</span>` : '') + (none ? ' · ' + t('%s uden opfølgning', none) : '');
	const cols = g.s.map(id => {
		const list = base.filter(c => c.st === id).sort(cardSort);
		const colSum = list.reduce((a, c) => a + openDeals(c.id).reduce((x, d) => x + d.v, 0), 0);
		return `<div class="col" data-col="${esc(id)}"><div class="colh"><span class="dot" style="--c:${stColor(id)}"></span>${esc(stName(id))}<span class="n">${list.length}</span><span class="sum">${colSum ? kr(colSum) : ''}</span></div>
			<div class="cards">${list.map(custCard).join('')}</div></div>`; }).join('');
	$('#v-pipe').innerHTML = `<div class="pipehead">
			${GROUPS.length ? `<div class="seg" role="tablist" aria-label="${t('Statusgruppe')}">${GROUPS.map(x => `<button data-bgrp="${x.id}" class="${x.id === g.id ? 'on' : ''}">${esc(x.n)}</button>`).join('')}</div>` : ''}
			<span class="psum">${sum}</span>
		</div>
		<div class="board">${cols}</div>
		<div class="ends">${g.won ? `<div class="end won" data-end="won">${t('Vundet')}<small>→ ${esc(stName(g.won))}</small></div>` : ''}${g.lost ? `<div class="end lost" data-end="lost">${t('Tabt')}<small>→ ${esc(stName(g.lost))}</small></div>` : ''}</div>`;
}

/* ================= 3. kalender ================= */
function evHtml(h, big) {
	const c = cust(h.cid), late = !h.done && diff(h.due) < 0, drag = DATA.canWrite && !h.done;
	if (big) return `<button class="wcard ${late ? 'late' : ''} ${h.done ? 'done' : ''}" draggable="${drag}" data-ev="${h.id}" data-open="${c.id}"><b>${esc(c.n)}</b><p>${esc(h.note)}</p><small>${esc(empName(h.by))}${h.to ? ' → ' + esc(ppl(c, h.to)) : ''}</small></button>`;
	return `<button class="ev ${late ? 'late' : ''} ${h.done ? 'done' : ''}" draggable="${drag}" data-ev="${h.id}" data-open="${c.id}" title="${esc(c.n)} – ${esc(h.note)}"><span>${esc(c.n)}</span></button>`;
}
function isoWeek(d) { const t = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate())); const n = t.getUTCDay() || 7; t.setUTCDate(t.getUTCDate() + 4 - n); const y = new Date(Date.UTC(t.getUTCFullYear(), 0, 1)); return Math.ceil(((t - y) / 864e5 + 1) / 7); }
function mondayOf(k) { const d = fromKey(k); d.setDate(d.getDate() - ((d.getDay() + 6) % 7)); return key(d); }
function renderCal() {
	const byDay = {};
	follows(true).forEach(h => (byDay[h.due] = byDay[h.due] || []).push(h));
	Object.values(byDay).forEach(a => a.sort((x, y) => x.done - y.done));
	let head, body = '';
	if (S.calMode === 'month') {
		head = `${MOL[S.calM]} ${S.calY}`;
		const first = new Date(S.calY, S.calM, 1), start = new Date(first);
		start.setDate(1 - ((first.getDay() + 6) % 7));
		const weeks = Math.ceil((((first.getDay() + 6) % 7) + new Date(S.calY, S.calM + 1, 0).getDate()) / 7);
		body = '<div class="month">' + [1, 2, 3, 4, 5, 6, 0].map(i => `<div class="dow">${esc(cap(WD[i]))}</div>`).join('');
		for (let i = 0; i < weeks * 7; i++) {
			const d = new Date(start); d.setDate(start.getDate() + i);
			const k = key(d), ev = byDay[k] || [];
			const cls = [d.getMonth() !== S.calM ? 'out' : '', k === DATA.today ? 'today' : '', d.getDay() % 6 === 0 ? 'weekend' : ''].join(' ');
			body += `<div class="day ${cls}" data-day="${k}"><span class="dn">${d.getDate()}</span>${DATA.canWrite ? `<button class="add" data-new="${k}" title="${esc(t('Ny opfølgning %s', fmt(k)))}">${SV.plus}</button>` : ''}` +
				ev.slice(0, 4).map(h => evHtml(h)).join('') + (ev.length > 4 ? `<button class="more" data-week="${k}">${t('+%s mere', ev.length - 4)}</button>` : '') + '</div>';
		}
		body += '</div>';
	} else {
		const ws = fromKey(S.weekStart), we = new Date(ws); we.setDate(ws.getDate() + 6);
		head = t('Uge %s', isoWeek(ws)) + ` · ${ws.getDate()}${DOT} ${MO[ws.getMonth()]} – ${we.getDate()}${DOT} ${MO[we.getMonth()]}`;
		body = '<div class="week">';
		for (let i = 0; i < 7; i++) {
			const d = new Date(ws); d.setDate(ws.getDate() + i);
			const k = key(d), ev = byDay[k] || [];
			body += `<div class="wcol ${k === DATA.today ? 'today' : ''}" data-day="${k}"><h3><b>${d.getDate()}</b>${WDL[d.getDay()]}</h3>${ev.map(h => evHtml(h, true)).join('')}</div>`;
		}
		body += '</div>';
	}
	$('#v-cal').innerHTML = `<div class="calhead">
		<button class="ghost" id="calPrev" aria-label="${t('Forrige')}">${SV.left}</button><button class="ghost" id="calNext" aria-label="${t('Næste')}">${SV.right}</button>
		<h2>${head}</h2><button class="ghost" id="calToday">${t('I dag')}</button>
		<span class="legend"><span class="dot" style="--c:var(--saldi-blue)">${t('Åben')}</span><span class="dot" style="--c:var(--bad)">${t('Forsinket')}</span><span class="dot" style="--c:${OFF}">${t('Gjort')}</span>${DATA.canWrite ? `<span class="c3">${t('Træk for at flytte')}</span>` : ''}</span>
		<div class="seg"><button data-mode="month" class="${S.calMode === 'month' ? 'on' : ''}">${t('Måned')}</button><button data-mode="week" class="${S.calMode === 'week' ? 'on' : ''}">${t('Uge')}</button></div>
	</div>` + body;
}

/* ================= 4. kunder ================= */
const KCOLS = 'minmax(190px,1.4fr) minmax(130px,1fr) 140px 118px 128px 150px 110px';
function renderCust() {
	const base = C.filter(c => custOk(c) && (!S.mine || c.own === ME) && custMatch(c));
	// Line 1 = the status groups, always shown (the way back to "Alle"). Line 2 = the chosen group's statuses as a funnel.
	const all = C.filter(c => (!S.mine || c.own === ME) && custMatch(c));
	const rest = all.filter(c => !GROUPS.some(g => g.s.includes(c.st))).length;
	let strip = `<div class="strip">` +
		`<button class="${S.grp === 'all' && S.stF !== 'rest' ? 'on' : ''}" data-grp="all">${t('Alle')} <span class="n">${all.length}</span></button>` +
		GROUPS.map(g => `<button class="${String(S.grp) === String(g.id) ? 'on' : ''}" data-grp="${g.id}">${esc(g.n)} <span class="n">${all.filter(c => g.s.includes(c.st)).length}</span></button>`).join('') +
		(rest && GROUPS.length ? `<button class="dot ${S.grp === 'all' && S.stF === 'rest' ? 'on' : ''}" style="--c:${OFF}" data-grp="all" data-stf="rest">${t('Uden gruppe')} <span class="n">${rest}</span></button>` : '') + '</div>';
	if (S.grp !== 'all') {
		const ids = GROUPS.find(g => String(g.id) === String(S.grp)).s;
		strip += `<div class="strip sub"><button class="${S.stF === 'all' ? 'on' : ''}" data-stf="all">${t('Alle i %s', esc(GROUPS.find(g => String(g.id) === String(S.grp)).n.toLowerCase()))} <span class="n">${base.length}</span></button>` +
			ids.map((id, i) => (i ? '<span class="arrow">›</span>' : '') + `<button class="dot ${S.stF === id ? 'on' : ''}" style="--c:${stColor(id)}" data-stf="${esc(id)}">${esc(stName(id))} <span class="n">${base.filter(c => c.st === id).length}</span></button>`).join('') + '</div>';
	}
	const list = base.filter(c => S.stF === 'all' || (S.stF === 'rest' ? !GROUPS.some(g => g.s.includes(c.st)) : c.st === S.stF)).sort((a, b) => a.n.localeCompare(b.n, LOC));
	let html = strip + `<div class="row thead" style="--cols:${KCOLS}"><span>${t('Kunde')}</span><span>${t('Kontaktperson')}</span><span>${t('Status')}</span><span>${t('Ansvarlig')}</span><span>${t('Seneste kontakt')}</span><span>${t('Næste opfølgning')}</span><span class="r">${t('Omsætning i år')}</span></div>`;
	html += list.map(c => {
		const nx = openFor(c.id)[0], lc = lastContact(c.id), nn = nx ? diff(nx.due) : null;
		return `<div class="row body-row" style="--cols:${KCOLS}" tabindex="0" data-open="${c.id}">
			<span class="cust"><b>${esc(c.n)}</b><small>${esc(c.nr)}${c.by ? ' · ' + esc(c.by) : ''}</small></span>
			<span class="c2">${c.ppl.length ? esc(c.ppl[0].n) + (c.ppl.length > 1 ? ` <span class="c3">+${c.ppl.length - 1}</span>` : '') : '<span class="c3">–</span>'}</span>
			<span class="dot c2" style="--c:${stColor(c.st)}">${c.st ? esc(stName(c.st)) : `<span class="c3">${t('Ingen')}</span>`}</span>
			<span class="who ${c.own === ME ? 'me' : ''}">${esc(empName(c.own)) || '<span class="c3">–</span>'}</span>
			<span class="due">${lc ? rel(lc) : '<span class="c3">–</span>'}</span>
			<span class="due ${nn !== null && nn < 0 ? 'late' : nn === 0 ? 'today' : ''}">${nx ? (nn < -1 ? fmt(nx.due) : rel(nx.due)) : `<span class="c3">${t('Ingen')}</span>`}</span>
			<span class="r num ${c.rev ? '' : 'c3'}">${c.rev ? kr(c.rev) : '–'}</span>
		</div>`;
	}).join('');
	if (!list.length) html += `<div class="empty"><b>${t('Ingen kunder')}</b>` + (C.length ? t('Ingen kunder matcher filtrene.') + (DATA.truncated && S.q ? ' ' + t('Kunden er måske ikke blandt de %s viste. Søg via »Ny opfølgning«.', num(DATA.max)) : '') : t('Kunder kommer med i CRM, når de får en status, en note eller en handel. Brug »Ny opfølgning« for at finde en kunde.')) + '</div>';
	$('#v-cust').innerHTML = html;
}

/* ================= 5. aktivitet ================= */
function itemHtml(h, withCust, actions) {
	const c = cust(h.cid) || {n: '?', id: h.cid, ppl: []};
	let fu = '';
	if (h.due) {
		const n = diff(h.due);
		fu = h.done ? `<span class="fu done">${SV.check}${t('Opfølgning %s · gjort', fmt(h.due))}</span>`
			: `<span class="fu ${n < 0 ? 'late' : ''}">${SV.clock}${t('Opfølgning %s', rel(h.due))} · ${esc(empLow(h.by))}</span>`;
	}
	const sys = h.type === 'status' || h.type === 'deal';
	const who = `<b>${esc(empName(h.by)) || t('Ukendt')}</b>${h.to ? ` → ${esc(ppl(c, h.to))}` : ''}`;
	return `<div class="item" data-h="${h.id}">
		<span class="ico" title="${TYPE_N[h.type] || TYPE_N.note}">${ICON[h.type] || ICON.note}</span>
		<div><div class="top">${TYPE_N[h.type] || TYPE_N.note} · ${who}${withCust ? ` · <a href="#" data-open="${c.id}">${esc(c.n)}</a>` : ''}</div>
			<div class="txt">${esc(h.note)}</div>${fu}${h.file ? `<span class="attach">${SV.clip}${esc(h.file)}</span>` : ''}</div>
		<div class="stack"><time title="${fmtY(h.date)}">${diff(h.date) > -7 ? rel(h.date) : fmtY(h.date)}</time>${actions && DATA.canWrite ? `<span class="ia">${sys ? '' : `<button class="mini" data-edit="${h.id}" title="${t('Ret')}">${SV.edit}</button>`}<button class="mini" data-del="${h.id}" title="${t('Slet')}">${SV.trash}</button></span>` : ''}</div>
	</div>`;
}
function renderAct() {
	const list = H.filter(h => custOk(cust(h.cid)) && (!S.mine || h.by === ME) && match(h)).sort((a, b) => b.date.localeCompare(a.date) || b.id - a.id).slice(0, 300);
	let html = '<div class="feed">', last = '';
	list.forEach(h => {
		if (h.date !== last) { last = h.date; const n = diff(h.date);
			html += `<div class="fday">${n === 0 ? t('I dag') : n === -1 ? t('I går') : cap(WDL[fromKey(h.date).getDay()]) + ' ' + fmtY(h.date)}</div>`; }
		html += itemHtml(h, true, false);
	});
	if (!list.length) html += `<div class="empty"><b>${t('Ingen aktivitet')}</b>${t('Ingen henvendelser matcher filtrene.')}</div>`;
	$('#v-act').innerHTML = html + '</div>';
}

/* ================= kundeskuffe ================= */
const DR = {type: 'opkald', due: null, owner: 0, closePrev: true};
function openDrawer(cid, tab) {
	S.drawer = cid; S.dtab = tab || 'tl';
	const c = cust(cid);
	Object.assign(DR, {type: 'opkald', due: null, owner: (c && c.own) || ME || (DATA.emps[0] || {}).id || 0, closePrev: true});
	renderDrawer();
	$('#drawer').classList.add('open'); $('#dscrim').classList.add('open');
	syncUrl();
}
function closeDrawer() { $('#drawer').classList.remove('open'); $('#dscrim').classList.remove('open'); S.drawer = null; syncUrl(); }

function composer(c, ctx) {
	const prev = openFor(c.id)[0];
	const chips = [[t('I morgen'), 1], [t('Om 1 uge'), 7], [t('Om 2 uger'), 14], [t('Om 1 måned'), 30]];
	const custom = DR.due && !chips.some(([, d]) => key(D(d)) === DR.due) ? DR.due : '';
	return `<div class="comp" id="${ctx}comp">
		<div class="ctop"><div class="types">${COMPOSE_TYPES.map(t => `<button type="button" data-type="${t}" class="${DR.type === t ? 'on' : ''}">${ICON[t]}${TYPE_N[t]}</button>`).join('')}</div>
			<label class="with">${t('med')} <select id="${ctx}to">${c.ppl.map(p => `<option value="${p.id}">${esc(p.n)}</option>`).join('')}<option value="0">${t('(ingen)')}</option></select></label></div>
		<textarea id="${ctx}Note" placeholder="${esc(t('Hvad blev der talt om?'))}"></textarea>
		<div class="cfoot"><span class="lbl">${t('Opfølgning')}</span>
			<div class="chips" id="${ctx}chips"><button type="button" data-due="" class="${!DR.due ? 'on' : ''}">${t('Ingen')}</button>${chips.map(([n, d]) => `<button type="button" data-due="${key(D(d))}" class="${DR.due === key(D(d)) ? 'on' : ''}" title="${fmt(key(D(d)))}">${n}</button>`).join('')}<input type="date" id="${ctx}date" title="${t('Vælg dato')}" value="${custom}"></div>
			${DATA.emps.length ? `<label class="owner ${DR.due ? 'show' : ''}" id="${ctx}owner">${t('til')} <select>${DATA.emps.map(e => `<option value="${e.id}" ${e.id === DR.owner ? 'selected' : ''}>${e.id === ME ? t('mig') : esc(e.n)}</option>`).join('')}</select></label>` : `<span id="${ctx}owner"></span>`}
			${prev ? `<label class="close-prev show"><input type="checkbox" id="${ctx}closePrev" ${DR.closePrev ? 'checked' : ''}> ${t('Markér opfølgningen fra %s som gjort', fmt(prev.due))}</label>` : ''}
			<span style="flex:1"></span><span class="kbd">Ctrl+Enter</span><button type="button" class="save" id="${ctx}save" disabled>${t('Gem')}</button>
		</div></div>`;
}

function renderDrawer() {
	const c = cust(S.drawer); if (!c) return;
	const hist = H.filter(h => h.cid === c.id).sort((a, b) => b.date.localeCompare(a.date) || b.id - a.id);
	const nx = openFor(c.id)[0], lc = lastContact(c.id);
	const deals = DEALS.filter(d => d.cid === c.id);
	const ret = encodeURIComponent('crm.php');
	const back = backTo(c.id); // back from the order to this customer's drawer, on Handler og ordrer
	$('#drawer').innerHTML = `<div class="dh">
			<div class="t"><div><h2>${esc(c.n)}</h2><div class="sub2">${t('Kunde %s', esc(c.nr))}${c.by ? ' · ' + esc(c.by) : ''}${c.own ? ' · ' + t('ansvarlig: %s', esc(empLow(c.own))) : ''}</div></div>
				<span class="x"></span>
				<span class="stwrap" style="--c:${stColor(c.st)}"><select class="stsel" id="stSel" aria-label="${t('Status')}" ${DATA.canWrite ? '' : 'disabled'}>${c.st && !ST[c.st] ? `<option value="${esc(c.st)}" selected>${esc(c.st)}</option>` : ''}<option value="" ${!c.st ? 'selected' : ''}>${t('Ingen status')}</option>${ST_ORDER.map(id => `<option value="${esc(id)}" ${id === c.st ? 'selected' : ''}>${esc(stName(id))}</option>`).join('')}</select></span>
				<button class="ibtn" id="dClose" aria-label="${t('Luk (Esc)')}">${SV.x}</button></div>
			<div class="links">${c.tlf ? `<a href="tel:${esc(c.tlf.replace(/ /g, ''))}">${ICON.opkald}${esc(c.tlf)}</a>` : ''}${c.mail ? `<a href="mailto:${esc(c.mail)}">${ICON.mail}${esc(c.mail)}</a>` : ''}${DATA.canWrite ? `<a href="#" data-adddeal="" data-cid="${c.id}">${ICON.deal}${t('Opret handel')}</a>${DATA.quick ? '' : `<a href="ordre.php?konto_id=${c.id}&returside=${back}">${ICON.note}${t('Opret tilbud')}</a>`}<a href="ordre.php?konto_id=${c.id}&ordrestatus=1&returside=${back}">${ICON.note}${t('Opret ordre')}</a>` : ''}<a href="debitorkort.php?id=${c.id}&returside=${ret}">${SV.ext}${t('Debitorkort')}</a>${DATA.jobs ? `<a href="jobliste.php?kontonr=${encodeURIComponent(c.nr)}&konto_id=${c.id}&returside=${ret}">${SV.ext}${t('Jobkort')}</a>` : ''}</div>
			<div class="kv"><div><span>${t('Kunde siden')}</span><b>${c.since ? fmtY(c.since) : '–'}</b></div><div><span>${t('Seneste kontakt')}</span><b>${lc ? rel(lc) : '–'}</b></div><div><span>${t('Omsætning i år')}</span><b>${kr(c.rev || 0)}</b></div><div><span>${t('Åbne poster')}</span><b class="${c.late ? 'late' : ''}">${c.inv ? c.inv + (c.late ? ' · ' + t('%s forfalden', c.late) : '') : '0'}</b></div></div>
			<div class="dtabs"><button data-dtab="tl" class="${S.dtab === 'tl' ? 'on' : ''}">${t('Historik')} <span class="c3">${hist.length}</span></button><button data-dtab="deals" class="${S.dtab === 'deals' ? 'on' : ''}">${t('Handler og ordrer')} <span class="c3">${deals.length + c.docs.length}</span></button><button data-dtab="ppl" class="${S.dtab === 'ppl' ? 'on' : ''}">${t('Kontaktpersoner')} <span class="c3">${c.ppl.length}</span></button></div>
		</div>
		<div class="db">${S.dtab === 'deals' ? dealsTab(c, deals) : S.dtab === 'tl' ? `
			${nx ? `<div class="next ${diff(nx.due) < 0 ? 'late' : ''}"><div><div class="lbl">${t('Næste opfølgning')} · ${diff(nx.due) < 0 ? t('forsinket') + ', ' : ''}${rel(nx.due)} · ${esc(empLow(nx.by))}</div><p>${esc(nx.note)}</p></div>
				${DATA.canWrite ? `<div class="ac"><button class="mini" data-snooze="${nx.id}">${SV.clock}${t('Udsæt')}</button><button class="mini" data-tick="${nx.id}">${SV.check}${t('Gjort')}</button></div>` : ''}</div>` : ''}
			${DATA.canWrite ? composer(c, 'c') : ''}
			<div class="tl">${hist.map(h => itemHtml(h, false, true)).join('') || `<div class="empty"><b>${t('Ingen historik endnu')}</b>${t('Skriv den første note ovenfor.')}</div>`}</div>` : `
			<div class="people">${c.ppl.map(p => `<div class="p"><div><b>${esc(p.n)}</b></div>${DATA.canWrite ? `<button class="mini" data-call="${p.id}">${t('Ny note')}</button>` : ''}
				<div class="pl">${p.tlf ? `<a href="tel:${esc(p.tlf.replace(/ /g, ''))}">${esc(p.tlf)}</a>` : ''}${p.mail ? `<a href="mailto:${esc(p.mail)}">${esc(p.mail)}</a>` : ''}</div></div>`).join('') || `<div class="empty" style="padding:32px"><b>${t('Ingen kontaktpersoner')}</b></div>`}</div>
			<a class="addp" href="debitorkort.php?id=${c.id}&returside=${ret}">${t('+ Tilføj kontaktperson på debitorkortet')}</a>`}
		</div>`;
	if (S.dtab === 'tl' && DATA.canWrite) wireComposer('c', c);
}
function dealsTab(c, list) {
	return `<div class="deals">${list.map(d => `<div class="d" data-editdeal="${d.id}"><b>${esc(d.t)}</b><span class="num">${kr(d.v)}</span>
		<small>${d.state === 'open' ? `<span class="dot" style="--c:${stColor(d.st)}">${esc(stName(d.st))}</span>${d.close ? ' · ' + t('forventet lukket %s', fmt(d.close)) : ''}` : d.state === 'won' ? t('Vundet %s', fmtY(d.closed)) : t('Tabt %s', fmtY(d.closed)) + (d.reason ? ' – ' + esc(d.reason) : '')} · ${esc(empLow(d.own))}${d.doc ? ` · ${esc(docName(d.doc))} (${docState(d.doc)})` : ''}</small></div>`).join('') || `<div class="empty" style="padding:32px"><b>${t('Ingen handler')}</b></div>`}</div>
		${DATA.canWrite ? `<button class="addp" data-adddeal="" data-cid="${c.id}">${t('+ Ny handel')}</button>` : ''}
		<h4 class="dsub">${t('Åbne tilbud og ordrer')}</h4>
		<div class="deals">${c.docs.map(o => `<a class="d doc" href="ordre.php?id=${o.id}&returside=${backTo(c.id)}"><b>${esc(docName(o))}</b><span class="num">${kr(o.sum)}</span>
			<small>${fmtY(o.date)}${o.st === 2 ? ' · ' + t('delvist leveret') : ''}${DEALS.find(d => d.doc && d.doc.id === o.id) ? ' · ' + t('handel: %s', esc(DEALS.find(d => d.doc && d.doc.id === o.id).t)) : ''}</small></a>`).join('') || `<div class="c3" style="padding:8px 0 4px">${t('Ingen åbne tilbud eller ordrer.')}</div>`}</div>
		${DATA.canWrite ? `<div class="docbtns">${DATA.quick ? '' : `<a class="addp" href="ordre.php?konto_id=${c.id}&returside=${backTo(c.id)}">${t('+ Opret tilbud')}</a>`}<a class="addp" href="ordre.php?konto_id=${c.id}&ordrestatus=1&returside=${backTo(c.id)}">${t('+ Opret ordre')}</a></div>` : ''}
		${DATA.quick && DATA.canWrite ? `<div class="c3" style="font-size:12.5px;margin-top:6px">${t('Tilbud kan ikke oprettes, fordi »Anvend hurtigfakturering« er slået til (Indstillinger → Ordrer).')}</div>` : ''}`;
}

function wireComposer(ctx, c, onSaved) {
	const root = document.getElementById(ctx + 'comp'); if (!root) return;
	const note = document.getElementById(ctx + 'Note'), save = document.getElementById(ctx + 'save');
	const upd = () => { save.disabled = busy || (!note.value.trim() && !DR.due); };
	note.addEventListener('input', upd);
	root.querySelectorAll('[data-type]').forEach(b => b.onclick = () => { DR.type = b.dataset.type; root.querySelectorAll('[data-type]').forEach(x => x.classList.toggle('on', x === b)); note.focus(); });
	const chips = document.getElementById(ctx + 'chips'), dateIn = document.getElementById(ctx + 'date'), owner = document.getElementById(ctx + 'owner');
	const setDue = v => { DR.due = v || null; chips.querySelectorAll('button').forEach(x => x.classList.toggle('on', (x.dataset.due || null) === DR.due)); owner.classList.toggle('show', !!DR.due); upd(); };
	chips.querySelectorAll('button').forEach(b => b.onclick = () => { dateIn.value = ''; setDue(b.dataset.due); });
	dateIn.onchange = () => setDue(dateIn.value);
	const osel = owner.querySelector('select'); if (osel) osel.onchange = e => { DR.owner = +e.target.value; };
	const cp = document.getElementById(ctx + 'closePrev'); if (cp) cp.onchange = () => { DR.closePrev = cp.checked; };
	note.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && !save.disabled) save.click(); });
	save.onclick = async () => {
		save.disabled = true;
		const prev = openFor(c.id)[0];
		const close = cp && cp.checked && prev ? [prev.id] : [];
		const due = DR.due;
		const payload = {cid: c.id, type: DR.type, to: +document.getElementById(ctx + 'to').value || 0, note: note.value.trim() || t('Opfølgning'), due: due || '', owner: DR.owner, close};
		try {
			const r = await api('add_entry', payload);
			Object.assign(DR, {due: null, closePrev: true});
			if (onSaved) onSaved();
			const el = document.querySelector(`#drawer .item[data-h="${r.id}"]`); if (el) el.classList.add('flash');
			toast(t('Gemt') + (due ? ' · ' + t('opfølgning %s', rel(due)) : '') + (r.closed.length ? ' · ' + t('forrige opfølgning markeret som gjort') : ''), async () => {
				await api('delete_entry', {id: r.id});
				for (const id of r.closed) await api('toggle_done', {id, done: false});
			});
		} catch (e) { upd(); }
	};
}

/* ================= dialoger ================= */
const closeDlg = () => $('#scrim').classList.remove('open');
function openDlg(html, cls) { const d = $('#dlg'); d.className = 'dlg ' + (cls || ''); d.innerHTML = html; $('#scrim').classList.add('open'); return d; }

function customerPicker(title, intro, onPick) {
	openDlg(`<h3>${title}</h3><div class="pi">${intro}</div>
		<label class="fs">${SV.search}<input id="dq" placeholder="${esc(t('Søg kunde, kundenummer eller kontaktperson'))}" autocomplete="off"></label><div class="res" id="dres"></div>`);
	const q = $('#dq'); let hl = 0, res = [], seq = 0, t = null;
	const draw = () => {
		$('#dres').innerHTML = res.map((c, i) => `<button class="${i === hl ? 'hl' : ''}" data-pick="${c.id}"><b>${esc(c.n)}</b><small>${esc(c.nr)}${c.by ? ' · ' + esc(c.by) : ''}</small><span class="ct">${c.ppl.map(p => esc(p.n)).join(', ')}</span></button>`).join('')
			|| `<div class="empty" style="padding:24px"><b>${t('Ingen kunder')}</b>${t('Opret kunden under Debitor → Konti.')}</div>`;
	};
	const search = async () => {
		const my = ++seq;
		try {
			const r = await fetch(DATA.api, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': DATA.csrf}, body: JSON.stringify({action: 'search', q: q.value})}).then(x => x.json());
			if (my !== seq) return;
			res = r.ok ? r.result : []; hl = 0; draw();
		} catch (e) { /* netværk: behold listen */ }
	};
	q.oninput = () => { clearTimeout(t); t = setTimeout(search, 180); };
	q.onkeydown = e => {
		if (e.key === 'ArrowDown') { hl = Math.min(hl + 1, res.length - 1); draw(); e.preventDefault(); }
		if (e.key === 'ArrowUp') { hl = Math.max(hl - 1, 0); draw(); e.preventDefault(); }
		if (e.key === 'Enter' && res[hl]) pick(res[hl]);
	};
	const pick = c => { if (!C.find(x => x.id === c.id)) EXTRA[c.id] = c; onPick(c); };
	$('#dres').onclick = e => { const b = e.target.closest('[data-pick]'); if (b) pick(res.find(c => c.id === +b.dataset.pick)); };
	search(); setTimeout(() => q.focus(), 30);
}

function newFollowUp(dateKey) {
	Object.assign(DR, {type: 'opkald', due: dateKey || null, owner: ME || (DATA.emps[0] || {}).id || 0, closePrev: true});
	customerPicker(t('Ny opfølgning'), (dateKey ? t('Opfølgning %s', fmt(dateKey)) + '. ' : '') + t('Vælg kunden – du kan også søge på kontaktperson eller kundenummer.'), c => {
		DR.owner = c.own || DR.owner;
		openDlg(`<h3>${t('Ny opfølgning')}</h3><div class="pick"><b>${esc(c.n)}</b><span class="c3">${esc(c.nr)}${c.by ? ' · ' + esc(c.by) : ''}</span><button id="back">${t('Skift kunde')}</button></div><div class="b2">${composer(c, 'n')}</div>`);
		wireComposer('n', c, closeDlg);
		$('#back').onclick = () => newFollowUp(dateKey);
		setTimeout(() => $('#nNote').focus(), 30);
	});
}

// An offer/order linked to a deal (crm_deals.ordre_id): name, state and the suggestions it gives.
const docName = o => t(o.st >= 3 ? 'Faktura %s' : o.st === 0 && !DATA.quick ? 'Tilbud %s' : 'Ordre %s', o.nr);
const docState = o => o.st >= 3 ? t('faktureret') : o.st === 2 ? t('delvist leveret') : o.st === 1 || DATA.quick ? t('ordre') : t('tilbud');
function dealDocBox(deal, c) {
	const back = backTo(c.id), o = deal.doc;
	if (o) {
		const diffSum = o.sum && Math.round(o.sum) !== Math.round(deal.v);
		return `<div class="dealdoc"><div class="dd1"><span>${ICON.note}<b>${esc(docName(o))}</b> · ${kr(o.sum)} · ${docState(o)}</span><a href="ordre.php?id=${o.id}&returside=${back}">${t('Åbn')}</a></div>
			${diffSum && deal.state === 'open' ? `<button type="button" class="ddsug" id="dUseSum" data-v="${Math.round(o.sum)}">${t(o.st === 0 && !DATA.quick ? 'Brug beløbet fra tilbuddet: %s' : 'Brug beløbet fra ordren: %s', kr(o.sum))}</button>` : ''}
			${o.st >= 3 && deal.state === 'open' ? `<button type="button" class="ddsug" id="dWonDoc">${t('Ordren er faktureret – markér handlen som vundet')}</button>` : ''}</div>`;
	}
	if (deal.state !== 'open' || !DATA.canWrite) return '';
	return `<div class="dealdoc"><div class="dd1"><span class="c3">${t('Intet tilbud eller ordre til handlen endnu')}</span><span class="ddl">${DATA.quick ? '' : `<a href="ordre.php?konto_id=${c.id}&crm_deal=${deal.id}&returside=${back}">${t('Opret tilbud')}</a>`}<a href="ordre.php?konto_id=${c.id}&crm_deal=${deal.id}&ordrestatus=1&returside=${back}">${t('Opret ordre')}</a></span></div></div>`;
}
// Deal stages come from the group holding the deal's stage, else the first status group (Salg).
const dealGroup = st => GROUPS.find(x => x.s.includes(st)) || GROUPS[0] || boardGroup();
function dealDialog(deal, cid, stage) {
	const g = dealGroup(deal ? deal.st : stage);
	const go = c => {
		const d = deal || {t: '', v: 0, st: stage || g.s[0] || '', close: key(D(30)), own: c.own || ME || (DATA.emps[0] || {}).id || 0};
		openDlg(`<h3>${deal ? t('Ret handel') : t('Ny handel')}</h3><div class="pick"><b>${esc(c.n)}</b><span class="c3">${esc(c.nr)}</span>${deal ? '' : `<button id="back">${t('Skift kunde')}</button>`}</div>
			<div class="fgrid">
				<label class="wide">${t('Titel')}<input id="dT" value="${esc(d.t)}" placeholder="${esc(t('Fx Saldi Plus, 3 brugere'))}" maxlength="120"></label>
				<label>${t('Beløb (kr)')}<input id="dV" inputmode="decimal" value="${d.v ? Math.round(d.v) : ''}" placeholder="0"></label>
				<label>${t('Forventet lukket')}<input id="dC" type="date" value="${esc(d.close || '')}"></label>
				<label>${t('Stadie')}<select id="dS" ${deal && deal.state !== 'open' ? 'disabled' : ''}>${(deal && !g.s.includes(d.st) ? [d.st] : []).concat(g.s).map(id => `<option value="${esc(id)}" ${id === d.st ? 'selected' : ''}>${esc(stName(id))}</option>`).join('')}</select></label>
				<label>${t('Ansvarlig')}<select id="dO">${DATA.emps.map(e => `<option value="${e.id}" ${e.id === d.own ? 'selected' : ''}>${e.id === ME ? t('Mig') : esc(e.n)}</option>`).join('') || '<option value="0">–</option>'}</select></label>
			</div>
			${deal ? dealDocBox(deal, c) : ''}
			<div class="ft">${deal ? `<button class="left" id="dDel">${t('Slet handel')}</button>` : ''}${deal && deal.state === 'open' ? `<button id="dWon">${t('Vundet')}</button><button id="dLost">${t('Tabt')}</button>` : ''}<button id="dNo">${t('Annullér')}</button><button class="primary" id="dYes">${deal ? t('Gem') : t('Opret handel')}</button></div>`);
		if ($('#back')) $('#back').onclick = () => dealDialog(null, null, stage);
		$('#dNo').onclick = closeDlg;
		if ($('#dDel')) $('#dDel').onclick = async () => { await api('delete_deal', {id: deal.id}); closeDlg(); toast(t('Handlen er slettet')); };
		if ($('#dWon')) $('#dWon').onclick = () => { closeDlg(); moveDeal(deal, {state: 'won'}, t('vundet')); };
		if ($('#dWonDoc')) $('#dWonDoc').onclick = () => { closeDlg(); moveDeal(deal, {state: 'won'}, t('vundet')); };
		if ($('#dUseSum')) $('#dUseSum').onclick = () => { $('#dV').value = $('#dUseSum').dataset.v; $('#dUseSum').remove(); $('#dV').focus(); };
		if ($('#dLost')) $('#dLost').onclick = () => lostDialog(deal, reason => moveDeal(deal, {state: 'lost', reason}, t('tabt') + (reason ? ' – ' + reason : '')));
		$('#dYes').onclick = async () => {
			const t = $('#dT').value.trim(); if (!t) { $('#dT').focus(); return; }
			await api('save_deal', {id: deal ? deal.id : 0, cid: c.id, t, v: $('#dV').value, st: $('#dS').value, close: $('#dC').value, own: +$('#dO').value});
			if (deal && deal.state === 'open' && $('#dS').value !== deal.st) await api('move_deal', {id: deal.id, st: $('#dS').value});
			closeDlg(); toast(deal ? t('Handlen er gemt') : t('Handel oprettet i %s', stName($('#dS').value)));
		};
		setTimeout(() => $('#dT').focus(), 30);
	};
	if (cid) go(cust(cid)); else customerPicker(t('Ny handel'), t('Hvilken kunde gælder handlen?'), go);
}

function lostDialog(d, done) {
	const reasons = [t('Pris'), t('Valgte en konkurrent'), t('Intet behov'), t('Forkert tidspunkt'), t('Ingen svar')];
	openDlg(`<h3>${t('Hvorfor blev »%s« tabt?', esc(d.t))}</h3><div class="pi">${t('Valgfrit. Over tid viser det, hvorfor I taber handler.')}</div>
		<div class="reasons">${reasons.map(r => `<button type="button" data-r="${esc(r)}">${esc(r)}</button>`).join('')}</div>
		<div class="fgrid"><label class="wide">${t('Bemærkning')}<input id="rTxt" maxlength="200"></label></div>
		<div class="ft"><button id="rSkip">${t('Spring over')}</button><button class="primary" id="rOk">${t('Markér som tabt')}</button></div>`);
	let pick = '';
	$('#dlg').querySelectorAll('[data-r]').forEach(b => b.onclick = () => { pick = pick === b.dataset.r ? '' : b.dataset.r; $('#dlg').querySelectorAll('[data-r]').forEach(x => x.classList.toggle('on', x.dataset.r === pick)); });
	$('#rSkip').onclick = () => { closeDlg(); done(''); };
	$('#rOk').onclick = () => { const t = $('#rTxt').value.trim(); closeDlg(); done([pick, t].filter(Boolean).join(': ')); };
}

function editDialog(hid) {
	const h = H.find(x => x.id === hid), c = cust(h.cid);
	openDlg(`<h3>${t('Ret %s fra %s', TYPE_N[h.type].toLowerCase(), fmtY(h.date))}</h3>
		<div class="fgrid">
			<label>${t('Type')}<select id="eT">${COMPOSE_TYPES.map(t => `<option value="${t}" ${t === h.type ? 'selected' : ''}>${TYPE_N[t]}</option>`).join('')}</select></label>
			<label>${t('Med')}<select id="eP">${c.ppl.map(p => `<option value="${p.id}" ${p.id === h.to ? 'selected' : ''}>${esc(p.n)}</option>`).join('')}<option value="0" ${!h.to ? 'selected' : ''}>${t('(ingen)')}</option></select></label>
			<label class="wide">${t('Note')}<textarea id="eN">${esc(h.note)}</textarea></label>
		</div>
		<div class="ft"><button id="eNo">${t('Annullér')}</button><button class="primary" id="eYes">${t('Gem')}</button></div>`);
	$('#eNo').onclick = closeDlg;
	$('#eYes').onclick = async () => {
		const old = {id: h.id, type: h.type, to: h.to, note: h.note};
		await api('update_entry', {id: h.id, type: $('#eT').value, to: +$('#eP').value, note: $('#eN').value});
		closeDlg(); toast(t('Noten er rettet'), () => api('update_entry', old));
	};
	setTimeout(() => $('#eN').focus(), 30);
}

function confirmDel(hid) {
	const h = H.find(x => x.id === hid);
	openDlg(`<h3>${t('Slet %s fra %s?', (TYPE_N[h.type] || TYPE_N.note).toLowerCase(), fmtY(h.date))}</h3><div class="pi">${esc(h.note.slice(0, 160))}${h.note.length > 160 ? '…' : ''}</div><div class="ft"><button id="cNo">${t('Annullér')}</button><button class="danger" id="cYes">${t('Slet')}</button></div>`, 'confirm');
	$('#cNo').onclick = closeDlg;
	$('#cYes').onclick = async () => { const r = await api('delete_entry', {id: h.id}); closeDlg(); toast(t('Slettet'), () => api('restore_entry', {row: r.row})); };
	setTimeout(() => $('#cYes').focus(), 30);
}

/* ================= handlinger ================= */
async function toggleDone(hid, rowEl) {
	const h = H.find(x => x.id === hid); if (!h) return;
	const to = !h.done;
	if (to && rowEl && rowEl.classList.contains('row')) { rowEl.querySelector('.tick').classList.add('on'); rowEl.classList.add('leaving'); }
	await api('toggle_done', {id: hid, done: to});
	const c = cust(h.cid);
	toast(to ? t('%s markeret som gjort', c.n) : t('%s er åben igen', c.n), () => api('toggle_done', {id: hid, done: !to}));
}
function snoozeMenu(hid, anchor) {
	const h = H.find(x => x.id === hid), m = $('#menu');
	const opts = [[t('I morgen'), 1], [t('Om 2 dage'), 2], [t('Næste mandag'), (8 - TODAY.getDay()) % 7 || 7], [t('Om 1 uge'), 7], [t('Om 2 uger'), 14]];
	m.innerHTML = opts.map(([n, d]) => `<button data-to="${key(D(d))}">${n}<small>${fmt(key(D(d)))}</small></button>`).join('') + `<hr><input type="date" id="snDate" aria-label="${t('Vælg dato')}">`;
	const r = anchor.getBoundingClientRect();
	m.style.left = Math.min(r.left, innerWidth - 220) + 'px'; m.style.top = Math.min(r.bottom + 4, innerHeight - 260) + 'px';
	m.classList.add('open');
	const go = async to => { const old = h.due; m.classList.remove('open'); await api('set_due', {id: hid, due: to}); toast(t('Udsat til %s', fmt(to)), () => api('set_due', {id: hid, due: old})); };
	m.querySelectorAll('[data-to]').forEach(b => b.onclick = e => { e.stopPropagation(); go(b.dataset.to); });
	$('#snDate').onchange = e => go(e.target.value);
	$('#snDate').onclick = e => e.stopPropagation();
}
async function setStatus(c, to, how) {
	if (c.st === to) return;
	const r = await api('set_status', {cid: c.id, status: to, how: how || ''});
	const fresh = cust(c.id);
	const suggest = to && !openFor(c.id).length && !isLost(to) && !isWon(to);
	toast(`${c.n}: ${to ? stName(to) : t('ingen status')}${how ? ' (' + how + ')' : ''}`, () => api('set_status', {cid: c.id, status: r.from, remove: r.id}),
		suggest ? {label: t('Opfølgning om 1 uge'), fn: async () => {
			const due = key(D(7));
			await api('add_entry', {cid: c.id, type: 'note', to: fresh.ppl[0] ? fresh.ppl[0].id : 0, note: t('Følg op – %s', stName(to).toLowerCase()), due, owner: fresh.own || ME, close: []});
			toast(t('Opfølgning sat til %s', fmt(due)));
		}} : null);
}
async function moveDeal(d, patch, label) {
	const r = await api('move_deal', Object.assign({id: d.id}, patch));
	const g = dealGroup(d.st), c = cust(d.cid);
	const suggestWon = patch.state === 'won' && g.won && c && c.st !== g.won;
	toast(`${d.t}: ${label}`, () => api('restore_deal', {id: d.id, before: r.before, remove: r.hid}),
		suggestWon ? {label: t('Sæt %s som %s', c.n, stName(g.won)), fn: () => setStatus(c, g.won, t('vundet handel'))} : null);
}

/* ================= statusgrupper (tandhjul) ================= */
let GDRAFT = [];
function renderGroups() {
	$('#grpSel').innerHTML = `<option value="all">${t('Alle statusser')}</option>` + GROUPS.map(g => `<option value="${g.id}" ${String(S.grp) === String(g.id) ? 'selected' : ''}>${esc(g.n)}</option>`).join('');
	$('#grpSel').style.display = GROUPS.length ? '' : 'none';
}
function renderGroupEditor() {
	const opts = sel => '<option value="">–</option>' + ST_ORDER.map(id => `<option value="${esc(id)}" ${id === sel ? 'selected' : ''}>${esc(stName(id))}</option>`).join('');
	$('#sgList').innerHTML = (ST_ORDER.length ? '' : `<div class="pi">${t('Der er ingen kundestatusser endnu. De oprettes på debitorkortet (feltet Status).')}</div>`) +
		GDRAFT.map((g, i) => `<div class="sg" data-i="${i}"><input type="text" value="${esc(g.n)}" aria-label="${t('Gruppenavn')}" data-f="n">${DATA.canWrite ? `<button class="del" data-delg="${i}">${t('Fjern')}</button>` : ''}
		<div class="opts">${ST_ORDER.map(id => `<label><input type="checkbox" data-st="${esc(id)}" ${g.s.includes(id) ? 'checked' : ''}>${esc(stName(id))}</label>`).join('')}</div>
		<div class="wl"><label>${t('Vundet')} → <select data-f="won">${opts(g.won)}</select></label><label>${t('Tabt')} → <select data-f="lost">${opts(g.lost)}</select></label></div></div>`).join('');
}

/* ================= fælles ================= */
let toastTimer, undoFn, extraFn;
function toast(t, undo, extra) {
	$('#toastT').textContent = t; undoFn = undo; $('#toastU').style.display = undo ? '' : 'none';
	extraFn = extra && extra.fn; $('#toastX').textContent = extra ? extra.label : ''; $('#toastX').style.display = extra ? '' : 'none';
	$('#toast').classList.add('show'); clearTimeout(toastTimer); toastTimer = setTimeout(() => $('#toast').classList.remove('show'), extra ? 9000 : 6000);
}
$('#toastU').onclick = async () => { const f = undoFn; $('#toast').classList.remove('show'); if (f) { await f(); toast(t('Fortrudt')); } };
$('#toastX').onclick = () => { const f = extraFn; $('#toast').classList.remove('show'); if (f) f(); };

function renderAll() {
	renderTabs();
	['follow', 'pipe', 'cal', 'cust', 'act'].forEach(t => { $('#v-' + t).hidden = S.tab !== t; });
	$('#grpSel').style.visibility = S.tab === 'pipe' ? 'hidden' : '';
	if (S.tab === 'follow') renderFollow();
	if (S.tab === 'pipe') renderPipe();
	syncUrl();
	if (S.tab === 'cal') renderCal();
	if (S.tab === 'cust') renderCust();
	if (S.tab === 'act') renderAct();
	const nb = $('#noteBar'), notes = [];
	if (!ME) notes.push(t('Din bruger er ikke knyttet til en medarbejder, så »Kun mine« og »Dig« virker ikke. Knyt brugeren til en medarbejder under Indstillinger → Brugere.'));
	if (!DATA.emps.length) notes.push(t('Der er ingen medarbejdere under Indstillinger → Stamdata, så opfølgninger kan ikke fordeles.'));
	if (DATA.truncated) notes.push(t('Viser de %s senest aktive kunder. Andre kunder findes med søgningen under »Ny opfølgning« og »Ny handel«.', num(DATA.max)));
	nb.innerHTML = notes.map(n => esc(n)).join('<br>'); nb.hidden = !notes.length;
	$('#mine').disabled = !ME;
}

/* ================= hændelser ================= */
document.addEventListener('click', e => {
	const t = e.target;
	const out = t.closest('a[href]');
	if (out && !e.defaultPrevented && !e.ctrlKey && !e.metaKey && !e.shiftKey && e.button === 0) {
		const href = out.getAttribute('href');
		if (href && href.charAt(0) !== '#' && !/^(tel|mailto):/.test(href) && out.target !== '_blank') {
			e.preventDefault();
			sendPlace().finally(() => { location.href = href; });
			return;
		}
	}
	if (!t.closest('#menu') && !t.closest('[data-snooze]')) $('#menu').classList.remove('open');
	if (!t.closest('.gear')) { $('#panel').classList.remove('open'); $('#gearBtn').setAttribute('aria-expanded', 'false'); }
	let b;
	if ((b = t.closest('[data-tab]'))) { S.tab = b.dataset.tab; renderAll(); return; }
	if ((b = t.closest('[data-tick]'))) { e.stopPropagation(); toggleDone(+b.dataset.tick, b.closest('.row')); return; }
	if ((b = t.closest('[data-snooze]'))) { e.stopPropagation(); snoozeMenu(+b.dataset.snooze, b); return; }
	if ((b = t.closest('[data-new]'))) { e.stopPropagation(); newFollowUp(b.dataset.new); return; }
	if ((b = t.closest('[data-week]'))) { S.calMode = 'week'; S.weekStart = mondayOf(b.dataset.week); renderCal(); return; }
	if ((b = t.closest('[data-grp]'))) { S.grp = b.dataset.grp; S.stF = b.dataset.stf || 'all'; renderGroups(); renderAll(); return; }
	if ((b = t.closest('[data-stf]'))) { S.stF = b.dataset.stf; renderCust(); return; }
	if ((b = t.closest('[data-bgrp]'))) { S.bgrp = b.dataset.bgrp; renderPipe(); syncUrl(); return; }
	if ((b = t.closest('[data-adddeal]'))) { e.stopPropagation(); e.preventDefault(); dealDialog(null, b.dataset.cid ? +b.dataset.cid : null, b.dataset.adddeal || null); return; }
	if ((b = t.closest('[data-editdeal]'))) { if (DATA.canWrite) dealDialog(DEALS.find(d => d.id === +b.dataset.editdeal), DEALS.find(d => d.id === +b.dataset.editdeal).cid); return; }
	if ((b = t.closest('[data-mode]'))) { S.calMode = b.dataset.mode; if (S.calMode === 'week') S.weekStart = mondayOf(S.calY === TODAY.getFullYear() && S.calM === TODAY.getMonth() ? DATA.today : key(new Date(S.calY, S.calM, 1))); renderCal(); return; }
	if ((b = t.closest('[data-dtab]'))) { S.dtab = b.dataset.dtab; renderDrawer(); syncUrl(); return; }
	if ((b = t.closest('[data-call]'))) { S.dtab = 'tl'; renderDrawer(); const sel = $('#cto'); if (sel) sel.value = b.dataset.call; const n = $('#cNote'); if (n) n.focus(); return; }
	if ((b = t.closest('[data-del]'))) { confirmDel(+b.dataset.del); return; }
	if ((b = t.closest('[data-edit]'))) { editDialog(+b.dataset.edit); return; }
	if ((b = t.closest('[data-open]')) && !t.closest('.drawer')) { e.preventDefault(); openDrawer(+b.dataset.open, b.dataset.deal ? 'deals' : 'tl'); return; }
	if (t.closest('#calPrev') || t.closest('#calNext')) {
		const dir = t.closest('#calPrev') ? -1 : 1;
		if (S.calMode === 'month') { S.calM += dir; if (S.calM < 0) { S.calM = 11; S.calY--; } if (S.calM > 11) { S.calM = 0; S.calY++; } }
		else { const d = fromKey(S.weekStart); d.setDate(d.getDate() + 7 * dir); S.weekStart = key(d); }
		renderCal(); return;
	}
	if (t.closest('#calToday')) { S.calY = TODAY.getFullYear(); S.calM = TODAY.getMonth(); S.weekStart = mondayOf(DATA.today); renderCal(); return; }
	if (t.closest('#showDone')) { S.showDone = !S.showDone; renderFollow(); return; }
});
document.addEventListener('keydown', e => {
	if (e.key === 'Escape') { if ($('#scrim').classList.contains('open')) closeDlg(); else if (S.drawer) closeDrawer(); $('#menu').classList.remove('open'); }
	if (e.key === 'Enter' && e.target.classList && e.target.classList.contains('body-row')) openDrawer(+e.target.dataset.open);
	if ((e.key === 'n' || e.key === 'N') && DATA.canWrite && !e.ctrlKey && !e.metaKey && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && !$('#scrim').classList.contains('open')) { e.preventDefault(); newFollowUp(); }
});
$('#dscrim').onclick = closeDrawer;
$('#drawer').addEventListener('click', e => { if (e.target.closest('#dClose')) closeDrawer(); });
$('#drawer').addEventListener('change', e => { if (e.target.id === 'stSel') setStatus(cust(S.drawer), e.target.value); });
$('#scrim').onclick = e => { if (e.target.id === 'scrim') closeDlg(); };
$('#newBtn').onclick = () => newFollowUp();
$('#newBtn').hidden = !DATA.canWrite;
$('#q').oninput = e => { S.q = e.target.value.trim(); renderAll(); };
$('#grpSel').onchange = e => { S.grp = e.target.value; S.stF = 'all'; renderAll(); };
$('#mine').onclick = () => { S.mine = !S.mine; localStorage.setItem('crm-mine', S.mine ? '1' : '0'); $('#mine').setAttribute('aria-pressed', S.mine); renderAll(); };
$('#gearBtn').onclick = e => {
	e.stopPropagation(); const p = $('#panel'); p.classList.toggle('open'); $('#gearBtn').setAttribute('aria-expanded', p.classList.contains('open'));
	if (p.classList.contains('open')) { GDRAFT = GROUPS.map(g => ({id: g.id, n: g.n, s: g.s.slice(), won: g.won, lost: g.lost})); renderGroupEditor(); }
};
$('#sgList').addEventListener('input', e => {
	const box = e.target.closest('.sg'); if (!box) return;
	const g = GDRAFT[+box.dataset.i];
	if (e.target.dataset.f === 'n') g.n = e.target.value;
	if (e.target.dataset.f === 'won' || e.target.dataset.f === 'lost') g[e.target.dataset.f] = e.target.value;
	if (e.target.dataset.st !== undefined) { const id = e.target.dataset.st; g.s = e.target.checked ? g.s.concat(id) : g.s.filter(x => x !== id); g.s.sort((a, b) => ST_ORDER.indexOf(a) - ST_ORDER.indexOf(b)); }
});
$('#sgList').addEventListener('click', e => { const b = e.target.closest('[data-delg]'); if (b) { e.stopPropagation(); GDRAFT.splice(+b.dataset.delg, 1); renderGroupEditor(); } });
$('#sgAdd').onclick = e => { e.stopPropagation(); GDRAFT.push({id: 0, n: t('Ny gruppe'), s: [], won: '', lost: ''}); renderGroupEditor(); };
$('#sgSave').onclick = async e => { e.stopPropagation(); await api('save_groups', {groups: GDRAFT}); renderGroups(); $('#panel').classList.remove('open'); toast(t('Statusgrupperne er gemt')); };
$('#sgAdd').hidden = $('#sgSave').hidden = !DATA.canWrite;

/* træk og slip: kalender (opfølgninger) og tavle (kunder/handler) */
let dragEv = null, dragCard = null;
document.addEventListener('dragstart', e => {
	const ev = e.target.closest && e.target.closest('[data-ev]'), card = e.target.closest && e.target.closest('[data-card]');
	if (ev) { dragEv = +ev.dataset.ev; ev.classList.add('dragging'); }
	else if (card) { dragCard = card.dataset.card; card.classList.add('dragging'); document.body.classList.add('carddrag'); }
	else return;
	e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', 'crm');
});
document.addEventListener('dragend', () => { dragEv = dragCard = null; document.body.classList.remove('carddrag'); document.querySelectorAll('.dragging,.drop').forEach(x => x.classList.remove('dragging', 'drop')); });
document.addEventListener('dragover', e => {
	const z = dragEv !== null ? e.target.closest('[data-day]') : dragCard !== null ? e.target.closest('[data-col],[data-end]') : null;
	if (!z) return; e.preventDefault();
	document.querySelectorAll('.drop').forEach(x => { if (x !== z) x.classList.remove('drop'); }); z.classList.add('drop');
});
document.addEventListener('drop', async e => {
	if (dragEv !== null) {
		const d = e.target.closest('[data-day]'); if (!d) return; e.preventDefault();
		const h = H.find(x => x.id === dragEv), old = h.due; dragEv = null;
		if (old === d.dataset.day) return renderCal();
		await api('set_due', {id: h.id, due: d.dataset.day});
		toast(t('%s flyttet til %s', cust(h.cid).n, fmt(d.dataset.day)), () => api('set_due', {id: h.id, due: old}));
		return;
	}
	if (dragCard === null) return;
	const z = e.target.closest('[data-col],[data-end]'); if (!z) return; e.preventDefault();
	const id = dragCard; dragCard = null; document.body.classList.remove('carddrag');
	const g = boardGroup(), c = cust(+id); if (!c) return;
	if (z.dataset.col !== undefined) setStatus(c, z.dataset.col);
	else setStatus(c, z.dataset.end === 'won' ? g.won : g.lost, z.dataset.end === 'won' ? t('vundet') : t('tabt'));
});

/* ================= start ================= */
load(DATA);
S.calY = TODAY.getFullYear(); S.calM = TODAY.getMonth(); S.bgrp = GROUPS.length ? GROUPS[0].id : 0;
$('#mine').setAttribute('aria-pressed', S.mine && !!ME);
if (!ME) S.mine = false;
// place from the query (crm.php?tab=pipe&bgrp=25&drawer=4&dtab=deals); the old #pipe / #drawer=4 links still work
const hs = location.hash.slice(1);
const qTab = QP0.get('tab') || hs;
if (/^(follow|pipe|cal|cust|act)$/.test(qTab)) S.tab = qTab;
if (QP0.get('bgrp') && GROUPS.some(g => String(g.id) === QP0.get('bgrp'))) S.bgrp = QP0.get('bgrp');
renderGroups();
renderAll();
const hm = hs.match(/^drawer=(\d+)(?:-(\w+))?$/);
const qDrawer = +(QP0.get('drawer') || (hm ? hm[1] : 0));
if (qDrawer && cust(qDrawer)) openDrawer(qDrawer, QP0.get('dtab') || (hm && hm[2]) || 'tl');
})();
