// ---- javascript/saldi-help-qmark.js --- ver 5.1.0 --- 2026.11.14 ---
// Copyright (c) 2026 Danosoft ApS. Licensed under the GNU General Public License, version 2 or later.
//
// The topbar's Hjælp disc shows the Saldi AI symbol as a question mark. When the chat opens, the "?"'s dot falls down
// into the chat's header seat, swelling on the way, and the symbol's stroke is drawn around it where it lands; when the
// chat closes, the dot rises back into the "?". The chat's loader still runs its own flight (it moves the symbol and tells the chat); that
// flyer is only made invisible, and this draws the flight instead.
// The topbar's own symbol stays in the disc, hidden, because javascript/saldi-help.js clones it for the panel and its
// rows; the "?" is a second svg beside it with the same classes, so the state rings (thinking, answering) still work.
// Loaded after saldi-help.js, only where the help panel is on. prefers-reduced-motion: the "?" swaps without a flight.
//
// 20261113 ASR Created from the prototype _prototypes/hjaelp-spoergsmaal (variant A, "Vender i flugten").
// 20261113 ASR Variant C, "Prikken falder" (the owner's choice), instead of A.
// 20261114 ASR While a conversation is minimised (saldi:chat:close with minimized), the Hjælp disc has a small dot and the
//               button says "Fortsæt samtalen" (data-txt-resume, text 9502); any open takes both away again.
(function () {
  'use strict';
  if (window.SaldiHelpQmark) { return; }
  const W = window, D = document;
  const reduce = W.matchMedia && W.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ---- geometry (viewBox 6 14 34 42, as topbar_help_symbol() in index/mainIncludes/topbar.php) ----
  const S = { p: [[35.6, 18.9], [17.9, 37.8], [16.4, 39.5], [15.9, 40.9], [15.9, 43.2], [15.9, 44.6], [15.9, 49], [19.4, 51], [23.4, 51], [27.6, 51], [30.9, 49], [30.9, 46.4]], dot: [13.5, 28.8, 5.808] };
  const Q = { p: [[23, 39.4], [23, 36.4], [23, 32.4], [31, 31.2], [31, 25.4], [31, 24.8], [31, 20.2], [27.4, 17.4], [23, 17.4], [18.6, 17.4], [15, 20], [15, 24.4]], dot: [23, 50.2, 4.3] };
  function lerp(a, b, t) { return a + (b - a) * t; }
  function f(n) { return Math.round(n * 100) / 100; }
  function pd(p) {
    const q = p.map(function (x) { return f(x[0]) + ' ' + f(x[1]); });
    return 'M' + q[0] + ' L' + q[1] + ' C' + q[2] + ' ' + q[3] + ' ' + q[4] + ' L' + q[5] + ' C' + q[6] + ' ' + q[7] + ' ' + q[8] + ' C' + q[9] + ' ' + q[10] + ' ' + q[11];
  }
  const GQ = { d: pd(Q.p), dot: Q.dot }, GS = { d: pd(S.p), dot: S.dot };
  function ease(t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; }
  function easeIn(t) { return t * t * t; }
  function easeOut(t) { return 1 - Math.pow(1 - t, 3); }
  // a viewBox point -> px offset from the disc's centre (glyph box = disc/2 x disc*0.625, centred)
  function off(pt, size) { return [(pt[0] - 23) * size / 68, (pt[1] - 35) * size / 67.2]; }

  // ---- CSS ----
  const css = D.createElement('style');
  css.textContent = [
    'html.qm-chat [data-saldi-assist-flyer], html.qm-chat .saldi-help-flyer { visibility: hidden !important; }',
    '.topbar-help .ai-avatar.qm-on > svg:not(.qm-top) { display: none; }',
    '.topbar-help .ai-avatar svg.qm-top { transition: opacity .18s; }',
    'html.qm-away .topbar-help .ai-avatar svg.qm-top { opacity: .3 !important; }',
    'html.qm-away .topbar-help .ai-avatar svg.qm-top .sa-dot { opacity: 0; }',
    '.topbar-help .ai-avatar.qm-on { position: relative; }',
    'html.qm-min .topbar-help .ai-avatar.qm-on::after { content: ""; position: absolute; top: -2px; right: -2px; width: 9px; height: 9px; border-radius: 50%; background: #7A2CBC; box-shadow: 0 0 0 2px #1f4373; }',
    '.qm-fly { position: fixed; left: 0; top: 0; z-index: 10003; pointer-events: none; display: flex; align-items: center; justify-content: center; border-radius: 999px; box-sizing: border-box; will-change: transform; }',
    '.qm-fly.qm-disc { background: #F7F4EF; border: 1px solid #E3DCD2; }',
    '.qm-fly svg { display: block; overflow: visible; }',
    '.qm-fly .sa-stroke, .qm-fly .sa-track { fill: none; stroke-width: 5.55; stroke-linecap: round; stroke-linejoin: round; }',
    '.qm-fly .sa-track { stroke: #EDE8E1; opacity: 0; }'
  ].join('\n');
  D.head.appendChild(css);

  let uid = 0;
  function glyphSvg(g, cls, withRings) {
    const id = 'qm-g-' + (++uid);
    const svg = D.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '6 14 34 42');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    if (cls) { svg.setAttribute('class', cls); }
    const dx = g.dot[0], dy = g.dot[1];
    svg.innerHTML = '<defs><linearGradient id="' + id + '" gradientUnits="userSpaceOnUse" x1="10" y1="16" x2="38" y2="54"><stop offset="0" stop-color="#1A66B8"></stop><stop offset="1" stop-color="#7A2CBC"></stop></linearGradient></defs>' +
      (withRings ? '<circle class="sa-glow" cx="' + dx + '" cy="' + dy + '" r="11.4"></circle><circle class="sa-halo" cx="' + dx + '" cy="' + dy + '" r="8.6"></circle><circle class="sa-ring sa-ring--outer" cx="' + dx + '" cy="' + dy + '" r="11.2"></circle><circle class="sa-ring sa-ring--inner" cx="' + dx + '" cy="' + dy + '" r="8.4"></circle>' : '') +
      '<path class="sa-track" d="' + g.d + '"></path>' +
      '<path class="sa-stroke" pathLength="100" stroke="url(#' + id + ')" d="' + g.d + '"></path>' +
      '<circle class="sa-dot" cx="' + dx + '" cy="' + dy + '" r="' + g.dot[2] + '" fill="url(#' + id + ')"></circle>';
    return { svg: svg, track: svg.querySelector('.sa-track'), stroke: svg.querySelector('.sa-stroke'), dot: svg.querySelector('.sa-dot') };
  }
  function sizeSvg(svg, disc) { svg.style.width = disc / 2 + 'px'; svg.style.height = disc * 0.625 + 'px'; }

  // ---- the topbar: the "?" beside the hidden symbol ----
  function topSpan() { return D.querySelector('.topbar-help [data-saldi-assist-symbol]'); }
  function ensureTop() {
    const sp = topSpan();
    if (!sp) { return null; }
    if (!sp.querySelector('svg.qm-top')) { sp.appendChild(glyphSvg(GQ, 'qm-top', true).svg); }
    sp.classList.add('qm-on');
    return sp;
  }
  function centre(el) { const r = el.getBoundingClientRect(); return [r.left + r.width / 2, r.top + r.height / 2]; }
  function seat() {   // the chat's header seat at the panel's resting box (docked: the window's right edge)
    const fr = D.querySelector('iframe[src*="/chaty-guide/embed"]');
    if (!fr) { return null; }
    const r = fr.getBoundingClientRect();
    return [Math.min(r.left, W.innerWidth - r.width) + 32, r.top + 28];
  }
  function setAway(on) { D.documentElement.classList.toggle('qm-away', !!on); }
  function popTop() {   // the dot is back in the "?"
    const sp = topSpan(), el = sp && sp.querySelector('svg.qm-top .sa-dot');
    if (el && el.animate && !reduce) { el.animate([{ transform: 'scale(1.18)' }, { transform: 'scale(1)' }], { duration: 320, easing: 'cubic-bezier(.2,.8,.2,1)' }); }
  }

  // ---- the flight ----
  let run = 0, busy = false, nodes = [];
  function clear() { nodes.forEach(function (n) { n.remove(); }); nodes = []; }
  function anim(ms, my, step) {
    return new Promise(function (res) {
      const t0 = performance.now();
      function tick(now) {
        if (my !== run) { res(false); return; }
        const p = Math.min(1, (now - t0) / ms);
        step(p);
        if (p < 1) { requestAnimationFrame(tick); } else { res(true); }
      }
      requestAnimationFrame(tick);
    });
  }
  function disc(cls) { const d = D.createElement('div'); d.className = 'qm-fly ' + (cls || ''); d.setAttribute('aria-hidden', 'true'); D.body.appendChild(d); nodes.push(d); return d; }
  function place(el, c, size) { el.style.width = el.style.height = size + 'px'; el.style.transform = 'translate(' + f(c[0] - size / 2) + 'px,' + f(c[1] - size / 2) + 'px)'; }
  function dur(a, b) { const dx = b[0] - a[0], dy = b[1] - a[1]; return Math.max(360, Math.min(640, 2 * Math.sqrt(dx * dx + dy * dy))); }
  function dash(el, o) { el.stroke.style.strokeDasharray = '100 200'; el.stroke.style.strokeDashoffset = String(o); }
  // a cover over the chat's own header seat, so its symbol does not show before ours has landed
  function cover(my) {
    const c = disc('qm-disc');
    (function follow() { if (my !== run || !c.isConnected) { return; } const s = seat(); if (s) { place(c, s, 32); } requestAnimationFrame(follow); })();
    return c;
  }
  function dotEl() {
    const d = disc(''), id = 'qm-dg-' + (++uid);
    d.innerHTML = '<svg viewBox="0 0 10 10" style="width:100%;height:100%"><defs><linearGradient id="' + id + '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1A66B8"/><stop offset="1" stop-color="#7A2CBC"/></linearGradient></defs><circle cx="5" cy="5" r="5" fill="url(#' + id + ')"/></svg>';
    return d;
  }
  function open(my, from) {
    const cv = cover(my);
    const el = glyphSvg(GS); el.dot.style.opacity = 0; dash(el, 100); cv.appendChild(el.svg); sizeSvg(el.svg, 32);
    const o = off(Q.dot, 28), a = [from[0] + o[0], from[1] + o[1]];
    const dt = dotEl(), to0 = seat() || from, so = off(S.dot, 32);
    const target = function () { const s = seat() || to0; return [s[0] + so[0], s[1] + so[1]]; };
    const r0 = Q.dot[2] * 28 / 68 * 2, r1 = S.dot[2] * 32 / 68 * 2;
    return anim(dur(a, target()) * 1.15, my, function (p) {
      const t = target(), x = lerp(a[0], t[0], ease(p)), y = lerp(a[1], t[1], easeIn(p));   // falls: faster at the end
      place(dt, [x, y], lerp(r0, r1, p) * (1 + 0.9 * Math.sin(Math.PI * p)));                 // swells on the way
    }).then(function (ok) {
      if (!ok) { return false; }
      dt.remove(); el.dot.style.opacity = 1; el.track.style.opacity = 1;
      if (el.dot.animate) { el.dot.animate([{ transform: 'scale(1.5)' }, { transform: 'scale(1)' }], { duration: 260, easing: 'cubic-bezier(.2,.8,.2,1)' }); }
      return anim(820, my, function (p) { dash(el, 100 * (1 - ease(p))); });                  // the stroke is drawn around it
    });
  }
  function close(my, from) {
    const so = off(S.dot, 32), a = [from[0] + so[0], from[1] + so[1]];
    const dt = dotEl(), r0 = S.dot[2] * 32 / 68 * 2, r1 = Q.dot[2] * 28 / 68 * 2;
    const target = function () { const c = centre(topSpan()), o = off(Q.dot, 28); return [c[0] + o[0], c[1] + o[1]]; };
    return anim(dur(a, target()) * 1.15, my, function (p) {
      const t = target(), x = lerp(a[0], t[0], ease(p)), y = lerp(a[1], t[1], easeOut(p));  // rises, slows at the top
      place(dt, [x, y], lerp(r0, r1, p) * (1 + 0.9 * Math.sin(Math.PI * p)));
    });
  }
  function flight(opening) {
    const sp = ensureTop();
    if (!sp) { return; }
    if (reduce || !watched) { setAway(opening); return; }
    const my = ++run; clear(); busy = true;
    if (opening) { setAway(true); }
    const p = opening ? open(my, centre(sp)) : close(my, seat() || centre(sp));
    p.then(function () {
      if (my !== run) { return; }
      clear(); busy = false;
      if (!opening) { setAway(false); popTop(); }
    });
  }

  // ---- watching the chat loader: its flyer starts a flight; data-here covers opens without one (keyboard) ----
  let watched = null, wasShown = false;
  function shown(fl) { return fl && getComputedStyle(fl).display !== 'none'; }
  function onFlyer(fl) {
    const now = shown(fl);
    if (now && !wasShown) {
      requestAnimationFrame(function () {
        const sp = topSpan();
        if (!sp) { return; }
        const c = centre(fl), t = centre(sp), s = seat();
        const dT = Math.hypot(c[0] - t[0], c[1] - t[1]), dS = s ? Math.hypot(c[0] - s[0], c[1] - s[1]) : 1e9;
        flight(dT <= dS);
      });
    }
    wasShown = now;
  }
  function watchFlyer() {
    const fl = D.querySelector('[data-saldi-assist-flyer]');
    if (!fl || fl === watched) { return; }
    watched = fl; wasShown = false;
    D.documentElement.classList.add('qm-chat');   // only with the chat's loader: without it, the help panel's own flight stays
    new MutationObserver(function () { onFlyer(fl); }).observe(fl, { attributes: true, attributeFilter: ['style'] });
    onFlyer(fl);
  }
  function start() {
    const sp = ensureTop();
    if (!sp) { return; }
    new MutationObserver(watchFlyer).observe(D.body, { childList: true });
    watchFlyer();
    new MutationObserver(function () {
      ensureTop();
      if (!busy) { setAway(sp.getAttribute('data-here') === 'false'); }
    }).observe(sp, { attributes: true, attributeFilter: ['data-here'] });
    setAway(sp.getAttribute('data-here') === 'false');
  }
  // ---- a minimised conversation: a dot on the disc, and Hjælp says "Fortsæt samtalen" ----
  function setMinimized(on) {
    D.documentElement.classList.toggle('qm-min', !!on);
    const b = D.getElementById('topbar-help-btn');
    if (!b || !b.dataset.txtResume) { return; }
    if (on) {
      if (b.dataset.qmTitle === undefined) { b.dataset.qmTitle = b.title || ''; b.dataset.qmLabel = b.getAttribute('aria-label') || ''; }
      b.title = b.dataset.txtResume;
      b.setAttribute('aria-label', b.dataset.txtResume);
    } else if (b.dataset.qmTitle !== undefined) {
      b.title = b.dataset.qmTitle;
      b.setAttribute('aria-label', b.dataset.qmLabel);
      delete b.dataset.qmTitle; delete b.dataset.qmLabel;
    }
  }
  W.addEventListener('saldi:chat:close', function (e) {
    const on = !!(e && e.detail && e.detail.minimized);
    setTimeout(function () { setMinimized(on); }, 0);   // after saldi-help.js has redrawn the button
  });
  W.addEventListener('saldi:chat:open', function () { setMinimized(false); });

  W.SaldiHelpQmark = { version: 1 };
  if (D.readyState === 'loading') { D.addEventListener('DOMContentLoaded', start); } else { start(); }
})();
