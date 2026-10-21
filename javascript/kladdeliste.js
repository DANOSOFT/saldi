// finans/kladdeliste.php – journal list (20261020 ASR). Rows come from kladdeliste.php with POST kl=list.
(function () {
  "use strict";
  const T = KL.texts, P = KL.prefs;
  const $ = id => document.getElementById(id);
  const TABS = ["open", "sim", "post", "all"];
  // columns per tab – only what matters for that job
  const C = {
    nr:     { t: T.cols.nr,     w: "56px",  sort: "id",     filt: true },
    note:   { t: T.cols.note,   w: "minmax(220px,1fr)", sort: "note", filt: true },
    dato:   { t: T.cols.dato,   w: "96px",  sort: "dato",   filt: true, date: true },
    ejer:   { t: T.cols.ejer,   w: "112px", sort: "ejer",   filt: true, opt: true },
    linjer: { t: T.cols.linjer, w: "56px",  sort: "linjer", a: "kl-r", opt: true },
    inuse:  { t: "",            w: "160px" },
    bogf:   { t: T.cols.bogf,   w: "96px",  sort: "bogf",   filt: true, date: true },
    sim:    { t: T.cols.sim,    w: "96px",  sort: "bogf",   filt: true, date: true, fkey: "bogf" },
    status: { t: T.cols.status, w: "150px", sort: "bogf" },
    af:     { t: T.cols.af,     w: "112px", sort: "af",     filt: true, opt: true },
    act:    { t: "",            w: "28px" }
  };
  const LAYOUT = {
    open: ["nr", "note", "dato", "ejer", "linjer", "inuse", "act"],
    sim:  ["nr", "note", "dato", "ejer", "linjer", "sim", "af"],
    post: ["nr", "note", "dato", "ejer", "linjer", "bogf", "af"],
    all:  ["nr", "note", "dato", "ejer", "linjer", "status", "af", "act"]
  };

  /* ---------- state (restored when coming back from a journal in the same tab) ---------- */
  const KEY = "kladdeliste.view";
  let saved = {};
  try { saved = JSON.parse(sessionStorage.getItem(KEY) || "{}"); } catch (e) { saved = {}; }
  const S = {
    tab: TABS.includes(saved.tab) ? saved.tab : "auto",
    scope: P.scope,
    f: saved.f && typeof saved.f === "object" ? saved.f : {},
    sort: saved.sort || null, dir: saved.dir || "asc",
    page: saved.page || 1, pp: P.pp,
    cols: Object.assign({}, P.cols)
  };
  let data = null, size = "w", focusId = saved.focusId || null, reqNo = 0;
  const persist = () => { try { sessionStorage.setItem(KEY, JSON.stringify({ tab: S.tab, f: S.f, sort: S.sort, dir: S.dir, page: S.page, focusId })); } catch (e) {} };

  /* ---------- helpers ---------- */
  const esc = s => String(s == null ? "" : s).replace(/[&<>"]/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));
  const nf = n => Number(n).toLocaleString(document.documentElement.lang === "en" ? "en-GB" : "da-DK");
  const fmt = (s, ...a) => a.reduce((r, v) => r.replace("%s", v), s);
  const hl = (s, term) => {
    s = esc(s); term = (term || "").trim(); if (!term || term.includes(":")) return s;
    const i = s.toLowerCase().indexOf(esc(term).toLowerCase());
    return i < 0 ? s : s.slice(0, i) + "<mark>" + s.slice(i, i + esc(term).length) + "</mark>" + s.slice(i + esc(term).length);
  };
  const toastEl = $("klToast"); let tt;
  const toast = m => { toastEl.textContent = m; toastEl.classList.add("show"); clearTimeout(tt); tt = setTimeout(() => toastEl.classList.remove("show"), 3200); };
  const post = (body, asBlob) => fetch("kladdeliste.php", {
    method: "POST", credentials: "same-origin",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams(body).toString()
  }).then(r => { if (!r.ok) throw new Error(r.status); return asBlob ? r.blob() : r.json(); });
  const listParams = extra => {
    const p = Object.assign({ tab: S.tab, scope: S.scope, sort: S.sort || "", dir: S.dir, page: S.page, pp: S.pp }, extra || {});
    Object.keys(S.f).forEach(k => { if (S.f[k]) p["f[" + k + "]"] = S.f[k]; });
    return p;
  };
  const cols = () => LAYOUT[data ? data.tab : "open"].filter(c => !C[c].opt || (S.cols[c] && !(size === "n" && (c === "ejer" || c === "af"))));
  const fkey = c => C[c].fkey || c;

  /* ---------- load + draw ---------- */
  function load() {
    const my = ++reqNo;
    $("kl").classList.add("kl-loading");
    return post(Object.assign({ kl: "list" }, listParams())).then(d => {
      if (my !== reqNo) return;
      data = d; S.tab = d.tab; S.page = d.page;
      persist(); draw();
    }).catch(() => { if (my === reqNo) toast(T.loadError); })
      .finally(() => { if (my === reqNo) $("kl").classList.remove("kl-loading"); });
  }

  const LOCK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>';
  const TRASH = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>';
  function cell(c, k) {
    const t = S.f[fkey(c)] || "";
    switch (c) {
      case "nr":     return `<span class="c-nr kl-num">${hl(k.id, t)}</span>`;
      case "note":   return k.note ? `<span class="c-note" title="${esc(k.note)}">${hl(k.note, t)}</span>` : `<span class="c-3">–</span>`;
      case "dato":   return `<span class="c-2 kl-num">${esc(k.dato)}</span>`;
      case "ejer":   return `<span class="c-2">${hl(k.ejer, t)}</span>`;
      case "linjer": return `<span class="kl-r kl-num ${k.linjer ? "c-2" : "c-3"}">${k.linjer}</span>`;
      case "inuse":  return k.laast ? `<span class="kl-state" title="${esc(T.lockTip)}">${LOCK}${esc(T.inUse)} ${esc(k.laast)}</span>` : "<span></span>";
      case "bogf":
      case "sim":    return `<span class="c-2 kl-num">${esc(k.bogf)}</span>`;
      case "status":
        if (k.laast) return `<span class="kl-state" title="${esc(T.lockTip)}">${LOCK}${esc(T.inUse)} ${esc(k.laast)}</span>`;
        if (k.g === "open") return `<span class="c-3">${esc(T.notPosted)}</span>`;
        if (k.g === "sim") return `<span class="kl-state">${esc(T.simulated)} ${esc(k.bogf.slice(0, 5))}</span>`;
        return `<span class="c-2 kl-num">${esc(T.posted)} ${esc(k.bogf.slice(0, 5))}</span>`;
      case "af":     return `<span class="c-2">${hl(k.af, t)}</span>`;
      case "act":    return k.g === "open" && !k.linjer && !k.laast
        ? `<button type="button" class="kl-trash" data-del="${k.id}" title="${esc(T.deleteTip)}" aria-label="${esc(T.deleteTip)} ${k.id}">${TRASH}</button>` : "<span></span>";
    }
    return "<span></span>";
  }

  function draw() {
    const d = data, vc = cols();
    $("klTabs").innerHTML = TABS.map(k =>
      `<button type="button" class="kl-tab ${d.counts[k] ? "" : "zero"}" role="tab" aria-selected="${d.tab === k}" tabindex="${d.tab === k ? 0 : -1}" data-tab="${k}">${esc(T.tabs[k])}<span class="n">${nf(d.counts[k])}</span></button>`).join("");

    $("klTbl").style.setProperty("--cols", vc.map(c => C[c].w).join(" "));
    $("klThead").innerHTML = vc.map(c => { const x = C[c];
      return x.sort && x.t ? `<span class="${x.a || ""}"><span class="s ${S.sort === x.sort ? "on" : ""}" data-sort="${x.sort}" role="button" tabindex="0">${esc(x.t)}<i>${S.sort === x.sort && S.dir === "desc" ? "↓" : "↑"}</i></span></span>` : `<span>${esc(x.t)}</span>`; }).join("");

    // the search row is only rebuilt when the columns change, so typing keeps focus
    const fr = $("klFrow"), fk = vc.join();
    if (fr.dataset.k !== fk) {
      fr.dataset.k = fk;
      fr.innerHTML = vc.map(c => C[c].filt
        ? `<input class="kl-fld ${C[c].a || ""}" data-f="${fkey(c)}" placeholder="${esc(T.search)}" ${C[c].date ? `data-date="1" title="${esc(T.dateHint)}"` : ""} aria-label="${esc(T.search)}: ${esc(C[c].t)}" autocomplete="off">`
        : "<span></span>").join("");
    }
    fr.querySelectorAll("[data-f]").forEach(i => { i.classList.toggle("set", !!S.f[i.dataset.f]); if (document.activeElement !== i) i.value = S.f[i.dataset.f] || ""; });

    const body = $("klBody"), anyF = Object.values(S.f).some(Boolean);
    if (d.rows.length) {
      body.innerHTML = d.rows.map(k => `<div class="kl-row" role="row" tabindex="-1" data-id="${k.id}">${vc.map(c => cell(c, k)).join("")}</div>`).join("");
      (body.querySelector(`.kl-row[data-id="${focusId}"]`) || body.firstElementChild).tabIndex = 0;
    } else {
      const name = T.tabs[d.tab].toLowerCase(), other = ["open", "sim", "post"].find(k => k !== d.tab && d.counts[k]);
      let msg, act = "";
      if (anyF) { msg = `<b>${esc(fmt(T.noMatch, name))}</b>`; act = other && d.tab !== "all" ? `<button type="button" data-goto="${other}">${esc(fmt(T.seeIn, nf(d.counts[other]), T.tabs[other]))}</button>` : `<button type="button" data-clear>${esc(T.clearAll)}</button>`; }
      else if (S.scope === "egne") { msg = `<b>${esc(fmt(T.noneMine, name))}</b>${esc(T.mineHint)}`; act = `<button type="button" data-all>${esc(T.showAll)}</button>`; }
      else if (d.tab === "open") { msg = `<b>${esc(T.noneOpen)}</b>${esc(T.allPosted)}`; act = `<button type="button" data-new>${esc(T.createNew)}</button>`; }
      else msg = `<b>${esc(fmt(T.none, name))}</b>`;
      body.innerHTML = `<div class="kl-empty">${msg}${act ? "<br>" + act : ""}</div>`;
    }

    const from = d.total ? (d.page - 1) * S.pp + 1 : 0, to = Math.min(d.page * S.pp, d.total);
    $("klCount").innerHTML = (d.total ? (d.pages > 1 ? `${nf(from)}–${nf(to)} ${esc(T.of)} ${nf(d.total)}` : `${nf(d.total)} ${esc(d.total > 1 ? T.journals : T.journal)}`) : "")
      + (S.scope === "egne" ? `${d.total ? " · " : ""}<span class="kl-scopenote">${esc(T.onlyMine)}</span>` : "");
    const pg = [];
    if (d.pages > 1) {
      const add = p => pg.push(`<button type="button" data-p="${p}" class="${p === d.page ? "on" : ""}">${p}</button>`);
      pg.push(`<button type="button" data-p="${d.page - 1}" ${d.page === 1 ? "disabled" : ""} aria-label="${esc(T.prev)}">‹</button>`);
      add(1); if (d.page > 3) pg.push("<span>…</span>");
      for (let p = Math.max(2, d.page - 1); p <= Math.min(d.pages - 1, d.page + 1); p++) add(p);
      if (d.page < d.pages - 2) pg.push("<span>…</span>"); add(d.pages);
      pg.push(`<button type="button" data-p="${d.page + 1}" ${d.page === d.pages ? "disabled" : ""} aria-label="${esc(T.next)}">›</button>`);
    }
    $("klPager").innerHTML = pg.join("");
    $("klPP").style.visibility = d.total > 50 ? "visible" : "hidden";
    $("klClear").hidden = !anyF;
    drawScope(); drawPanel();
  }
  function drawScope() { $("klScope").querySelectorAll("[data-scope]").forEach(b => b.setAttribute("aria-checked", b.dataset.scope === S.scope)); }
  function drawPanel() {
    $("klPCols").innerHTML = ["nr", "note", "dato", "ejer", "linjer", "af"].map(c => C[c].opt
      ? `<button type="button" class="kl-sw" role="switch" aria-checked="${!!S.cols[c]}" data-col="${c}"><span>${esc(c === "af" ? T.cols.afLong : C[c].t)}${size === "n" && (c === "ejer" || c === "af") && S.cols[c] ? `<small class="kl-swn">${esc(T.hiddenNarrow)}</small>` : ""}</span><i></i></button>`
      : `<button type="button" class="kl-sw" role="switch" aria-checked="true" disabled><span>${esc(C[c].t)}<small class="kl-swn">${esc(T.always)}</small></span><i></i></button>`).join("");
  }

  /* ---------- events ---------- */
  const setTab = k => { S.tab = k; S.page = 1; S.sort = null; focusId = null; load(); };
  $("klTabs").addEventListener("click", e => { const b = e.target.closest("[data-tab]"); if (b) setTab(b.dataset.tab); });
  $("klTabs").addEventListener("keydown", e => {
    if (e.key !== "ArrowLeft" && e.key !== "ArrowRight") return;
    e.preventDefault();
    const i = TABS.indexOf(S.tab), k = TABS[(i + (e.key === "ArrowRight" ? 1 : -1) + TABS.length) % TABS.length];
    setTab(k); setTimeout(() => document.querySelector(`[data-tab="${k}"]`)?.focus(), 150);
  });

  const scope = $("klScope");
  const setScope = (v, say) => {
    if (v === S.scope) return;
    S.scope = v; S.page = 1; drawScope(); load();
    post({ kl: "pref", scope: v }).catch(() => {});
  };
  scope.addEventListener("click", e => { const b = e.target.closest("[data-scope]"); if (b) setScope(b.dataset.scope); });
  scope.addEventListener("keydown", e => { if (e.key === "ArrowLeft" || e.key === "ArrowRight") { e.preventDefault(); setScope(S.scope === "egne" ? "alle" : "egne"); scope.querySelector('[aria-checked="true"]').focus(); } });

  const fr = $("klFrow"); let ft;
  const setF = (k, v) => { v = v.trim(); if ((S.f[k] || "") === v) return; if (v) S.f[k] = v; else delete S.f[k]; S.page = 1; load(); };
  // text fields search while typing; date fields on Enter or when leaving (a from:to period must be finished first)
  fr.addEventListener("input", e => { const i = e.target; if (!i.dataset.f || i.dataset.date) return; clearTimeout(ft); ft = setTimeout(() => setF(i.dataset.f, i.value), 250); });
  fr.addEventListener("change", e => { if (e.target.dataset.f) { clearTimeout(ft); setF(e.target.dataset.f, e.target.value); } });
  fr.addEventListener("keydown", e => {
    if (e.key === "Enter") { e.preventDefault(); clearTimeout(ft); setF(e.target.dataset.f, e.target.value); }
    if (e.key === "Escape" && e.target.value) { e.target.value = ""; setF(e.target.dataset.f, ""); }
    if (e.key === "ArrowDown") { e.preventDefault(); $("klBody").querySelector(".kl-row")?.focus(); }
  });
  $("klClear").onclick = () => { S.f = {}; S.page = 1; load(); fr.querySelector("input")?.focus(); };

  const sortBy = s => { if (S.sort !== s) { S.sort = s; S.dir = "asc"; } else if (S.dir === "asc") S.dir = "desc"; else S.sort = null; S.page = 1; load(); };
  $("klThead").addEventListener("click", e => { const s = e.target.closest("[data-sort]"); if (s) sortBy(s.dataset.sort); });
  $("klThead").addEventListener("keydown", e => { const s = e.target.closest("[data-sort]"); if (s && (e.key === "Enter" || e.key === " ")) { e.preventDefault(); sortBy(s.dataset.sort); } });

  const url = id => KL.open.replace(/%d/g, id);
  const open = (id, newTab) => { focusId = id; persist(); if (newTab) window.open(url(id), "_blank"); else location.href = url(id); };
  const body = $("klBody");
  body.addEventListener("click", e => {
    const g = e.target.closest("[data-goto]"); if (g) return setTab(g.dataset.goto);
    if (e.target.closest("[data-clear]")) return $("klClear").click();
    if (e.target.closest("[data-all]")) return setScope("alle");
    if (e.target.closest("[data-new]")) return $("create-new").click();
    const d = e.target.closest("[data-del]"); if (d) { e.stopPropagation(); return askDelete(+d.dataset.del); }
    const r = e.target.closest(".kl-row[data-id]"); if (r) open(+r.dataset.id, e.ctrlKey || e.metaKey);
  });
  body.addEventListener("auxclick", e => { const r = e.target.closest(".kl-row[data-id]"); if (r && e.button === 1) open(+r.dataset.id, true); });
  body.addEventListener("keydown", e => {
    const r = e.target.closest(".kl-row[data-id]"); if (!r || e.target !== r) return;
    const go = el => { if (!el || !el.dataset.id) return; r.tabIndex = -1; el.tabIndex = 0; el.focus(); focusId = +el.dataset.id; };
    if (e.key === "ArrowDown") { e.preventDefault(); go(r.nextElementSibling); }
    if (e.key === "ArrowUp") { e.preventDefault(); r.previousElementSibling ? go(r.previousElementSibling) : fr.querySelector("input")?.focus(); }
    if (e.key === "Enter") open(+r.dataset.id, e.ctrlKey || e.metaKey);
    if (e.key === "Delete") { const d = r.querySelector("[data-del]"); if (d) askDelete(+d.dataset.del); }
  });
  $("klPager").addEventListener("click", e => { const p = e.target.closest("[data-p]"); if (!p || p.disabled) return; S.page = +p.dataset.p; load().then(() => { $("klTbl").scrollTop = 0; }); });
  $("klPP").innerHTML = [50, 100, 250, 500].map(n => `<option value="${n}" ${n === S.pp ? "selected" : ""}>${esc(fmt(T.perPage, n))}</option>`).join("");
  $("klPP").onchange = e => { S.pp = +e.target.value; S.page = 1; load(); post({ kl: "pref", pp: S.pp }).catch(() => {}); };

  /* delete – the existing endpoint at the top of kladdeliste.php */
  const scrim = $("klScrim"), dlg = $("klDlg");
  function askDelete(id) {
    dlg.innerHTML = `<h3 id="klDlgT">${esc(fmt(T.deleteQ, id))}</h3><p>${esc(T.deleteBody)}</p><footer><button type="button" class="b" id="klNo">${esc(T.cancel)}</button><button type="button" class="b danger" id="klYes">${esc(T.delete)}</button></footer>`;
    scrim.classList.add("open"); $("klNo").focus();
    $("klNo").onclick = () => scrim.classList.remove("open");
    $("klYes").onclick = () => {
      $("klYes").disabled = true;
      post({ delete_kladde: id }).then(r => {
        scrim.classList.remove("open");
        if (r.status === "deleted") { toast(fmt(T.deleted, id)); load(); }
        else { toast(r.message || r.status); load(); }
      }).catch(() => { scrim.classList.remove("open"); toast(T.loadError); });
    };
  }
  scrim.addEventListener("click", e => { if (e.target === scrim) scrim.classList.remove("open"); });

  /* gear panel */
  const gear = $("klGear"), panel = $("klPanel");
  const setPanel = o => { panel.classList.toggle("open", o); gear.setAttribute("aria-expanded", o); };
  gear.onclick = e => { e.stopPropagation(); setPanel(!panel.classList.contains("open")); };
  panel.addEventListener("click", e => {
    const c = e.target.closest("[data-col]");
    if (c) { const k = c.dataset.col; S.cols[k] = !S.cols[k]; draw(); post({ kl: "pref", col: k, on: S.cols[k] ? "1" : "0" }).catch(() => {}); return; }
    const x = e.target.closest("[data-exp]");
    if (x && x.dataset.exp === "csv") {
      setPanel(false);
      post(Object.assign({ kl: "csv" }, listParams()), true).then(b => {
        const a = document.createElement("a"); a.href = URL.createObjectURL(b); a.download = "kassekladder.csv";
        document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 2000);
      }).catch(() => toast(T.loadError));
    }
    if (x && x.dataset.exp === "pdf") { setPanel(false); setTimeout(() => window.print(), 50); }
  });
  $("klReset").onclick = () => {
    setPanel(false);
    post({ kl: "pref", reset: "1" }).then(r => {
      Object.assign(S.cols, r.prefs.cols); S.pp = r.prefs.pp; S.scope = r.prefs.scope; S.sort = null; S.f = {}; S.page = 1;
      $("klPP").value = String(S.pp); load(); toast(T.resetDone);
    }).catch(() => toast(T.loadError));
  };
  document.addEventListener("click", e => { if (!e.target.closest(".kl-cols")) setPanel(false); });
  document.addEventListener("keydown", e => { if (e.key === "Escape") { setPanel(false); scrim.classList.remove("open"); } });

  /* inside the shell (index/main.php) the tour sits under Assist, so the page's own help icon hides */
  if (window.parent && window.parent !== window) document.documentElement.classList.add("kl-in-shell");

  /* width → density */
  const kl = $("kl");
  const fit = () => {
    const ns = kl.clientWidth < 900 ? "n" : "w";
    if (ns !== size) { size = ns; kl.dataset.size = ns; if (data) draw(); }
  };
  new ResizeObserver(fit).observe(kl);
  fit();
  load();
})();
