// --- javascript/crm.js --- ver 5.1.1 --- 2026.11.11 ---
// 20261020 ASR CRM redesign (debitor/crm.php) after prototype_crm.html v1.1. Draws the data the page embeds as JSON
//              (#crm-data) and posts every change to crmApi.php, which answers with the whole dataset again.
//              Every change can be undone from the message at the bottom (the inverse action is posted).
//              Automatic help is only offered, never done: a follow-up after a status move, the customer's status
//              after a won deal, closing the previous follow-up when a new entry is saved (pre-ticked, can be unticked).
// 20261024 ASR after the customer-perspective review (CRM_review_consolidated_2026-10-23_EN.md): a draft per composer
//              that survives re-renders (R1, R2, R9), the opened follow-up is the one offered for closing (R3), the author
//              is shown instead of the owner (R4), "Ny opfølgning" starts with a date (R5), no first-employee default
//              (R6), weekend dates roll to Monday (R18), one-click close of old follow-ups (R23), deal dialog keeps edits,
//              confirms delete with undo, parses amounts by locale (R29, R30, R41), board/deal suggestions (R26-R28),
//              plain error texts and rollback of optimistic UI (R75), no duplicate creates or stale datasets (R76),
//              server search from the main search box (R77).
// 20261028 ASR Pipeline -> Sager and handel -> sag in the texts (tekster.csv); ids, tab key 'pipe' and actions unchanged.
// 20261029 ASR Meetings with colleagues: Nyt møde (Kalender's button, the drawer, a week day's +) with time, length,
//              colleagues and the customer's contact; a warning when someone is busy then, with the next free time
//              offered; calendar invitations only when ticked; colleagues on a sag; "Mine" includes what the user is
//              a colleague on; a personal calendar feed (Kalender -> Abonnér).
// 20261109 ASR The page in the new design (prototype D) with the shared UI kit (css/saldi-kit.css): the templates use the kit's
//              classes (tables, panels, tabs, segmented controls, buttons, fields, drawer, dialogs, popover, toast) next to the hook
//              classes the handlers select on (.row, .body-row, .tick, .item, .drawer, .gear, .strow, .sg and the states .on, .open,
//              .show, .drop, .dragging, .leaving, .flash), which are unchanged. Status colours are tone classes (css/crm.css), not
//              inline colours; segmented buttons carry aria-pressed beside .on; the icons have width and height. Behaviour unchanged.
// 20261109 ASR Review round 1: the picker and "which sag" lists are the kit's .sk-list rows, the lost reasons small kit buttons, the
//              Kunder funnel a frameless .sk-seg, the feed's day heads .sk-form-sub; the week panel fills the frame (crm-calw); the
//              status-group controls are named groups (role group, no longer a tablist without tabs), the drawer tabs are a tablist
//              with tabs and aria-selected; class names without a rule or a selector are gone. Handlers and hooks unchanged.
// 20261110 CDX/ASR Create and edit CRM-only leads, then convert to a debtor before the first quote or order.
// 20261110 CDX/ASR Offer lead creation in every new activity's customer picker and continue the selected activity.
// 20261110 CDX/ASR Explain when a session needs the CRM database update before continuing.
// 20261111 CDX/ASR Show personal notifications inside CRM without depending on the redesigned global shell.
(function () {
'use strict';

/* ================= data ================= */
let DATA = JSON.parse(document.getElementById('crm-data').textContent);
let TODAY, ME, C = [], H = [], DEALS = [], ST = {}, ST_ORDER = [], GROUPS = [], EMP = {}, EXTRA = {};
// a status's colour is a tone class: the status group's series colour (css/crm.css .crm-t1 - .crm-t7), the kit's sk-off without a group
const PAL = ['crm-t1', 'crm-t7', 'crm-t3', 'crm-t2', 'crm-t4', 'crm-t6'];
const OFF = 'sk-off';

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
		ST[id].c = gi >= 0 ? PAL[gi % PAL.length] : won.has(id) ? 'crm-t7' : OFF;
	});
	C = DATA.customers; H = DATA.hist; DEALS = DATA.deals;
	EMP = {}; DATA.emps.forEach(e => { EMP[e.id] = e; });
	mergeFull();
	// a group deleted meanwhile (by this or another user) must not leave the lists pointing at it (R46)
	if (S.grp !== 'all' && !GROUPS.some(g => String(g.id) === String(S.grp))) { S.grp = 'all'; S.stF = 'all'; }
	if (S.bgrp && !GROUPS.some(g => String(g.id) === String(S.bgrp))) S.bgrp = GROUPS.length ? GROUPS[0].id : 0;
}
// One customer's whole history and deals, fetched when its drawer opens (R55) - or the customer itself when it is
// outside the loaded set (R53, R77). Kept across re-loads: rows older than the page's window, and everything of a
// customer the dataset does not hold.
const FULL = {};
function mergeFull() {
	Object.keys(FULL).forEach(k => {
		const cid = +k, f = FULL[k], current = C.find(c => c.id === cid), inC = !!current;
		// Keep the cached customer type/number current after a lead is converted or edited.
		if (current) f.customer = current; else EXTRA[cid] = f.customer;
		const haveH = new Set(H.filter(h => h.cid === cid).map(h => h.id));
		f.hist.forEach(h => { if (!haveH.has(h.id) && (!inC || h.date < DATA.histFrom)) H.push(h); });
		const haveD = new Set(DEALS.filter(d => d.cid === cid).map(d => d.id));
		f.deals.forEach(d => { if (!haveD.has(d.id) && (!inC || (d.state !== 'open' && (d.closed || '') < DATA.dealFrom))) DEALS.push(d); });
	});
}
async function refreshFull(cid) {
	const version = applied;
	const r = await apiRead('customer', {id: cid});
	// A read started before a successful mutation cannot restore the lead's old details.
	if (!r || version !== applied) return false;
	const index = C.findIndex(c => c.id === cid);
	if (index >= 0) C[index] = r.customer;
	FULL[cid] = r; mergeFull();
	return true;
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
// the year is added when the date is not in the current year, so a follow-up from 2023 does not look current (R19)
const fmt = k => { const d = fromKey(k); return `${WD[d.getDay()]} ${d.getDate()}${DOT} ${MO[d.getMonth()]}` + (TODAY && d.getFullYear() !== TODAY.getFullYear() ? ' ' + d.getFullYear() : ''); };
const fmtY = k => { if (!k) return ''; const d = fromKey(k); return `${d.getDate()}${DOT} ${MO[d.getMonth()]} ${d.getFullYear()}`; };
const rel = k => { const n = diff(k); if (n === 0) return t('i dag'); if (n === 1) return t('i morgen'); if (n === -1) return t('i går');
	if (n < 0) return t('for %s dage siden', -n); return n < 300 ? fmt(k) : fmtY(k); };
const num = n => Math.round(n).toLocaleString(LOC);
const kr = n => t('%s kr', num(n));
// Amounts (R41): shown with the ore they have, read back by the user's locale and sent as "12500.50".
const fmtAmt = v => v ? v.toLocaleString(LOC, {minimumFractionDigits: Math.abs(v % 1) > 0.004 ? 2 : 0, maximumFractionDigits: 2}) : '';
function parseAmt(s) {
	s = String(s == null ? '' : s).replace(/\s|kr\.?/gi, '').trim();
	if (s === '') return '0';
	const neg = s.charAt(0) === '-'; if (neg) s = s.slice(1);
	if (!/^[\d.,]+$/.test(s)) return null;
	const ld = s.lastIndexOf('.'), lc = s.lastIndexOf(',');
	let dec = '';
	if (ld >= 0 && lc >= 0) dec = ld > lc ? '.' : ',';
	else if (ld >= 0 || lc >= 0) {
		const sep = ld >= 0 ? '.' : ',', idx = Math.max(ld, lc), after = s.length - idx - 1, times = s.split(sep).length - 1;
		dec = (times === 1 && after !== 3) ? sep : '';   // "12.500" / "1,500" = thousands; "12500.50" / "1234,5" = decimals
	}
	const digits = s.split(dec === '.' ? ',' : '.').join('');
	const parts = dec ? digits.split(dec) : [digits];
	if (parts.length > 2 || !/^\d+$/.test(parts[0]) || (parts[1] !== undefined && !/^\d{1,2}$/.test(parts[1]))) return null;
	return (neg ? '-' : '') + parts[0] + (parts[1] ? '.' + parts[1] : '');
}
// A date n days ahead that lands on a weekend rolls on to Monday (R18): the customer works Monday to Friday.
const roll = n => { const d = D(n); while (d.getDay() % 6 === 0) d.setDate(d.getDate() + 1); return key(d); };
const rollLabel = (label, n) => diff(roll(n)) === n ? label : t('Mandag');
const rollMonth = () => { const d = new Date(TODAY); const day = d.getDate(); d.setMonth(d.getMonth() + 1); if (d.getDate() !== day) d.setDate(0); return roll(diff(key(d))); };
const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
const cust = id => C.find(c => c.id === id) || EXTRA[id];
const customerRef = c => c.lead ? t('Lead') : c.nr;
const canDocument = c => DATA.canOrder && (!c.lead || DATA.canWrite);
const documentUrl = (cid, order, deal) => 'ordre.php?konto_id=' + cid + (order ? '&ordrestatus=1' : '') + (deal ? '&crm_deal=' + deal : '') + '&returside=' + backTo(cid);
const documentHref = (c, order, deal) => c.lead ? '#' : documentUrl(c.id, order, deal);
const stName = id => (ST[id] || {n: id || t('Ingen status')}).n;
const stTone = id => (ST[id] || {c: OFF}).c;
const empName = id => id && id === ME ? t('Dig') : (EMP[id] ? EMP[id].n : '');
const empFirst = id => id && id === ME ? t('Dig') : (EMP[id] ? EMP[id].n.split(' ')[0] : '');
const empLow = id => id && id === ME ? t('dig') : empName(id);   // inside a sentence
const ppl = (c, id) => { const p = c && c.ppl.find(p => p.id === id); return p ? p.n : ''; };
const isLost = id => GROUPS.some(g => g.lost === id);
const isWon = id => GROUPS.some(g => g.won === id);
// Meetings: h.tm 'HH:MM', h.dur minutes, h.with = the colleagues, h.inv = invitations sent (1 colleagues, 2 contact).
const isMeet = h => !!(h && h.tm);
const people = h => [h.by].concat(h.with || []).filter((v, i, a) => v && a.indexOf(v) === i);
const isMine = h => h.by === ME || (h.with || []).includes(ME);
const hhmm = m => String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
const toMin = s => { const p = String(s).split(':').map(Number); return p[0] * 60 + (p[1] || 0); };
const tspan = h => h.tm + '–' + hhmm(toMin(h.tm) + (h.dur || 60));
const byTime = (a, b) => (a.tm || '99').localeCompare(b.tm || '99');
const initials = id => EMP[id] ? (EMP[id].i || EMP[id].n.split(' ').map(x => x.charAt(0)).join('').slice(0, 3)) : '';
const peopleTxt = h => people(h).map(empLow).filter(Boolean).join(', ');
// "Mine": what the user is responsible for or a colleague on - a customer also when the user is on one of its open sager
const dealMine = d => d.own === ME || (d.with || []).includes(ME);
const custMine = c => c.own === ME || DEALS.some(d => d.cid === c.id && d.state === 'open' && dealMine(d));

/* ================= tilstand ================= */
const S = {tab: 'follow', mine: localStorage.getItem('crm-mine') === '1', grp: 'all', q: '', stF: 'all', calMode: 'month', calY: 0, calM: 0,
	weekStart: null, showDone: false, showNofu: false, noFu: false, drawer: null, dtab: 'tl', bgrp: null};

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
	if (S.grp !== 'all') p.set('grp', S.grp);   // review R58: a reload keeps the status group filter and the calendar view
	if (st.tab === 'cal' && S.calY) p.set('cal', S.calMode === 'week' && S.weekStart ? 'w' + S.weekStart : 'm' + S.calY + '-' + (S.calM + 1));
	return p.toString();
}
let placeTimer = null, placeSent = '';
// Tell the server where the page is so the navigation stack preserves the current CRM view. Debounced; flushed
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
	note: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg>',
	opkald: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>',
	mail: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
	moede: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18.5 14a6.5 6.5 0 0 1 3 6"/></svg>',
	status: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="5" height="16" rx="1.5"/><rect x="10" y="4" width="5" height="11" rx="1.5"/><rect x="17" y="4" width="4" height="7" rx="1.5"/></svg>',
	deal: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>'
};
const TYPE_N = {note: t('Note'), opkald: t('Opkald'), mail: t('Mail'), moede: t('Møde'), status: t('Status'), deal: t('Sag')};
const COMPOSE_TYPES = ['opkald', 'mail', 'moede', 'note'];
const SV = {
	check: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>',
	clock: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
	edit: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16z"/></svg>',
	trash: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>',
	x: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>',
	plus: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
	ext: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>',
	clip: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 11-8.5 8.5a5 5 0 0 1-7-7L14 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L15 7"/></svg>',
	left: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m15 6-6 6 6 6"/></svg>',
	right: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg>',
	search: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
	cal: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
	up: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m6 15 6-6 6 6"/></svg>',
	down: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>'
};

/* ================= server ================= */
// Errors as plain sentences, optimistic UI rolled back, an expired session with a Reload button (R75); a response older
// than one already applied is ignored (R76).
let busy = false, seqNo = 0, applied = 0;
const shown = msg => { const e = new Error(msg); e.crmShown = true; return e; };
window.addEventListener('unhandledrejection', e => { if (e.reason && e.reason.crmShown) e.preventDefault(); });
const ERR = () => ({noaccess: t('Du har ikke rettighed til at ændre i CRM.'), status_used: t('Statussen er i brug og kan ikke slettes.'),
	stale: t('Indstillingerne er ændret af en anden. Genindlæs siden.'),
	lead_group: t('Vælg en debitorgruppe. Hvis listen er tom, skal en debitorgruppe oprettes under Indstillinger.'),
	lead_duplicate: t('CVR-nummeret findes allerede på en kunde. Find kunden via søgningen i CRM.'),
	lead_changed: t('Dette lead er allerede oprettet som debitor. Genindlæs siden for at se debitorkortet.'),
	lead_name: t('Skriv et navn på kunden.'), lead_email: t('Skriv en gyldig e-mailadresse.'),
	schema: t('CRM-opdateringen er ikke klar. Log ud og ind igen.'),
	lead_length: t('Et felt indeholder for mange tegn. Forkort teksten og prøv igen.'),
	quick: t('Tilbud kan ikke oprettes, fordi »Anvend hurtigfakturering« er slået til (Indstillinger → Ordrer).'),
	linked: t('Sagen har allerede et tilbud eller en ordre. Genindlæs siden for at åbne det.'),
	customer: t('Kunden eller sagen er ændret. Genindlæs siden og prøv igen.'),
	amount: t('Beløbet kunne ikke læses – skriv fx 12.500,50.'), fields: t('Kunne ikke gemme. Prøv igen.'), date: t('Kunne ikke gemme. Prøv igen.')});
function redraw() { renderAll(); if (S.drawer) renderDrawer(); }
function loggedOut() { redraw(); toast(t('Du er logget ud – genindlæs siden og prøv igen.'), null, {label: t('Genindlæs'), fn: () => location.reload()}); }
async function api(action, payload) {
	busy = true;
	const my = ++seqNo;
	try {
		let res;
		try {
			res = await fetch(DATA.api, {method: 'POST', credentials: 'same-origin',
				headers: {'Content-Type': 'application/json', 'X-CSRF-Token': DATA.csrf}, body: JSON.stringify(Object.assign({action}, payload || {}))});
		} catch (e) { redraw(); toast(t('Ingen forbindelse – ændringen blev ikke gemt.')); throw shown('network'); }
		let j = null;
		try { j = await res.json(); } catch (e) { j = null; }
		if (!j) {
			// includes/online.php answers an expired session with an HTML box and status 200
			if (res.status === 200 || res.status === 401 || res.status === 403) loggedOut(); else { redraw(); toast(t('Kunne ikke gemme. Prøv igen.')); }
			throw shown('http ' + res.status);
		}
		if (!j.ok) {
			if (j.error === 'csrf') loggedOut(); else { redraw(); toast(ERR()[j.error] || t('Kunne ikke gemme (%s). Prøv igen.', j.error || res.status)); }
			throw shown(j.error);
		}
		if (j.data && my > applied) { applied = my; load(j.data); redraw(); }
		if (S.drawer && FULL[S.drawer]) { const cid = S.drawer; refreshFull(cid).then(ok => { if (ok && S.drawer === cid) { mergeFull(); redraw(); } }); }
		return j.result;
	} finally { busy = false; }
}
// A read that returns no dataset (search, customer): null on any failure.
async function apiRead(action, payload) {
	try {
		const r = await fetch(DATA.api, {method: 'POST', credentials: 'same-origin',
			headers: {'Content-Type': 'application/json', 'X-CSRF-Token': DATA.csrf}, body: JSON.stringify(Object.assign({action}, payload || {}))}).then(x => x.json());
		return r && r.ok ? r.result : null;
	} catch (e) { return null; }
}
// Open the drawer on a customer outside the loaded set: fetch the customer and its history first (R53, R77).
async function openOutside(cid, tab) {
	if (cust(cid) && (C.some(c => c.id === cid) || FULL[cid])) { openDrawer(cid, tab); return; }
	if (!(await refreshFull(cid))) { toast(t('Kunne ikke gemme. Prøv igen.')); return; }
	openDrawer(cid, tab);
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
	const q = S.q.toLowerCase(), qd = q.replace(/\D/g, '');
	if ((c.n + ' ' + c.nr + ' ' + c.by + ' ' + (c.mail || '') + ' ' + c.ppl.map(p => p.n + ' ' + (p.mail || '')).join(' ')).toLowerCase().includes(q)) return true;
	// a phone number, with or without spaces (R77)
	return qd.length >= 4 && ((c.tlf || '') + ' ' + c.ppl.map(p => p.tlf || '').join(' ')).replace(/\D/g, '').includes(qd);
}
// The main search only knows the loaded customers; with no hit the server search covers them all (R77).
const srvSearchBtn = () => S.q ? `<br><button type="button" class="sk-link" data-srvsearch="1">${t('Søg blandt alle kunder')}</button>` : '';
const follows = incDone => H.filter(h => h.due && (incDone || !h.done) && custOk(cust(h.cid)) && !cust(h.cid).closed && (!S.mine || isMine(h)) && match(h));
const openFor = cid => H.filter(h => h.cid === cid && h.due && !h.done).sort((a, b) => a.due.localeCompare(b.due) || byTime(a, b));
const lastContact = cid => { const c = cust(cid); return H.filter(h => h.cid === cid && h.type !== 'status' && h.type !== 'deal').map(h => h.date).concat(c && c.last ? [c.last] : []).sort().pop(); };
// customers on a status group's board (not on a won/lost status) without an open follow-up (R14)
const noFollowUp = () => C.filter(c => !c.closed && GROUPS.some(g => g.s.includes(c.st)) && !isWon(c.st) && !isLost(c.st) && custOk(c) && (!S.mine || custMine(c)) && custMatch(c) && !openFor(c.id).length);

/* ================= faner ================= */
function renderTabs() {
	const open = follows(false), late = open.filter(h => diff(h.due) < 0).length;
	const custs = C.filter(c => custOk(c) && (!S.mine || custMine(c)) && custMatch(c)).length;
	const tabs = [
		['follow', t('Opfølgning'), `<span class="sk-n">${open.length}</span>${late ? `<span class="crm-tab-late">· ${t('%s forsinket', late)}</span>` : ''}`],
		['pipe', t('Sager'), ''],
		['cal', t('Kalender'), ''],
		['cust', t('Kunder'), `<span class="sk-n">${custs}</span>`],
		['act', t('Aktivitet'), '']
	];
	$('#tabs').innerHTML = tabs.map(([id, n, x]) => `<button type="button" class="sk-tab" role="tab" aria-selected="${S.tab === id}" data-tab="${id}">${n}${x}</button>`).join('');
}

/* ================= 1. opfølgning ================= */
function bucket(k) {
	const n = diff(k);
	if (n < 0) return 0; if (n === 0) return 1; if (n === 1) return 2;
	const toSun = (7 - TODAY.getDay()) % 7;
	if (n <= toSun) return 3; if (n <= toSun + 7) return 4; return 5;
}
const BUCKETS = [t('Forsinket'), t('I dag'), t('I morgen'), t('Resten af ugen'), t('Næste uge'), t('Senere')];
// One table (css/saldi-kit.css .sk-table) in a white panel; a tbody per group, so the zebra starts again under each group heading.
const FCOLS = '<colgroup><col class="crm-w-tick"><col class="crm-w-cust"><col><col class="crm-w-st"><col class="crm-w-who"><col class="crm-w-due"><col class="crm-w-act"></colgroup>';
const grpRow = (label, n, tone) => `<tbody class="crm-grp"><tr class="sk-tr-sub${tone ? ' ' + tone : ''}"><td colspan="7">${label} <span class="sk-n">${n}</span></td></tr></tbody>`;
const showRow = (id, label) => `<tbody><tr class="crm-showrow"><td colspan="7"><button type="button" class="sk-link" id="${id}">${label}</button></td></tr></tbody>`;
function followRow(h, done) {
	const c = cust(h.cid), n = diff(h.due);
	const cls = done ? '' : n < 0 ? ' crm-late' : n === 0 ? ' crm-today' : '';
	return `<tr class="row body-row${done ? ' done-row' : ''}" tabindex="0" data-open="${c.id}" data-h="${h.id}">
		<td class="crm-tc">${DATA.canWrite ? `<button type="button" class="tick ${h.done ? 'on' : ''}" data-tick="${h.id}" title="${h.done ? t('Markér som ikke gjort') : t('Markér som gjort')}" aria-label="${t('Gjort')}">${SV.check}</button>` : ''}</td>
		<td><span class="crm-name">${esc(c.n)}</span>${h.to ? `<span class="sk-sub">${esc(ppl(c, h.to))}</span>` : ''}</td>
		<td class="crm-txt">${isMeet(h) ? `<span class="crm-mt">${ICON.moede}${t('Møde')} ${tspan(h)}</span> ` : ''}${esc(h.note)}</td>
		<td><span class="sk-status ${stTone(c.st)}">${esc(stName(c.st))}</span></td>
		<td class="crm-who${isMine(h) ? ' crm-me' : ''}">${esc(empName(h.by))}${(h.with || []).length ? ` <span class="sk-faint" title="${esc(cap(peopleTxt(h)))}">+${h.with.length}</span>` : ''}</td>
		<td class="crm-due${cls}" title="${fmtY(h.due)}">${n < -1 ? fmt(h.due) : rel(h.due)}${isMeet(h) ? ' ' + h.tm : ''}</td>
		<td class="sk-act"><span class="crm-acts">${done || !DATA.canWrite ? '' : `<button type="button" class="sk-btn sk-btn-ghost sk-btn-sm" data-snooze="${h.id}">${SV.clock}${t('Udsæt')}</button>`}<button type="button" class="sk-btn sk-btn-ghost sk-btn-sm" data-open="${c.id}">${t('Åbn')}</button></span></td>
	</tr>`;
}
function renderFollow() {
	const list = follows(false).sort((a, b) => a.due.localeCompare(b.due) || byTime(a, b) || a.cid - b.cid);
	let html = `<div class="sk-panel"><table class="sk-table sk-table-flush crm-table crm-follow">${FCOLS}<thead><tr><th></th><th>${t('Kunde · kontakt')}</th><th>${t('Opfølgning')}</th><th>${t('Status')}</th><th>${t('Ansvarlig')}</th><th>${t('Dato')}</th><th></th></tr></thead>`;
	if (!list.length) html += `<tbody><tr><td class="sk-empty" colspan="7"><b>${t('Ingen åbne opfølgninger')}</b>${S.mine || S.grp !== 'all' ? t('Prøv at fjerne filtrene.') : S.q ? t('Ingen opfølgninger matcher »%s«.', esc(S.q)) + srvSearchBtn() : DATA.canWrite ? t('Opret en med »Ny opfølgning« eller fra en kunde.') : ''}</td></tr></tbody>`;
	BUCKETS.forEach((b, i) => {
		const rows = list.filter(h => bucket(h.due) === i);
		if (rows.length) html += grpRow(b, rows.length, i === 0 ? 'sk-bad' : '') + '<tbody>' + rows.map(h => followRow(h, false)).join('') + '</tbody>';
	});
	// "done in the last 14 days" by the day it was marked done, so a long-overdue call ticked today is found again (R16)
	const doneDay = h => h.doneAt || h.due;
	const done = follows(true).filter(h => h.done && diff(doneDay(h)) >= -14).sort((a, b) => doneDay(b).localeCompare(doneDay(a)));
	if (done.length) {
		html += showRow('showDone', S.showDone ? t('Skjul %s gjort de sidste 14 dage', done.length) : t('Vis %s gjort de sidste 14 dage', done.length));
		if (S.showDone) html += grpRow(t('Gjort'), done.length) + '<tbody>' + done.map(h => followRow(h, true)).join('') + '</tbody>';
	}
	const nofu = noFollowUp().sort((a, b) => (lastContact(a.id) || '').localeCompare(lastContact(b.id) || '') || a.n.localeCompare(b.n, LOC));
	if (nofu.length) {
		const one = nofu.length === 1;
		html += showRow('showNofu', S.showNofu ? (one ? t('Skjul 1 kunde uden opfølgning') : t('Skjul %s kunder uden opfølgning', nofu.length)) : (one ? t('Vis 1 kunde uden opfølgning') : t('Vis %s kunder uden opfølgning', nofu.length)));
		if (S.showNofu) html += '<tbody>' + nofu.map(c => { const lc = lastContact(c.id); return `<tr class="row body-row" tabindex="0" data-open="${c.id}"><td class="crm-tc"></td>
			<td><span class="crm-name">${esc(c.n)}</span>${c.ppl.length ? `<span class="sk-sub">${esc(c.ppl[0].n)}</span>` : ''}</td>
			<td class="sk-faint">${lc ? t('Seneste kontakt %s', rel(lc)) : t('Ingen kontakt endnu')}</td>
			<td><span class="sk-status ${stTone(c.st)}">${esc(stName(c.st))}</span></td>
			<td class="crm-who${c.own === ME ? ' crm-me' : ''}">${esc(empName(c.own))}</td>
			<td class="sk-act" colspan="2"><span class="crm-acts crm-acts-on">${DATA.canWrite ? `<button type="button" class="sk-btn sk-btn-ghost sk-btn-sm" data-fu7="${c.id}">${SV.clock}${t('Opfølgning om 1 uge')}</button>` : ''}</span></td></tr>`; }).join('') + '</tbody>';
	}
	$('#v-follow').innerHTML = html + '</table></div>';
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
	const invoiced = deals.some(d => d.doc && d.doc.st >= 3);   // R32: an invoiced deal still open
	return `<button type="button" class="crm-card" draggable="${DATA.canWrite}" data-card="${c.id}" data-open="${c.id}"${invoiced ? ' data-deal="1"' : ''}>
		<span class="crm-card-1"><b>${esc(c.n)}</b>${sum ? `<span class="crm-val" title="${deals.map(d => esc(d.t)).join(', ')}">${num(sum)}</span>` : ''}</span>
		<span class="crm-card-2">${invoiced ? `<span class="crm-inv">${t('Faktureret – markér vundet?')}</span>` : deals.length ? (deals.length === 1 ? esc(deals[0].t) : t('%s åbne sager', deals.length)) : c.ppl.length ? esc(c.ppl[0].n) + (c.ppl.length > 1 ? ` +${c.ppl.length - 1}` : '') : esc(c.by || c.nr)}</span>
		<span class="crm-card-3">${nx ? `<span class="crm-due${n < 0 ? ' crm-late' : n === 0 ? ' crm-today' : ''}" title="${esc(nx.note)}">${n < -1 ? fmt(nx.due) : rel(nx.due)}</span>` : `<span class="crm-nofu">${t('Ingen opfølgning')}</span>`}
			<span>${esc(empFirst(c.own))}</span>
			<span class="crm-age${age > 14 ? ' crm-stale' : ''}" title="${esc(t('I %s siden %s', stName(c.st), fmtY(c.stSince)))}">${ageTxt(age)}</span></span>
	</button>`;
}
// the status groups above the board: a segmented control (css/saldi-kit.css .sk-seg)
const boardSeg = g => `<div class="sk-seg" role="group" aria-label="${t('Statusgruppe')}">${GROUPS.map(x => `<button type="button" data-bgrp="${x.id}" class="${x.id === g.id ? 'on' : ''}" aria-pressed="${x.id === g.id}">${esc(x.n)}</button>`).join('')}</div>`;
function renderPipe() {
	const g = boardGroup();
	const starter = !GROUPS.length && DATA.canWrite ? `<button type="button" class="sk-link" data-starter="1">${t('Start med en salgstavle')}</button> · <button type="button" class="sk-link" data-opengear="1">${t('Åbn tandhjulet')}</button>` : '';
	if (!g.s.length) {
		$('#v-pipe').innerHTML = (GROUPS.length > 1 ? `<div class="crm-pipebar">${boardSeg(g)}</div>` : '') + `<div class="sk-panel"><div class="sk-empty"><b>${t('Ingen statusser at vise')}</b>${t('Opsæt statusser og statusgrupper under tandhjulet.')}${starter ? `<br>${t('Forslag: en salgstavle med Henvendelse › Tilbud sendt › Forhandling, hvor Vundet giver status Kunde og Tabt giver status Tabt.')}<br>${starter}` : ''}</div></div>`;
		return;
	}
	const base = C.filter(c => !c.closed && g.s.includes(c.st) && (!S.mine || custMine(c)) && custMatch(c) && (!S.noFu || !openFor(c.id).length));
	const late = base.filter(c => { const nx = openFor(c.id)[0]; return nx && diff(nx.due) < 0; }).length;
	const none = base.filter(c => !openFor(c.id).length).length;
	const dealSum = base.reduce((a, c) => a + openDeals(c.id).reduce((x, d) => x + d.v, 0), 0);
	const sum = t('%s kunder', `<b>${base.length}</b>`) + (dealSum ? ' · ' + t('åbne sager %s', `<b>${kr(dealSum)}</b>`) : '') + (late ? ` · <span class="crm-late">${t('%s med forsinket opfølgning', late)}</span>` : '') + (none || S.noFu ? ` · <button type="button" class="sk-link" data-nofu="1">${S.noFu ? t('Vis alle') : t('%s uden opfølgning', none)}</button>` : '');
	const cols = g.s.map(id => {
		const list = base.filter(c => c.st === id).sort(cardSort);
		const colSum = list.reduce((a, c) => a + openDeals(c.id).reduce((x, d) => x + d.v, 0), 0);
		return `<div class="crm-col" data-col="${esc(id)}"><div class="crm-colh"><span class="sk-dot ${stTone(id)}"></span><span class="crm-coln">${esc(stName(id))}</span><span class="sk-n">${list.length}</span><span class="crm-colsum">${colSum ? kr(colSum) : ''}</span></div>
			<div class="crm-cards">${list.map(custCard).join('')}</div></div>`; }).join('');
	$('#v-pipe').innerHTML = `<div class="crm-pipebar">
			${GROUPS.length ? boardSeg(g) : ''}
			<span class="crm-psum">${sum}</span>
		</div>
		${starter ? `<div class="sk-note sk-info"><div>${t('Ingen statusgrupper endnu.')} ${t('Forslag: en salgstavle med Henvendelse › Tilbud sendt › Forhandling, hvor Vundet giver status Kunde og Tabt giver status Tabt.')} ${starter}</div></div>` : ''}
		<div class="crm-board">${cols}</div>
		<div class="crm-ends">${g.won ? `<div class="crm-end sk-ok" data-end="won">${t('Vundet')}<small>→ ${esc(stName(g.won))}</small></div>` : ''}${g.lost ? `<div class="crm-end sk-bad" data-end="lost">${t('Tabt')}<small>→ ${esc(stName(g.lost))}</small></div>` : ''}</div>`;
}

/* ================= 3. kalender ================= */
function evHtml(h, big) {
	const c = cust(h.cid), late = !h.done && diff(h.due) < 0, drag = DATA.canWrite && !h.done;
	const meet = isMeet(h), cls = (late ? ' crm-late' : '') + (h.done ? ' crm-done' : '') + (meet ? ' crm-meet' : '');
	if (big) return `<button type="button" class="crm-wcard${cls}" draggable="${drag}" data-ev="${h.id}" data-open="${c.id}">${meet ? `<span class="crm-tm">${tspan(h)}</span>` : ''}<b>${esc(c.n)}</b><span class="crm-wnote">${esc(h.note)}</span><small>${esc(meet ? cap(peopleTxt(h)) : empName(h.by))}${h.to ? ' → ' + esc(ppl(c, h.to)) : ''}</small></button>`;
	const ini = (meet ? people(h) : [h.by]).map(initials).filter(Boolean).slice(0, 3).join(' ');
	return `<button type="button" class="crm-ev${cls}" draggable="${drag}" data-ev="${h.id}" data-open="${c.id}" title="${meet ? esc(t('Møde')) + ' ' + tspan(h) + ' – ' : ''}${esc(c.n)} – ${esc(h.note)}${ini ? ' · ' + esc(meet ? cap(peopleTxt(h)) : empName(h.by)) : ''}">${meet ? `<i class="crm-tm">${h.tm}</i>` : ''}<span>${esc(c.n)}</span>${ini ? `<small class="crm-ini">${esc(ini)}</small>` : ''}</button>`;
}
function isoWeek(d) { const t = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate())); const n = t.getUTCDay() || 7; t.setUTCDate(t.getUTCDate() + 4 - n); const y = new Date(Date.UTC(t.getUTCFullYear(), 0, 1)); return Math.ceil(((t - y) / 864e5 + 1) / 7); }
function mondayOf(k) { const d = fromKey(k); d.setDate(d.getDate() - ((d.getDay() + 6) % 7)); return key(d); }
function renderCal() {
	const byDay = {};
	follows(true).forEach(h => (byDay[h.due] = byDay[h.due] || []).push(h));
	Object.values(byDay).forEach(a => a.sort((x, y) => x.done - y.done || byTime(x, y)));
	let head, body = '';
	if (S.calMode === 'month') {
		head = `${MOL[S.calM]} ${S.calY}`;
		const first = new Date(S.calY, S.calM, 1), start = new Date(first);
		start.setDate(1 - ((first.getDay() + 6) % 7));
		const weeks = Math.ceil((((first.getDay() + 6) % 7) + new Date(S.calY, S.calM + 1, 0).getDate()) / 7);
		body = '<div class="sk-panel crm-cal"><div class="crm-month">' + [1, 2, 3, 4, 5, 6, 0].map(i => `<div class="crm-dow">${esc(cap(WD[i]))}</div>`).join('');
		for (let i = 0; i < weeks * 7; i++) {
			const d = new Date(start); d.setDate(start.getDate() + i);
			const k = key(d), ev = byDay[k] || [];
			const cls = (d.getMonth() !== S.calM ? ' crm-out' : '') + (k === DATA.today ? ' crm-today' : '') + (d.getDay() % 6 === 0 ? ' crm-wkend' : '');
			body += `<div class="crm-day${cls}" data-day="${k}"><span class="crm-dn">${d.getDate()}</span>${DATA.canWrite ? `<button type="button" class="sk-btn sk-btn-ghost sk-btn-icon sk-btn-sm crm-add" data-new="${k}" title="${esc(t('Ny opfølgning %s', fmt(k)))}">${SV.plus}</button>` : ''}` +
				ev.slice(0, 4).map(h => evHtml(h)).join('') + (ev.length > 4 ? `<button type="button" class="sk-link crm-more" data-week="${k}">${t('+%s mere', ev.length - 4)}</button>` : '') + '</div>';
		}
		body += '</div></div>';
	} else {
		const ws = fromKey(S.weekStart), we = new Date(ws); we.setDate(ws.getDate() + 6);
		head = t('Uge %s', isoWeek(ws)) + ` · ${ws.getDate()}${DOT} ${MO[ws.getMonth()]} – ${we.getDate()}${DOT} ${MO[we.getMonth()]}`;
		body = '<div class="sk-panel crm-cal crm-calw"><div class="crm-week">';
		for (let i = 0; i < 7; i++) {
			const d = new Date(ws); d.setDate(ws.getDate() + i);
			const k = key(d), ev = byDay[k] || [];
			body += `<div class="crm-wcol${k === DATA.today ? ' crm-today' : ''}" data-day="${k}"><h3 class="crm-wh"><b>${d.getDate()}</b>${WDL[d.getDay()]}${DATA.canWrite ? `<button type="button" class="sk-btn sk-btn-ghost sk-btn-icon sk-btn-sm crm-add" data-newmeet="${k}" title="${esc(t('Nyt møde %s', fmt(k)))}">${SV.plus}</button>` : ''}</h3><div class="crm-wbody">${ev.map(h => evHtml(h, true)).join('')}</div></div>`;
		}
		body += '</div></div>';
	}
	$('#v-cal').innerHTML = `<div class="crm-calbar">
		<button type="button" class="sk-btn sk-btn-ghost sk-btn-icon sk-btn-sm" id="calPrev" aria-label="${t('Forrige')}">${SV.left}</button><button type="button" class="sk-btn sk-btn-ghost sk-btn-icon sk-btn-sm" id="calNext" aria-label="${t('Næste')}">${SV.right}</button>
		<h2 class="crm-calt">${head}</h2><button type="button" class="sk-btn sk-btn-sm" id="calToday">${t('I dag')}</button>
		<span class="crm-legend"><span class="sk-status sk-info">${t('Åben')}</span><span class="sk-status sk-bad">${t('Forsinket')}</span><span class="sk-status sk-off">${t('Gjort')}</span>${DATA.canWrite ? `<span class="crm-hint">${t('Træk for at flytte')}</span>` : ''}</span>
		<span class="sk-grow"></span>
		<div class="sk-seg sk-seg-sm"><button type="button" data-mode="month" class="${S.calMode === 'month' ? 'on' : ''}" aria-pressed="${S.calMode === 'month'}">${t('Måned')}</button><button type="button" data-mode="week" class="${S.calMode === 'week' ? 'on' : ''}" aria-pressed="${S.calMode === 'week'}">${t('Uge')}</button></div>
		<button type="button" class="sk-btn sk-btn-ghost sk-btn-sm" id="calSub" title="${esc(t('Se dine møder i Google Kalender, Outlook eller Apple Kalender'))}">${SV.cal}${DATA.ical ? t('Abonnement') : t('Abonnér')}</button>
	</div>` + body;
	syncUrl();
}

/* ================= 4. kunder ================= */
const KCOLS = '<colgroup><col class="crm-w-name"><col class="crm-w-ppl"><col class="crm-w-st"><col class="crm-w-who"><col class="crm-w-last"><col class="crm-w-next"><col class="crm-w-rev"></colgroup>';
function renderCust() {
	const base = C.filter(c => custOk(c) && (!S.mine || custMine(c)) && custMatch(c));
	// Line 1 = the status groups, always shown (the way back to "Alle"). Line 2 = the chosen group's statuses as a funnel.
	const all = C.filter(c => (!S.mine || custMine(c)) && custMatch(c));
	const rest = all.filter(c => !GROUPS.some(g => g.s.includes(c.st))).length;
	const on = v => `class="${v ? 'on' : ''}" aria-pressed="${!!v}"`;
	let strip = `<div class="sk-bar"><div class="sk-seg" role="group" aria-label="${t('Statusgruppe')}">` +
		`<button type="button" ${on(S.grp === 'all' && S.stF !== 'rest')} data-grp="all">${t('Alle')} <span class="sk-n">${all.length}</span></button>` +
		GROUPS.map(g => `<button type="button" ${on(String(S.grp) === String(g.id))} data-grp="${g.id}">${esc(g.n)} <span class="sk-n">${all.filter(c => g.s.includes(c.st)).length}</span></button>`).join('') +
		(rest && GROUPS.length ? `<button type="button" ${on(S.grp === 'all' && S.stF === 'rest')} data-grp="all" data-stf="rest"><span class="sk-dot sk-off"></span>${t('Uden gruppe')} <span class="sk-n">${rest}</span></button>` : '') + '</div></div>';
	if (S.grp !== 'all') {
		const ids = GROUPS.find(g => String(g.id) === String(S.grp)).s;
		strip += `<div class="sk-bar crm-funnel"><div class="sk-seg sk-seg-sm crm-funnel-seg" role="group" aria-label="${t('Status')}"><button type="button" ${on(S.stF === 'all')} data-stf="all">${t('Alle i %s', esc(GROUPS.find(g => String(g.id) === String(S.grp)).n.toLowerCase()))} <span class="sk-n">${base.length}</span></button>` +
			ids.map((id, i) => (i ? '<span class="crm-arrow">›</span>' : '') + `<button type="button" ${on(S.stF === id)} data-stf="${esc(id)}"><span class="sk-dot ${stTone(id)}"></span>${esc(stName(id))} <span class="sk-n">${base.filter(c => c.st === id).length}</span></button>`).join('') + '</div></div>';
	}
	const list = base.filter(c => S.stF === 'all' || (S.stF === 'rest' ? !GROUPS.some(g => g.s.includes(c.st)) : c.st === S.stF)).sort((a, b) => a.n.localeCompare(b.n, LOC));
	let html = strip + `<table class="sk-table sk-table-flush crm-table crm-custs">${KCOLS}<thead><tr><th>${t('Kunde')}</th><th>${t('Kontaktperson')}</th><th>${t('Status')}</th><th>${t('Ansvarlig')}</th><th>${t('Seneste kontakt')}</th><th>${t('Næste opfølgning')}</th><th class="sk-num">${t('Omsætning i år')}</th></tr></thead><tbody>`;
	html += list.map(c => {
		const nx = openFor(c.id)[0], lc = lastContact(c.id), nn = nx ? diff(nx.due) : null;
		return `<tr class="body-row" tabindex="0" data-open="${c.id}">
			<td><span class="crm-name">${esc(c.n)}</span><span class="sk-sub">${esc(customerRef(c))}${c.by ? ' · ' + esc(c.by) : ''}</span></td>
			<td class="sk-muted">${c.ppl.length ? esc(c.ppl[0].n) + (c.ppl.length > 1 ? ` <span class="sk-faint">+${c.ppl.length - 1}</span>` : '') : '<span class="sk-faint">–</span>'}</td>
			<td><span class="sk-status ${stTone(c.st)}">${c.st ? esc(stName(c.st)) : `<span class="sk-faint">${t('Ingen')}</span>`}</span></td>
			<td class="crm-who${c.own === ME ? ' crm-me' : ''}">${esc(empName(c.own)) || '<span class="sk-faint">–</span>'}</td>
			<td class="crm-due">${lc ? rel(lc) : '<span class="sk-faint">–</span>'}</td>
			<td class="crm-due${nn !== null && nn < 0 ? ' crm-late' : nn === 0 ? ' crm-today' : ''}">${nx ? (nn < -1 ? fmt(nx.due) : rel(nx.due)) : `<span class="sk-faint">${t('Ingen')}</span>`}</td>
			<td class="sk-num${c.rev ? '' : ' sk-faint'}">${c.rev ? kr(c.rev) : '–'}</td>
		</tr>`;
	}).join('');
	if (!list.length) html += `<tr><td class="sk-empty" colspan="7"><b>${t('Ingen kunder')}</b>` + (C.length ? t('Ingen kunder matcher filtrene.') + (DATA.truncated && S.q ? ' ' + t('Kunden er måske ikke blandt de %s viste. Søg via »Ny opfølgning«.', num(DATA.max)) : '') : t('Kunder kommer med i CRM, når de har en status i en statusgruppe, en note eller en sag. Søg efter andre kunder med søgefeltet.')) + srvSearchBtn() + '</td></tr>';
	$('#v-cust').innerHTML = `<div class="sk-panel">${html}</tbody></table></div>`;
}

/* ================= 5. aktivitet ================= */
function itemHtml(h, withCust, actions) {
	const c = cust(h.cid) || {n: '?', id: h.cid, ppl: []};
	let fu = '';
	if (h.due && isMeet(h)) {
		const n = diff(h.due);
		const what = t('Møde %s kl. %s', rel(h.due), tspan(h)) + ' · ' + esc(peopleTxt(h)) + (h.inv ? ' · ' + t('invitation sendt') : '');
		fu = h.done ? `<span class="crm-fu crm-done">${SV.check}${t('Møde %s · gjort', fmt(h.due))}</span>` : `<span class="crm-fu${n < 0 ? ' crm-late' : ''}">${ICON.moede}${what}</span>`;
	} else if (h.due) {
		const n = diff(h.due);
		fu = h.done ? (actions && DATA.canWrite ? `<button type="button" class="crm-fu crm-done" data-tick="${h.id}" title="${t('Markér som ikke gjort')}">${SV.check}${t('Opfølgning %s · gjort', fmt(h.due))}</button>` : `<span class="crm-fu crm-done">${SV.check}${t('Opfølgning %s · gjort', fmt(h.due))}</span>`)
			: `<span class="crm-fu${n < 0 ? ' crm-late' : ''}">${SV.clock}${t('Opfølgning %s', rel(h.due))} · ${esc(empLow(h.by))}</span>`;
	}
	const sys = h.type === 'status' || h.type === 'deal';
	// the author wrote it; the owner (h.by) is named on the follow-up line (R4)
	const who = `<b>${esc(empName(h.au || h.by)) || t('Ukendt')}</b>${h.to ? ` → ${esc(ppl(c, h.to))}` : ''}`;
	return `<div class="item crm-item" data-h="${h.id}">
		<span class="crm-ico" title="${TYPE_N[h.type] || TYPE_N.note}">${ICON[h.type] || ICON.note}</span>
		<div><div class="crm-top">${TYPE_N[h.type] || TYPE_N.note} · ${who}${withCust ? ` · <a href="#" data-open="${c.id}">${esc(c.n)}</a>` : ''}</div>
			<div class="crm-itx">${esc(h.note)}</div>${fu}${h.file ? `<a class="crm-attach" target="_blank" href="../includes/bilag.php?kilde=historik&filnavn=${encodeURIComponent(h.file)}&kilde_id=${h.cid}&bilag_id=${h.id}">${SV.clip}${esc(h.file)}</a>` : ''}</div>
		<div class="crm-stack"><time title="${fmtY(h.date)}">${diff(h.date) > -7 ? rel(h.date) : fmtY(h.date)}</time>${actions && DATA.canWrite && !sys ? `<span class="crm-ia"><button type="button" class="sk-btn sk-btn-ghost sk-btn-icon sk-btn-sm" data-edit="${h.id}" title="${t('Ret')}" aria-label="${t('Ret')}">${SV.edit}</button><button type="button" class="sk-btn sk-btn-ghost sk-btn-icon sk-btn-sm" data-del="${h.id}" title="${t('Slet')}" aria-label="${t('Slet')}">${SV.trash}</button></span>` : ''}</div>
	</div>`;
}
function renderAct() {
	const list = H.filter(h => custOk(cust(h.cid)) && (!S.mine || isMine(h) || (h.au || h.by) === ME) && match(h)).sort((a, b) => b.date.localeCompare(a.date) || b.id - a.id).slice(0, 300);
	let html = '<div class="sk-panel crm-feed">', last = '';
	list.forEach(h => {
		if (h.date !== last) { last = h.date; const n = diff(h.date);
			html += `<div class="sk-form-sub crm-fday">${n === 0 ? t('I dag') : n === -1 ? t('I går') : cap(WDL[fromKey(h.date).getDay()]) + ' ' + fmtY(h.date)}</div>`; }
		html += itemHtml(h, true, false);
	});
	if (!list.length) html += `<div class="sk-empty"><b>${t('Ingen aktivitet')}</b>${S.q || S.mine || S.grp !== 'all' ? t('Ingen henvendelser matcher filtrene.') : t('Der er ingen noter eller opkald endnu.')}</div>`;
	$('#v-act').innerHTML = html + '</div>';
}

/* ================= kundeskuffe ================= */
// One draft per composer (R1, R2, R9): 'c:<cid>' = the drawer of that customer, 'n' = the Ny opfølgning dialog. The
// draft holds the text too, so a re-render after any other action (status, Gjort, Udsæt, undo) puts it back. It is
// reset before the save's response re-renders the composer, so the chips never show a date that will not be sent.
const DRAFTS = {};
const DR0 = () => ({type: 'opkald', due: null, owner: 0, closePrev: null, note: '', to: null, prevId: 0, lastId: 0});
function draft(ctx, cid) { const k = ctx === 'c' ? 'c:' + cid : 'n'; return DRAFTS[k] || (DRAFTS[k] = DR0()); }
// the follow-up the composer offers to close: the one the user opened the drawer from, else the earliest open one (R3)
function prevFor(c, d) {
	const sel = d.prevId ? H.find(h => h.id === d.prevId && h.cid === c.id && h.due && !h.done) : null;
	return sel || openFor(c.id)[0];
}
function openDrawer(cid, tab, hid) {
	S.drawer = cid; S.dtab = tab || 'tl';
	const c = cust(cid);
	const d = draft('c', cid);
	// the writer by default, else the customer's owner - never the first employee in the alphabet (R6, R7)
	if (!d.owner) d.owner = ME || (c && c.own) || 0;
	d.prevId = hid || 0;
	const ae = document.activeElement;
	if (ae && !ae.closest('#drawer')) {
		const row = ae.closest('[data-h],[data-card],.body-row[data-open]');
		opener = row ? (row.dataset.h ? `.body-row[data-h="${row.dataset.h}"]` : row.dataset.card ? `[data-card="${row.dataset.card}"]` : `.body-row[data-open="${row.dataset.open}"]`) : '';
	}
	renderDrawer();
	$('#drawer').classList.add('open'); $('#dscrim').classList.add('open');
	const h2 = $('#drawer h2'); if (h2 && !$('#drawer').contains(document.activeElement)) h2.focus({preventScroll: true});
	syncUrl();
	if (!FULL[cid]) refreshFull(cid).then(ok => { if (ok && S.drawer === cid) renderDrawer(); });
}
let opener = '';
function closeDrawer() {
	$('#drawer').classList.remove('open'); $('#dscrim').classList.remove('open'); S.drawer = null; syncUrl();
	const back = opener && document.querySelector(opener); opener = '';
	if (back) back.focus();
}

function composer(c, ctx) {
	const d = draft(ctx, c.id);
	const prev = prevFor(c, d);
	// pre-ticked only for the user's own follow-up, and not for the one just created in this composer (R2, R3)
	const closePrev = d.closePrev !== null ? d.closePrev : !!(prev && prev.by === ME && prev.id !== d.lastId);
	const chips = [[rollLabel(t('I morgen'), 1), roll(1)], [t('Om 1 uge'), roll(7)], [t('Om 2 uger'), roll(14)], [t('Om 1 måned'), rollMonth()]];
	const custom = d.due && !chips.some(([, k]) => k === d.due) ? d.due : '';
	const to = d.to === null ? 0 : d.to;   // no contact person unless chosen (R8)
	const needOwner = !ME && !d.owner;
	const on = v => `class="${v ? 'on' : ''}" aria-pressed="${!!v}"`;
	// the type and the follow-up date are segmented controls (css/saldi-kit.css .sk-seg); Gem is the drawer's one primary button
	return `<div class="crm-comp" id="${ctx}comp">
		<div class="crm-ctop"><div class="sk-seg sk-seg-sm" role="group" aria-label="${t('Type')}">${COMPOSE_TYPES.map(ty => `<button type="button" data-type="${ty}" ${on(d.type === ty)}>${ICON[ty]}${TYPE_N[ty]}</button>`).join('')}</div>
			<label class="crm-with">${t('med')} <select class="sk-input sk-input-sm" id="${ctx}to"><option value="0" ${!to ? 'selected' : ''}>${t('(ingen)')}</option>${c.ppl.map(p => `<option value="${p.id}" ${p.id === to ? 'selected' : ''}>${esc(p.n)}</option>`).join('')}</select></label></div>
		<textarea class="sk-input" id="${ctx}Note" placeholder="${esc(t('Hvad blev der talt om?'))}">${esc(d.note)}</textarea>
		<div class="crm-cfoot"><span class="crm-lbl" id="${ctx}fuL">${t('Opfølgning')}</span>
			<div class="sk-seg sk-seg-sm" id="${ctx}chips" role="group" aria-labelledby="${ctx}fuL"><button type="button" data-due="" ${on(!d.due)}>${t('Ingen')}</button>${chips.map(([n, k]) => `<button type="button" data-due="${k}" ${on(d.due === k)} title="${fmt(k)}">${n}</button>`).join('')}</div><input type="date" class="sk-input sk-input-sm crm-date" id="${ctx}date" title="${t('Vælg dato')}" value="${custom}">
			${DATA.emps.length ? `<label class="owner ${d.due ? 'show' : ''}" id="${ctx}owner">${t('til')} <select class="sk-input sk-input-sm">${needOwner ? `<option value="0" selected>${t('– vælg –')}</option>` : ''}${DATA.emps.map(e => `<option value="${e.id}" ${e.id === d.owner ? 'selected' : ''}>${e.id === ME ? t('mig') : esc(e.n)}</option>`).join('')}</select></label>` : `<span id="${ctx}owner"></span>`}
			${prev ? `<label class="sk-check-lbl crm-closeprev" title="${esc(prev.note)}"><input type="checkbox" class="sk-check" id="${ctx}closePrev" ${closePrev ? 'checked' : ''}><span>${t('Markér opfølgningen fra %s som gjort', fmt(prev.due))} <span class="sk-faint">· ${esc(empFirst(prev.by))}: ${esc(prev.note.length > 40 ? prev.note.slice(0, 40) + '…' : prev.note)}</span></span></label>` : ''}
			<span class="sk-grow"></span><kbd>Ctrl+Enter</kbd><button type="button" class="sk-btn sk-btn-primary" id="${ctx}save" disabled>${t('Gem')}</button>
		</div></div>`;
}

function renderDrawer() {
	const c = cust(S.drawer); if (!c) return;
	// a re-render (after any save, or when the whole history arrives) keeps the cursor where the user is typing
	const ae = document.activeElement, keep = ae && ae.closest && ae.closest('#drawer') && ae.id ? {id: ae.id, a: ae.selectionStart, b: ae.selectionEnd} : null;
	const hist = H.filter(h => h.cid === c.id).sort((a, b) => b.date.localeCompare(a.date) || b.id - a.id);
	const nx = prevFor(c, draft('c', c.id)), lc = lastContact(c.id);
	const deals = DEALS.filter(d => d.cid === c.id);
	const ret = encodeURIComponent('crm.php');
	const tab = (k, label, n) => `<button type="button" class="sk-tab${S.dtab === k ? ' on' : ''}" role="tab" aria-selected="${S.dtab === k}" data-dtab="${k}">${label} <span class="sk-n">${n}</span></button>`;
	$('#drawer').innerHTML = `<div class="sk-drawer-h crm-dh">
			<div class="crm-dt"><h2 class="sk-drawer-t" id="dTitle" tabindex="-1">${esc(c.n)}${c.closed ? ` <span class="sk-faint">(${t('lukket')})</span>` : ''}</h2><div class="crm-dsub">${c.lead ? t('Lead · ikke oprettet som debitor') : t('Kunde %s', esc(c.nr))}${c.by ? ' · ' + esc(c.by) : ''}${DATA.canWrite && DATA.emps.length ? ` · <label class="crm-own">${t('Ansvarlig')} <select class="sk-input sk-input-sm" id="ownSel" aria-label="${t('Ansvarlig')}"><option value="0" ${!c.own ? 'selected' : ''}>${t('Ingen')}</option>${DATA.emps.map(e => `<option value="${e.id}" ${e.id === c.own ? 'selected' : ''}>${esc(e.n)}</option>`).join('')}</select></label>` : c.own ? ' · ' + t('ansvarlig: %s', esc(empLow(c.own))) : ''}</div></div>
			<span class="crm-stwrap ${stTone(c.st)}"><select class="sk-input crm-stsel" id="stSel" aria-label="${t('Status')}" ${DATA.canWrite ? '' : 'disabled'}>${c.st && !ST[c.st] ? `<option value="${esc(c.st)}" selected>${esc(c.st)}</option>` : ''}<option value="" ${!c.st ? 'selected' : ''}>${t('Ingen status')}</option>${ST_ORDER.map(id => `<option value="${esc(id)}" ${id === c.st ? 'selected' : ''}>${esc(stName(id))}</option>`).join('')}</select></span>
			<button type="button" class="sk-btn sk-btn-ghost sk-btn-icon" id="dClose" aria-label="${t('Luk (Esc)')}" title="${t('Luk (Esc)')}">${SV.x}</button>
		</div>
		<div class="crm-dtop">
			<div class="crm-links">${c.tlf ? `<a href="tel:${esc(c.tlf.replace(/ /g, ''))}">${ICON.opkald}${esc(c.tlf)}</a>` : ''}${c.mail ? `<a href="mailto:${esc(c.mail)}">${ICON.mail}${esc(c.mail)}</a>` : ''}${DATA.canWrite ? `<a href="#" data-addmeet="" data-cid="${c.id}">${ICON.moede}${t('Book møde')}</a><a href="#" data-adddeal="" data-cid="${c.id}">${ICON.deal}${t('Opret sag')}</a>` : ''}${canDocument(c) ? `${DATA.quick ? '' : `<a data-mkdoc="0" data-cid="${c.id}" href="${documentHref(c, false)}">${ICON.note}${t('Opret tilbud')}</a>`}<a data-mkdoc="1" data-cid="${c.id}" href="${documentHref(c, true)}">${ICON.note}${t('Opret ordre')}</a>` : ''}${c.lead ? (DATA.canWrite ? `<a href="#" data-editlead="${c.id}">${SV.edit}${t('Ret lead')}</a>` : '') : `<a href="debitorkort.php?id=${c.id}&returside=${ret}">${SV.ext}${t('Debitorkort')}</a>${DATA.jobs ? `<a href="jobliste.php?kontonr=${encodeURIComponent(c.nr)}&konto_id=${c.id}&returside=${ret}">${SV.ext}${t('Jobkort')}</a>` : ''}`}</div>
			<div class="crm-kv"><div><span>${c.lead ? t('Oprettet') : t('Kunde siden')}</span><b>${c.since ? fmtY(c.since) : '–'}</b></div><div><span>${t('Seneste kontakt')}</span><b>${lc ? rel(lc) : '–'}</b></div><div><span>${t('Omsætning i år')} <small class="sk-faint">${t('ekskl. moms')}</small></span><b>${kr(c.rev || 0)}</b></div><div><span>${t('Åbne poster')}</span><b class="${c.late ? 'crm-late' : ''}">${c.bal ? kr(c.bal) + (c.late ? ' · ' + t('%s forfalden', c.late) : '') : '0'}</b></div></div>
			<div class="sk-tabs crm-dtabs" role="tablist" aria-label="${t('Kunde')}">${tab('tl', t('Historik'), hist.length)}${tab('deals', t('Sager og ordrer'), deals.length + c.docs.length)}${tab('ppl', t('Kontaktpersoner'), c.ppl.length)}</div>
		</div>
		<div class="sk-drawer-b crm-db">${S.dtab === 'deals' ? dealsTab(c, deals) : S.dtab === 'tl' ? `
			${nx ? `<div class="sk-note${diff(nx.due) < 0 ? ' sk-bad' : ''} crm-next"><div><div class="crm-next-l">${isMeet(nx) ? t('Næste møde') : t('Næste opfølgning')} · ${diff(nx.due) < 0 ? t('forsinket') + ', ' : ''}${rel(nx.due)}${isMeet(nx) ? ' ' + tspan(nx) + ' · ' + esc(peopleTxt(nx)) : ' · ' + esc(empLow(nx.by))}</div><p>${esc(nx.note)}</p></div>
				${DATA.canWrite ? `<div class="crm-next-ac"><button type="button" class="sk-btn sk-btn-sm" data-snooze="${nx.id}">${SV.clock}${t('Udsæt')}</button><button type="button" class="sk-btn sk-btn-sm" data-tick="${nx.id}">${SV.check}${t('Gjort')}</button></div>` : ''}</div>` : ''}
			${DATA.canWrite ? composer(c, 'c') : ''}
			<div class="crm-tl">${hist.map(h => itemHtml(h, false, true)).join('') || `<div class="sk-empty"><b>${t('Ingen historik endnu')}</b>${DATA.canWrite ? t('Skriv den første note ovenfor.') : ''}</div>`}</div>` : `
			<div class="sk-panel crm-box">${c.ppl.map(p => `<div class="crm-p"><div class="crm-p1"><b>${esc(p.n)}</b>${DATA.canWrite ? `<button type="button" class="sk-btn sk-btn-ghost sk-btn-sm" data-call="${p.id}">${t('Ny note')}</button>` : ''}</div>
				<div class="crm-pl">${p.tlf ? `<a href="tel:${esc(p.tlf.replace(/ /g, ''))}">${esc(p.tlf)}</a>` : ''}${p.mail ? `<a href="mailto:${esc(p.mail)}">${esc(p.mail)}</a>` : ''}</div></div>`).join('') || `<div class="sk-empty"><b>${t('Ingen kontaktpersoner')}</b></div>`}</div>
			${!c.lead && DATA.canWrite ? `<div class="crm-btns"><a class="sk-btn sk-btn-sm" href="debitorkort.php?id=${c.id}&returside=${ret}">${t('+ Tilføj kontaktperson på debitorkortet')}</a></div>` : ''}`}
		</div>`;
	if (S.dtab === 'tl' && DATA.canWrite) wireComposer('c', c);
	if (keep) { const el = document.getElementById(keep.id); if (el) { el.focus(); try { if (keep.a != null) el.setSelectionRange(keep.a, keep.b); } catch (e) { /* not a text field */ } } }
}
function dealsTab(c, list) {
	const free = list.filter(d => d.state === 'open' && !d.doc);
	return `<div class="sk-panel crm-box">${list.map(d => `<div class="crm-d" data-editdeal="${d.id}"><b>${esc(d.t)}</b><span class="crm-amt">${kr(d.v)}</span>
		${d.state === 'open' && d.doc && d.doc.st >= 3 && DATA.canWrite ? `<button type="button" class="sk-link crm-ddsug" data-wondeal="${d.id}">${t('Faktureret – markér vundet?')}</button>` : ''}
		<small>${d.state === 'open' ? `<span class="sk-status ${stTone(d.st)}">${esc(stName(d.st))}</span>${d.close ? ' · ' + t('forventet lukket %s', fmt(d.close)) : ''}` : d.state === 'won' ? t('Vundet %s', fmtY(d.closed)) : t('Tabt %s', fmtY(d.closed)) + (d.reason ? ' – ' + esc(d.reason) : '')} · ${esc([d.own].concat(d.with || []).map(empLow).filter(Boolean).join(', '))}${d.doc ? ` · ${esc(docName(d.doc))} (${docState(d.doc)})` : ''}</small></div>`).join('') || `<div class="sk-empty"><b>${t('Ingen sager')}</b></div>`}</div>
		${DATA.canWrite ? `<div class="crm-btns"><button type="button" class="sk-btn sk-btn-sm" data-adddeal="" data-cid="${c.id}">${t('+ Ny sag')}</button></div>` : ''}
		<h3 class="sk-form-sub crm-boxh">${t('Åbne tilbud og ordrer')}</h3><div class="sk-panel crm-box">${c.docs.map(o => { const dl = DEALS.find(d => d.doc && d.doc.id === o.id);
			return `<div class="crm-d crm-doc"><a href="ordre.php?id=${o.id}&returside=${backTo(c.id)}"><b>${esc(docName(o))}</b></a><span class="crm-amt">${kr(o.sum)}</span>
			<small>${fmtY(o.date)}${o.st === 2 ? ' · ' + t('delvist leveret') : ''}${dl ? ' · ' + t('sag: %s', esc(dl.t)) : ''}${!dl && free.length && DATA.canWrite ? ` · <button type="button" class="sk-link" data-linkdoc="${o.id}" data-cid="${c.id}">${t('Knyt til sag')}</button>` : ''}</small></div>`; }).join('') || `<div class="crm-none">${t('Ingen åbne tilbud eller ordrer.')}</div>`}</div>
		${c.docs.length >= 10 ? `<p class="crm-hint">${t('Viser de 10 nyeste.')}</p>` : ''}
		${canDocument(c) ? `<div class="crm-btns">${DATA.quick ? '' : `<a class="sk-btn sk-btn-sm" data-mkdoc="0" data-cid="${c.id}" href="${documentHref(c, false)}">${t('+ Opret tilbud')}</a>`}<a class="sk-btn sk-btn-sm" data-mkdoc="1" data-cid="${c.id}" href="${documentHref(c, true)}">${t('+ Opret ordre')}</a></div>` : ''}
		${DATA.quick && canDocument(c) ? `<p class="crm-hint">${t('Tilbud kan ikke oprettes, fordi »Anvend hurtigfakturering« er slået til (Indstillinger → Ordrer).')}</p>` : ''}`;
}

function wireComposer(ctx, c, onSaved) {
	const root = document.getElementById(ctx + 'comp'); if (!root) return;
	const d = draft(ctx, c.id);
	const note = document.getElementById(ctx + 'Note'), save = document.getElementById(ctx + 'save'), toSel = document.getElementById(ctx + 'to');
	const owner = document.getElementById(ctx + 'owner'), osel = owner.querySelector('select');
	const upd = () => { save.disabled = busy || (!note.value.trim() && !d.due) || !!(d.due && !ME && !d.owner); save.title = d.due && !ME && !d.owner ? t('Vælg, hvem opfølgningen er til.') : ''; };
	note.addEventListener('input', () => { d.note = note.value; upd(); });
	if (toSel) toSel.onchange = () => { d.to = +toSel.value || 0; };
	const press = (x, v) => { x.classList.toggle('on', v); x.setAttribute('aria-pressed', String(v)); };
	root.querySelectorAll('[data-type]').forEach(b => b.onclick = () => { d.type = b.dataset.type; root.querySelectorAll('[data-type]').forEach(x => press(x, x === b)); note.focus(); });
	const chips = document.getElementById(ctx + 'chips'), dateIn = document.getElementById(ctx + 'date');
	const setDue = v => { d.due = v || null; chips.querySelectorAll('button').forEach(x => press(x, (x.dataset.due || null) === d.due)); owner.classList.toggle('show', !!d.due); upd(); };
	chips.querySelectorAll('button').forEach(b => b.onclick = () => { dateIn.value = ''; setDue(b.dataset.due); });
	dateIn.onchange = () => setDue(dateIn.value);
	if (osel) osel.onchange = e => { d.owner = +e.target.value; upd(); };
	const cp = document.getElementById(ctx + 'closePrev'); if (cp) cp.onchange = () => { d.closePrev = cp.checked; };
	note.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && !save.disabled) save.click(); });
	upd();
	save.onclick = async () => {
		if (save.disabled) return;
		save.disabled = true;
		const prev = prevFor(c, d);
		const close = cp && cp.checked && prev ? [prev.id] : [];
		const due = d.due;
		const payload = {cid: c.id, type: d.type, to: toSel ? +toSel.value || 0 : 0, note: note.value.trim() || t('Opfølgning'), due: due || '', owner: d.owner, close};
		// the draft is cleared BEFORE the response re-renders the composer (R2); put back if the save fails
		const keep = Object.assign({}, d);
		Object.assign(d, DR0(), {owner: d.owner, type: d.type});
		try {
			const r = await api('add_entry', payload);
			// the response already re-rendered the composer; once more now that it knows the new row, so that row is
			// never offered pre-ticked for closing (R2)
			d.lastId = r.id;
			if (ctx === 'c' && S.drawer === c.id && S.dtab === 'tl') renderDrawer();
			if (onSaved) onSaved();
			const el = document.querySelector(`#drawer .item[data-h="${r.id}"]`); if (el) el.classList.add('flash');
			toast(t('Gemt') + (due ? ' · ' + t('opfølgning %s', rel(due)) : '') + (r.closed.length ? ' · ' + t('forrige opfølgning markeret som gjort') : ''), async () => {
				await api('delete_entry', {id: r.id});
				if (r.closed.length) await api('reopen_many', {ids: r.closed});
			});
		} catch (e) { Object.assign(d, keep); redraw(); }
	};
}

/* ================= dialoger ================= */
let dialogGuard = null, dialogAfterClose = null, dialogDocumentGuard = null;
function closeDlg() {
	if (dialogGuard && !dialogGuard()) return;
	const after = dialogAfterClose;
	dialogGuard = dialogAfterClose = dialogDocumentGuard = null;
	$('#scrim').classList.remove('open');
	if (after) after();
}
// The dialog is the kit's .sk-dialog in .sk-modal (#scrim).
function openDlg(html, cls) {
	dialogGuard = dialogAfterClose = dialogDocumentGuard = null;
	const d = $('#dlg'); d.className = 'sk-dialog crm-dlg ' + (cls || ''); d.innerHTML = html;
	$('#scrim').classList.add('open'); return d;
}
// Keep the actual elements and their handlers: cancelling conversion returns to any edited case intact.
function rememberDialog() {
	if (!$('#scrim').classList.contains('open')) return null;
	const d = $('#dlg'), nodes = Array.from(d.childNodes), cls = d.className;
	const guard = dialogGuard, after = dialogAfterClose, documentGuard = dialogDocumentGuard;
	return () => {
		d.replaceChildren(...nodes); d.className = cls;
		dialogGuard = guard; dialogAfterClose = after; dialogDocumentGuard = documentGuard;
		$('#scrim').classList.add('open');
	};
}

// Notifications stay inside CRM; only links to this installation's CRM page may be opened.
function notificationUrl(value) {
	if (typeof value !== 'string' || !/^crm\.php(?:\?|$)/.test(value)) return '';
	try {
		const url = new URL(value, location.href), crm = new URL('crm.php', location.href);
		if (url.origin !== crm.origin || url.pathname !== crm.pathname) return '';
		if (QP0.get('inframe')) url.searchParams.set('inframe', QP0.get('inframe'));
		return 'crm.php' + url.search + url.hash;
	} catch (e) { return ''; }
}
function renderNotifications() {
	const unread = Math.max(0, Number(DATA.notificationUnread) || 0), button = $('#notificationsBtn'), badge = $('#notificationCount');
	badge.hidden = !unread;
	badge.textContent = unread > 99 ? '99+' : String(unread);
	const label = t('CRM-notifikationer') + (unread ? ': ' + t('%s ulæste notifikationer', num(unread)) : '');
	button.setAttribute('aria-label', label); button.title = label;
	const list = $('#notificationsList');
	if (!list) return;
	list.innerHTML = (DATA.notifications || []).map(n => {
		const url = notificationUrl(n.url), date = String(n.created || '').slice(0, 10);
		const content = `<span class="sk-list-tx"><span class="sk-list-t">${esc(n.title)}${n.seen ? '' : ` <span class="sk-tag sk-info">${esc(t('Ulæst'))}</span>`}</span><span class="sk-list-d">${esc(n.body)}</span>${/^\d{4}-\d{2}-\d{2}$/.test(date) ? `<time class="sk-list-d" datetime="${esc(date)}">${esc(fmtY(date))}</time>` : ''}</span>`;
		return url ? `<a class="sk-list-row crm-notification" href="${esc(url)}">${content}</a>` : `<div class="sk-list-row crm-notification">${content}</div>`;
	}).join('') || `<div class="sk-empty">${esc(t('Ingen notifikationer'))}</div>`;
	const mark = $('#notificationsSeen');
	if (mark) mark.disabled = !unread;
}
function notificationsDialog() {
	const d = openDlg(`<h3 class="sk-dialog-t" id="notificationsTitle">${esc(t('CRM-notifikationer'))}</h3><div class="sk-list crm-notifications-list" id="notificationsList"></div><div class="sk-dialog-f"><button type="button" class="sk-btn" id="notificationsSeen">${esc(t('Markér alle som læst'))}</button><button type="button" class="sk-btn" id="notificationsClose">${esc(t('Luk'))}</button></div>`);
	d.setAttribute('aria-labelledby', 'notificationsTitle');
	$('#notificationsBtn').setAttribute('aria-expanded', 'true');
	dialogAfterClose = () => { d.removeAttribute('aria-labelledby'); $('#notificationsBtn').setAttribute('aria-expanded', 'false'); $('#notificationsBtn').focus(); };
	$('#notificationsClose').onclick = closeDlg;
	$('#notificationsSeen').onclick = async e => {
		if (busy) return;
		e.currentTarget.disabled = true;
		try { await api('notifications_seen'); } finally { renderNotifications(); }
	};
	// Keep keyboard focus within this modal, including when the list is empty.
	d.onkeydown = e => {
		if (e.key !== 'Tab') return;
		const controls = Array.from(d.querySelectorAll('a[href], button:not(:disabled)'));
		const first = controls[0], last = controls[controls.length - 1];
		if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
		else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
	};
	const after = dialogAfterClose;
	dialogAfterClose = () => { d.onkeydown = null; after(); };
	renderNotifications();
	$('#notificationsClose').focus();
	// Opening the inbox refreshes notices created by colleagues while this tab stayed open.
	if (!busy) {
		$('#notificationsSeen').disabled = true;
		api('data').catch(() => {}).finally(renderNotifications);
	}
}

function leadDialog(c, options = {}) {
	if (!DATA.canWrite || (c && !c.lead)) return;
	const editing = !!c;
	c = c || {id: 0, n: Array.from(options.name || '').slice(0, 90).join(''), own: ME || 0, st: ''};
	const field = (name, label, type, max, required) => `<label class="sk-field${name === 'n' ? ' crm-wide' : ''}"><span class="sk-field-lbl">${label}${required ? ' *' : ''}</span><input class="sk-input" name="${name}" type="${type || 'text'}" maxlength="${max || 60}" value="${esc(c[name] || '')}"${required ? ' required' : ''}></label>`;
	openDlg(`<h3 class="sk-dialog-t">${editing ? t('Ret lead') : t('Ny kunde')}</h3>
		<p class="sk-dialog-b">${t('Kunden gemmes som lead i CRM. Den oprettes først som debitor, når du opretter det første tilbud eller den første ordre.')}</p>
		<form id="leadForm" class="crm-lead-form">
			<div class="sk-fields-2">
				${field('n', t('Navn'), 'text', 90, true)}
				${field('tlf', t('Telefon'), 'tel')}${field('mail', t('E-mail'), 'email')}
				${field('addr1', t('Adresse'))}${field('addr2', t('Adresse 2'))}
				${field('zip', t('Postnummer'), 'text', 10)}${field('by', t('By'))}
				${field('land', t('Land'))}${field('cvr', t('CVR-nummer'), 'text', 20)}
				<label class="sk-field"><span class="sk-field-lbl">${t('Ansvarlig')}</span><select class="sk-input" name="own"><option value="0">${t('Ingen')}</option>${c.own && !EMP[c.own] ? `<option value="${c.own}" selected>${t('(tidligere medarbejder)')}</option>` : ''}${DATA.emps.map(e => `<option value="${e.id}"${e.id === c.own ? ' selected' : ''}>${esc(e.n)}</option>`).join('')}</select></label>
				<label class="sk-field"><span class="sk-field-lbl">${t('Status')}</span><select class="sk-input" name="st"><option value="">${t('Ingen status')}</option>${c.st && !ST[c.st] ? `<option value="${esc(c.st)}" selected>${esc(c.st)}</option>` : ''}${ST_ORDER.map(id => `<option value="${esc(id)}"${id === c.st ? ' selected' : ''}>${esc(stName(id))}</option>`).join('')}</select></label>
			</div>
			<p class="sk-note sk-bad crm-form-error" id="leadError" role="alert" hidden></p>
			<div class="sk-dialog-f"><button type="button" class="sk-btn" id="leadCancel">${t('Annullér')}</button><button type="submit" class="sk-btn sk-btn-primary" id="leadSave">${editing ? t('Gem') : options.onSaved ? t('Opret og fortsæt') : t('Opret lead')}</button></div>
		</form>`, 'crm-dlg-w');
	const form = $('#leadForm'), button = $('#leadSave'), cancel = $('#leadCancel'), error = $('#leadError');
	const values = () => Object.assign({id: c.id}, Object.fromEntries(new FormData(form).entries()));
	const initial = JSON.stringify(values()); let saving = false;
	dialogGuard = () => !saving && (JSON.stringify(values()) === initial || window.confirm(t('Forlad uden at gemme ændringerne?')));
	dialogAfterClose = options.onCancel || null;
	cancel.onclick = closeDlg;
	form.onsubmit = async e => {
		e.preventDefault();
		if (saving || !form.reportValidity()) return;
		const payload = values(); payload.own = +payload.own;
		if (!payload.n.trim()) { form.elements.n.focus(); return; }
		const controls = Array.from(form.elements).map(el => [el, el.disabled]);
		saving = true; controls.forEach(([el]) => { el.disabled = true; }); error.hidden = true;
		try {
			const r = await api('save_lead', payload);
			// The save response includes this customer even outside the normal dataset limit.
			dialogGuard = dialogAfterClose = null; closeDlg();
			if (options.onSaved) options.onSaved(r.customer);
			else {
				await refreshFull(r.id);
				await openOutside(r.id, 'tl');
			}
			toast(editing ? t('Lead gemt') : t('Lead oprettet'));
		} catch (err) {
			error.textContent = ERR()[err.message] || t('Kunne ikke gemme. Prøv igen.'); error.hidden = false;
		} finally { saving = false; controls.forEach(([el, disabled]) => { el.disabled = disabled; }); }
	};
	form.elements.n.focus();
}

function customerPicker(title, intro, onPick, initial, allowCreate = false) {
	const canCreate = allowCreate && DATA.canWrite;
	openDlg(`<h3 class="sk-dialog-t">${title}</h3><p class="sk-dialog-b">${intro}</p>
		${canCreate ? `<button type="button" class="sk-btn" id="pickerNew">${SV.plus}${t('Opret ny kunde')}</button>` : ''}
		<label class="sk-search crm-dq">${SV.search}<input class="sk-input" id="dq" placeholder="${esc(t('Søg kunde, kundenummer eller kontaktperson'))}" autocomplete="off" value="${esc(initial || '')}"></label><div class="sk-list crm-res" id="dres"></div>`);
	const q = $('#dq'), list = $('#dres'); let hl = 0, res = [], seq = 0, timer = null;
	const active = () => q.isConnected && $('#scrim').classList.contains('open');
	const stop = () => { ++seq; clearTimeout(timer); };
	dialogAfterClose = stop;
	const draw = () => {
		list.innerHTML = res.map((c, i) => `<button type="button" class="sk-list-row${i === hl ? ' hl' : ''}" data-pick="${c.id}"><span class="sk-list-tx"><span class="sk-list-t">${esc(c.n)}</span><span class="sk-list-d">${c.ppl.map(p => esc(p.n)).join(', ')}</span></span><span class="sk-list-meta">${esc(customerRef(c))}${c.by ? ' · ' + esc(c.by) : ''}</span></button>`).join('')
			|| `<div class="sk-empty"><b>${t('Ingen kunder')}</b>${t('Prøv en anden søgning.')}</div>`;
	};
	const search = async () => {
		if (!active()) return;
		const my = ++seq;
		try {
			const r = await fetch(DATA.api, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': DATA.csrf}, body: JSON.stringify({action: 'search', q: q.value})}).then(x => x.json());
			if (my !== seq || !active()) return;
			res = r.ok ? r.result : []; hl = 0; draw();
		} catch (e) { /* netværk: behold listen */ }
	};
	q.oninput = () => { stop(); timer = setTimeout(search, 180); };
	q.onkeydown = e => {
		if (e.key === 'ArrowDown') { hl = Math.min(hl + 1, res.length - 1); draw(); e.preventDefault(); }
		if (e.key === 'ArrowUp') { hl = Math.max(hl - 1, 0); draw(); e.preventDefault(); }
		if (e.key === 'Enter' && res[hl]) pick(res[hl]);
	};
	const pick = c => { stop(); if (!C.find(x => x.id === c.id)) EXTRA[c.id] = c; onPick(c); };
	list.onclick = e => { const b = e.target.closest('[data-pick]'); if (b) pick(res.find(c => c.id === +b.dataset.pick)); };
	if (canCreate) $('#pickerNew').onclick = () => {
		const restore = rememberDialog();
		stop();
		leadDialog(null, {name: q.value.trim(), onSaved: pick, onCancel: () => { restore(); search(); q.focus(); }});
	};
	search(); q.focus();
}

function newFollowUp(dateKey, asNote) {
	const title = asNote ? t('Ny note') : t('Ny opfølgning');
	// "Ny opfølgning" starts with a date (tomorrow, or Monday), so Gem never silently saves a plain note (R5)
	DRAFTS.n = Object.assign(DR0(), {type: asNote ? 'note' : 'opkald', due: dateKey || (asNote ? null : roll(1)), owner: ME || 0});
	customerPicker(title, (dateKey ? t('Opfølgning %s', fmt(dateKey)) + '. ' : '') + t('Vælg en eksisterende kunde, eller opret en ny.'), c => {
		const d = draft('n');
		if (!ME) d.owner = c.own || 0;   // the writer by default; a customer's owner only when there is no writer (R7)
		openDlg(`<h3 class="sk-dialog-t">${title}</h3><div class="crm-pickh"><b>${esc(c.n)}</b><span class="sk-faint">${esc(customerRef(c))}${c.by ? ' · ' + esc(c.by) : ''}</span><button type="button" class="sk-link" id="back">${t('Skift kunde')}</button></div>${composer(c, 'n')}`);
		wireComposer('n', c, closeDlg);
		$('#back').onclick = () => newFollowUp(dateKey, asNote);
		setTimeout(() => $('#nNote').focus(), 30);
	}, '', true);
}

/* ================= møder ================= */
// Colleague chips for a meeting or a sag: everyone but the responsible (not); W is changed in place.
function colleagueChips(sel, W, not) {
	const box = $(sel); if (!box) return;
	for (let i = W.length - 1; i >= 0; i--) if (W[i] === not) W.splice(i, 1);
	box.innerHTML = DATA.emps.filter(e => e.id !== not).map(e => `<button type="button" class="crm-token ${W.includes(e.id) ? 'on' : ''}" aria-pressed="${W.includes(e.id)}" data-pw="${e.id}" ${DATA.canWrite ? '' : 'disabled'}>${SV.plus}${e.id === ME ? t('Mig') : esc(e.n)}${SV.x}</button>`).join('')
		|| `<span class="sk-faint">${t('Ingen andre medarbejdere')}</span>`;
	box.onclick = e => {
		const b = e.target.closest('[data-pw]'); if (!b) return;
		const id = +b.dataset.pw, i = W.indexOf(id);
		if (i >= 0) W.splice(i, 1); else W.push(id);
		b.classList.toggle('on', i < 0); b.setAttribute('aria-pressed', String(i < 0));
		box.dispatchEvent(new Event('change'));
	};
}
// "· 3 invitationer sendt", or that the mail setup failed (the server answers result.mail after the commit)
function mailTxt(r) {
	const m = r && r.mail; if (!m) return '';
	if (m.failed) return ' · ' + t('%s invitationer kunne ikke sendes – tjek mailopsætningen under Indstillinger', m.failed);
	return ' · ' + (m.sent === 1 ? t('1 invitation sendt') : t('%s invitationer sendt', m.sent));
}
const mailToast = (r, head) => toast((head || t('Gemt')) + mailTxt(r));
// After a meeting with invitations out was moved: new invitations are offered, never sent by the move itself.
const resendOffer = (hid, r) => r && r.inv ? {label: t('Send ændringen til deltagerne'), fn: async () => mailToast(await api('send_invites', {id: hid}), t('Ændringen er sendt'))} : null;
const DURS = [15, 30, 45, 60, 90, 120, 180, 240];
const durTxt = n => n < 60 ? t('%s min', n) : t('%s t', (n / 60).toLocaleString(LOC));
// the next whole hour today (within 8-16), else 10:00
const startFor = k => { if (k !== DATA.today) return '10:00'; const h = new Date().getHours() + 1; return h >= 8 && h <= 16 ? hhmm(h * 60) : '10:00'; };

// Nyt møde / Ret møde: a follow-up of type Møde with a time. Who is busy then is shown, with the next free time as a
// suggestion; nothing is moved or sent by itself - invitations go out only when the box is ticked.
function meetingDialog(h, cid, dateKey) {
	const go = c => {
		const m = h ? {note: h.note, date: h.due, start: h.tm, dur: h.dur || 60, owner: h.by, to: h.to, inv: h.inv || 0}
			: {note: '', date: dateKey || roll(1), start: startFor(dateKey || roll(1)), dur: 60, owner: ME || c.own || 0, to: 0, inv: 0};
		const W = h ? (h.with || []).slice() : [];
		const ro = DATA.canWrite ? '' : 'disabled';
		const durs = DURS.includes(m.dur) ? DURS : DURS.concat(m.dur).sort((a, b) => a - b);
		const F = (label, control) => `<label class="sk-field"><span class="sk-field-lbl">${label}</span>${control}</label>`;
		openDlg(`<h3 class="sk-dialog-t">${h ? t('Ret møde') : t('Nyt møde')}</h3><div class="crm-pickh"><b>${esc(c.n)}</b><span class="sk-faint">${esc(customerRef(c))}${c.by ? ' · ' + esc(c.by) : ''}</span>${h ? '' : `<button type="button" class="sk-link" id="back">${t('Skift kunde')}</button>`}</div>
			${F(t('Emne'), `<textarea class="sk-input" id="mN" placeholder="${esc(t('Fx gennemgang af tilbuddet'))}" ${ro}>${esc(m.note)}</textarea>`)}
			<div class="sk-fields-2">
				${F(t('Dato'), `<input type="date" class="sk-input" id="mD" value="${esc(m.date)}" ${ro}>`)}
				<div class="sk-fields-2 crm-pair">${F(t('Fra'), `<input type="time" class="sk-input" id="mS" value="${esc(m.start)}" step="900" ${ro}>`)}
					${F(t('Varighed'), `<select class="sk-input" id="mL" ${ro}>${durs.map(n => `<option value="${n}" ${n === m.dur ? 'selected' : ''}>${durTxt(n)}</option>`).join('')}</select>`)}</div>
				${F(t('Ansvarlig'), `<select class="sk-input" id="mO" ${ro}>${m.owner && !EMP[m.owner] ? `<option value="${m.owner}" selected>${t('(tidligere medarbejder)')}</option>` : m.owner ? '' : `<option value="0" selected>${t('– vælg –')}</option>`}${DATA.emps.map(e => `<option value="${e.id}" ${e.id === m.owner ? 'selected' : ''}>${e.id === ME ? t('Mig') : esc(e.n)}</option>`).join('')}</select>`)}
				${F(t('Kontaktperson'), `<select class="sk-input" id="mK" ${ro}><option value="0">${t('(ingen)')}</option>${m.to && !c.ppl.some(p => p.id === m.to) ? `<option value="${m.to}" selected>${t('(lukket kontakt)')}</option>` : ''}${c.ppl.map(p => `<option value="${p.id}" ${p.id === m.to ? 'selected' : ''}>${esc(p.n)}</option>`).join('')}</select>`)}
			</div>
			${DATA.emps.length > 1 ? `<div class="sk-field"><span class="sk-field-lbl">${t('Kolleger')}</span><div class="crm-tokens" id="mW"></div></div>` : ''}
			<div class="sk-note sk-warn crm-busy" id="mBusy" hidden></div>
			${DATA.canWrite ? `<div class="crm-minvs"><label class="sk-check-lbl crm-minv"><input type="checkbox" class="sk-check" id="mI"><span id="mIt"></span></label><span class="sk-faint crm-mix" id="mIx"></span>
				<label class="sk-check-lbl crm-minv" id="mKl" hidden><input type="checkbox" class="sk-check" id="mKI"><span id="mKt"></span></label></div>` : ''}
			<div class="sk-dialog-f">${h && DATA.canWrite ? `<button type="button" class="sk-btn sk-btn-ghost sk-btn-danger crm-left" id="mDel">${t('Slet møde')}</button>` : ''}<button type="button" class="sk-btn" id="mNo">${DATA.canWrite ? t('Annullér') : t('Luk')}</button>${DATA.canWrite ? `<button type="button" class="sk-btn sk-btn-primary" id="mYes">${h ? t('Gem') : t('Book møde')}</button>` : ''}</div>`, 'crm-dlg-w');
		$('#mNo').onclick = closeDlg;
		if ($('#back')) $('#back').onclick = () => meetingDialog(null, null, dateKey);
		const owner = () => +$('#mO').value;
		const everyone = () => [owner()].concat(W).filter((v, i, a) => v && a.indexOf(v) === i);
		const contact = () => c.ppl.find(p => p.id === +$('#mK').value);
		let touched = false;   // the user changed the invitation box: no more pre-ticking
		const drawInvite = () => {
			if (!$('#mI')) return;
			const all = everyone(), withMail = all.filter(id => EMP[id] && EMP[id].m), noMail = all.filter(id => !(EMP[id] && EMP[id].m));
			$('#mIt').textContent = m.inv & 1 ? t('Send ændringen til deltagerne på mail') : t('Send kalenderinvitation til deltagerne på mail');
			$('#mI').disabled = !withMail.length;
			// pre-ticked when colleagues other than the user are on it (or it was invited before); the user decides
			if (!touched) $('#mI').checked = !!withMail.length && (!!(m.inv & 1) || all.some(id => id !== ME));
			$('#mIx').textContent = noMail.length ? t('Uden mailadresse: %s', noMail.map(empName).join(', ')) : '';
			const k = contact();
			$('#mKl').hidden = !(k && k.mail);
			if (k && k.mail) $('#mKt').textContent = t('Send også invitation til %s (%s)', k.n, k.mail);
			else $('#mKI').checked = false;
		};
		// who is busy then (all meetings in the company, from the server); the free time is only a suggestion
		let bt = null, bseq = 0;
		const busy = () => { clearTimeout(bt); bt = setTimeout(async () => {
			const my = ++bseq, date = $('#mD') && $('#mD').value, start = $('#mS') && $('#mS').value, dur = $('#mL') ? +$('#mL').value : 60;
			if (!date || !/^\d{2}:\d{2}$/.test(start)) { if ($('#mBusy')) $('#mBusy').hidden = true; return; }
			const r = await apiRead('busy', {date, start, dur, ids: everyone(), except: h ? h.id : 0});
			if (my !== bseq || !$('#mBusy')) return;
			if (!r || !r.conflicts.length) { $('#mBusy').hidden = true; return; }
			$('#mBusy').innerHTML = `<b>${t('Optaget på det tidspunkt')}</b>` + r.conflicts.map(x => `<div>${x.who === ME ? t('Du har et møde %s–%s (%s)', x.s, x.e, esc(x.n)) : t('%s har et møde %s–%s (%s)', esc(empName(x.who) || t('(tidligere medarbejder)')), x.s, x.e, esc(x.n))}</div>`).join('')
				+ (r.free && r.free !== start ? `<button type="button" class="sk-link" id="mFree" data-t="${r.free}">${t('Brug %s, hvor alle er ledige', r.free)}</button>` : r.free ? '' : `<div class="sk-faint">${t('Ingen ledig tid senere samme dag for alle.')}</div>`);
			$('#mBusy').hidden = false;
			if ($('#mFree')) $('#mFree').onclick = () => { $('#mS').value = $('#mFree').dataset.t; busy(); };
		}, 250); };
		if ($('#mW')) { colleagueChips('#mW', W, owner()); $('#mW').addEventListener('change', () => { drawInvite(); busy(); }); }
		$('#mO').addEventListener('change', () => { if ($('#mW')) colleagueChips('#mW', W, owner()); drawInvite(); busy(); });
		['#mD', '#mS', '#mL'].forEach(s => $(s).addEventListener('change', busy));
		$('#mS').addEventListener('input', busy);
		$('#mK').addEventListener('change', drawInvite);
		if ($('#mI')) $('#mI').addEventListener('change', () => { touched = true; });
		drawInvite(); busy();
		if (!DATA.canWrite) return;
		if ($('#mDel')) $('#mDel').onclick = () => confirmDel(h.id);
		$('#mYes').onclick = async () => {
			const btn = $('#mYes'); if (btn.disabled) return;   // no duplicates from a double click
			const f = {note: $('#mN').value.trim(), date: $('#mD').value, start: $('#mS').value, dur: +$('#mL').value, owner: owner(), with: W.filter(x => x !== owner()), to: +$('#mK').value,
				invite: !!($('#mI') && $('#mI').checked && !$('#mI').disabled), inviteContact: !!($('#mKI') && $('#mKI').checked)};
			if (!f.date) { $('#mD').focus(); return; }
			if (!/^\d{2}:\d{2}$/.test(f.start)) { $('#mS').focus(); return; }
			if (!f.owner) { toast(t('Vælg, hvem der er ansvarlig for mødet.')); $('#mO').focus(); return; }
			btn.disabled = true;
			let r;
			try { r = await api('save_meeting', Object.assign({id: h ? h.id : 0, cid: c.id}, f)); } catch (e) { btn.disabled = false; return; }
			closeDlg();
			const head = h ? t('Mødet er gemt') : t('Møde booket %s kl. %s', fmt(f.date), f.start);
			// undo: the old meeting back (and to the calendars, if this save sent invitations) / the new one deleted (cancelled, if invited)
			const sent = !!(r.mail && r.mail.sent);
			const undo = h ? () => api('save_meeting', {id: h.id, cid: c.id, note: m.note, date: m.date, start: m.start, dur: m.dur, owner: m.owner, with: (h.with || []).slice(), to: m.to, invite: sent})
				: () => api('delete_entry', {id: r.id, cancel: sent});
			toast(head + mailTxt(r), undo);
		};
		setTimeout(() => $('#mN').focus(), 30);
	};
	if (cid) go(cust(cid)); else customerPicker(t('Nyt møde'), (dateKey ? cap(fmt(dateKey)) + '. ' : '') + t('Vælg en eksisterende kunde, eller opret en ny.'), go, '', true);
}

// Kalender -> Abonnér: the user's own secret feed link (api/crm/ical.php) for Google, Outlook or Apple.
function icalDialog() {
	const draw = url => {
		DATA.ical = url;
		openDlg(`<h3 class="sk-dialog-t">${t('Se dine møder i din egen kalender')}</h3>
			<p class="sk-dialog-b">${t('Med et personligt link kan Google Kalender, Outlook eller Apple Kalender vise dine møder fra CRM – dem, du er ansvarlig for eller deltager i. Kalenderen henter selv ændringerne; Google kan være op til et døgn om det.')}</p>
			${!ME ? `<p class="sk-dialog-b">${t('Din bruger er ikke knyttet til en medarbejder, så der er ingen møder at vise. Bed en administrator knytte brugeren under Indstillinger → Brugere.')}</p>` : url ? `<div class="crm-icurl"><input class="sk-input" id="icU" readonly value="${esc(url)}" aria-label="${esc(t('Dit kalenderlink'))}"><button type="button" class="sk-btn" id="icCopy">${t('Kopiér')}</button></div>
			<ul class="crm-ich"><li><b>Google:</b> ${t('Andre kalendere → + → Fra webadresse, indsæt linket.')}</li><li><b>Outlook:</b> ${t('Tilføj kalender → Abonner fra nettet, indsæt linket.')}</li><li><b>Apple:</b> ${t('Arkiv → Nyt kalenderabonnement, indsæt linket.')} <a href="${esc(url.replace(/^https?:/, 'webcal:'))}">${t('Åbn direkte')}</a></li></ul>
			<p class="sk-dialog-b">${t('Linket er personligt: den, der har det, kan se dine møder. Er det kommet ud, så lav et nyt – det gamle holder op med at virke.')}</p>` : ''}
			<div class="sk-dialog-f">${url ? `<button type="button" class="sk-btn sk-btn-ghost sk-btn-danger crm-left" id="icOff">${t('Slå fra')}</button><button type="button" class="sk-btn" id="icNew">${t('Lav nyt link')}</button>` : ''}<button type="button" class="sk-btn" id="icNo">${t('Luk')}</button>${ME && !url ? `<button type="button" class="sk-btn sk-btn-primary" id="icMake">${t('Lav mit link')}</button>` : ''}</div>`);
		$('#icNo').onclick = closeDlg;
		const set = async mode => { const r = await apiRead('ical', {mode}); if (!r) { toast(t('Kunne ikke gemme. Prøv igen.')); return; } draw(r.url); if (S.tab === 'cal') renderCal(); };
		if ($('#icMake')) $('#icMake').onclick = () => set('new');
		if ($('#icNew')) $('#icNew').onclick = () => set('new');
		if ($('#icOff')) $('#icOff').onclick = () => set('off');
		if ($('#icCopy')) $('#icCopy').onclick = async () => {
			const u = $('#icU'); u.select();
			try { await navigator.clipboard.writeText(u.value); } catch (e) { try { document.execCommand('copy'); } catch (e2) { /* the field stays selected */ } }
			toast(t('Linket er kopieret'));
		};
	};
	draw(DATA.ical || '');
}

// An offer/order linked to a deal (crm_deals.ordre_id): name, state and the suggestions it gives.
const docName = o => o.st >= 3 ? t('Faktura %s', o.fnr || o.nr) : t(o.st === 0 && !DATA.quick ? 'Tilbud %s' : 'Ordre %s', o.nr);
const docState = o => o.st >= 3 ? t('faktureret') : o.st === 2 ? t('delvist leveret') : o.st === 1 || DATA.quick ? t('ordre') : t('tilbud');
function dealDocBox(deal, c) {
	const back = backTo(c.id), o = deal.doc;
	if (o) {
		const diffSum = o.sum && Math.abs(o.sum - deal.v) >= 0.005;
		return `<div class="crm-docbox"><div class="crm-dd1"><span>${ICON.note}<b>${esc(docName(o))}</b> · ${kr(o.sum)} <span class="sk-faint">${t('ekskl. moms')}</span> · ${docState(o)}</span><a href="ordre.php?id=${o.id}&returside=${back}">${t('Åbn')}</a></div>
			${diffSum && deal.state === 'open' ? `<button type="button" class="sk-link" id="dUseSum" data-v="${esc(fmtAmt(o.sum))}">${t(o.st === 0 && !DATA.quick ? 'Brug beløbet fra tilbuddet: %s' : 'Brug beløbet fra ordren: %s', kr(o.sum))}</button>` : ''}
			${o.st >= 3 && deal.state === 'open' ? `<button type="button" class="sk-link" id="dWonDoc">${t('Ordren er faktureret – markér sagen som vundet')}</button>` : ''}</div>`;
	}
	if (deal.state !== 'open' || !canDocument(c)) return '';
	return `<div class="crm-docbox"><div class="crm-dd1"><span class="sk-faint">${t('Intet tilbud eller ordre til sagen endnu')}</span><span class="crm-ddl">${DATA.quick ? '' : `<a data-mkdoc="0" data-cid="${c.id}" data-deal="${deal.id}" href="${documentHref(c, false, deal.id)}">${t('Opret tilbud')}</a>`}<a data-mkdoc="1" data-cid="${c.id}" data-deal="${deal.id}" href="${documentHref(c, true, deal.id)}">${t('Opret ordre')}</a></span></div></div>`;
}
// Deal stages come from the group holding the deal's stage, else the first status group (Salg).
const dealGroup = st => GROUPS.find(x => x.s.includes(st)) || GROUPS[0] || boardGroup();
function dealDialog(deal, cid, stage) {
	if (!deal && !dealGroup(stage).s.length) { toast(t('Opsæt statusser først (tandhjulet øverst til højre).')); return; }   // R27, empty setup
	const go = c => {
		// a new deal starts in the customer's own status when that is on the board (R33); never the first employee (R6)
		const g = dealGroup(deal ? deal.st : (stage || c.st));
		const d = deal || {t: '', v: 0, st: stage || (g.s.includes(c.st) ? c.st : g.s[0]) || '', close: key(D(30)), own: c.own || ME || 0};
		const F = (label, control) => `<label class="sk-field"><span class="sk-field-lbl">${label}</span>${control}</label>`;
		openDlg(`<h3 class="sk-dialog-t">${deal ? t('Ret sag') : t('Ny sag')}</h3><div class="crm-pickh"><b>${esc(c.n)}</b><span class="sk-faint">${esc(customerRef(c))}</span>${deal ? '' : `<button type="button" class="sk-link" id="back">${t('Skift kunde')}</button>`}</div>
			${F(t('Titel'), `<input class="sk-input" id="dT" value="${esc(d.t)}" placeholder="${esc(t('Fx Saldi Plus, 3 brugere'))}" maxlength="120">`)}
			<div class="sk-fields-2">
				${F(t('Beløb (kr, ekskl. moms)'), `<input class="sk-input" id="dV" inputmode="decimal" value="${esc(fmtAmt(d.v))}" placeholder="0">`)}
				${F(t('Forventet lukket'), `<input class="sk-input" id="dC" type="date" value="${esc(d.close || '')}">`)}
				${F(t('Stadie'), `<select class="sk-input" id="dS" ${deal && deal.state !== 'open' ? 'disabled' : ''}>${(deal && !g.s.includes(d.st) ? [d.st] : []).concat(g.s).map(id => `<option value="${esc(id)}" ${id === d.st ? 'selected' : ''}>${esc(stName(id))}</option>`).join('')}</select>`)}
				${F(t('Ansvarlig'), `<select class="sk-input" id="dO">${(d.own && !EMP[d.own] ? [`<option value="${d.own}" selected>${t('(tidligere medarbejder)')}</option>`] : d.own ? [] : [`<option value="0" selected>${t('– vælg –')}</option>`]).concat(DATA.emps.map(e => `<option value="${e.id}" ${e.id === d.own ? 'selected' : ''}>${e.id === ME ? t('Mig') : esc(e.n)}</option>`)).join('') || '<option value="0">–</option>'}</select>`)}
			</div>
			${DATA.emps.length > 1 ? `<div class="sk-field"><span class="sk-field-lbl">${t('Kolleger på sagen')}</span><div class="crm-tokens" id="dW"></div></div>` : ''}
			${deal ? dealDocBox(deal, c) : ''}
			<div class="sk-dialog-f">${deal ? `<button type="button" class="sk-btn sk-btn-ghost sk-btn-danger crm-left" id="dDel">${t('Slet sag')}</button>` : ''}${deal && deal.state !== 'open' ? `<button type="button" class="sk-btn" id="dReopen">${t('Genåbn')}</button>` : ''}${deal && deal.state === 'open' ? `<button type="button" class="sk-btn" id="dWon">${t('Vundet')}</button><button type="button" class="sk-btn" id="dLost">${t('Tabt')}</button>` : ''}<button type="button" class="sk-btn" id="dNo">${t('Annullér')}</button><button type="button" class="sk-btn sk-btn-primary" id="dYes">${deal ? t('Gem') : t('Opret sag')}</button></div>`);
		if (!DATA.canWrite) {
			// read-only: the deal is shown, nothing can be changed (R72)
			$('#dlg').querySelectorAll('input,select').forEach(x => { x.disabled = true; });
			['dDel', 'dWon', 'dLost', 'dYes', 'dReopen', 'dWonDoc', 'dUseSum', 'back'].forEach(id => { const el = document.getElementById(id); if (el) el.remove(); });
			$('#dNo').textContent = t('Luk'); $('#dNo').onclick = closeDlg;
			return;
		}
		// colleagues on the sag (not the responsible); the server tells the ones added in their bell
		const W = (d.with || []).slice();
		const drawW = () => colleagueChips('#dW', W, +$('#dO').value);
		drawW();
		$('#dO').addEventListener('change', drawW);
		const fields = () => ({t: $('#dT').value.trim(), v: parseAmt($('#dV').value), st: $('#dS').value, close: $('#dC').value, own: +$('#dO').value, with: W.filter(x => x !== +$('#dO').value)});
		const initialFields = JSON.stringify(fields());
		dialogDocumentGuard = () => JSON.stringify(fields()) === initialFields || window.confirm(t('Forlad uden at gemme ændringerne?'));
		const badAmount = () => { toast(t('Beløbet kunne ikke læses – skriv fx 12.500,50.')); $('#dV').focus(); };
		// Vundet/Tabt/markér vundet first save what was edited in the dialog, incl. "Brug beløbet" (R29)
		const saveIfChanged = async () => {
			const f = fields();
			if (f.v === null) { badAmount(); return false; }
			if (f.t && (f.t !== deal.t || +f.v !== +deal.v || f.close !== (deal.close || '') || f.own !== deal.own || f.with.join() !== (deal.with || []).join())) {
				await api('save_deal', {id: deal.id, cid: c.id, t: f.t, v: f.v, st: f.st, close: f.close, own: f.own, with: f.with});
			}
			return true;
		};
		if ($('#back')) $('#back').onclick = () => dealDialog(null, null, stage);
		$('#dNo').onclick = closeDlg;
		if ($('#dDel')) $('#dDel').onclick = () => {
			// confirmed, undoable and logged (R30)
			openDlg(`<h3 class="sk-dialog-t">${t('Slet sagen »%s«?', esc(deal.t))}</h3><p class="sk-dialog-b">${t('Sagen, dens beløb og koblingen til tilbud eller ordre forsvinder fra CRM. Du kan fortryde fra beskeden nederst.')}</p><div class="sk-dialog-f"><button type="button" class="sk-btn" id="cNo">${t('Annullér')}</button><button type="button" class="sk-btn sk-btn-danger" id="cYes">${t('Slet')}</button></div>`);
			$('#cNo').onclick = () => dealDialog(deal, cid, stage);
			$('#cYes').onclick = async () => { const r = await api('delete_deal', {id: deal.id}); closeDlg(); toast(t('Sag »%s« slettet', deal.t), () => api('restore_deal', {id: deal.id, before: r.before, remove: r.hid})); };
		};
		const won = async () => { if (!(await saveIfChanged())) return; closeDlg(); moveDeal(DEALS.find(x => x.id === deal.id) || deal, {state: 'won'}, t('vundet')); };
		if ($('#dWon')) $('#dWon').onclick = won;
		if ($('#dWonDoc')) $('#dWonDoc').onclick = won;
		if ($('#dReopen')) $('#dReopen').onclick = () => { closeDlg(); moveDeal(deal, {state: 'open'}, t('genåbnet')); };
		if ($('#dUseSum')) $('#dUseSum').onclick = () => { $('#dV').value = $('#dUseSum').dataset.v; $('#dUseSum').remove(); $('#dV').focus(); };
		if ($('#dLost')) $('#dLost').onclick = async () => { if (!(await saveIfChanged())) return; lostDialog(deal, reason => moveDeal(DEALS.find(x => x.id === deal.id) || deal, {state: 'lost', reason}, t('tabt') + (reason ? ' – ' + reason : ''))); };
		$('#dYes').onclick = async () => {
			const btn = $('#dYes'); if (btn.disabled) return;   // no duplicates from a double click (R76)
			const f = fields();
			if (!f.t) { $('#dT').focus(); return; }
			if (f.v === null) { badAmount(); return; }
			btn.disabled = true;
			try {
				await api('save_deal', {id: deal ? deal.id : 0, cid: c.id, t: f.t, v: f.v, st: f.st, close: f.close, own: f.own, with: f.with});
				if (deal && deal.state === 'open' && f.st !== deal.st) await api('move_deal', {id: deal.id, st: f.st});
			} catch (e) { btn.disabled = false; return; }
			closeDlg();
			const fresh = cust(c.id) || c;
			// the customer is not in the column the deal was put in: offer the status, never set it (R27, R26)
			const offBoard = f.st && fresh.st !== f.st && g.s.includes(f.st) && !isWon(f.st) && !isLost(f.st);
			toast(deal ? t('Sagen er gemt') : t('Sag oprettet i %s', stName(f.st)), null,
				offBoard ? {label: t('Kunden står ikke på tavlen – sæt status til %s', stName(f.st)), fn: () => setStatus(fresh, f.st)} : null);
		};
		setTimeout(() => $('#dT').focus(), 30);
	};
	if (cid) go(cust(cid)); else customerPicker(t('Ny sag'), t('Vælg en eksisterende kunde, eller opret en ny.'), go, '', true);
}

function lostDialog(d, done) {
	const reasons = [t('Pris'), t('Valgte en konkurrent'), t('Intet behov'), t('Forkert tidspunkt'), t('Ingen svar')];
	openDlg(`<h3 class="sk-dialog-t">${t('Hvorfor blev »%s« tabt?', esc(d.t))}</h3><p class="sk-dialog-b">${t('Valgfrit. Over tid viser det, hvorfor I taber sager.')}</p>
		<div class="crm-reasons">${reasons.map(r => `<button type="button" class="sk-btn sk-btn-sm crm-choice" data-r="${esc(r)}" aria-pressed="false">${esc(r)}</button>`).join('')}</div>
		<label class="sk-field"><span class="sk-field-lbl">${t('Bemærkning')}</span><input class="sk-input" id="rTxt" maxlength="200"></label>
		<div class="sk-dialog-f"><button type="button" class="sk-btn" id="rSkip">${t('Spring over')}</button><button type="button" class="sk-btn sk-btn-primary" id="rOk">${t('Markér som tabt')}</button></div>`);
	let pick = '';
	$('#dlg').querySelectorAll('[data-r]').forEach(b => b.onclick = () => { pick = pick === b.dataset.r ? '' : b.dataset.r; $('#dlg').querySelectorAll('[data-r]').forEach(x => { x.classList.toggle('on', x.dataset.r === pick); x.setAttribute('aria-pressed', String(x.dataset.r === pick)); }); });
	$('#rSkip').onclick = () => { closeDlg(); done(''); };
	$('#rOk').onclick = () => { const note = $('#rTxt').value.trim(); closeDlg(); done([pick, note].filter(Boolean).join(': ')); };
}

function editDialog(hid) {
	const h = H.find(x => x.id === hid), c = cust(h.cid);
	if (isMeet(h)) { meetingDialog(h, h.cid); return; }
	const F = (label, control, cls) => `<label class="sk-field${cls ? ' ' + cls : ''}"><span class="sk-field-lbl">${label}</span>${control}</label>`;
	openDlg(`<h3 class="sk-dialog-t">${t('Ret %s fra %s', TYPE_N[h.type].toLowerCase(), fmtY(h.date))}</h3>
		<div class="sk-fields-2">
			${F(t('Type'), `<select class="sk-input" id="eT">${COMPOSE_TYPES.map(ty => `<option value="${ty}" ${ty === h.type ? 'selected' : ''}>${TYPE_N[ty]}</option>`).join('')}</select>`)}
			${F(t('Dato'), `<input type="date" class="sk-input" id="eDate" value="${esc(h.date)}" max="${DATA.today}">`)}
			${F(t('Med'), `<select class="sk-input" id="eP">${h.to && !c.ppl.some(p => p.id === h.to) ? `<option value="${h.to}" selected>${t('(lukket kontakt)')}</option>` : ''}${c.ppl.map(p => `<option value="${p.id}" ${p.id === h.to ? 'selected' : ''}>${esc(p.n)}</option>`).join('')}<option value="0" ${!h.to ? 'selected' : ''}>${t('(ingen)')}</option></select>`)}
			${F(t('Note'), `<textarea class="sk-input" id="eN">${esc(h.note)}</textarea>`, 'crm-wide')}
			${h.due && !h.done ? F(t('Opfølgning'), `<input type="date" class="sk-input" id="eD" value="${esc(h.due)}" title="${t('Tøm feltet for at fjerne påmindelsen – noten bliver stående')}">`)
				+ F(t('Til'), `<select class="sk-input" id="eO">${h.by && !EMP[h.by] ? `<option value="${h.by}" selected>${t('(tidligere medarbejder)')}</option>` : ''}${DATA.emps.map(e => `<option value="${e.id}" ${e.id === h.by ? 'selected' : ''}>${e.id === ME ? t('Mig') : esc(e.n)}</option>`).join('')}</select>`) : ''}
		</div>
		<div class="sk-dialog-f"><button type="button" class="sk-btn" id="eNo">${t('Annullér')}</button><button type="button" class="sk-btn sk-btn-primary" id="eYes">${t('Gem')}</button></div>`);
	$('#eNo').onclick = closeDlg;
	$('#eYes').onclick = async () => {
		const old = {id: h.id, type: h.type, to: h.to, note: h.note, date: h.date};
		const upd = {id: h.id, type: $('#eT').value, to: +$('#eP').value, note: $('#eN').value, date: $('#eDate').value};
		if ($('#eD')) { old.due = h.due; old.owner = h.by; upd.due = $('#eD').value; if ($('#eO')) upd.owner = +$('#eO').value; }
		await api('update_entry', upd);
		closeDlg(); toast(t('Noten er rettet'), () => api('update_entry', old));
	};
	setTimeout(() => $('#eN').focus(), 30);
}

function confirmDel(hid) {
	const h = H.find(x => x.id === hid);
	// a meeting with invitations out: a cancellation is offered (pre-ticked); undo restores it and invites again
	const cancel = isMeet(h) && h.inv;
	openDlg(`<h3 class="sk-dialog-t">${isMeet(h) ? t('Slet mødet %s kl. %s?', fmt(h.due), h.tm) : t('Slet %s fra %s?', (TYPE_N[h.type] || TYPE_N.note).toLowerCase(), fmtY(h.date))}</h3><p class="sk-dialog-b">${esc(h.note.slice(0, 160))}${h.note.length > 160 ? '…' : ''}</p>
		${cancel ? `<label class="sk-check-lbl crm-minv crm-cancel"><input type="checkbox" class="sk-check" id="cCancel" checked><span>${t('Send aflysning til dem, der fik en invitation')}</span></label>` : ''}<div class="sk-dialog-f"><button type="button" class="sk-btn" id="cNo">${t('Annullér')}</button><button type="button" class="sk-btn sk-btn-danger" id="cYes">${t('Slet')}</button></div>`);
	$('#cNo').onclick = closeDlg;
	$('#cYes').onclick = async () => {
		const r = await api('delete_entry', {id: h.id, cancel: !!(cancel && $('#cCancel').checked)}); closeDlg();
		const sent = r.row.mt && r.row.mt.cancelled;
		toast(t('Slettet') + (sent ? ' · ' + t('aflysning sendt') : ''), async () => {
			const back = await api('restore_entry', {row: r.row});
			if (sent && back && back.id) mailToast(await api('send_invites', {id: back.id}), t('Mødet er tilbage'));
		});
	};
	setTimeout(() => $('#cYes').focus(), 30);
}

/* ================= handlinger ================= */
async function toggleDone(hid, rowEl) {
	const h = H.find(x => x.id === hid); if (!h) return;
	const to = !h.done;
	if (to && rowEl && rowEl.classList.contains('row')) { rowEl.querySelector('.tick').classList.add('on'); rowEl.classList.add('leaving'); }
	await api('toggle_done', {id: hid, done: to});
	const c = cust(h.cid);
	// after a tick: offer the next step for that customer, never set it by itself (R13)
	toast(to ? t('%s markeret som gjort', c.n) : t('%s er åben igen', c.n), () => api('toggle_done', {id: hid, done: !to}),
		to && DATA.canWrite ? {label: t('Ny opfølgning'), fn: () => nextStep(h.cid)} : null);
}
function nextStep(cid) {
	const d = draft('c', cid);
	d.due = roll(7); d.closePrev = false;
	openDrawer(cid, 'tl');
	const n = $('#cNote'); if (n) n.focus();   // kept through the re-render when the whole history arrives
}
async function followUpIn7(cid) {
	const c = cust(cid); if (!c) return;
	const due = roll(7);
	const r = await api('add_entry', {cid, type: 'note', to: 0, note: t('Følg op – %s', stName(c.st).toLowerCase()), due, owner: ME || c.own || 0, close: []});
	toast(t('Opfølgning sat til %s', fmt(due)), () => api('delete_entry', {id: r.id}));
}
// responsible employee of the customer, from the drawer; moving its open follow-ups along is offered (R15)
async function setOwner(c, to) {
	const r = await api('set_owner', {cid: c.id, owner: to});
	const name = to ? (EMP[to] ? EMP[to].n : '') : t('ingen');
	const move = to ? openFor(c.id).filter(h => h.by !== to) : [];
	toast(`${c.n}: ${t('ansvarlig: %s', name)}`, () => api('unset_owner', {cid: c.id, owner: r.from, remove: r.hid}),
		move.length ? {label: move.length === 1 ? t('Flyt opfølgningen til %s', name) : t('Flyt %s åbne opfølgninger til %s', move.length, name), fn: async () => {
			const m = await api('move_followups', {cid: c.id, owner: to, ids: move.map(h => h.id)});
			toast(t('Opfølgningerne er flyttet'), () => api('unmove_followups', {moved: m.moved}));
		}} : null);
}
// A lead becomes a debtor only after choosing the accounting group and confirming the first document.
function prepareLeadDocument(c, order, deal) {
	const restore = rememberDialog(), groups = DATA.debtorGroups || [];
	openDlg(`<h3 class="sk-dialog-t">${order ? t('Opret ordre') : t('Opret tilbud')}</h3>
		<p class="sk-dialog-b">${t('»%s« er et lead. Når du fortsætter, oprettes kunden automatisk i debitorkartoteket. Historik og sager bevares.', esc(c.n))}</p>
		${deal ? `<p class="sk-dialog-b">${t('Sag: %s', esc((DEALS.find(d => d.id === deal) || {}).t || ''))}</p>` : ''}
		<form id="leadDocument" class="crm-lead-form">
			<label class="sk-field"><span class="sk-field-lbl">${t('Debitorgruppe')}</span><select class="sk-input" id="leadGroup" required${groups.length ? '' : ' disabled'}>${groups.length === 1 ? '' : `<option value="">${t('– vælg –')}</option>`}${groups.map(g => `<option value="${g.id}">${esc(g.n)}</option>`).join('')}</select><span class="sk-field-help">${t('Gruppen bestemmer blandt andet kundens moms.')}</span></label>
			${groups.length ? '' : `<p class="sk-note sk-warn">${t('Der er ingen debitorgrupper. Opret en under Indstillinger, og prøv igen.')}</p>`}
			<p class="sk-note sk-bad crm-form-error" id="leadDocumentError" role="alert" hidden></p>
			<div class="sk-dialog-f"><button type="button" class="sk-btn" id="leadDocumentCancel">${t('Annullér')}</button><button type="submit" class="sk-btn sk-btn-primary" id="leadDocumentSave"${groups.length ? '' : ' disabled'}>${order ? t('Opret debitor og ordre') : t('Opret debitor og tilbud')}</button></div>
		</form>`);
	const form = $('#leadDocument'), group = $('#leadGroup'), button = $('#leadDocumentSave'), cancel = $('#leadDocumentCancel'), error = $('#leadDocumentError');
	let saving = false;
	dialogGuard = () => !saving;
	dialogAfterClose = restore;
	cancel.onclick = closeDlg;
	form.onsubmit = async e => {
		e.preventDefault();
		if (saving || !groups.length || !form.reportValidity()) return;
		saving = true; button.disabled = cancel.disabled = true; group.disabled = true; error.hidden = true;
		try {
			const result = await api('prepare_document', {cid: c.id, group: +group.value, kind: order ? 'order' : 'quote', deal: deal || 0});
			dialogGuard = dialogAfterClose = null; closeDlg();
			await sendPlace();
			location.href = documentUrl(result.id, order, deal);
		} catch (err) {
			error.textContent = ERR()[err.message] || t('Kunne ikke gemme. Prøv igen.'); error.hidden = false;
			saving = false; button.disabled = cancel.disabled = group.disabled = false;
		}
	};
	(groups.length > 1 ? group : button).focus();
}
// "Opret tilbud/ordre" when the customer has open cases without a document: which case is it for?
function makeDoc(a) {
	const cid = +a.dataset.cid, c = cust(cid), order = a.dataset.mkdoc === '1';
	if (!c || !canDocument(c)) { toast(t('Du har ikke rettighed til at oprette tilbud og ordrer.')); return; }
	if (!order && DATA.quick) { toast(ERR().quick); return; }
	if (a.closest('#dlg') && dialogDocumentGuard && !dialogDocumentGuard()) return;
	const go = deal => {
		if (c.lead) { prepareLeadDocument(c, order, deal); return; }
		const href = documentUrl(c.id, order, deal);
		closeDlg();
		sendPlace().finally(() => { location.href = href; });
	};
	if (a.dataset.deal) { go(+a.dataset.deal); return; }
	const free = openDeals(cid).filter(d => !d.doc);
	if (!free.length) { go(0); return; }
	openDlg(`<h3 class="sk-dialog-t">${order ? t('Hvilken sag gælder ordren?') : t('Hvilken sag gælder tilbuddet?')}</h3>
		<div class="sk-list crm-res">${free.map(d => `<button type="button" class="sk-list-row" data-mkdeal="${d.id}"><span class="sk-list-tx"><span class="sk-list-t">${esc(d.t)}</span><span class="sk-list-d">${kr(d.v)} · ${esc(stName(d.st))}</span></span></button>`).join('')}
		<button type="button" class="sk-list-row" data-mkdeal="0"><span class="sk-list-tx"><span class="sk-list-t">${t('Ingen sag')}</span></span></button></div>`);
	$('#dlg').querySelectorAll('[data-mkdeal]').forEach(b => b.onclick = () => go(+b.dataset.mkdeal));
}
// an existing quote/order attached to one of the customer's deals without a document (R31)
function linkDoc(oid, cid) {
	const free = openDeals(cid).filter(d => !d.doc);
	const link = async deal => {
		const r = await api('link_doc', {cid, deal: deal.id, doc: oid});
		toast(t('Knyttet til sagen »%s«', deal.t), () => api('unlink_doc', {cid, deal: deal.id, remove: r.hid}));
	};
	if (free.length === 1) { link(free[0]); return; }
	openDlg(`<h3 class="sk-dialog-t">${t('Knyt til sag')}</h3><div class="sk-list crm-res">${free.map(d => `<button type="button" class="sk-list-row" data-lk="${d.id}"><span class="sk-list-tx"><span class="sk-list-t">${esc(d.t)}</span><span class="sk-list-d">${kr(d.v)} · ${esc(stName(d.st))}</span></span></button>`).join('')}</div>`);
	$('#dlg').querySelectorAll('[data-lk]').forEach(b => b.onclick = () => { closeDlg(); link(free.find(d => d.id === +b.dataset.lk)); });
}
async function startBoard() {
	await api('starter');
	S.tab = 'pipe'; S.bgrp = GROUPS.length ? GROUPS[0].id : 0;
	renderGroups(); renderAll();
	toast(t('Salgstavlen er oprettet'));
}
async function closeOld() {
	const r = await api('close_old');
	localStorage.setItem('crm-oldlate', DATA.today);
	toast(t('%s opfølgninger fra det gamle CRM (mere end 90 dage forsinkede) markeret som gjort', r.ids.length), () => api('reopen_many', {ids: r.ids}));
}
const serverSearch = () => customerPicker(t('Søg blandt alle kunder'), t('Fundet uden for listen'), c => { closeDlg(); openOutside(c.id); }, S.q);
const openGear = () => { if (!$('#panel').classList.contains('open')) $('#gearBtn').click(); };
function snoozeMenu(hid, anchor) {
	const h = H.find(x => x.id === hid), m = $('#menu');
	// weekend targets roll to Monday (R18); the list never shows the same date twice
	const seen = {};
	const opts = [[rollLabel(t('I morgen'), 1), roll(1)], [rollLabel(t('Om 2 dage'), 2), roll(2)], [t('Næste mandag'), key(D((8 - TODAY.getDay()) % 7 || 7))], [t('Om 1 uge'), roll(7)], [t('Om 2 uger'), roll(14)]]
		.filter(([, k]) => !seen[k] && (seen[k] = true));
	m.innerHTML = opts.map(([n, k]) => `<button type="button" class="sk-opt" data-to="${k}">${n}<small>${fmt(k)}</small></button>`).join('') + `<hr class="crm-sep"><div class="crm-menu-d"><input type="date" class="sk-input sk-input-sm" id="snDate" aria-label="${t('Vælg dato')}"></div>`;
	const r = anchor.getBoundingClientRect();
	m.style.left = Math.min(r.left, innerWidth - 220) + 'px'; m.style.top = Math.min(r.bottom + 4, innerHeight - 260) + 'px';
	m.classList.add('open');
	const go = async to => { const old = h.due; m.classList.remove('open'); const r = await api('set_due', {id: hid, due: to}); toast(t('Udsat til %s', fmt(to)), () => api('set_due', {id: hid, due: old}), resendOffer(hid, r)); };
	m.querySelectorAll('[data-to]').forEach(b => b.onclick = e => { e.stopPropagation(); go(b.dataset.to); });
	$('#snDate').onchange = e => go(e.target.value);
	$('#snDate').onclick = e => e.stopPropagation();
}
// $extra overrides the default suggestion (a follow-up in a week) - the board uses it for the customer's open deals (R26, R28)
async function setStatus(c, to, how, extra) {
	if (c.st === to) return;
	const r = await api('set_status', {cid: c.id, status: to, how: how || ''});
	const fresh = cust(c.id);
	const suggest = to && !openFor(c.id).length && !isLost(to) && !isWon(to);
	toast(`${c.n}: ${to ? stName(to) : t('ingen status')}${how ? ' (' + how + ')' : ''}`, () => api('set_status', {cid: c.id, status: r.from, remove: r.id}),
		extra || (suggest ? {label: t('Opfølgning om 1 uge'), fn: async () => {
			const due = roll(7);
			await api('add_entry', {cid: c.id, type: 'note', to: 0, note: t('Følg op – %s', stName(to).toLowerCase()), due, owner: fresh.own || ME, close: []});
			toast(t('Opfølgning sat til %s', fmt(due)));
		}} : null));
}
// Drop on the board (R26, R28): a column offers to move the customer's open deals along; Vundet/Tabt offer to close them.
function dropOnBoard(c, g, col, end) {
	const deals = openDeals(c.id);
	if (end) {
		const to = end === 'won' ? g.won : g.lost, lbl = end === 'won' ? t('vundet') : t('tabt');
		const extra = deals.length ? {label: deals.length === 1 ? t('Markér sagen som %s', lbl) : t('Markér %s åbne sager som %s', deals.length, lbl), fn: () => {
			if (end === 'won') { deals.forEach(d => moveDeal(d, {state: 'won'}, t('vundet'))); return; }
			lostDialog(deals.length === 1 ? deals[0] : {t: c.n}, reason => deals.forEach(d => moveDeal(d, {state: 'lost', reason}, t('tabt') + (reason ? ' – ' + reason : ''))));
		}} : null;
		return setStatus(c, to, lbl, extra);
	}
	const behind = deals.filter(d => d.st !== col);
	const extra = behind.length ? {label: t('Flyt også sagen til %s', stName(col)), fn: () => behind.forEach(d => api('move_deal', {id: d.id, st: col}))} : null;
	return setStatus(c, col, '', extra);
}
async function moveDeal(d, patch, label) {
	const r = await api('move_deal', Object.assign({id: d.id}, patch));
	const g = dealGroup(d.st), c = cust(d.cid);
	const suggestWon = patch.state === 'won' && g.won && c && c.st !== g.won;
	toast(`${d.t}: ${label}`, () => api('restore_deal', {id: d.id, before: r.before, remove: r.hid}),
		suggestWon ? {label: t('Sæt %s som %s', c.n, stName(g.won)), fn: () => setStatus(c, g.won, t('vundet sag'))} : null);
}

/* ================= statusgrupper (tandhjul) ================= */
let GDRAFT = [];
function renderGroups() {
	$('#grpSel').innerHTML = `<option value="all">${t('Alle statusser')}</option>` + GROUPS.map(g => `<option value="${g.id}" ${String(S.grp) === String(g.id) ? 'selected' : ''}>${esc(g.n)}</option>`).join('');
	$('#grpSel').style.display = GROUPS.length ? '' : 'none';
}
// Gear panel: the customer statuses (grupper DebInfo box3/box4) and the status groups (SKN), both saved with Gem.
// SDRAFT: [{id, n, used}] - a new status has the id 'new:<n>' until the server gives it a number.
let SDRAFT = [], newSeq = 0;
const TRASH = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>';
const sdName = id => { const s = SDRAFT.find(x => x.id === id); return s ? (s.n || t('Ny status')) : stName(id); };
function renderGroupEditor() {
	const ro = DATA.canWrite ? '' : 'disabled';
	const ib = 'sk-btn sk-btn-ghost sk-btn-icon sk-btn-sm';
	const arrows = (i, n, up, down) => DATA.canWrite ? `<button type="button" class="${ib}" ${up}="${i}" ${i === 0 ? 'disabled' : ''} title="${t('Flyt op')}" aria-label="${t('Flyt op')}">${SV.up}</button><button type="button" class="${ib}" ${down}="${i}" ${i === n - 1 ? 'disabled' : ''} title="${t('Flyt ned')}" aria-label="${t('Flyt ned')}">${SV.down}</button>` : '';
	$('#stList').innerHTML = SDRAFT.map((s, i) => `<div class="strow crm-strow" data-si="${i}"><input type="text" class="sk-input sk-input-sm" value="${esc(s.n)}" maxlength="60" aria-label="${t('Statusnavn')}" placeholder="${esc(t('Ny status'))}" data-sf="n" ${ro}>
		<span class="crm-use">${s.used ? t('%s i brug', s.used) : ''}</span>${arrows(i, SDRAFT.length, 'data-sup', 'data-sdown')}
		${DATA.canWrite ? `<button type="button" class="${ib} crm-trash" data-dels="${i}" ${s.used ? 'disabled' : ''} title="${esc(s.used ? t('Bruges af %s kunder og sager – kan ikke slettes', s.used) : t('Slet status'))}" aria-label="${esc(t('Slet status'))}">${TRASH}</button>` : ''}</div>`).join('')
		|| `<p class="crm-gear-n">${t('Der er ingen kundestatusser endnu.')}</p>`;
	const opts = sel => '<option value="">–</option>' + SDRAFT.map(s => `<option value="${esc(s.id)}" ${s.id === sel ? 'selected' : ''}>${esc(sdName(s.id))}</option>`).join('');
	$('#sgList').innerHTML = (!GDRAFT.length && !GROUPS.length && DATA.canWrite ? `<p class="crm-gear-n">${t('Ingen statusgrupper endnu.')} ${t('Forslag: en salgstavle med Henvendelse › Tilbud sendt › Forhandling, hvor Vundet giver status Kunde og Tabt giver status Tabt.')} <button type="button" class="sk-link" data-starter="1">${t('Start med en salgstavle')}</button></p>` : '')
		+ GDRAFT.map((g, i) => `<div class="sg crm-sg" data-i="${i}"><div class="crm-sgh"><input type="text" class="sk-input sk-input-sm" value="${esc(g.n)}" aria-label="${t('Gruppenavn')}" data-f="n" ${ro}>${arrows(i, GDRAFT.length, 'data-gup', 'data-gdown')}
		${DATA.canWrite ? `<button type="button" class="${ib} crm-trash" data-delg="${i}" title="${esc(t('Slet gruppe'))}" aria-label="${esc(t('Slet gruppe'))}">${TRASH}</button>` : ''}</div>
		<div class="crm-sgopts">${SDRAFT.map(s => `<label class="sk-check-lbl"><input type="checkbox" class="sk-check" data-st="${esc(s.id)}" ${g.s.includes(s.id) ? 'checked' : ''} ${ro}>${esc(sdName(s.id))}</label>`).join('')}</div>
		<div class="crm-wl"><label>${t('Vundet')} → <select class="sk-input sk-input-sm" data-f="won" ${ro}>${opts(g.won)}</select></label><label>${t('Tabt')} → <select class="sk-input sk-input-sm" data-f="lost" ${ro}>${opts(g.lost)}</select></label></div></div>`).join('');
	$('#stAdd').hidden = $('#sgAdd').hidden = $('#sgSave').hidden = !DATA.canWrite;
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
	renderNotifications();
	['follow', 'pipe', 'cal', 'cust', 'act'].forEach(t => { $('#v-' + t).hidden = S.tab !== t; });
	drawNewBtn();
	$('#grpSel').style.visibility = S.tab === 'pipe' ? 'hidden' : '';
	if (S.tab === 'follow') renderFollow();
	if (S.tab === 'pipe') renderPipe();
	syncUrl();
	if (S.tab === 'cal') renderCal();
	if (S.tab === 'cust') renderCust();
	if (S.tab === 'act') renderAct();
	const nb = $('#noteBar'), notes = [];
	if (!ME) notes.push(esc(t('Din bruger er ikke knyttet til en medarbejder: »Mine« virker ikke, dine noter får ingen afsender, og du skal selv vælge, hvem en opfølgning er til. Bed en administrator knytte brugeren under Indstillinger → Brugere.')));
	if (!DATA.emps.length) notes.push(esc(t('Der er ingen medarbejdere under Indstillinger → Stamdata, så opfølgninger kan ikke fordeles.')));
	if (DATA.truncated) notes.push(esc(t('Viser de %s senest aktive kunder. Andre kunder findes med søgningen under »Ny opfølgning« og »Ny sag«.', num(DATA.max))));
	// follow-ups from the old CRM, long overdue: offered closed in one click, never done by itself (R23)
	const old = H.filter(h => h.due && !h.done && diff(h.due) < -90).length;
	if (old >= 3 && DATA.canWrite && localStorage.getItem('crm-oldlate') !== DATA.today) {
		notes.push(esc(t('%s opfølgninger fra det gamle CRM er mere end 90 dage forsinkede.', old)) + ` <button type="button" class="sk-link" data-closeold="1">${t('Markér dem som gjort')}</button> <button type="button" class="sk-link" data-notnow="1">${t('Ikke nu')}</button>`);
	}
	if (DATA.ungrouped && DATA.canWrite) notes.push(esc(DATA.ungrouped === 1 ? t('1 kunde har en status, der ikke er med i en statusgruppe, og vises derfor ikke i CRM.') : t('%s kunder har en status, der ikke er med i en statusgruppe, og vises derfor ikke i CRM.', num(DATA.ungrouped))) + ` <button type="button" class="sk-link" data-opengear="1">${t('Opsæt statusgrupper')}</button>`);
	if (!DATA.canWrite) notes.push(esc(t('Du kan se CRM, men ændringer kræver skriveadgang til Debitor → Konti.')));
	nb.innerHTML = notes.length ? '<div>' + notes.join('<br>') + '</div>' : ''; nb.hidden = !notes.length;   // one text block beside the kit note's edge
	$('#gearBtn').closest('.gear').hidden = !DATA.canWrite;
	const mineBtn = $('#scope').querySelector('[data-scope="mine"]');
	mineBtn.disabled = !ME;
	if (!ME) mineBtn.title = t('Din bruger er ikke knyttet til en medarbejder: »Mine« virker ikke, dine noter får ingen afsender, og du skal selv vælge, hvem en opfølgning er til. Bed en administrator knytte brugeren under Indstillinger → Brugere.');
	drawScope();
}

/* ================= hændelser ================= */
const TXT_WON = () => t('vundet');
document.addEventListener('click', e => {
	const t = e.target;
	const mk = t.closest('a[data-mkdoc]');
	if (mk && ((cust(+mk.dataset.cid) || {}).lead || (e.button === 0 && !e.ctrlKey && !e.metaKey && !e.shiftKey))) { e.preventDefault(); makeDoc(mk); return; }
	const out = t.closest('a[href]');
	if (out && !e.defaultPrevented && !e.ctrlKey && !e.metaKey && !e.shiftKey && e.button === 0) {
		const href = out.getAttribute('href');
		if (href && href.charAt(0) !== '#' && !/^(tel|mailto):/.test(href) && out.target !== '_blank') {
			e.preventDefault();
			if (out.closest('#dlg') && dialogDocumentGuard && !dialogDocumentGuard()) return;
			sendPlace().finally(() => { location.href = href; });
			return;
		}
	}
	if (!t.closest('#menu') && !t.closest('[data-snooze]')) $('#menu').classList.remove('open');
	if (!t.closest('.gear') && !t.closest('#toast')) gearOutside();
	let b;
	if ((b = t.closest('[data-tab]'))) { S.tab = b.dataset.tab; renderAll(); return; }
	if ((b = t.closest('[data-tick]'))) { e.stopPropagation(); toggleDone(+b.dataset.tick, b.closest('.row')); return; }
	if ((b = t.closest('[data-snooze]'))) { e.stopPropagation(); snoozeMenu(+b.dataset.snooze, b); return; }
	if ((b = t.closest('[data-new]'))) { e.stopPropagation(); newFollowUp(b.dataset.new); return; }
	if ((b = t.closest('[data-week]'))) { S.calMode = 'week'; S.weekStart = mondayOf(b.dataset.week); renderCal(); return; }
	if ((b = t.closest('[data-grp]'))) { S.grp = b.dataset.grp; S.stF = b.dataset.stf || 'all'; renderGroups(); renderAll(); return; }
	if ((b = t.closest('[data-stf]'))) { S.stF = b.dataset.stf; renderCust(); return; }
	if ((b = t.closest('[data-bgrp]'))) { S.bgrp = b.dataset.bgrp; renderPipe(); syncUrl(); return; }
	if ((b = t.closest('[data-addmeet]'))) { e.stopPropagation(); e.preventDefault(); meetingDialog(null, b.dataset.cid ? +b.dataset.cid : null); return; }
	if ((b = t.closest('[data-newmeet]'))) { e.stopPropagation(); meetingDialog(null, null, b.dataset.newmeet); return; }
	if (t.closest('#calSub')) { icalDialog(); return; }
	if ((b = t.closest('[data-editlead]'))) { e.preventDefault(); leadDialog(cust(+b.dataset.editlead)); return; }
	if ((b = t.closest('[data-adddeal]'))) { e.stopPropagation(); e.preventDefault(); dealDialog(null, b.dataset.cid ? +b.dataset.cid : null, b.dataset.adddeal || null); return; }
	if ((b = t.closest('[data-editdeal]'))) { const d = DEALS.find(x => x.id === +b.dataset.editdeal); if (d) dealDialog(d, d.cid); return; }
	if ((b = t.closest('[data-mode]'))) { S.calMode = b.dataset.mode; if (S.calMode === 'week') S.weekStart = mondayOf(S.calY === TODAY.getFullYear() && S.calM === TODAY.getMonth() ? DATA.today : key(new Date(S.calY, S.calM, 1))); renderCal(); return; }
	if ((b = t.closest('[data-dtab]'))) { S.dtab = b.dataset.dtab; renderDrawer(); syncUrl(); return; }
	if ((b = t.closest('[data-call]'))) { S.dtab = 'tl'; draft('c', S.drawer).to = +b.dataset.call; renderDrawer(); const n = $('#cNote'); if (n) n.focus(); return; }
	if ((b = t.closest('[data-del]'))) { confirmDel(+b.dataset.del); return; }
	if ((b = t.closest('[data-edit]'))) { editDialog(+b.dataset.edit); return; }
	// NB: in this handler t is the clicked element, not the text function - texts are made in the functions called
	if ((b = t.closest('[data-closeold]'))) { closeOld(); return; }
	if ((b = t.closest('[data-notnow]'))) { localStorage.setItem('crm-oldlate', DATA.today); renderAll(); return; }
	if ((b = t.closest('[data-srvsearch]'))) { serverSearch(); return; }
	if ((b = t.closest('[data-opengear]'))) { e.stopPropagation(); openGear(); return; }
	if ((b = t.closest('[data-starter]'))) { startBoard(); return; }
	if ((b = t.closest('[data-nofu]'))) { S.noFu = !S.noFu; renderPipe(); return; }
	if ((b = t.closest('#showNofu'))) { S.showNofu = !S.showNofu; renderFollow(); return; }
	if ((b = t.closest('[data-fu7]'))) { e.stopPropagation(); followUpIn7(+b.dataset.fu7); return; }
	if ((b = t.closest('[data-wondeal]'))) { e.stopPropagation(); const d = DEALS.find(x => x.id === +b.dataset.wondeal); if (d) moveDeal(d, {state: 'won'}, TXT_WON()); return; }
	if ((b = t.closest('[data-linkdoc]'))) { e.stopPropagation(); linkDoc(+b.dataset.linkdoc, +b.dataset.cid); return; }
	// the row's own follow-up goes with it into the drawer, so the composer offers to close THAT one (R3)
	if ((b = t.closest('[data-open]')) && !t.closest('.drawer')) {
		e.preventDefault();
		const row = b.closest('[data-h]');
		openDrawer(+b.dataset.open, b.dataset.deal ? 'deals' : 'tl', +(b.dataset.h || b.dataset.ev || (row ? row.dataset.h : 0) || 0));
		return;
	}
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
	if (e.key === 'Escape') { if ($('#scrim').classList.contains('open')) closeDlg(); else if ($('#panel').classList.contains('open')) closeGear(); else if (S.drawer) closeDrawer(); $('#menu').classList.remove('open'); }
	if (e.key === 'Enter' && e.target.classList && e.target.classList.contains('body-row')) openDrawer(+e.target.dataset.open);
	if ((e.key === 'n' || e.key === 'N') && DATA.canWrite && !e.ctrlKey && !e.metaKey && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && !$('#scrim').classList.contains('open')) { e.preventDefault(); newForTab(); }
});
$('#dscrim').onclick = closeDrawer;
$('#drawer').addEventListener('click', e => { if (e.target.closest('#dClose')) closeDrawer(); });
$('#drawer').addEventListener('change', e => {
	if (e.target.id === 'stSel') setStatus(cust(S.drawer), e.target.value);
	if (e.target.id === 'ownSel') setOwner(cust(S.drawer), +e.target.value);
});
$('#scrim').onclick = e => { if (e.target.id === 'scrim') closeDlg(); };
$('#notificationsBtn').onclick = notificationsDialog;
// The new-button follows the tab (also key N): Opfølgning -> follow-up, Kalender -> meeting, Sager -> deal (sag),
// Kunder -> new CRM lead, Aktivitet -> note. Offers and orders are made from the customer's drawer.
const NEW_FOR = {follow: 'Ny opfølgning', cal: 'Nyt møde', pipe: 'Ny sag', cust: 'Ny kunde', act: 'Ny note'};
function drawNewBtn() {
	const k = NEW_FOR[S.tab] || 'Ny opfølgning';
	$('#newLbl').textContent = t(k);
	$('#newBtn').title = t(k) + ' (N)';
}
function newForTab() {
	if (S.tab === 'pipe') dealDialog(null, null, null);
	else if (S.tab === 'cust') leadDialog();
	else if (S.tab === 'act') newFollowUp(null, true);
	else if (S.tab === 'cal') meetingDialog(null, null, S.calMode === 'week' && S.weekStart && diff(S.weekStart) > 0 ? S.weekStart : null);
	else newFollowUp();
}
$('#newBtn').onclick = e => { e.stopPropagation(); newForTab(); };
$('#newBtn').hidden = !DATA.canWrite;
$('#q').oninput = e => { S.q = e.target.value.trim(); renderAll(); };
// Enter: one hit opens it; no hit searches all customers on the server (R77)
$('#q').onkeydown = e => {
	if (e.key !== 'Enter' || !S.q) return;
	const hits = C.filter(c => custMatch(c));
	if (hits.length === 1) openDrawer(hits[0].id);
	else if (!hits.length) customerPicker(t('Søg blandt alle kunder'), t('Fundet uden for listen'), c => { closeDlg(); openOutside(c.id); }, S.q);
};
$('#grpSel').onchange = e => { S.grp = e.target.value; S.stF = 'all'; renderAll(); };
// Alle | Mine - same control as the cash journal list (finans/kladdeliste.php): radio group, arrow keys, remembered.
function drawScope() { $('#scope').querySelectorAll('[data-scope]').forEach(b => b.setAttribute('aria-checked', String((b.dataset.scope === 'mine') === S.mine))); }
function setScope(mine) {
	if (mine && !ME) return;
	S.mine = mine; localStorage.setItem('crm-mine', mine ? '1' : '0'); renderAll();
}
$('#scope').addEventListener('click', e => { const b = e.target.closest('[data-scope]'); if (b && !b.disabled) setScope(b.dataset.scope === 'mine'); });
$('#scope').addEventListener('keydown', e => {
	if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') { e.preventDefault(); setScope(!S.mine); $('#scope').querySelector('[aria-checked="true"]').focus(); }
});
$('#gearBtn').onclick = e => {
	e.stopPropagation(); const p = $('#panel'); p.classList.toggle('open'); $('#gearBtn').setAttribute('aria-expanded', p.classList.contains('open'));
	if (p.classList.contains('open')) {
		GDRAFT = GROUPS.map(g => ({id: g.id, n: g.n, s: g.s.slice(), won: g.won, lost: g.lost}));
		SDRAFT = DATA.statuses.map(s => ({id: s.id, n: s.n, used: s.used || 0}));
		renderGroupEditor();
		gearSnap = gearState();
	}
};
let gearSnap = '';
const gearState = () => JSON.stringify([GDRAFT, SDRAFT]);
const closeGear = () => { $('#panel').classList.remove('open'); $('#gearBtn').setAttribute('aria-expanded', 'false'); gearSnap = ''; };
function gearOutside() {
	if (!$('#panel').classList.contains('open')) return;
	if (gearSnap && gearState() !== gearSnap) { toast(t('Gem eller annullér ændringerne i tandhjulet først.')); return; }
	closeGear();
}
$('#sgCancel').onclick = e => { e.stopPropagation(); closeGear(); };
$('#sgList').addEventListener('input', e => {
	const box = e.target.closest('.sg'); if (!box) return;
	const g = GDRAFT[+box.dataset.i];
	if (e.target.dataset.f === 'n') g.n = e.target.value;
	if (e.target.dataset.f === 'won' || e.target.dataset.f === 'lost') g[e.target.dataset.f] = e.target.value;
	// a group's statuses follow the status list's order (new ones are last there), not the order they were ticked (R45)
	if (e.target.dataset.st !== undefined) { const id = e.target.dataset.st; g.s = e.target.checked ? g.s.concat(id) : g.s.filter(x => x !== id); g.s.sort(bySdraft); }
});
const bySdraft = (a, b) => SDRAFT.findIndex(x => x.id === a) - SDRAFT.findIndex(x => x.id === b);
const swap = (arr, i, j) => { if (j < 0 || j >= arr.length) return; const x = arr[i]; arr[i] = arr[j]; arr[j] = x; };
$('#sgList').addEventListener('click', e => {
	let b;
	if ((b = e.target.closest('[data-delg]'))) { e.stopPropagation(); GDRAFT.splice(+b.dataset.delg, 1); renderGroupEditor(); }
	else if ((b = e.target.closest('[data-gup]'))) { e.stopPropagation(); swap(GDRAFT, +b.dataset.gup, +b.dataset.gup - 1); renderGroupEditor(); }
	else if ((b = e.target.closest('[data-gdown]'))) { e.stopPropagation(); swap(GDRAFT, +b.dataset.gdown, +b.dataset.gdown + 1); renderGroupEditor(); }
	else if ((b = e.target.closest('[data-starter]'))) { e.stopPropagation(); $('#panel').classList.remove('open'); startBoard(); }
});
$('#stList').addEventListener('input', e => { const r = e.target.closest('.strow'); if (r && e.target.dataset.sf === 'n') SDRAFT[+r.dataset.si].n = e.target.value; });
// a renamed status shows up in the groups' chips when the field is left
$('#stList').addEventListener('change', () => renderGroupEditor());
$('#stList').addEventListener('click', e => {
	const mv = e.target.closest('[data-sup],[data-sdown]');
	if (mv) { e.stopPropagation(); const i = +(mv.dataset.sup ?? mv.dataset.sdown); swap(SDRAFT, i, mv.dataset.sup !== undefined ? i - 1 : i + 1); GDRAFT.forEach(g => g.s.sort(bySdraft)); renderGroupEditor(); return; }
	const b = e.target.closest('[data-dels]'); if (!b || b.disabled) return;
	e.stopPropagation();
	const id = SDRAFT[+b.dataset.dels].id;
	SDRAFT.splice(+b.dataset.dels, 1);
	GDRAFT.forEach(g => { g.s = g.s.filter(x => x !== id); if (g.won === id) g.won = ''; if (g.lost === id) g.lost = ''; });
	renderGroupEditor();
});
$('#stAdd').onclick = e => {
	e.stopPropagation();
	SDRAFT.push({id: 'new:' + (++newSeq), n: '', used: 0});
	renderGroupEditor();
	const ins = $('#stList').querySelectorAll('input'); if (ins.length) ins[ins.length - 1].focus();
};
$('#sgAdd').onclick = e => { e.stopPropagation(); GDRAFT.push({id: 0, n: t('Ny gruppe'), s: [], won: '', lost: ''}); renderGroupEditor(); };
$('#sgSave').onclick = async e => {
	e.stopPropagation();
	if (SDRAFT.some(s => !s.n.trim())) { toast(t('Giv alle statusser et navn.')); return; }
	GDRAFT.forEach(g => g.s.sort(bySdraft));
	await api('save_groups', {groups: GDRAFT, statuses: SDRAFT.map(s => ({id: s.id, n: s.n.trim()})), rev: DATA.rev});
	gearSnap = '';
	renderGroups(); $('#panel').classList.remove('open'); toast(t('Statusser og statusgrupper er gemt'));
};

/* træk og slip: kalender (opfølgninger) og tavle (kunder/sager) */
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
		const r = await api('set_due', {id: h.id, due: d.dataset.day});
		toast(t('%s flyttet til %s', cust(h.cid).n, fmt(d.dataset.day)), () => api('set_due', {id: h.id, due: old}), resendOffer(h.id, r));
		return;
	}
	if (dragCard === null) return;
	const z = e.target.closest('[data-col],[data-end]'); if (!z) return; e.preventDefault();
	const id = dragCard; dragCard = null; document.body.classList.remove('carddrag');
	const g = boardGroup(), c = cust(+id); if (!c) return;
	dropOnBoard(c, g, z.dataset.col, z.dataset.end);
});

/* ================= start ================= */
load(DATA);
let loadedAt = Date.now();
document.addEventListener('visibilitychange', async () => {
	if (document.visibilityState !== 'visible') return;
	if (DATA.today === key(new Date()) && Date.now() - loadedAt < 5 * 60000) return;
	const my = ++seqNo;
	try {
		const r = await fetch(DATA.api, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': DATA.csrf}, body: JSON.stringify({action: 'data'})}).then(x => x.json());
		if (r && r.ok && r.data && my > applied) { applied = my; loadedAt = Date.now(); load(r.data); redraw(); }
	} catch (e) { /* offline: keep what is shown */ }
});
S.calY = TODAY.getFullYear(); S.calM = TODAY.getMonth(); S.bgrp = GROUPS.length ? GROUPS[0].id : 0;
if (!ME) S.mine = false;
// place from the query (crm.php?tab=pipe&bgrp=25&drawer=4&dtab=deals); the old #pipe / #drawer=4 links still work
const hs = location.hash.slice(1);
const qTab = QP0.get('tab') || hs;
if (/^(follow|pipe|cal|cust|act)$/.test(qTab)) S.tab = qTab;
if (QP0.get('bgrp') && GROUPS.some(g => String(g.id) === QP0.get('bgrp'))) S.bgrp = QP0.get('bgrp');
if (QP0.get('grp') && GROUPS.some(g => String(g.id) === QP0.get('grp'))) S.grp = QP0.get('grp');
{ const cal = QP0.get('cal') || '', w = cal.match(/^w(\d{4}-\d{2}-\d{2})$/), m = cal.match(/^m(\d{4})-(\d{1,2})$/);
	if (w) { S.calMode = 'week'; S.weekStart = w[1]; } else if (m) { S.calY = +m[1]; S.calM = Math.min(11, Math.max(0, +m[2] - 1)); } }
renderGroups();
renderAll();
const hm = hs.match(/^drawer=(\d+)(?:-(\w+))?$/);
const qDrawer = +(QP0.get('drawer') || (hm ? hm[1] : 0));
if (qDrawer) openOutside(qDrawer, QP0.get('dtab') || (hm && hm[2]) || 'tl');
})();
