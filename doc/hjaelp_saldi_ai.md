# Hjælp + Saldi AI, variant B: implementation spec (phase 1)

Based on commit `1b8cb4d4`. Written 7 Nov 2026 for three builders who work in parallel on disjoint files; revised the same day after review (see "Review notes" at the end). Line numbers refer to `1b8cb4d4` unless marked otherwise.

References:
- Visual and behavioural reference: the prototype `prototype_hjaelp`, kept outside the repository (variant B: `help/surface-b.js`, `help/surface-b.css`; shared engine: `help/core.js`, `help/core.css`; shots `shots/b2_aaben_ordre.png`, `b4_vis_mig.png`, `b5_felt_kladde.png`, `b6_support.png`, `x1_topbar_symbol.png`, `x2_symbol_tilstande.png`).
- Concept note: the proposal of 6 Nov 2026 (`Forslag_hjaelp_og_Saldi_AI_2026-11-06.md`, "Forslag" below), kept outside the repository.
- The Guide chat (Danosoft's own, `DANOSOFT/support-sally`, commit `148a2af1` of 15 Oct 2026, served from wuweiworkai.com). A copy of the production loader as fetched on 7 Nov (`https://wuweiworkai.com/chaty-guide/widget.js`, sha256 `ce441469…a860cbb6`) is kept outside the repository as `guide_now.js`; every `guide_now.js:N` below refers to that file. Widget-side behaviour is cited from the repo as `support-sally@148a2af1:<path>:<line>`.

Owner rules that every builder must follow:
- "Foreslå, brugeren bekræfter." Nothing opens, starts or acts by itself. Help suggests; the user clicks.
- Calm lists. No pills, count chips, UPPERCASE labels, gradient cards, hover lift or emoji. Status is a small dot plus text.
- Green only for "balances" and "saved". Red only for errors. The Klar dot is blue, never green.
- Classic pages keep their classic look. The shell (sidebar, topbar colours, fonts) changes only in the Assist slot.
- Tours never auto-start. Vis mig is a ring, never a scrim.
- Code comments and docs in English. UI text in Danish, with English and Norwegian rows in `importfiler/tekster.csv` (numeric ids, chrome and tours, §8.1) or `importfiler/tekster_keyed.tsv` (descriptive keys `help.*`, the page help content, §8.2), always read through `$t('id|Dansk')` / `findtekst('id|Dansk', $sprog_id)` / `findtekst_utf8('help.…', $sprog_id)`. Never `findtekst()` with plain text (it is uncached and slow).
- Text never reaches the DOM as HTML. One helper, `md()`, turns `**x**` into `<strong>`; everything else is `textContent` (§1.14).
- The names in the UI are "Hjælp" (the place and the button) and "Saldi AI" (the helper). "SALDI Assist", "Assist" and "Sally" never appear in new UI text.

---

## 0. What phase 1 delivers, and decisions this spec takes

Phase 1 delivers:
1. The topbar Assist button becomes the Saldi AI symbol in a 28 px disc plus the label "Hjælp" (from 1240 px). One click opens one light panel on the right. There is no menu.
2. The panel (prototype B): search/ask field, "Om siden", "Feltet du stod i", "Sådan gør du" (tasks ordered by page state, each with Vis mig), "Rundvisning af siden (N trin)" only where the page has a tour, "Tastaturgenveje", "Kontakt support" and "Hvad får Saldi AI at vide?".
3. Real content for `finans/kladdeliste.php` and `finans/kassekladde.php`, in Danish, English and Norwegian (`importfiler/tekster_keyed.tsv`, served per language by `index/helpTexts.php`). Every other page gets a generic panel that keeps everything the old Assist menu offered.
4. "Ro på" in `includes/tutorial.php`: no auto-start, ring instead of scrim, no reload from the blue Hjælp cell, a `window.SaldiTour` API for the panel.
5. "Spørg Saldi AI" hands the question the user confirmed to the existing Guide, and says honestly when the Guide is not there.
6. A pilot switch: after merge the panel is on only for tenants that have the settings row `help/panel = 'on'` (a test tenant on ssl12). Everyone else keeps the old Assist menu until the owner has tested and approved (§9).

Not in phase 1: an inline AI conversation (thread, streaming, thumbs, disclaimer), the first-login hint, the error-card link in `includes/errorReport.php`, links to the saldi.dk web guides (Forslag decision 10: only after the menu-path review), the SST-915 gear hint, any change to the Guide, the idle blink and the "har noget til dig" signal (D11).

Decisions this spec takes (each can be reversed by the owner; they are listed again in §15):

| # | Decision | Why |
|---|---|---|
| D1 | Dock threshold is exactly 960 px of page left beside the panel. No "covered" middle zone, no lower limit for a pinned panel. At 1440 px with the sidebar open the panel therefore overlays. | Task and Forslag decision 9. Prototype B used 800 (`surface-b.js:36`), which the brief never approved. |
| D2 | Vis mig and Rundvisning end on Esc, ×, Forstået/Færdig, or a pointer press anywhere on the page outside the card. The press still does what it does. | Forslag step 1 ("Esc eller et klik ved siden af lukker"). |
| D3 | For a tenant with the panel on, the blue Hjælp cell opens the panel on every page in the shell, also where it was dead before. Tenants without the panel keep today's cell (it starts the tour in place, §5.1). | BRIEF §2.2 and Forslag §3. The topbar slot cannot be per page, so a pilot tenant sees the panel (generic outside the two pilot pages) everywhere. `tutorial.php` changes globally anyway. |
| D4 | Max 3 tasks in "Sådan gør du" (as the approved shots). More tasks are still found by search. | Shots b2, b5. |
| D5 | A task row expands inline to its steps (accordion). A prepared question found by search expands the same way. | No AI engine in the panel yet; the content is hand-written help, not AI. |
| D6 | An unpinned panel closes when the frame goes to another route (path), and stays open on a reload of the same route (a kassekladde save). A pinned panel always stays. | Prototype tooltip "Løsn: hjælpen lukker, når du skifter side"; a bookkeeper saves with Enter all the time. |
| D7 | `?` opens help on every page, also on settings pages that registered a shortcut list. That list is reached from the panel's "Tastaturgenveje". | One meaning for one key. |
| D8 | "Spørg Saldi AI" closes an unpinned panel when it hands the question to the Guide, so the Guide's window has room. | Two docked panels do not fit. |
| D9 | "Skriv til os" posts to a new endpoint only when a recipient is configured (`SALDI_HELP_SUPPORT_TO` or `$helpSupportTo`). Otherwise the card shows a mail link instead of the form. | No accidental mail from test servers. |
| D10 | All help content lives in `importfiler/tekster_keyed.tsv` under `help.*` keys with Danish, English and Norwegian columns, and reaches the panel through `findtekst_utf8()` in a small JSON endpoint (§3.4, §8.2). Selectors, detectors and search keywords stay in `javascript/saldi-help-content.js` and refer to the keys. | Owner rule: UI text in the tekster files with en/no, read through `findtekst()`. Descriptive keys exist since 29 Oct (`includes/stdFunc/findTxt.php:52-93`, `doc/ai/convention_findtekst_keys.md`) and the `tekster` guardrail checks the TSV (`tools/guardrails/checks/tekster.mjs:140-206`). |
| D11 | No idle blink and no "har noget til dig" signal in phase 1. The mood machinery and `SaldiHelp.setEngineState()` exist; the signal is wired but switched off by `data-signal="0"` on the host (§1.10). | BRIEF: "har noget til dig" is for an unread answer or the one-time first-login hint, never tips; "blinker" is once on a thumbs-up (`prototype_hjaelp/research/report_symbol-kit.md:174-175`). Owner rules "calm" and "nothing acts by itself". Owner questions 5–6. |
| D12 | Pilot by default: `SALDI_HELP_PANEL` unset means `pilot` (§9). | The owner asked for B on the two pilot pages first, and the rest only after his test on ssl12. |

---

## 1. Architecture

### 1.1 Overview

```
index/main.php (top window, the shell)
├─ header.topbar#topbar
│   └─ button#topbar-help-btn  ← Saldi AI symbol in a 28 px disc + "Hjælp"   (topbar.php)
├─ div.home-content  (display:flex)
│   ├─ iframe#iframe_a  ← classic page, same origin
│   │     ├─ #tutorial-ring / #tutorial-tooltip, window.SaldiTour   (tutorial.php, inside the frame)
│   │     └─ #saldi-help-ring / #saldi-help-callout + <style>       (injected by saldi-help.js for Vis mig)
│   └─ aside#saldi-help  ← the panel, built by saldi-help.js           (host printed by topbar.php)
└─ body > .saldi-help-flyer   ← the mascot while it flies (saldi-help.js)

javascript/saldi-help-content.js  → window.SaldiHelpContent  (selectors, state detectors, text keys, search keywords)
javascript/saldi-help.js          → window.SaldiHelp         (all behaviour in the top window)
css/saldi-help.css                → topbar slot, panel, flyer
importfiler/tekster_keyed.tsv     → help.* texts, da/en/no
index/helpTexts.php               → GET ?group=kl|kk → { texts: { 'help.kk.…': '…' } } in the user's language (findtekst_utf8)
```

`saldi-help.js` lives in the top window. It reaches into the same-origin frame for four things: the route and page state, focus tracking ("Feltet du stod i"), the classic Hjælp cell, and the Vis mig ring. It re-binds on every frame navigation. Classic pages are not edited for help, except the tour step lists of the two pilot pages (§5.4).

### 1.2 Files

| File | New/changed | Builder |
|---|---|---|
| `javascript/saldi-help.js` | new | SHELL |
| `css/saldi-help.css` | new | SHELL |
| `includes/saldi_help_panel.php` | new (pilot/kill switch + support recipient) | SHELL |
| `index/helpSupport.php` | new (JSON endpoint for "Skriv til os") | SHELL |
| `tests/includes/saldiHelpPanel.test.php` | new (PHPUnit) | SHELL |
| `index/main.php` | changed | SHELL |
| `index/mainIncludes/topbar.php` | changed | SHELL |
| `css/topbar.css` | **unchanged** (the old `.topbar-assist*` rules stay for tenants without the panel) | – |
| `javascript/saldi-help-content.js` | new (shared data and helpers; the pages moved to the module file in §16) | CONTENT |
| `javascript/saldi-help-finans.js` | new 8 Nov (every finance page, §16) | CONTENT |
| `javascript/saldi-help-debitor.js`, `javascript/saldi-help-kreditor.js` | new 8 Nov (the Debitor and Kreditor pages, §16.1) | CONTENT |
| `javascript/saldi-help-lager.js`, `javascript/saldi-help-settings.js` | new 9 Nov (the Lager and Indstillinger pages, §16.1, §24) | CONTENT |
| `importfiler/tekster_keyed.tsv` | changed (the `help.*` rows, §8.2) | CONTENT |
| `includes/saldi_help_texts.php` | new (collects the `help.<group>.*` keys and resolves them, §3.4) | CONTENT |
| `index/helpTexts.php` | new (JSON endpoint for the help texts, §3.4) | CONTENT |
| `tests/includes/saldiHelpTexts.test.php` | new (PHPUnit, key collection) | CONTENT |
| `tests/includes/ui/saldiHelpContent.test.mjs` | new (node --test, schema + key check against the TSV) | CONTENT |
| `includes/tutorial.php` | changed (rewrite of `create_tutorial()`'s body) | TOURS |
| `finans/kassekladde.php` | changed (tour step block only, `:5701-5735`) | TOURS |
| `finans/kladdeliste.php` | changed (tour step block only, `:838-847`) | TOURS |
| `importfiler/tekster.csv` | changed (rows 8700–8777 for all builders) | TOURS |

Nobody else touches these files. `includes/stdFunc/pageChrome.php`, `javascript/pageChrome.js`, `javascript/saldi-assist-navigate.js`, `includes/saldi_assist_*`, `includes/stdFunc/findTxt.php`, `css/topbar.css`, `css/sidebar_style.css`, `includes/errorReport.php` (only included, §2.6) and `.github/workflows/*` are not edited.

### 1.3 Load order and globals

In `index/main.php`, only when the panel is enabled (§9):
1. `<link href='../css/saldi-help.css?v=2' rel='stylesheet'>` after `saldi-theme.css` (`:154`).
2. The panel host `aside#saldi-help` inside `.home-content`, after the iframe (`:422`).
3. At the end of the page, after the `SaldiAssist` object (`:1329`) and the records block (`:1330-1336`), before `</html>` (`:1337`):
   ```html
   <script src="../javascript/saldi-help-content.js?v=2"></script>
   <script src="../javascript/saldi-help.js?v=2"></script>
   ```
   Since 8 Nov (§16) a module file sits between the two: `saldi-help-content.js?v=4`, `saldi-help-finans.js?v=4`, `saldi-help.js?v=3`.
   Since §19 the state builder sits before `saldi-help.js`: `saldi-help-content.js?v=4`, `saldi-help-finans.js?v=6`, `saldi-help-state.js?v=1`, `saldi-help.js?v=6`.
   The Debitor and Kreditor module files follow `saldi-help-finans.js`, before `saldi-help-state.js`: `saldi-help-debitor.js?v=1`, `saldi-help-kreditor.js?v=1` (§16.1).
   The Lager and Indstillinger module files follow `saldi-help-kreditor.js`, before `saldi-help-state.js`: `saldi-help-lager.js?v=1`, `saldi-help-settings.js?v=1` (`?v=2` with the content, §24.5; `?v=3` after the two reviews, §24.13, §24.14; `?v=4` after the live check, §24.15); with them `saldi-help-state.js?v=3` and `saldi-help.js?v=10` (variants, §24).
   `saldi-help.js` boots synchronously (the DOM above it exists). It reads `window.SaldiAssist` lazily, never at load time, because the Guide loader is `defer` and sets `SaldiAssist.v2Ready` later (`guide_now.js:1436-1441`).

Globals:

| Global | Defined by | Read by |
|---|---|---|
| `window.SaldiHelpContent` | `saldi-help-content.js` | `saldi-help.js` |
| `window.SaldiHelpState` (§19) | `saldi-help-state.js` | `saldi-help.js` |
| `window.SaldiAssist.helpReady`, `window.SALDI_CHAT` (§19) | the Guide loader (`support-sally`, `frontend/app/widget.js/route.ts`) | `saldi-help.js`, at the time of use |
| `window.SaldiHelp` | `saldi-help.js` | `topbar.php` inline onclick fallback, `tutorial.php` (only to test that it exists), Playwright |
| `window.SaldiTour` (in the frame window) | `tutorial.php` | `saldi-help.js`, `main.php:792` |
| `topbarCrumbMsg`, `topbarAssistPage`, `topbarAssistRun`, `topbarCloseAll`, `topbarOpenAssist`, `update_iframe` | `main.php` (global lexical bindings / functions) | `saldi-help.js`, by bare name |

### 1.4 The panel host and docking

The host is printed empty and hidden; `saldi-help.js` builds the content.

```html
<aside id="saldi-help" class="saldi-help hs-b hc-root" hidden tabindex="-1"
       aria-labelledby="saldi-help-title"
       data-lang="da|en|no" data-support-send="0|1" data-records="0|1" data-signal="0" data-version="1"
       data-txt="{…JSON of chrome strings, §8.1…}"></aside>
```

`data-records` is `1` when `SALDI_ASSIST_RECORDS_ENABLED=1` (the Guide can then read the open saved journal or invoice, `main.php:1330-1336`); it picks the text under "Hvad får Saldi AI at vide?" (§6). `data-signal` is the phase-1 off switch for the "har noget til dig" state (D11).

CSS placement (in `css/saldi-help.css`; do not edit `sidebar_style.css`):

```css
.home-section .home-content { position: relative; }                          /* anchor for overlay mode */
.home-section .home-content > #iframe_a { flex: 1 1 auto; min-width: 0; width: auto; }
#saldi-help { display: flex; flex-direction: column; overflow: hidden; background: var(--surface); color: var(--text); border-left: 1px solid var(--border); }
#saldi-help[hidden] { display: none !important; }
#saldi-help[data-mode="docked"]  { position: relative; flex: 0 0 400px; align-self: stretch; }
#saldi-help[data-mode="overlay"] { position: absolute; top: 0; right: 0; bottom: 0; width: 400px; max-width: 100%; z-index: 45; }
#saldi-help[data-mode="sheet"]   { position: fixed; left: 8px; right: 8px; bottom: 8px; top: auto; width: auto; max-height: 85dvh; z-index: 45; border-radius: 16px; }
```

Why here: the topbar keeps its full width; the panel starts under the bar and follows `--topbar-h` (44 px, 48 px on Oversigt, `topbar.css:8,373`); topbar pops (z 60, `topbar.css:206`) open over it; the phone sidebar (z 100) covers it; and it does not fight the Guide, which docks by setting an inline `width … !important` on `.home-section` (`guide_now.js:512`).

Mode decision (`layout()`), run on open, on a `ResizeObserver` of `.home-content` (this also catches the 0.5 s sidebar transition, `sidebar_style.css:372`) and on window resize:

```js
function decide() {
  if (matchMedia('(max-width: 600px)').matches) return 'sheet';
  const room = document.querySelector('.home-section .home-content').clientWidth - 400;
  return room >= 960 ? 'docked' : 'overlay';
}
```

- `docked`: the iframe narrows; classic pages reflow and get a `resize`. Panel `role="region"`, no shadow, 1 px left hairline.
- `overlay`: the panel lies over the page with a shadow (`-14px 0 32px rgba(11,26,58,.12)`) and **no scrim**. `role="dialog"`, `aria-modal="false"`.
- `sheet` (≤600 px layout width; with `initial-scale=0.8`, `main.php:156`, a 390 px phone is about 488 px): bottom sheet, 8 px from the edges, max 85 dvh, radius 16, a drag handle (tap or drag down more than 80 px closes), no pin, 44 px touch targets, 16 px inputs. `role="dialog"`, `aria-modal="false"`.
- Between 601 and 780 px the panel overlays at full height; at 780 px and below the sidebar is gone (`sidebar_style.css:504-511`).

Reference numbers (viewport → page left when docked): 1366 open sidebar → 756 overlay; 1440 open → 830 overlay; 1440 collapsed (72 px) → 968 dock; 1536 open → 926 overlay; 1920 open → 1310 dock.

Kassekladde's own fixed controls sit under an overlay panel. That is accepted. In the shell (`$menu == 'S'`) the column gear is `#kk-vis-cell` in the top line (`topLineKassekladde.php:105`); the floating `.kkVisFab` is printed only when `$menu != 'S'` (`kassekladde.php:3114-3117`). What can lie under the panel is the gear's pop-out `#kkVisPanel` (`position:fixed; right:14px`, `kassekladde.php:3088`) and the one-time hint `#kkVisHint` (`position:fixed`, placed beside the gear by script, `:3102`).

### 1.5 The frame adapter

All in `saldi-help.js`.

- `frame()` → `document.getElementById('iframe_a')` (do not rename; the Guide finds the frame by name).
- `fwin()` / `fdoc()` → `frame().contentWindow` / `.contentDocument`. Every access to the frame (`location`, `document`, `SaldiTour`, `docChange`) is inside try/catch; a throw means "unreadable" (§1.5.1).
- `route()` → the frame path relative to the Saldi root, or `null` when the frame is unreadable or not a Saldi page:
  ```js
  function route() {
    try {
      const w = fwin();
      if (!w) return null;
      const p = w.location.pathname;                                   // throws SecurityError on another origin
      const root = location.pathname.replace(/\/index\/main\.php$/, ''); // e.g. '/saldi', '' at the domain root
      return p.indexOf(root + '/') === 0 ? p.slice(root.length + 1) : null;   // about:blank ('blank') → null
    } catch (e) { return null; }
  }
  ```
- `rectInTop(el)` → `el.getBoundingClientRect()` shifted by `frame().getBoundingClientRect()` left/top.
- Frame nodes come from another realm: never `instanceof Element` / `HTMLInputElement` on them (always false in the shell). Use `node.nodeType === 1` and `node.tagName` (upper case), and the frame window's `getComputedStyle` (§3.1).
- Binding per document (`bindFrame(doc)`), idempotent through a `doc.__saldiHelpBound` flag, and only when `route() !== null`. No timers, no polling. It is called:
  - on the `saldi:breadcrumb` message from the frame (table below). `javascript/pageChrome.js:7,35` posts it while the page's `<head>` is parsed (`includes/online.php:502-504` → `includes/stdFunc/pageChrome.php:85-86`). The new document exists then and is still `loading`; listeners on `document` work for everything parsed later. Both pilot pages are in the breadcrumb map (`includes/stdFunc/pageRoutes.php:48-49`), so `?`, F1 and the Hjælp cell work long before `load` on a heavy kassekladde.
  - on the iframe `load` event (`frame().addEventListener('load', …)`, which runs after the inline `onLoad` at `main.php:409-418`; that one needs no edit). This is the fallback for pages without a breadcrumb (`index/dashboard.php`, the settings pages and the others listed in `pageChrome.php:346`, pages that do not include `online.php`).
- Listeners bound into each frame document:

| Event | Phase | Purpose |
|---|---|---|
| `keydown` | bubble | `?`, F1, Esc (§1.12). Skip when `e.defaultPrevented` (the account autocomplete calls `preventDefault()` on Esc, `accountAutocomplete.js:388-390,2217-2219`). |
| `pointerdown`, `keydown` | capture | the first trusted user input sets `doc.__saldiHelpUserActed = true` and may adopt the focused field (§1.6); remember the last pressed element; end a Vis mig ring on a press outside the callout (D2). |
| `focusin`, `focusout`, `keyup`, `mouseup` | capture | "Feltet du stod i" and the caret (§1.6). |
| `change` | capture | drop the cached `detect()` result (§1.6). |
| `click` | capture | the classic Hjælp cell: `const c = e.target.closest && e.target.closest('#tutorial-help')`; when found → `preventDefault()`, `stopPropagation()`, `SaldiHelp.toggle({ source: 'classic', trigger: c.querySelector('button') \|\| c })`. It runs before `tutorial.php`'s delegated listener (bubble phase on the same document, §5.2), so that one never runs once the shell has bound. |
| `pagehide` | – | the busy state (§1.5.2). |

- A `message` listener on the shell `window`, with the same checks as `main.php:574-576` (`e.origin === location.origin && e.source === frame().contentWindow`). It is registered after `main.php`'s own listener (the help script loads at the end of the page), so `topbarCrumbMsg` is already updated when it runs.

| `e.data.type` | From | Action |
|---|---|---|
| `saldi:breadcrumb` | every page with a breadcrumb (`pageChrome.js:7`) | `bindFrame(fdoc())` when not bound yet. If the panel is open and shows the generic head (§1.13), re-render the head: `topbarFrameLoaded()` clears `topbarCrumbMsg` on every load and asks the page again (`main.php:484-487`), so until this reply the label is `doc.title`. |
| `saldi:help-open` | `tutorial.php` (a Hjælp click before the shell bound) | `SaldiHelp.toggle({source:'classic'})` |
| `saldi:help-ask` | `tutorial.php` (the tour card's "Spørg Saldi AI om dette") | open or restore the panel, prefill the search field with `t('8756').replace('%s', text)`, focus it. Nothing is sent. |
| `saldi:tour` | `tutorial.php` | `state:'start'` → minimise the panel to the strip with label `t('8760')`; `state:'step'` → re-place the strip (§1.8); `state:'end'` → restore it. |

#### 1.5.1 Unreadable or blank frame

The frame can show another origin (a bank-integration redirect, an external address loaded with `update_iframe()`), `about:blank` or a browser error page. Then `route()` and `pageCtx()` return `null`, and:
- the panel is the generic panel (§1.13) with the title "Hjælp" and no page label, and the row `t('8777')` "Spørg Saldi AI" instead of "Spørg Saldi AI om <side>";
- no field box, no Vis mig, no Rundvisning row, no "Sidens egne genveje", no "Vejledning";
- `bindFrame` is skipped, so `?`, F1 and Esc work only while focus is in the shell;
- nothing throws (every frame read is in try/catch) and nothing is logged.

#### 1.5.2 Between `pagehide` and the next bind

Every Enter-save on the kassekladde is a reload of the same route, and a long kladde can take seconds to load. On `pagehide`:
- end a Vis mig ring silently (its elements die with the document);
- set `aria-busy="true"` on the panel; set `disabled` on every Vis mig button and on the Rundvisning row; drop the cached `st` and any `SaldiTour` reference;
- keep the field box as it is and keep `S.lastField` without its element (`{ key, name, start, end, el: null }`), so a docked panel does not jump in height.

On the next `bindFrame`:
- same route → recompute `pageCtx()`, `detect()` and `render()`. Look the field up again by its `name` (`doc.forms.kassekladde.elements.namedItem(name)` where that form exists, else `doc.getElementsByName(name)[0]`); when it is a field, reattach it as `S.lastField.el`; otherwise keep the box text without a caret target (Esc then returns focus to the trigger). Remove `aria-busy` and the `disabled` attributes.
- another route → an unpinned panel closes (D6); a pinned one clears the field box and renders the new page.

### 1.6 Page context, state and "Feltet du stod i"

```js
function pageCtx() {                                       // null → unreadable frame (§1.5.1)
  const r = route();
  if (r === null) return null;
  try {
    const win = fwin(), doc = win.document;
    return { doc, win, url: new URL(win.location.href), route: r, lang, t,          // t = the chrome text lookup (§2.2)
             field: S.lastField && S.lastField.el ? { key: S.lastField.key, el: S.lastField.el } : null };
  } catch (e) { return null; }
}
page = ctx && SaldiHelpContent.pages[ctx.route] || null    // null → generic panel (§1.13)
st = page ? cachedDetect(page, ctx) : null                 // { state, flags, data }
```

`cachedDetect()` keeps one result per frame document (`S.stDoc === ctx.doc`). It is dropped on bind, on `load`, on `pagehide`, on a `change` event in the frame document, and once 400 ms after `load` on kladdeliste (a single timeout, not a poll: kladdeliste injects `#filter-toggle-btn` 300 ms after `DOMContentLoaded` and the separator rows on load, `kladdeliste.php:684-731,857-953`). `pageCtx()` runs on every panel open and every `render()`; a field change while the panel is open re-runs only `page.order(ctx, st)` (KK4 depends on the field) and the field box, never `detect()`.

Every call into content is wrapped in try/catch; a throwing detector gives `st = { state: 'unknown', flags: {}, data: {} }` and logs once to `console.warn`.

"Feltet du stod i":
- A field is an element with `nodeType === 1` and `tagName` `INPUT` (type not `hidden`, `button`, `submit`, `reset`, `image`), `SELECT` or `TEXTAREA` in the frame document.
- A `focusin` counts only after the first trusted `pointerdown` or `keydown` in that document (`doc.__saldiHelpUserActed`). The kassekladde focuses a field by itself on load (`kassekladde.php:3342-3356, 5212-5217`); without this rule the box would always show Dato or Bilag.
- Adoption: the capture `pointerdown`/`keydown` that first sets `__saldiHelpUserActed` also sets `S.lastField` to `doc.activeElement` when that is a field and either the event is a `keydown` (the user types in, or tabs out of, the field the page focused) or the `pointerdown` target is that same element (a click into a field that already has focus fires no `focusin`). A first press anywhere else, such as on the blue Hjælp cell, adopts nothing, so opening help right after load still shows no box (S11). This keeps the box in the keyboard scenario: Enter-save, the page focuses the next line, the user types, then presses F1 or clicks Hjælp.
- F1 pressed in the frame: when `e.target` is a field, it becomes `S.lastField` (with its caret) before the panel opens, whatever the flag says.
- Store `S.lastField = { el, key, name, start, end }` with the caret (`selectionStart/End`, in try/catch). Update the caret on `focusout`, `keyup`, `mouseup`.
- A `focusin` on a button, link or `[tabindex]` for which `!el.closest('#tutorial-help')` clears `S.lastField` only when the panel is closed (an open panel keeps the box, as the prototype's `fillField(keepOld)`). The focusable element of the classic cell is the `<button>` inside `td#tutorial-help` (`topLineFinans.php:65-68`, `topLineKassekladde.php:89-92`), hence `closest()`.
- Field key (default rule; content may override with `page.fieldKey(el)`):
  ```js
  function fieldKey(el) {
    const n = el.getAttribute('name') || '';
    let m;
    if ((m = /^search\[[^\]]+\]\[([^\]]+)\]$/.exec(n))) return 'search:' + m[1];   // search[kladdelst][kladdenote]
    if (/^rowcount\[/.test(n)) return 'rowcount';
    if (n) return n.replace(/_?\d+$/, '');                                          // kred12 → kred, afd_3 → afd (fieldNavigation.js:51)
    return el.id || null;                                                           // #kkRegnaar
  }
  ```
- The box shows when `page.fields[key]` exists: heading `t('8704')` with the label, 1–2 lines of text, and the link `t('8705')` "Spørg Saldi AI om feltet". The link prefills the panel's search field with `t('8756')` ("Forklar mere om: <label>") and focuses it. Nothing is sent; the user confirms with Enter or the "Spørg Saldi AI" row (§6).
- While the panel is open, the box follows the field the user moves to (`focusin` in the frame) and never shrinks in height (port `lockField()`, `surface-b.js:198-206`).
- Opened with F1 or `?` from a field, the box is placed directly under the search field, above "Om siden" (`surface-b.js:560-563`).

### 1.7 Vis mig (single target), drawn inside the frame

`SaldiHelp.showMe(target, { label, kind, note })`, `target` = a selector string (queried in the frame document) or an element of the frame document (checked with `nodeType === 1`, never `instanceof`, §1.5).

1. Resolve the element; it must be visible (`Array.from(el.getClientRects()).some(r => r.width > 0 && r.height > 0)`). If not, do nothing and return `false` (rows never offer Vis mig for an invisible target).
2. Inject once per frame document: `<style id="saldi-help-layer-style">` with the CSS below (`style.textContent = CSS`), and `div#saldi-help-ring` + `div#saldi-help-callout` appended to `doc.body`, all built with `doc.createElement()` in the frame document; the callout's label, text and button labels are set with `textContent` (§1.14). Re-inject after each navigation (the old ones die with the document).
3. Remember scroll positions of the target's scrolling ancestors (port `rememberScroll()` from `tutorial.php:375-383`), `el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: reduced ? 'auto' : 'smooth' })`.
4. `place()` positions the ring from the visible, clipped rect of the target inside the frame (port `visibleRect()` from `tutorial.php:347-371`, with the frame window's `getComputedStyle`): `left = rect.left - 6`, `top = rect.top - 6`, `width = rect.width + 12`, `height = rect.height + 12` (4 px offset + 2 px border). When nothing is visible the ring hides; the callout stays where it was. `place()` is event-driven, as `tutorial.php` already does it (`onScrollOrResize`, `tutorial.php:148-155`): while a ring is shown, and only then, these listeners are bound and all of them schedule one coalesced `requestAnimationFrame` (a second event before it runs is dropped):
   - `scroll` on the frame window in **capture** phase (catches the kassekladde's own scroll container) and `resize` on the frame window;
   - a `ResizeObserver` on the target element and on `.home-section .home-content` (docking, sidebar transition);
   - `saldi:tour` messages (§1.5).
   They are removed when the ring ends. Nothing runs while nothing moves; there is no rAF loop.
5. The callout is placed below the target (14 px gap), else above, else beside, clamped 8 px inside the frame viewport (port `placeCard()`, `core.js:450-470`, with the frame's `innerWidth/innerHeight`). Its head is "Vis mig · <label>" with × (`aria-label` and `title` both `t('8747')` "Luk (Esc)"); its text is `note` or by kind: `button` → `t('8742')`, `field` → `t('8743')`, `status` → `t('8744')`, `row` → `t('8759')`. Foot: one button `findtekst 5148` "Forstået" (type="button").
6. The panel minimises to the strip with label `<label>` (§1.8). Focus goes to Forstået (`preventScroll`), unless `opts.focus === false`.
7. Ends on: Forstået, ×, Esc, a click on the target (capture listener on the frame document; the click still happens), a `pointerdown` anywhere else outside the callout (D2), "Tilbage til hjælpen" in the strip, `pagehide`. Ending restores the panel. Focus moves back into the panel only when it was in the callout, on the frame `body` or in the panel; a user who clicked into the page keeps their place. Scroll positions are restored only when ended by Forstået, × or Esc.

Injected CSS (verbatim; classic pages have no tokens and no kit fonts):

```css
#saldi-help-ring{position:fixed;z-index:100000;display:none;box-sizing:border-box;margin:0;padding:0;pointer-events:none;border:2px solid #4FA0EE;border-radius:6px;box-shadow:0 0 0 3px rgba(79,160,238,.18)}
#saldi-help-ring[data-on="true"]{display:block}
#saldi-help-callout{position:fixed;z-index:100010;display:none;box-sizing:border-box;width:320px;max-width:calc(100vw - 16px);margin:0;padding:12px 14px;background:#fff;color:#0b1a3a;border:1px solid #e4e8ef;border-radius:8px;box-shadow:0 10px 34px rgba(16,30,60,.16);font:13px/1.45 Inter,"Segoe UI",system-ui,-apple-system,Arial,sans-serif;text-align:left;letter-spacing:normal;text-transform:none}
#saldi-help-callout[data-on="true"]{display:block}
#saldi-help-callout *{box-sizing:border-box;font-family:inherit}
#saldi-help-callout .shc-head{display:flex;align-items:center;gap:8px;margin:0 0 6px}
#saldi-help-callout .shc-step{flex:1 1 auto;font-size:12px;font-weight:600;color:#5b6b85}
#saldi-help-callout .shc-x{width:28px;height:28px;padding:0;border:0;border-radius:4px;background:none;color:#5b6b85;font-size:18px;line-height:1;cursor:pointer}
#saldi-help-callout .shc-x:hover{background:#eef2f8;color:#0b1a3a}
#saldi-help-callout p{margin:0}
#saldi-help-callout .shc-foot{display:flex;justify-content:flex-end;margin-top:12px}
#saldi-help-callout .shc-ok{height:28px;padding:0 12px;border:1px solid #114691;border-radius:4px;background:#114691;color:#fff;font-size:12.5px;font-weight:500;cursor:pointer}
#saldi-help-callout .shc-ok:hover{background:#0e3a78}
#saldi-help-callout button:focus-visible{outline:2px solid #1B68BD;outline-offset:2px}
@media print{#saldi-help-ring,#saldi-help-callout{display:none!important}}
```

The ring and the callout sit above the page's own layers: the kassekladde's account list container is fixed at z-index 99999 (`accountAutocomplete.js:92`), and an open list would otherwise hide the callout and its Forstået. Vis mig (and the panel's Rundvisning row) also call the frame's `closeAccountAutocomplete()` when it exists (`accountAutocomplete.js:2400`); it only hides that transient list.

All buttons are `<button type="button">`. `online.php:627-641` recolours `input[type=submit]`, `input[type=button]` and `button[type=submit]` with `!important` in every page; a `<button type="button">` is not matched.

The tour (§5) draws its own ring with the same numbers. The shell never draws a tour; the frame never draws a Vis mig.

### 1.8 The minimise strip

While a Vis mig ring or a tour is shown and the panel is open, the panel shrinks to a 44 px strip: "Viser: **<label>**" (`t('8729')`) and the button "Tilbage til hjælpen" (`t('8728')`), `role="status"`. "Tilbage til hjælpen" ends the ring (`SaldiHelp.endShowMe()`) or the tour (`fwin().SaldiTour.stop()`).

- Docked: the panel keeps its 400 px column so the page does not move. The strip sits at the top of the column; the rest of the column is the panel's own `--surface` background with `pointer-events: none`.
- Overlay: the strip is 400 px wide, 44 px high, at one of three positions inside `.home-content`: `tr` (top right, default), `tl` (top left: `left: 0; right: auto`), `br` (bottom right). `avoid()` (port `surface-b.js:358-400`) measures in top-window coordinates (`rectInTop`) the rings and cards that must never be covered (`#saldi-help-ring`, `#saldi-help-callout`, `#tutorial-ring`, `#tutorial-tooltip`, those that are shown) and the page buttons it should rather not cover (kassekladde footer: `input[type=submit][accesskey]`, `input[name="bilagsmatch"]`), and moves to the first position that is clear. It is event-driven like `place()` (§1.7): it runs once when the strip appears, and then only from the same coalesced frame callback, triggered by frame `scroll` (capture) and `resize`, the `ResizeObserver` on `.home-content`, a Vis mig `place()`, and the `saldi:tour` messages `start` and `step`. Because `tutorial.php` moves its card in its own `requestAnimationFrame` after a scroll, and the top window's frame callbacks run before the frame's, `avoid()` reads the tour card one frame later (a nested `requestAnimationFrame` inside the coalesced one). Nothing runs while nothing moves; during a long tour with no scrolling `avoid()` does not run at all.
- Sheet: positions `t` (just under the topbar) and `b` (bottom).
- On restore: if focus is now in a page field that the full overlay panel would cover again, the panel closes instead and the caret stays (port `pageSpotUnderPanel()`, `surface-b.js:423-432`).
- The mascot sits in the topbar seat while minimised.

### 1.9 Support card and endpoint

Port `supportCard()` (`core.js:1006-1056`) and `openStatus()` (`core.js:996-1003`) with these changes:
- Phone, hours, mail and opening hours come from `SaldiHelpContent.support` (§4.1); strings from `data-txt`. Danish users see `phone` ("46 90 22 08"); English and Norwegian users see `phoneIntl` ("+45 46 90 22 08"), because the Danish form reads as a valid domestic number abroad (a Norwegian mobile number is 8 digits from 4). The `tel:` link is `+4546902208` for everyone.
- Status line in Europe/Copenhagen: `t('8717')` "Åbent nu, til kl. %s" (blue dot `#4FA0EE`), `t('8718')`, `t('8719')`, `t('8720')` (grey dot `--text-3`). Never green. Public holidays are ignored (known limitation).
- "Skriv til os" (`t('8721')`):
  - `data-support-send="1"`: inline form with "Din besked" (textarea, max 2000 characters), "Send med" with one checkbox "Sidens adresse" (ticked, shows the frame URL without `inframe=1` as text via `textContent`), and **Send** (`<button type="button">`, the panel's only filled primary button). There is no "Samtalen med Saldi AI" checkbox in phase 1 (there is no conversation in the panel).
  - `data-support-send="0"`: a link `t('8757')` "Skriv til %s" → `mailto:support@saldi.dk?subject=…&body=…` with the page address in the body. No form.
- Send posts to `../index/helpSupport.php` (§2.6), header `X-CSRF-Token: window.SALDI_CSRF`, body `application/x-www-form-urlencoded`: `message`, `page` (only when ticked), `page_label`. On `{ok:true}` the form is replaced by the receipt `t('8726')` (neutral, grey check, takes focus with `tabindex="-1"`). On any error: `t('8727')` under the button, the message stays.
- The support view is a sub-page of the panel with "‹ Tilbage til hjælpen" (`t('8728')`); a typed draft survives going back and forth (`surface-b.js:209-223`).
- The footer always shows "Kontakt support" (`t('8714')`, desc "Ring 46 90 22 08 · hverdage 9–16", "+45 46 90 22 08" in English and Norwegian) and "Hvad får Saldi AI at vide?" (`t('8736')`), which expands to `t('8737')` when `data-records="0"` or `t('8771')` when `data-records="1"`, followed by `t('8772')` (§6).

### 1.10 Symbol, mascot and states

- The symbol SVG is the landing page's, byte-identical except the gradient id (the landing page's `index.html:1122`; same as `prototype_hjaelp/help/core.js:88`). The topbar copy is printed by PHP (§2.2). `saldi-help.js` clones it for the panel seat, the flyer and 20 px row icons with `svg.cloneNode(true)`, then gives the clone its own gradient id: `linearGradient.id = 'sa-g-' + uid()` and `setAttribute('stroke'|'fill', 'url(#sa-g-…)')` on `.sa-stroke` and `.sa-dot`. No `outerHTML`/`innerHTML` (§1.14).
- CSS for `.ai-avatar` and its states: copy `prototype_hjaelp/help/core.css:30-73` verbatim (it equals the landing page's `index.html:631-656` and the reduced-motion block `:707-712`), plus the help sizes `.ai-avatar--28` (svg 14 × 17.5) and `.ai-avatar--20` (svg 10 × 12.5) and the light empty seat (`core.css:58-61`). Never change the form.
- One mascot (port `fly()`, `core.js:164-216`): 640 ms easeInOutCubic arc from the topbar seat to the panel header seat (40 px), landing pop 1.12 → 1 over 320 ms; flies back on close; instant for keyboard opens and `prefers-reduced-motion`. The flyer is `div.saldi-help-flyer` on `body`, `position: fixed; z-index: 90` (above `.home-section`, below the sidebar's 100).
- While the mascot sits in the panel, the topbar seat shows the symbol at 30 % opacity, never an empty disc (`prototype_hjaelp/shell.js:148-150`).
- States in phase 1 (`data-ai-state` on every registered avatar; port `computeMood()`/`applyMood()`, `core.js:108-141`):

| State | Value | When | Ends |
|---|---|---|---|
| klar | `ready` | default | – |
| har noget til dig | `signal` | **Off in phase 1** (D11): evaluated only when the host has `data-signal="1"`, which `topbar_help_host()` never prints in phase 1. When on: `page.signal(ctx, st)` is true for the current frame document and the user has not opened help since that state appeared (ack key: the route, `st.data.kladdeId` and `st.state`, joined; kept in memory for the shell's lifetime), checked on frame load. Kassekladde content returns true only for `afvist`, a refused save the user must fix (§4.3); a saved difference is normal while a multi-line bilag is being entered and never signals. The topbar button's `aria-label` becomes `t('8701')`. | opening help (acknowledges), or the state goes away |
| tænker | `thinking` | reserved; only `SaldiHelp.setEngineState('thinking')` | engine |
| svarer | `answering` | reserved; only `SaldiHelp.setEngineState('answering')` | engine |

  Precedence: `thinking` > `answering` > `signal` > `ready`. Nothing opens or moves by itself. There is no idle blink and no global input listener for one (D11); `blink` is kept only in the copied CSS for a later thumbs-up.

### 1.11 Search / ask field

Port `searchBox()` and `search()` (`core.js:606-686, 1218-1288`) with the following changes:
- Placeholder `t('8702')` "Søg i hjælpen, eller spørg Saldi AI".
- Hits come only from the current page's content, matched against the texts as fetched for the user's language (§3.4): tasks (title, why, Vis mig label and steps), prepared questions (q, the search keywords for the language, intro and steps), the page's fields (label and text; changed in review round 2), the page's shortcuts and codes (text and keys; review round 2), the tour row (words starting `rundvis|tur|rundt|vis`, only when the tour count is > 0) and the shortcuts row (`genvej|tast|shortcut|alt`). Max 3, under the group head `t('8748')` "Til denne side". No web guides (`SaldiHelpContent.guideLinks === false`).
- Ranking (review round 2): each part has a weight, 3 for what names the hit (a task's title, a question's q and search keywords, a field's label), 2 for what describes it (a task's why and Vis mig label, a question's intro, a shortcut's text and keys), 1.5 for a field's text and 1 for steps. A query word counts with its best part, times 1 for the same word, .9 when one word starts the other and .8 for the end of a compound (`kladde` → `kassekladde`) or the same stem with an inflection ending (`bogfører` → `bogføres`). When every query word hits a part of weight 2 or more the hit gets 2 more, and 1 more when the query names all of it (every word of its title, question or label other than stop words). The tour and shortcuts rows score 5. Equal scores keep the page's own order for its state: the index in `page.order(ctx, st)` for tasks, in `page.questionOrder(ctx, st)` for questions, the field the user stood in first, then the declared order. Word matching: a 2-letter word only as the same word; a prefix needs 4 letters (3 with at most 3 more, any length while it is the last word being typed); the shorter word as the prefix of the longer needs 5 letters (4 with at most 3 more); `é` is folded to `e`; `kredit…` and `kreditor…` never match. So `kontrolkonto` no longer matches `kontrolsum` or `kontrollér`, and `afstemning` matches `afstem`.
- A field hit expands to the field's text, with Vis mig on the field the user stood in when it has that key, else on the first visible field with the key. A shortcut or code hit is a plain line with its text and keys (no action).
- Stop words and contact words come from `SaldiHelpContent` for the user's language (`da`, `en` or `no`).
- A contact word (whole word) shows the contact card first and makes no AI hand-off.
- The last row is always `t('8712')` "Spørg Saldi AI: »<text>«" → `SaldiHelp.ask(text)` (§6). The user's text is put into the row with `textContent` (§1.14), never into markup.
- ↑ ↓ move between result rows only; they never move the caret in the input. Enter on a row activates it. Enter in the input with no row selected activates the last row. The first Esc in a filled field clears it.
- A task or question hit expands inline to its steps (D5) with its Vis mig and, for a question, the link `t('8763')` "Spørg Saldi AI om dette" (hands off the question text).

### 1.12 Keyboard, focus and Esc

Bound in the shell document and, per load, in the frame document.

| Key | Where | Action |
|---|---|---|
| `?` | outside `input/textarea/select/[contenteditable]`, no Ctrl/Alt/Meta | `preventDefault()`; panel closed → open with focus in search (keyboard open: no flight); open → focus search. |
| F1 | anywhere | `preventDefault()` (Chrome opens its own help otherwise). In the frame, a field `e.target` becomes `S.lastField` first (§1.6). Closed → open (from a page field: field help first, focus search). Open and focus in a page field → show that field's help (`render`), set the return target to that field and caret, focus search. |
| Esc | see order below | |
| Ctrl/Cmd+K, `/` | unchanged | `topbarSearchKeys` (`main.php:774-783`) |
| Alt+L | unchanged | `topbarGoBack` (`main.php:586-588`) |

Esc order (shell: a `keydown` listener on `window` in **capture** phase, so it runs before `main.php:1014-1018`; frame: the per-document listener):
1. `e.defaultPrevented` → do nothing (the page, the autocomplete, the tour card handled it).
2. A topbar pop or the search is open (`.topbar-pop.open, .topbar-search.open`) → do nothing; `main.php` closes it.
3. Focus is in the panel's search field and it has text → clear it, `preventDefault()`.
4. A Vis mig ring is shown → end it; focus stays where it is if it is in the page. A tour is running and the key came from the shell → `fwin().SaldiTour.stop()`.
5. Focus is inside the panel → close the panel and return focus to the last page element the user left (with caret), else to the trigger. `preventDefault()`.
6. Focus is in the page and the panel is `overlay` or `sheet` → close the panel, leave focus and caret where they are. Do not `preventDefault()` (the page still gets its Esc).
7. Focus is in the page and the panel is `docked` or pinned → do nothing.

Focus rules:
- Open: focus the search field (`preventScroll`), except on a coarse pointer without a keyboard open (then the panel root, `tabindex="-1"`, so no on-screen keyboard pops up).
- Return: to a frame element, call `fwin().focus()` first, then `el.focus({ preventScroll: true })` and `setSelectionRange(start, end)` in try/catch. An `INPUT` in a frame whose window has `focusAccountQuietly()` (the kassekladde, `accountAutocomplete.js:2401-2407`) is focused through that function instead: the account lookup opens its list on every focus that is not a keyboard move (`accountAutocomplete.js:310-321`), so a plain `focus()` would open it by itself when help closes or a tour ends. It focuses the field as a Tab into it does (it may scroll a field that is out of view into view). `tutorial.php` does the same when a tour gives focus back (§5.2).
- `window.blur` (`main.php:1021`) and clicks outside the topbar (`main.php:1009-1013`) call `topbarCloseAll()`; the panel is not a topbar pop and is never closed by them. `topbarCloseAll()` no longer resets the Hjælp button's `aria-expanded` (§2.1, `:434`).

### 1.13 Generic panel for every other page, and the old Assist menu

On a route without content (dashboard, ordre, debitor, settings, …) the panel shows:
- Title "Hjælp" + the page label from `topbarAssistPage().label` (`main.php:787-802`), set with `textContent`: it is page data (the last breadcrumb item the frame posted, or the frame's `document.title`). On Oversigt (`index/dashboard.php`, no breadcrumb, `<title>Overblik</title>` hard-coded in Danish) the label is the sidebar entry's text instead (`#dashboard .link_name`, findtekst 2224 "Oversigt" in the user's language), so the panel says "Hjælp Oversigt" as the sidebar does. The head is re-rendered when a `saldi:breadcrumb` message arrives while the panel is open (§1.5), because right after a load the label is `doc.title` until the page answers. No label (unreadable frame, §1.5.1) → title "Hjælp" alone.
- Search/ask (only contact words and the "Spørg Saldi AI" row).
- No "Om siden", no "Sådan gør du", no field box.
- Rows:
  - "Rundvisning af siden (N trin)" (`t('8709')`) when `fwin().SaldiTour && SaldiTour.count() > 0` → `SaldiTour.start({ focus: true })`. If instead the page registered `page_help(['tour' => <selector other than '#tutorial-help'>])`, show "Rundvisning af siden" without a count (findtekst 6455, in `data-txt`) → `topbarAssistRun('tour')`. (No page does that today. `topbarAssistPage()` itself sets `help.tour` to `'#tutorial-help'` or `'saldi:tour'` for a SaldiTour, §2.1; both are the counted row above, not this one.)
  - "Tastaturgenveje" (findtekst 6024) always; it expands to the global list (§4.1 `globalShortcuts`). When the page registered `help.shortcuts` (only `systemdata/settings.php:187` today), the list ends with the row `t('8754')` "Sidens egne genveje" → `topbarAssistRun('shortcuts')`.
  - "Vejledning" (findtekst 92) when the page registered `help.guide` (only `systemdata/settingsSection.php:120-121`) → `topbarAssistRun('guide')`.
  - `t('8713')` "Spørg Saldi AI om <label>" → `SaldiHelp.ask('')` (opens the Guide empty, §6); without a label `t('8777')` "Spørg Saldi AI".
- Footer: Kontakt support, Hvad får Saldi AI at vide?

| Old Assist menu (`topbar.php:401-406`) | Phase 1 |
|---|---|
| Spørg Assist om <side> (6453/6454) | "Spørg Saldi AI om <side>" row and the search field's last row |
| Rundvisning af siden (6455) | "Rundvisning af siden (N trin)", only where `SaldiTour.count() > 0` |
| Tastaturgenveje (6024), `?` | "Tastaturgenveje" row with global keys, plus "Sidens egne genveje" where registered; `?` opens help (D7) |
| Vejledning (92) | "Vejledning" row where registered |

### 1.14 How text reaches the DOM (hard rule)

Every string the panel or the frame callout shows is data, never markup. That covers the page label (frame breadcrumb or `document.title`), the page name, the frame URL in "Sidens adresse", the user's search text in "Spørg Saldi AI: »…«", the chrome strings in `data-txt`, and the help texts from `helpTexts.php` (tenant admins can edit any text in `systemdata/tekster.php`, so these are not trusted either).

- Exactly one helper produces formatted text: `md(text)`. It returns a `DocumentFragment` built with DOM calls: split the string on `**`, append the even parts as text nodes (`document.createTextNode`) and the odd parts as `<strong>` elements whose `textContent` is the part; an unbalanced trailing `**` is shown literally. Because nothing is parsed as HTML, `& < > " '` need no escaping and cannot become markup. For the frame callout, `md(text, doc)` builds the nodes in the frame document.
- Everything else uses `createElement`/`createElementNS` and `textContent`, `setAttribute`, `classList`. No `innerHTML`, `outerHTML`, `insertAdjacentHTML`, `document.write` and no template-string HTML anywhere in `saldi-help.js`, also not for constant markup: icons are built with `createElementNS` from a constant table of path strings, the symbol is cloned from the topbar (§1.10), and the injected `<style>` is set with `textContent`.
- The tour card's "Spørg Saldi AI om dette" sends plain text: `tutorial.php` strips the step's tags with `new DOMParser().parseFromString(html, 'text/html').body.textContent` (an inert document: no scripts run, no images load), never with a detached element's `innerHTML` (§5.2).
- Check (SHELL done criteria, §12.1): `grep -nE 'innerHTML|outerHTML|insertAdjacentHTML|document\.write' javascript/saldi-help.js` prints nothing.

---

## 2. Changes to existing files (SHELL)

Every changed PHP file gets a history line at the bottom of its header block, the header date moved to the day of the change, and the copyright year range ending in 2026 (guardrail `file-history`). The header marker stays `ver 5.1.0` (`includes/version.php`; guardrail `file-version`).

### 2.1 `index/main.php`

| Anchor | Change |
|---|---|
| `:8` | `// ---- index/main.php --- ver 5.1.0 --- 2026-11-07 ---` |
| after `:85` (last history line) | `// 20261107 CL/ASR Hjælp variant B: the topbar's Hjælp symbol opens one help panel docked beside the page (javascript/saldi-help.js), for now only for tenants with settings help/panel=on (SALDI_HELP_PANEL unset or pilot); everyone else keeps the Assist menu.` |
| after `:117` (`$topbar = topbar_context(…)`) | `require_once __DIR__ . '/../includes/saldi_help_panel.php';`<br>`$topbar['helpPanel'] = saldi_help_panel_enabled((string) getenv('SALDI_HELP_PANEL'), saldi_help_panel_needs_tenant((string) getenv('SALDI_HELP_PANEL')) ? saldi_help_panel_tenant_setting() : null);`<br>`$topbar['helpSupportSend'] = saldi_help_support_to() !== '';`<br>`$topbar['helpRecords'] = getenv('SALDI_ASSIST_RECORDS_ENABLED') === '1';` (same test as `main.php:1330`)<br>(runs after `online.php`, so the tenant connection is active) |
| after `:154` | `<?php if (!empty($topbar['helpPanel'])) { ?><link href='../css/saldi-help.css?v=2' rel='stylesheet'><?php } ?>` |
| after `:422` (inside `.home-content`, before its `</div>` at `:423`) | `<?php if (!empty($topbar['helpPanel'])) { topbar_help_host($topbar, (int) $sprog_id); } ?>` |
| `:434` | `document.querySelectorAll('.topbar [aria-expanded="true"]:not(#topbar-help-btn)')…` (gotcha: `window.blur` fires on every click into the frame) |
| `:792` | offer the tour whenever the frame's `SaldiTour` has visible steps, with or without a Hjælp cell (code below the table); the old menu stops offering Rundvisning on pages whose Hjælp cell was dead and starts offering it on the booking pages, which have a tour but no cell (tenants without the panel) |
| `topbarAssistRun()` | a `'saldi:tour'` target calls the frame's `SaldiTour.start({ focus: true })` instead of clicking a selector (code below the table) |
| `:837` (first line of `topbarAssistKeys`) | add `if (window.SaldiHelp && window.SaldiHelp.enabled) return;` (help owns `?` when the panel is on) |
| after `:1336`, before `</html>` | `<?php if (!empty($topbar['helpPanel'])) { ?>`<br>`<script src="../javascript/saldi-help-content.js?v=2"></script>`<br>`<script src="../javascript/saldi-help.js?v=2"></script>`<br>`<?php } ?>` |

`:792` becomes:

```js
    if (!help.tour && doc) {
      let n = 0;
      try { n = (iframe.contentWindow.SaldiTour && iframe.contentWindow.SaldiTour.count()) || 0; } catch (e) { n = 0; }
      if (n > 0) help.tour = doc.getElementById('tutorial-help') ? '#tutorial-help' : 'saldi:tour';
    }
```

and `topbarAssistRun()` gets, right after `if (!target) return;`:

```js
    if (target === 'saldi:tour') {
      try { page.doc.defaultView.SaldiTour.start({ focus: true }); } catch (e) { /* the page went away */ }
      return;
    }
```

Without the second branch the booking tours (`rental/items.php`, `rental/edit.php`, `rental/daysoff.php`, `rental/index.php`: a tour, no Hjælp cell, no `page_help()`) could not be reached by any tenant without the panel, now that `tutorial.php` no longer starts them by itself (§5.3).

No other line of `main.php` changes (besides the cache version `?v=2` of the help assets after review round 1). `topbarAssistMenu`, `topbarAssistAsk`, `topbarAssistRun`, `topbarOpenAssist` and `topbarHideAssistEntry` stay as they are (used for tenants without the panel and by the panel's generic rows). The inline iframe `onLoad` (`:409-418`) is not edited.

### 2.2 `index/mainIncludes/topbar.php`

| Anchor | Change |
|---|---|
| `:7` | `// ---- index/mainIncludes/topbar.php --- ver 5.1.0 --- 2026.11.07 ---` |
| after `:42` | `// 20261107 CL/ASR Hjælp variant B: the Saldi AI symbol in a 28 px disc with "Hjælp" replaces the Assist button and its menu where the help panel is on (topbar_help_symbol(), topbar_help_host()); elsewhere the Assist button stays.` |
| after `:349` | new `function topbar_help_symbol(string $gradientId): string` returning the landing SVG (below) with `sa-g-<gradientId>`. Keep `topbar_assist_icon()` (tenants without the panel). |
| `:399-409` | wrap: `<?php if (empty($ctx['helpPanel'])) { ?>` the existing Assist block unchanged `<?php } else { ?>` the new block below `<?php } ?>` |
| end of file | new `function topbar_help_host(array $ctx, int $sprogId): void` (below) |

New topbar block (replaces the Assist slot only when enabled):

```php
    <div class="topbar-item topbar-help-item">
      <button type="button" class="topbar-help" id="topbar-help-btn"
        aria-label="<?= $t('8700|Hjælp og Saldi AI') ?>" title="<?= $t('2564|Hjælp') ?> (?)"
        aria-controls="saldi-help" aria-expanded="false"
        onclick="if (window.SaldiHelp) { SaldiHelp.toggle({ source: 'topbar', trigger: this, keyboard: event.detail === 0 }); } else { topbarOpenAssist(); }">
        <span class="ai-avatar ai-avatar--28 ai-avatar--seat" data-here="true" data-ai-state="ready" aria-hidden="true"><?= topbar_help_symbol('top') ?></span>
        <span class="topbar-help-lbl"><?= $t('2564|Hjælp') ?></span>
      </button>
    </div>
```

The inline fallback means a failed `saldi-help.js` still opens the Guide. `saldi-help.js` must not bind a second click handler on the button.

`topbar_help_symbol()` returns exactly (one line in the source; `%s` = `$gradientId`, escaped with `topbar_h()`):

```html
<svg viewBox="6 14 34 42" aria-hidden="true" focusable="false"><defs><linearGradient id="sa-g-%s" gradientUnits="userSpaceOnUse" x1="10" y1="16" x2="38" y2="54"><stop offset="0" stop-color="#1A66B8"></stop><stop offset="1" stop-color="#7A2CBC"></stop></linearGradient></defs><circle class="sa-glow" cx="13.5" cy="28.8" r="11.4"></circle><circle class="sa-halo" cx="13.5" cy="28.8" r="8.6"></circle><circle class="sa-ring sa-ring--outer" cx="13.5" cy="28.8" r="11.2"></circle><circle class="sa-ring sa-ring--inner" cx="13.5" cy="28.8" r="8.4"></circle><path class="sa-track" d="M35.6 18.9 L17.9 37.8 C16.4 39.5 15.9 40.9 15.9 43.2 L15.9 44.6 C15.9 49 19.4 51 23.4 51 C27.6 51 30.9 49 30.9 46.4"></path><path class="sa-stroke" pathLength="100" stroke="url(#sa-g-%s)" d="M35.6 18.9 L17.9 37.8 C16.4 39.5 15.9 40.9 15.9 43.2 L15.9 44.6 C15.9 49 19.4 51 23.4 51 C27.6 51 30.9 49 30.9 46.4"></path><circle class="sa-dot" cx="13.5" cy="28.8" r="5.808" fill="url(#sa-g-%s)"></circle></svg>
```

`topbar_help_host()`:

```php
function topbar_help_host(array $ctx, int $sprogId): void
{
	$lang = array(1 => 'da', 2 => 'en', 3 => 'no');
	$f = function (string $s): string { return topbar_utf8($s); };
	$txt = array(
		'8700' => $f(findtekst('8700|Hjælp og Saldi AI', $sprogId)),
		'8701' => $f(findtekst('8701|Hjælp og Saldi AI, har et forslag til siden', $sprogId)),
		// … one literal line per id: 8702–8759 (not 8711), 8760, 8763, 8771–8777, 2564, 6024, 6455, 92, 5148, 636, 641, Danish exactly as in §8.1
	);
	print '<aside id="saldi-help" class="saldi-help hs-b hc-root" hidden tabindex="-1" aria-labelledby="saldi-help-title"'
		. ' data-lang="' . topbar_h($lang[(int) $ctx['langId']] ?? 'da') . '"'
		. ' data-support-send="' . (!empty($ctx['helpSupportSend']) ? '1' : '0') . '"'
		. ' data-records="' . (!empty($ctx['helpRecords']) ? '1' : '0') . '"'
		. ' data-signal="0"'   // D11: the "har noget til dig" state stays off in phase 1
		. ' data-version="1"'
		. ' data-txt="' . topbar_h(json_encode($txt, JSON_UNESCAPED_UNICODE)) . '"></aside>' . "\n";
}
```

The `$txt` array holds every chrome id the panel uses: 8700–8759 except 8711 (§8.1), 8760, 8763, 8771–8777, and the reused 2564 (Hjælp), 6024 (Tastaturgenveje), 6455 (Rundvisning af siden, the generic row without a count, §1.13), 92 (Vejledning), 5148 (Forstået), 636 (Vis alle) and 641 (Vis egne). 636 and 641 are there for the kladdeliste detector (§4.2): it compares the page's toggle text with the same tenant row the page printed. Each line is a literal `findtekst('NNNN|Dansk', $sprogId)` with the Danish exactly as in §8 (never built from a variable), so the `tekster` guardrail can compare it with `tekster.csv`. The JSON keys are the ids as strings; `saldi-help.js` reads them with `t('8700')`, which returns the string or, if a key is missing, the id in brackets (visible in testing, never silent).

Before the array, `topbar_help_host()` calls `saldi_help_chrome_prefetch(saldi_help_chrome_ids(), $sprogId)` (`includes/saldi_help_panel.php`, review round 2): one `SELECT tekst_id, tekst FROM tekster WHERE sprog_id = … AND tekst_id IN (…) ORDER BY id` puts the first row per id, when its text is not `''` or `-`, into `findtekst()`'s session cache (`findtekst_session_var()`). `login.php` starts an empty session on every login, so without it the first shell load per login ran two queries per id (about 150). Missing, empty and `-` rows are left to `findtekst()`, which still seeds them. PHPUnit checks the id list against the `findtekst()` lines of `topbar_help_host()`.

### 2.3 `css/saldi-help.css` (new)

Header comment `/* ---- css/saldi-help.css --- ver 5.1.0 --- 2026.11.07 --- 20261107 CL/ASR Created: Hjælp variant B … */`.

Contents, in this order:
1. Token shim on `#saldi-help, .saldi-help-flyer` for the kit tokens the shell lacks: `--hover:#eef2f8; --border-field:#c6d2e4; --border-field-hover:#a9b8cf; --focus:#1B68BD; --focus-ring:0 0 0 2px rgba(79,160,238,.45); --user-primary:#114691; --user-primary-hover:#0e3a78; --row-sel:#eaf3fd`, redefined under `[data-theme="dark"]` and under `@media (prefers-color-scheme: dark) { [data-theme="system"] … }` (the two selectors `saldi-theme.css:25-52` uses) as `--hover: rgba(255,255,255,.06); --row-sel: rgba(79,160,238,.16); --border-field: #34466e; --border-field-hover: #4a5f8c; --focus: #4FA0EE`. Without that a hovered or selected row would be near white with near-white text in dark mode. Everything else uses `css/saldi-theme.css` (`--surface`, `--surface-2`, `--border`, `--text`, `--text-2`, `--text-3`, `--link`, `--saldi-blue`) and `css/saldi-fonts.css` (`--font-ui`, `--font-display`).
2. Font reset: `#saldi-help, #saldi-help *, .saldi-help-flyer { font-family: var(--font-ui); box-sizing: border-box; }` and `#saldi-help h2, #saldi-help h3, #saldi-help .hc-sec { font-family: var(--font-display); }` (`sidebar_style.css:1-6` sets Segoe UI and zero margins on `*`). Lists get explicit padding where needed.
3. Topbar slot:
   ```css
   .topbar-help { position: relative; display: flex; align-items: center; gap: 8px; height: 34px; min-width: 34px; padding: 0 3px; border: 0; border-radius: 10px; background: transparent; color: var(--topbar-txt); cursor: pointer; }
   .topbar-help:hover, .topbar-help[aria-expanded="true"] { background: var(--topbar-hover); }
   .topbar-help:focus-visible { outline: 2px solid var(--topbar-accent); outline-offset: 2px; }
   .topbar-help .ai-avatar { width: 28px; height: 28px; transition: none; }
   .topbar-help .ai-avatar svg { width: 14px; height: 17.5px; }
   .topbar-help .ai-avatar--seat:not([data-here="true"]) { background: #F7F4EF; border-color: #E3DCD2; }
   .topbar-help .ai-avatar--seat:not([data-here="true"]) svg { visibility: visible; opacity: .3; }
   .topbar-help-lbl { display: none; font-family: var(--font-display); font-weight: 500; font-size: .8125rem; line-height: 1; color: var(--topbar-txt); white-space: nowrap; }
   @media (min-width: 1240px) { .topbar-help { padding: 0 11px 0 3px; } .topbar-help-lbl { display: inline; } }
   ```
   The label uses a viewport query, not `.topbar-narrow` (that class follows the bar width, `main.php:589-598`; at 1440 px the bar is 1230 px). `.topbar-help-lbl` wins over `.topbar :where(:not(.bx))` (`topbar.css:39`) because both have one class and this file loads later.
4. Dark mode: the disc keeps its light fill `#F7F4EF` / `#E3DCD2` in every theme (Forslag decision 2: the disc is what gives contrast in dark). Only the label, hover and the panel follow the tokens, which `saldi-theme.css:25-52` re-maps under `[data-theme="dark"]` and `[data-theme="system"]` with `prefers-color-scheme: dark`. No extra dark rules are needed beyond using tokens and the shim's dark values (item 1); the shell is always `data-theme="light"` on this branch (`online.php:92,428`).
5. The symbol CSS (§1.10), the flyer (`core.css:140-143` renamed to `.saldi-help-flyer`, `z-index: 90`).
6. The panel: port `prototype_hjaelp/help/surface-b.css` and the `.hc-*` rules of `core.css` that variant B uses (rows, rows list, sections, buttons, links, ×, search and results, field box, keys list, strip, support card and form, receipt, open dot, disclosure, sheet handle), with:
   - root selector `#saldi-help` instead of `.hs-b` where the prototype positions the panel; the class `hs-b` stays on the root so scoped rules port unchanged;
   - all font sizes in rem (px / 16; house rule, `saldi-fonts.css:5`);
   - every hard-coded `44px` top and `210px` left removed (placement is §1.4 and §1.8);
   - `z-index: 9500` (`surface-b.css:14`) replaced by §1.4's values; drop `html.sh-menu-open` (`surface-b.css:164`);
   - drop `.ai-pop`, `.ai-chat*`, `#hc-err`, `.hc-hint`, `.hc-thread`, `.hc-feedback`, `.hc-vote` (not used in phase 1);
   - never use the class names `text`, `bx-menu`, `small`, `wide` inside the panel (`sidebar_style.css:386-400,483-511` style or hide them).
7. `@media print { #saldi-help, .saldi-help-flyer { display: none !important } }`.
8. Reduced motion: no enter/leave animation, no flight (`@media (prefers-reduced-motion: reduce)`).

### 2.4 `javascript/saldi-help.js` (new)

Header: `// ---- javascript/saldi-help.js --- ver 5.1.0 --- 2026.11.07 ---` plus `// 20261107 CL/ASR Created: …`. One IIFE, `'use strict'`, no dependencies, ES2018 (the guardrail `js-syntax` parses it).

It implements §1.4–§1.14, §6, §7 and §10, by porting from the prototype (map in §12.1) and defines `window.SaldiHelp` (§3.1). The prototype builds rows with template strings and `innerHTML` (`surface-b.js` `h()`); the port builds every node with DOM calls instead (§1.14).

Help texts: on the first open (or F1/`?`) on a route whose page has content, the shell fetches `../index/helpTexts.php?group=<page.group>` once (§3.4) and keeps the map in memory for the shell's lifetime, per group. `T(key, ...args)` returns the text with each `%s` replaced in order, or `[key]` and one `console.warn` when the key is missing. While the fetch runs, the panel shows its head, the search field and the generic rows with `aria-busy="true"`; when it fails (network, session gone, non-JSON answer), the page is shown as a generic page (§1.13) for this open and the fetch is retried on the next open.

Rendering of a page with content (`render()`), top to bottom (shots b2/b5):
1. Head: 40 px mascot seat (light empty seat), `h2#saldi-help-title` "Hjælp" + grey page name (`T(...page.name(ctx, st))`, `textContent`), pin toggle "Fastgør"/"Løsn" (`t('8730')`/`t('8731')`, titles `t('8732')`/`t('8733')`, or `t('8734')` when pinned but overlaying; hidden in sheet mode), × (`aria-label` `t('8735')`, title `t('8747')`).
2. Search/ask field.
3. "Om siden" (`t('8703')`): `page.about(ctx, st)` (1–2 short paragraphs).
4. Field box (§1.6) when there is one (placed above "Om siden" after F1/`?` from a field).
5. "Sådan gør du" (`t('8706')`): `page.order(ctx, st)` → first 3 existing task ids. Each row: title, grey `why`, chevron; click toggles the steps (`aria-expanded`, numbered list; each step through `md()`, §1.14). A quiet underlined "Vis mig" (`t('8707')`, `aria-label` `t('8708')`) at the right when `task.show` resolves to a visible element (its label in the strip and callout is `show.label`, except for kind `field` on a field the page describes in `fields`: then that field's own label, so KK4 says "Debet" or "Kredit" for the field it rings); in overlay mode, when that element lies under the panel, a grey note `t('8745')` / `t('8746')` / `t('8758')` (by kind).
6. A group under a hairline: "Rundvisning af siden (N trin)" (`t('8709')`, desc `t('8710')`) when `SaldiTour.count() > 0`; "Tastaturgenveje" (6024), expandable: the page's shortcuts under the headings `t('8774')` "Taster" (group `keys`) and `t('8775')` "Koder i felterne" (group `codes`), then the global list under `t('8776')` "Overalt i Saldi", then `page.shortcutsNote`. Headings are plain `h3`-level text in the list, no chips.
7. Footer: Kontakt support, Hvad får Saldi AI at vide?

Persistence (§10), mode (§1.4), strip (§1.8), Esc and keys (§1.12), symbol states (§1.10), support (§1.9), hand-off (§6).

### 2.5 `includes/saldi_help_panel.php` (new)

Modelled on `includes/saldi_assist_guide.php`. Header block with `ver 5.1.0`, date 2026-11-07, `// 20261107 CL/ASR Created.`

```php
/** SALDI_HELP_PANEL: '' (unset) or 'pilot' = only tenants with help/panel = 'on' (phase-1 default, D12); 'on' = every tenant unless its row says 'off'; 'off' or anything else = the old Assist menu. */
function saldi_help_panel_enabled(string $mode, ?string $tenantSetting): bool
{
	if ($mode === '' || $mode === 'pilot') return $tenantSetting === 'on';
	if ($mode === 'on') return $tenantSetting !== 'off';
	return false;
}
function saldi_help_panel_needs_tenant(string $mode): bool { return $mode === '' || $mode === 'on' || $mode === 'pilot'; }
/** The tenant's own row (not a user's or a till's), as saldi_assist_guide_tenant_setting(). */
function saldi_help_panel_tenant_setting(): ?string
{
	$row = db_fetch_array(db_select(
		"SELECT var_value FROM settings WHERE var_grp = 'help' AND var_name = 'panel'"
		. " AND COALESCE(user_id, 0) = 0 AND COALESCE(pos_id, 0) = 0 ORDER BY id DESC LIMIT 1",
		__FILE__ . ' linje ' . __LINE__));
	return $row ? (string) $row['var_value'] : null;
}
/** Recipient of "Skriv til os": SALDI_HELP_SUPPORT_TO, else $helpSupportTo from includes/connect.php, else '' (form off). Never $errorReportTo. */
function saldi_help_support_to(): string
{
	$to = trim((string) getenv('SALDI_HELP_SUPPORT_TO'));
	if ($to === '' && isset($GLOBALS['helpSupportTo'])) $to = trim((string) $GLOBALS['helpSupportTo']);
	return filter_var($to, FILTER_VALIDATE_EMAIL) ? $to : '';
}
```

Unknown or upper-case modes switch the panel off, as the Guide's switch does (`tests/includes/saldiAssistGuide.test.php:29`): a typo restores the old menu, which is the safe direction.

After the owner has approved the ssl12 test, the default flips in a separate one-line change: `''` moves to the `'on'` line (`if ($mode === '' || $mode === 'on') …`, `if ($mode === 'pilot') …`), with the test matrix in §2.7 updated in the same commit and the history line `// <date> CL/ASR The help panel is on by default; SALDI_HELP_PANEL=pilot keeps the pilot.`

### 2.6 `index/helpSupport.php` (new)

A JSON endpoint modelled on `index/fejlrapport.php` (session start, `$permission_key='any'`, connect + online, an answer function that clears output buffers and always answers JSON, a shutdown function for early exits). Header `ver 5.1.0`, `// 20261107 CL/ASR Created: the JSON endpoint behind "Skriv til os" in the help panel.`

- Method POST only (405 otherwise).
- CSRF: `X-CSRF-Token` header or `csrf_token` field, compared with `hash_equals((string) $_SESSION['csrf_token'], $sent)` after checking that both are non-empty (403 `csrf`), exactly as `fejlrapport.php:80-82`.
- Recipient: `saldi_help_support_to()`; `''` → 503 `not_configured` (the UI never posts then).
- Fields: `message` string, 1–2000 characters after trim (400 `empty` / `too_long`); `page` ≤ 500 characters, must start with `/` (else dropped); `page_label` ≤ 120 characters. Any field sent as an array → 400 before anything else.
- Rate: at most 5 per session per hour (timestamps in `$_SESSION['help_support_sent']`) → 429 `rate`.
- Mail: `include_once(__DIR__ . '/../includes/errorReport.php')` (it only defines guarded functions; set `$saldi_error_json = true` first, as `fejlrapport.php:33-36`) and reuse its helpers: every header value and every value that goes into the subject passes `saldi_error_header_value()` (`errorReport.php:627-630`: UTF-8, every control character including CR/LF replaced by a space, trimmed); the subject `Saldi Hjælp: <regnskab> / <brugernavn>` is cut to 200 characters and, because it is not ASCII, encoded with `mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n")`, as `errorReport.php:659-661`. Headers as `errorReport.php:714-723`: `From: kan_ikke_besvares@saldi.dk`, `MIME-Version: 1.0`, `Content-Type: text/plain; charset=UTF-8`, `Content-Transfer-Encoding: 8bit`, `X-Mailer: Saldi`, and `Reply-To` = the user's e-mail from `brugere.email` for `$bruger_id > 0` when it passes `FILTER_VALIDATE_EMAIL` after `saldi_error_header_value()`. The body is UTF-8 (`saldi_error_utf8()` on database values), CRLF normalised to LF, no line over 900 bytes (wrap as `errorReport.php:702-711`). Body lines: regnskab, db, brugernavn, bruger_id, page label, page address (only when sent), app version, time (Europe/Copenhagen), then the message. `mail()` false → 502 `mail`.
- Answer `{ "ok": true }` or `{ "ok": false, "error": "<code>" }`.

### 2.7 `tests/includes/saldiHelpPanel.test.php` (new)

PHPUnit, as `tests/includes/saldiAssistGuide.test.php`: data provider over `saldi_help_panel_enabled()` (`'' / null → false`, `'' / 'on' → true`, `'' / 'off' → false`, `'pilot' / 'on' → true`, `'pilot' / null → false`, `'pilot' / 'off' → false`, `'on' / null → true`, `'on' / 'off' → false`, `'off' / 'on' → false`, `'ON' / 'on' → false`, `'Pilot' / 'on' → false`), `saldi_help_panel_needs_tenant()` (`''`, `'pilot'`, `'on'` → true; `'off'` → false), and `saldi_help_support_to()` with `putenv()` (valid, invalid, unset → `''`). Run with `php vendor/bin/phpunit tests/includes/saldiHelpPanel.test.php`.

---

## 3. Interfaces between the builders (verbatim)

Every string that crosses these interfaces (help texts, chrome strings, page labels, URLs, the user's query, tour step text) is data. The shell puts it into the DOM only through `textContent` or `md()` (§1.14); CONTENT and TOURS never hand the shell markup.

### 3.1 `window.SaldiHelp` (SHELL provides)

```js
window.SaldiHelp = {
  enabled: true,
  version: 1,
  open(opts),            // opts: { source: 'topbar'|'classic'|'key'|'api', trigger?: Element, keyboard?: boolean,
                         //         view?: 'home'|'support', focus?: 'search'|false, prefill?: string }
                         // first line: topbarCloseAll() (main.php:431-435), so a bell, user or language menu
                         // that is open does not stay over the panel
  close(opts),           // opts: { restoreFocus?: boolean (default true), keyboard?: boolean }
  toggle(opts),          // open → close; minimised → end Vis mig / tour; closed → open
                         // §19 merged mode: closed → saldi:help:open { focus: 'help' } (the chat), the panel stays closed
  isOpen(),              // boolean
  refresh(),             // drop the cached st, recompute the page context and re-render if open
  showMe(target, opts),  // target: selector string (frame document) or a frame element; opts: { label, kind: 'button'|'field'|'status'|'row', note?, focus? }; → boolean
  endShowMe(),
  ask(question),         // hand-off to the Guide (§6); '' opens the Guide empty; → 'sent'|'opened'|'none'
  setEngineState(s),     // reserved: 'thinking'|'answering'|null
  state(),               // test hook, see below
  helpState()            // §19 test hook: the last HelpState sent as saldi:help:state, null before the first one
};
// state() → { open, view, mode: 'docked'|'overlay'|'sheet'|null, pinned, minimised, busy, route: string|null, page: true|false,
//             texts: 'none'|'loading'|'ready'|'failed', pageState, flags, mood, lastField: key|null, ring: boolean, tour: boolean,
//             guide: 'ask'|'open'|'none', merged: boolean (§19 merged mode now), back: boolean (§19 the strip leads back to the chat) }
```

In merged mode (§19) the shell also sends and receives window events: `saldi:help:state` (detail: a HelpState or `null`) and `saldi:help:open` (detail `{ focus }`) out, `saldi:help:action` in. §19.2 lists them.

Frame elements (a `target`, the result of a `show.target` function, `ctx.field.el`) belong to the frame's realm. In the shell, `x instanceof Element` and `x instanceof HTMLInputElement` are always false for them. Type checks use `x && x.nodeType === 1` and `x.tagName` (`'INPUT'`, `'SELECT'`, …); styles are read with `ctx.win.getComputedStyle(x)` (the frame window), as §1.7 does for `visibleRect`.

### 3.2 `window.SaldiHelpContent` (CONTENT provides)

```js
/** Key: a descriptive tekst key in importfiler/tekster_keyed.tsv, 'help.<group>.…' or 'help.common.…' (§8.2).
 *  Msg:  a Key, or [Key, ...args] where each arg replaces the next %s.
 *  The shell resolves keys with T() from the texts fetched for the user's language (§3.4).
 *  Markup inside a text: **bold** only, and only for the exact on-screen name of a button or field (rendered by md(), §1.14). */
window.SaldiHelpContent = {
  version: 1,
  guideLinks: false,                                    // Forslag decision 10
  support: { phone: '46 90 22 08', phoneIntl: '+45 46 90 22 08', tel: '+4546902208', mail: 'support@saldi.dk',
             openHour: 9, closeHour: 16, tz: 'Europe/Copenhagen' },   // phone for da, phoneIntl for en and no (§1.9)
  contactWords: { da: [/* … */], en: [/* … */], no: [/* … */] },   // search vocabulary, never shown
  stopWords:    { da: [/* … */], en: [/* … */], no: [/* … */] },   // search vocabulary, never shown
  globalShortcuts: [ { keys: '?', id: '8749' }, /* … §4.1: chrome ids from data-txt */ ],
  pages: {
    'finans/kladdeliste.php': Page,
    'finans/kassekladde.php': Page
  }
};

/** Page */
{
  group: 'kl' | 'kk',                                     // the text group: helpTexts.php?group=…, keys help.<group>.*
  name(ctx, st)  → Msg,                                   // 'help.kl.name' | ['help.kk.name', '45'] | 'help.kk.name_new'
  detect(ctx)    → { state: string, flags: { [name]: boolean }, data: { [name]: any } },
  about(ctx, st) → Key[],                                 // 1–2 paragraphs for "Om siden"
  tasks:   { [id]: { title: Key, why: Key, steps: Key[], show?: Show } },
  order(ctx, st) → string[],                              // task ids in display order; the shell shows the first 3
  fields:  { [fieldKey]: { label: Key, text: Key } },     // field keys per the field-key rule (§1.6)
  fieldKey?(el) → string|null,                            // optional override of the default rule
  shortcuts: [ { keys: string, text: Key, group: 'keys'|'codes' } ],   // keys = the key names, not translated
  shortcutsNote?: Key,
  questions: { [id]: { q: Key, words: { da: string[], en: string[], no: string[] }, intro?: Key, steps: Key[], show?: Show } },
  questionOrder(ctx, st) → string[],                      // up to 3 question ids, used by search ranking
  signal(ctx, st) → boolean,                              // only read when data-signal="1" (D11)
  problem?(ctx, st) → string,                             // §18: a task id for the problem card, or ''
  variants?: { [key]: Page },                             // §24: the pages one route shows, such as one per settings section
  variant?(ctx) → string,                                 // §24: the key of the variant that serves this frame, or ''
  inPlace?: boolean,                                      // §22.10: the page redraws in place, so the shell reads it again after a click or Esc, Enter, N, and when typing pauses
  ownWords?: { da?: string[], en?: string[], no?: string[] }   // §22.10: stop words that name something on this page ("mine", the CRM's view Mine), searched on it only
}

/** Show */
{ target: string | function (ctx, st) → Element|null, label: Key, kind: 'button'|'field'|'status'|'row', note?: Key }

/** ctx, built by the shell; null for an unreadable frame (§1.5.1), in which case no Page function is called */
{ doc: Document, win: Window, url: URL, route: string, lang: 'da'|'en'|'no',
  t: (id: string) => string,                              // the chrome text lookup (data-txt), e.g. ctx.t('636')
  field: { key: string, el: Element } | null }
```

Rules for CONTENT:
- Detectors and `show.target` functions only read the DOM: no writes, no `focus()`, no `click()`, no network, no timers. Type checks on elements use `nodeType`/`tagName`, never `instanceof` (§3.1).
- Since §19 `window.SaldiHelpState.build()` calls the same page functions (`name`, `about`, `order`, `questionOrder`, `problem`) and every task's and question's `show.target` each time it builds the state for the chat, also while the panel is closed. They must stay cheap and read-only. A question's `words[lang]` goes to the chat as its search vocabulary (lower case), so it must hold only words a user would type.
- Selectors must match the real page (§4). A `show.target` that finds nothing returns `null`; the shell then shows no Vis mig.
- No UI text in the JS file: every shown string is a key in `tekster_keyed.tsv` (or a chrome id). The JS holds selectors, detectors, key names, key combinations (`'Alt+G'`) and search vocabulary only.
- No text may contain "Assist", "Sally" or an exclamation mark.

### 3.3 `window.SaldiTour` and messages (TOURS provides, inside the frame)

```js
window.SaldiTour = {
  id: 'kasseklad',          // the create_tutorial() id
  count(),                  // number of steps whose target is visible now (0 while a book-* page has not sent renderComplete)
  active(),                 // boolean
  start(opts),              // opts: { step?: 0, focus?: true } → boolean (false when count() is 0). Never called by the page itself.
  stop()                    // ends the tour like ✕ (restores focus and scroll)
};
```

Messages from the frame to `window.parent` (`postMessage(msg, location.origin)`), only when `window.parent !== window`:

| Message | When |
|---|---|
| `{ type: 'saldi:tour', id, state: 'start', step: 1, of: N }` | tour started |
| `{ type: 'saldi:tour', id, state: 'step', step: i, of: N }` | the card moved to step `i` (Næste, Forrige, ← / →); the shell re-places the strip (§1.8) |
| `{ type: 'saldi:tour', id, state: 'end' }` | tour ended, any way |
| `{ type: 'saldi:help-open', source: 'classic' }` | the Hjælp cell was clicked and `window.parent.SaldiHelp` exists (the shell had not bound yet) |
| `{ type: 'saldi:help-ask', text }` | "Spørg Saldi AI om dette" in the tour card; `text` = the step's plain text (tags stripped with `DOMParser`, §1.14), max 200 characters |

### 3.4 Help texts endpoint (CONTENT provides, SHELL calls)

`GET ../index/helpTexts.php?group=<group>` with the session cookie (same origin).

- `group` must match `/^[a-z]{2,12}$/` and have at least one `help.<group>.` key in the keyed TSV files; otherwise 400 `{ "ok": false, "error": "group" }`.
- 200 `{ "ok": true, "group": "kk", "lang": "da"|"en"|"no", "texts": { "help.common.shortcuts_note": "…", "help.kk.about.aaben": "…", … } }`: every key starting with `help.<group>.` or `help.common.` in `importfiler/egne_tekster_keyed.tsv` and `importfiler/tekster_keyed.tsv`, each resolved with `findtekst_utf8($key, $sprog_id)` and `html_entity_decode(…, ENT_QUOTES | ENT_HTML5, 'UTF-8')` (as `index/globalSearch.php:44-50`). Through `findtekst()` a tenant's own edit in `systemdata/tekster.php` wins, and each text is cached in the session after the first call.
- Headers `Content-Type: application/json; charset=utf-8`, `Cache-Control: no-store`. JSON with `JSON_UNESCAPED_UNICODE`; if `json_encode` fails, 500 `{ "ok": false, "error": "encode" }`.
- Never more than the TSV keys: the endpoint does not accept key names from the client, so it cannot be used to self-heal arbitrary keys into a tenant's `tekster` table (`findTxt.php:226-246`).

### 3.5 Text ids

The table in §8.1 is the contract for numeric ids. SHELL writes `'NNNN|Dansk'` exactly as listed; TOURS adds the rows to `tekster.csv` with exactly the same Danish text. The key scheme in §8.2 is the contract between the content JS and the TSV; both are CONTENT's.


---

## 4. Content

### 4.1 Shared data (`saldi-help-content.js`) and how to read §4.2–§4.3

Every Danish text quoted in §4.2 and §4.3 is the **Dansk** column of a `tekster_keyed.tsv` row; its key follows §8.2 (for example K1's why → `help.kl.task.k1.why`, KK3 step 2 → `help.kk.task.kk3.step2`, the Bilag field text → `help.kk.field.bila.text`). CONTENT writes the English and Norwegian columns for every row and puts only the key in the JS.

- `support` as in §3.2.
- `contactWords.da`: `menneske, mennesker, mennesket, support, supporten, ring, ringe, kontakt, kontakte, telefon, telefonen, telefonisk` (`prototype_hjaelp/help/content.js:97`). `contactWords.en`: `human, person, support, call, phone, contact`. `contactWords.no`: `menneske, mennesker, mennesket, support, ring, ringe, kontakt, kontakte, telefon, telefonen`.
- `stopWords.da`: the list in `prototype_hjaelp/help/core.js:628-633`. `stopWords.en`: `i, how, what, where, can, the, a, an, and, to, of, with, about, is, my, why, do, does, not, saldi, me, you, have, or, for, from, now, want, would, like, help, please, in, on, at, it, by, be, if, as, are, there, this, that, which, when, should, need, goes`. `stopWords.no`: `jeg, hvordan, hva, hvor, kan, en, et, ei, og, å, til, av, med, om, er, min, mitt, mine, hvorfor, gjør, ikke, saldi, meg, du, har, eller, for, fra, nå, vil, ville, gjerne, hjelp, det, den, de, på, som, skal, så, der, hvilken, hvilke`. (The words after `please` and `hjelp` were added in review round 2: since 2-letter words count in search, `in` or `på` would otherwise rank hits.)
- `globalShortcuts`: the five global keys use the chrome ids that the generic panel uses too, so there is one text per key:

| keys | id | Dansk |
|---|---|---|
| `?` | 8749 | Åbn hjælpen (uden for felter) |
| `F1` | 8750 | Åbn hjælpen, også i et felt |
| `Esc` | 8751 | Luk hjælpen og gå tilbage til feltet |
| `Ctrl+K` | 8752 | Søg i Saldi |
| `Alt+L` | 8753 | Gå et niveau tilbage |

On-screen names in **bold**: the English and Norwegian columns use the same id's English and Norwegian columns in `tekster.csv`, because that is what those users see on the page:

| Dansk (id) | English | Norsk |
|---|---|---|
| Gem (3) | Save | Lagre |
| Opslag (644) | Lookup | Oppslag |
| Simulér (1064) | Simulate | Simuler |
| Bogfør (1065) | Post | Bokfør |
| Udlign (1066) | Equalize | Utjevner |
| Importér (1356) | Import | Import |
| Hent (1078) | Fetch | Hent |
| Vis (1133) | Show | Vis |
| Posteringskonto (1396) | Posting account | Bokføringskonto |
| Ny (39) | New | Ny |
| Vis alle (636) | Show all | Vis alt |
| Vis egne (641) | Show own | Vis egne |
| Annuller simulering (1090) | Cancel simulation | Avbryt simulering |
| Kopier til ny (1598) | Copy to new | Kopier til nytt |
| Bilags match (3341) | Batch invoice | Vedleggsmatch |
| Afregn (2993) | Settle | Avregn |
| Spring over (5305) | Skip | Hopp over |
| Tilbage til kladden (5313) | Back to journal | Tilbake til journalen |
| Skriv 1. bilagsnr (1553) | Write 1st attachment no | Skriv 1. bilagsnr |
| Skriv dato for alle bilag (1555) | Write the date for all documents | Skriv dato for alle bilag |
| Vend fortegn (tilbagefør) (1557) | Flip sign (reverse) | Vend fortegn (tilbagefør) |
| Tilbagefør postering (1574) | Return posting | Tilbakefør postering |
| Fortryd (159) | Undo | Angre |
| Ryd søgning (2755) | Clear search | Tøm søk |
| Linjer pr. side (2125) | Lines per page | Linjer pr. side |
| Bemærkning (599) | Remark | Merknad |
| Tilbage (30) | Back | Tilbake |
| Simuleret Bogføring (1085 + 1086) | Simulated Accounting | Simulert Bokføring |
| Simuleret Kladdeliste (1085 + 639) | Simulated Draft list | Simulert Utkastliste |
| Bogførte kladder (1093) | Posted drafts | Skrevet utkast |

Field labels use the English and Norwegian columns of the column header's id where one exists. The copy dialog's **Fortryd** button (`copy2new.php:106`) is hard-coded Danish; it is not named in any step.

### 4.2 Kladdeliste (`finans/kladdeliste.php`)

Facts checked in the code (do not promise anything else):
- Rows: `#datatable-kladdelst tbody tr`; the ID cell is `td[data-bogfort]` (`kladdeliste.php:290,295`). `data-bogfort` holds `k.bogfort` from the list query (`:289,294,566`): `-` (open), `!` (an interrupted posting), `S` (simulated) or `V` (posted). The Bogført column shows the posting date (`:386`), the attribute does not.
- Locked: the ID link carries a `title` only when locked (`:290`, text 1607 "Kladde er låst af <bruger>"); locked = another user and less than 3600 s since their last open or save (`:283`).
- Slet: `#datatable-kladdelst button[onclick*="deleteKladde"]`, only on empty, unposted kladder (`:457-503`); deleting also deletes the kladde's own documents (SD-730, `:46`).
- Filter: the server decides it per request (`:509-541`): the URL parameter `filter` (`alle`/`egne`), else the user's saved grid filter setup in `datatables` (`:509-516`), else `egne`, which shows only the user's own kladder (`:539-541`). `#filter-toggle-btn` is injected 300 ms after DOMContentLoaded (`:857-953`); its text is findtekst 636 "Vis alle" when the filter is `egne` and 641 "Vis egne" when it is `alle` (`:917-921`). A click navigates to `kladdeliste.php?filter=<other>` (`:923-936`: the page looks for `form[name^="filter_form"]`, which no page prints, `includes/grid.php:1265,2031`, so the fallback always runs). The choice is saved for the user (`:510-527, 549-556`, corrected in §17), so the next visit without `?filter` keeps it.
- Columns are not sortable (`:573-585`). Search fields: `input[name="search[kladdelst][<field>]"]`, Enter searches.
- Grid menu: `#datatable-wrapper-kladdelst .dropdown-trigger` (aria-label "Handlinger", `includes/grid.php:1420`) with **Ryd søgning** (2755).
- Separators `tr.simulated-separator` "Simuleret Kladdeliste" and `tr.posted-separator` "Bogførte kladder" are injected by JS (`:684-731`).
- Ny: `#create-new` (`topLineFinans.php:70-77`), accesskey N.

`group`: `'kl'`. `name`: `'help.kl.name'` (Kladdeliste / Draft list / Utkastliste, the columns of 639).

`detect(ctx)`:

```js
const cells = [...doc.querySelectorAll('#datatable-kladdelst td[data-bogfort]')];
const v = cells.map(td => td.getAttribute('data-bogfort'));
const btn = doc.getElementById('filter-toggle-btn');
const f = ctx.url.searchParams.get('filter');          // the server's own first source (:520-521)
const btnText = btn ? btn.textContent.trim() : '';
const flags = {
  tom:        cells.length === 0,
  aabne:      v.some(x => x === '-' || x === '!'),
  simuleret:  v.includes('S'),
  bogfoert:   v.some(x => x && x !== '-' && x !== '!' && x !== 'S'),
  laast:      !!doc.querySelector('#datatable-kladdelst td[data-bogfort] > a[title]'),
  kanSlettes: !!doc.querySelector('#datatable-kladdelst button[onclick*="deleteKladde"]'),
  soegning:   [...doc.querySelectorAll('input[name^="search[kladdelst]"]')].some(i => i.value.trim() !== ''),
  kunEgne:    f === 'egne' || (f !== 'alle' && btnText !== '' && btnText === ctx.t('636')),
                                                  // no language list: ctx.t('636') is the same tenant row the page printed
  mange:      cells.length > 15
};
return { state: flags.tom ? 'tom' : 'liste', flags, data: { rows: cells.length } };
```

`about`:
- Always: "Her er kassekladderne. Åbne kladder står øverst, derefter simulerede og til sidst bogførte, nyeste først. Klik på en linje for at åbne kladden."
- Plus, when `kunEgne`: "Du ser kun de kladder, du selv har oprettet." (Before the toggle exists, 300 ms after DOMContentLoaded, and with no `filter` parameter, `kunEgne` is false and the line is left out; the 400 ms re-check in §1.6 adds it.)
- Plus, when `soegning`: "Listen er filtreret af en søgning."

`order`: `K1` (soegning) → `K2` (if tom) → `K3` (aabne) → `K6` (simuleret) → `K5` (laast) → `K2` → `K4` (kunEgne) → `K7` (kanSlettes) → `K8` (bogfoert) → `K9` (mange and not soegning). Drop duplicates; the shell takes the first 3.

Tasks:

**K1 · Ryd søgningen**
- why: "Listen viser kun kladder, der passer til søgningen."
- steps: 1. "Klik på pilen yderst til højre over listen, og vælg **Ryd søgning**." 2. "Du kan også slette teksten i søgefeltet og trykke Enter."
- show: `#datatable-wrapper-kladdelst .dropdown-trigger`, label "Handlinger", kind `button`.

**K2 · Opret en ny kassekladde**
- why: "Til bankposteringer, bilag eller en rettelse."
- steps: 1. "Klik **Ny** øverst til højre (Alt+N)." 2. "Skriv evt. en **Bemærkning**, fx Bank oktober, så du kan kende kladden igen." 3. "Skriv den første linje, og tryk Enter. Kladden får sit nummer og kommer på listen, når du gemmer første gang."
- show: `#create-new`, label "Ny", kind `button`.

**K3 · Fortsæt i en åben kladde**
- why: "De gemte linjer venter i kladden, til den bogføres." (review round 2: "Om siden" already says that open kladder are at the top)
- steps: 1. "Klik på kladdens linje." 2. "Står nummeret med rødt, har en kollega kladden åben lige nu."
- show: function → the `tr` of the first `td[data-bogfort="-"]` (or `"!"`), label "Åben kladde", kind `row`.

**K4 · Se kollegers kladder**
- why: "Listen viser kun de kladder, du selv har oprettet."
- steps: 1. "Klik **Vis alle** nederst i listen." 2. "Klik **Vis egne** for at se dine egne igen. Saldi husker dit valg til næste gang." (§17: the choice is saved, `kladdeliste.php:510-527, 549-556`.)
- show: `#filter-toggle-btn`, label "Vis alle", kind `button`.

**K5 · En kladde er i brug**
- why: "Nummeret står med rødt, fordi en kollega har kladden åben."
- steps: 1. "Hold musen over nummeret for at se, hvem der har den åben." 2. "Kladden bliver ledig, når kollegaen lukker den, og senest en time efter at kollegaen sidst åbnede eller gemte den."
- show: `#datatable-kladdelst td[data-bogfort] > a[title]` (first), label "Kladde i brug", kind `status`.

**K6 · Gør en simuleret kladde færdig**
- why: "En simuleret kladde er ikke bogført og kan ikke rettes."
- steps: 1. "Åbn kladden under **Simuleret Kladdeliste**." 2. "Klik **Annuller simulering**. Så kan kladden rettes og bogføres igen."
- show: `tr.simulated-separator`, label "Simuleret Kladdeliste", kind `status`.

**K7 · Slet en tom kladde**
- why: "Kun en kladde uden linjer, som ikke er bogført, kan slettes."
- steps: 1. "Klik **Slet** på kladdens linje, og bekræft." 2. "Dokumenter, der er vedhæftet selve kladden, slettes med." 3. "Står der et tal i parentes i stedet, har kladden linjer og kan ikke slettes."
- show: first `#datatable-kladdelst button[onclick*="deleteKladde"]`, label "Slet", kind `button`.

**K8 · Ret i en bogført kladde**
- why: "En bogført kladde kan ikke ændres, kun tilbageføres."
- steps: 1. "Åbn kladden under **Bogførte kladder**." 2. "Klik på fortryd-pilen yderst til højre på linjen (**Tilbagefør postering**), og vælg den åbne kladde, rettelsen skal lægges i." 3. "Bogfør den kladde bagefter."
- show: `tr.posted-separator`, label "Bogførte kladder", kind `status`.

**K9 · Find en bestemt kladde**
- why: "Søg i en kolonne."
- steps: 1. "Skriv i feltet under kolonneoverskriften, fx en del af bemærkningen, og tryk Enter." 2. "Under **Dato** og **Bogført** kan du vælge en dato i kalenderen og klikke **Søg**."
- show: `input[name="search[kladdelst][kladdenote]"]`, label "Bemærkning", kind `field`.

Fields:

| key | label | text |
|---|---|---|
| `search:id` | ID | Kladdens nummer. Skriv nummeret, og tryk Enter. |
| `search:kladdedate` | Dato | Datoen, kladden blev oprettet. Vælg en dato i kalenderen og klik **Søg**, eller skriv et interval som 01-10-2026:31-10-2026 og tryk Enter. |
| `search:oprettet_af` | Ejer | Brugeren, der oprettede kladden. Viser listen kun dine egne kladder, så klik **Vis alle** først. |
| `search:kladdenote` | Bemærkning | Søger i kladdernes bemærkning, fx bank eller oktober. |
| `search:bogfort` | Bogført | Datoen, kladden blev bogført eller simuleret. Vælg en dato i kalenderen, og klik **Søg**. |
| `search:bogfort_af` | Af | Brugeren, der bogførte eller simulerede kladden. |
| `rowcount` | Linjer pr. side | Hvor mange kladder der vises pr. side. Bladr med pilene ved siden af. |

Field keys in the TSV: the field key with `:` replaced by `_` (§8.2), e.g. `help.kl.field.search_kladdenote.label`.

Shortcuts: `Alt+N` "Ny kassekladde" (group `keys`); `Enter` "I et søgefelt: søg" (group `keys`). `shortcutsNote`: `help.common.shortcuts_note` "Alt-genvejene er browserens adgangstaster. I Firefox bruges Alt+Shift, og på Mac Ctrl+Option."

Questions (`questionOrder`: Q1, Q2, Q3). The `keys …` after each question are its Danish search words (`words.da`); CONTENT adds `words.en` and `words.no` with the same meaning:

**Q1 "Hvordan opretter jeg en ny kassekladde?"** keys `opret, ny kladde, ny kassekladde, oprette`. Steps: 1. "Klik **Ny** øverst til højre (Alt+N). En tom kladde åbner." 2. "Skriv evt. en **Bemærkning**, fx Bank oktober." 3. "Første linje har allerede **Bilag** og **Dato**. Udfyld **Bilagstekst**, konti i **Debet** og **Kredit** og **Beløb**." 4. "Tryk Enter for at gemme. Kladden får sit nummer og står nu øverst i listen." Show `#create-new`.

**Q2 "Hvorfor kan jeg ikke se alle kladder?"** keys `kan ikke se, mangler, finde, kollega, alle kladder, væk, forsvundet`. Intro: "Listen viser som udgangspunkt kun de kladder, du selv har oprettet." Steps: 1. "Klik **Vis alle** nederst i listen for at se alle brugeres kladder." 2. "Står der tekst i søgefelterne, så vælg **Ryd søgning** i menuen yderst til højre over listen." 3. "Bogførte kladder står længere nede under **Bogførte kladder**. Er listen lang, så bladr til næste side nederst." Show `#filter-toggle-btn`.

**Q3 "Hvordan sletter jeg en kassekladde?"** keys `slet, slette, fjern, tom kladde`. Intro: "Du kan kun slette en kladde, der er tom og ikke bogført." Steps: 1. "Står der **Slet** på kladdens linje, så klik og bekræft. Dokumenter vedhæftet kladden slettes med." 2. "Står der et tal i parentes, har kladden så mange linjer. Slet linjerne i kladden først ved at skrive - i **Bilag** og trykke Enter, eller lad kladden stå." 3. "En bogført kladde kan ikke slettes. Er der bogført forkert, så tilbagefør posteringen." Show: first delete button, else none.

`signal`: always `false`.

### 4.3 Kassekladde (`finans/kassekladde.php`)

Facts checked in the code that the content relies on:
- The page has no ids on footer buttons; they are reached by name: `bilagsmatch`, `save`, `lookup`, `simulate`, `doPost`, `import`, `offset` (`:4279-4298`), `cancelSimulation` (`:4262-4266`), `copy2new`/`#kopier-button` (`:4237`), `revert` (`:4242`).
- Line inputs: `<prefix><row>`: `bila`, `dato`, `besk`, `d_ty`, `debe`, `dvat` (select), `k_ty`, `kred`, `kvat` (select), `fakt`, `belo`, `afd_`, `meda`, `proj`, `valu`, `forf`, `b_id`, `moms` (checkbox) (`:3773-3860`). Saved rows carry `input[type=hidden][name="id[N]"]` with a value and `dkkaN` (`:3893-3894`).
- `#kk-balance-status` gets a span with a red `#cc0000` "Difference: x DKK" (2396) or a green `#1a7a1a` "Kladden balancerer" (5144), only on editable kladder, computed from saved lines (`:5197-5209`). It does not change while typing and already says "balancerer" on an empty new kladde.
- Simulér opens `bogfor.php?funktion=simuler`; its **Simuleret Bogføring** (`bogfor.php:739`) sets status S and returns to the list. A simulated kladde is read-only with **Annuller simulering**. **Simulér is not a step in "how to post".**
- Bogfør first shows an overview with Kontrolsum (1084) and Finansbevægelser (1088); the Bogfør button there is hidden when the total difference is at least 0,01 (`bogfor.php:610`). A saved line with text, amount or invoice number but no account stops Simulér and Bogfør (texts 5440/5441, `bogfor.php:133-141`).
- A refused save (`$fejl`, lines in `tmpkassekl`) hides Simulér, Bogfør, Hent, DocuBizz, Importér and Udlign and the clip column (`:3384-3388, 4282, 3717`).
- Udlign (`autoudlign.php`) walks one-sided lines and offers open posts: **Afregn** (2993), **Spring over** (5305), **Tilbage til kladden** (5313).
- Importér → `importer.php` → bank import: **Hent** (1078) the file, choose the column contents, **Posteringskonto** (1396), **Vis** (1133), then **Importér** (1356) (`bankimport.php:197,378-393`).
- Posted rows have a per-row undo link `a[href*="ompost="]` with `id='undo'` repeated on every row (`:2643`); use the first `a[href*="ompost="]`, never `#undo`. The undo column is added for every kladde shown in the posted grid except a simulated one (`$bogfort != 'S'`, `:2636-2652`), so an interrupted kladde (`!`) has these links too.
- "Kopier til ny" posts the kladde form back to `kassekladde.php` without query parameters; `copy2new()` then prints its own dialog (`Form1` with `bilagsnr`, `ny_dato`, `vend_fortegn` and a second **Kopier til ny**) and exits before `form#kassekladde` is printed (`:1697-1699`; `kassekladde_includes/copy2new.php:77-118`).
- The `#back-btn` text is `findtekst('1072|Kassekladde') . $kladde_id` (`topLineKassekladde.php:58-62`); row 1072 has a trailing space in Danish only (`Kassekladde ` / `Cash journal` / `Kontantjournal`), so it is not used for the name.

`group`: `'kk'`. `name(ctx, st)`: `st.state === 'ny'` → `'help.kk.name_new'` (Ny kassekladde); `st.data.kladdeId` not empty → `['help.kk.name', st.data.kladdeId]` (Kassekladde %s / Cash journal %s / Kontantjournal %s); else `'help.kk.name_plain'` (Kassekladde).

`detect(ctx)` (exact order). Row values are read through `form.elements.namedItem()`, which the browser indexes once per document, instead of one attribute scan of the whole document per row and field (a 500-line kladde has about 9,000 inputs):

```js
const q = s => doc.querySelector(s);
const form = q('form#kassekladde');
const url = ctx.url;
let state;
if (url.searchParams.has('udskriv')) state = 'udskrift';
else if (!form && q('input[name="vend_fortegn"]')) state = 'kopier';                 // copy2new dialog
else if (!form && url.searchParams.has('ompost') && !url.searchParams.has('ompost_til')) state = 'vaelgTilbagefoersel';
else if (!form) state = 'opslag';
else if (q('input[name="cancelSimulation"]')) state = 'simuleret';
else if (q('input[name="revert"]')) state = 'afbrudt';                                // before the ompost check: '!' has undo links
else if (q('#kopier-button, a[href*="ompost="]')) state = 'bogfoert';
else {
  const kid = (form.elements.namedItem('kladde_id') || {}).value || '';
  if (kid === '' || kid === '0') state = 'ny';
  else if (!q('input[name="doPost"]')) state = 'afvist';
  else state = 'aaben';
}
const els = form ? form.elements : null;
const val = name => {                                 // value of one named control, '' when missing
  let el = els && els.namedItem(name);
  if (el && el.nodeType !== 1) el = el[0];            // several controls share the name → RadioNodeList
  return el && el.nodeType === 1 ? String(el.value || '').trim() : '';   // (kladde_id is printed twice, :2993 and :4177)
};
// saved rows: one pass over the hidden id[N] inputs (:3893)
const saved = [];
if (form) {
  for (const i of form.querySelectorAll('input[type="hidden"][name^="id["]')) {
    const m = /^id\[(\d+)\]$/.exec(i.name);
    if (m && i.value !== '') saved.push(m[1]);
  }
}
const nonZero = s => s !== '' && !/^-?0*([.,]0*)?$/.test(s);
const manglerKontoRow = saved.find(n => !val('debe' + n) && !val('kred' + n) && (val('besk' + n) || val('fakt' + n) || nonZero(val('belo' + n))));
const enSideRow = saved.find(n => !!val('debe' + n) !== !!val('kred' + n));
const bal = q('#kk-balance-status span span');
const color = bal ? ctx.win.getComputedStyle(bal).color : '';
const flags = {
  hasSaved:     saved.length > 0,
  difference:   saved.length > 0 && color === 'rgb(204, 0, 0)',
  balancerer:   saved.length > 0 && color === 'rgb(26, 122, 26)',
  ikkeGemt:     ctx.win.docChange === true,
  manglerKonto: !!manglerKontoRow,
  enSide:       !!enSideRow,
  dublet:       !!q('input[name^="bila"][title]:not([title=""])'),
  fakturaBrugt: !!q('.invoice-reuse-flag'),
  kontrolkonto: !!val('kontrolkonto')
};
const data = { kladdeId: form ? val('kladde_id').replace(/^0$/, '') : (url.searchParams.get('kladde_id') || ''),
               manglerKontoRow, enSideRow };
return { state, flags, data };
```

The shell caches this result per document (§1.6): it is not recomputed on `focusin`, so an open panel beside a long kladde costs nothing while the user types.

`about(ctx, st)` (first line by state, second line `ikkeGemt` when it applies):

| state / flag | text |
|---|---|
| `ny` | En ny kladde. Skriv linjerne, og tryk Enter for at gemme. Kladden får sit nummer, når du gemmer første gang. |
| `aaben` + `difference` | Her registrerer du posteringer, før de bogføres. Debet og kredit er ikke ens endnu, så kladden kan ikke bogføres. |
| `aaben` + `balancerer` | Her registrerer du posteringer, før de bogføres. Kladden balancerer og kan bogføres. |
| `aaben` otherwise | Her registrerer du posteringer, før de bogføres. |
| `afvist` | Kladden er ikke gemt, fordi en linje har en fejl. **Simulér** og **Bogfør** vises igen, når kladden er gemt. |
| `simuleret` | Kladden er simuleret. Den er ikke bogført og kan ikke rettes, før du annullerer simuleringen. |
| `bogfoert` | Kladden er bogført og kan ikke rettes. En fejl retter du ved at tilbageføre posteringen. |
| `vaelgTilbagefoersel` | Vælg den åbne kladde, som modposteringen skal lægges i, eller klik **Fortryd**. |
| `kopier` | Vælg første bilagsnummer og dato for den nye kladde. |
| `opslag` | Klik på en linje for at vælge den, eller gå tilbage uden at vælge noget. |
| `afbrudt`, `udskrift`, `unknown` | (none) |
| + `ikkeGemt` | Du har ændringer, der ikke er gemt. Tryk Enter, eller klik **Gem**. |

`order(ctx, st)`:

| state | order |
|---|---|
| `vaelgTilbagefoersel` | KK15 |
| `kopier` | KK14k |
| `afvist` | KK1, then KK2 if `manglerKonto` |
| `ny` | KK9, KK8 |
| `simuleret` | KK12 |
| `bogfoert` | KK13, KK14 |
| `aaben` | KK2 if `manglerKonto`; KK10 if `dublet`; KK11 if `fakturaBrugt`; then if `difference`: KK3, KK4 if `enSide` or `ctx.field` key is `debe`/`kred`, KK5 if `enSide`; if `balancerer`: KK6, KK7; then KK8; KK16 if not `kontrolkonto` |
| others | none |

`signal(ctx, st)`: `st.state === 'afvist'` (a refused save the user must fix). Read by the shell only when `data-signal="1"`, which phase 1 never prints (D11).

Tasks:

**KK1 · Ret fejlen, så kladden kan gemmes**
- why: "Saldi har ikke gemt kladden, fordi en linje har en fejl."
- steps: 1. "Beskeden fra Saldi nævner bilagsnummeret og fejlen, fx en konto, der ikke findes, eller en dato uden for regnskabsåret." 2. "Ret feltet på linjen med det bilagsnummer." 3. "Tryk Enter eller klik **Gem**. Når kladden er gemt, vises **Simulér** og **Bogfør** igen."
- show: `input[name="save"]`, label "Gem", kind `button`.

**KK2 · Udfyld kontoen på alle linjer**
- why: "En linje med tekst eller beløb, men uden konto, stopper bogføringen."
- steps: 1. "Skriv kontoen i **Debet** eller **Kredit** på linjen." 2. "Skal linjen ikke bruges, så skriv - (minus) i **Bilag**, og tryk Enter."
- show: function → `debe{data.manglerKontoRow}`, label "Debet", kind `field`.

**KK3 · Find differencen**
- why: "Ofte er det en linje med konto i kun den ene side." (review round 2: "Om siden" already says that debet and kredit differ)
- steps: 1. "Find linjer med konto i kun den ene side, fx bankposteringer fra en import. Udfyld den tomme side, eller klik **Udlign**." 2. "Er et bilag fordelt på flere linjer, skal linjerne med samme nummer i **Bilag** tilsammen gå i nul." 3. "Klik **Gem**. Differencen øverst opdateres først, når kladden er gemt."
- show: `#kk-balance-status`, label "Difference", kind `status`, note "Her står differencen. Den opdateres, når du gemmer."

**KK4 · Udfyld modkontoen**
- why: "Kontoen på den anden side af posteringen."
- steps: 1. "Klik i det tomme felt i **Debet** eller **Kredit**. Er den anden side udfyldt, viser listen de konti, der senest er brugt sammen med den." 2. "Skriv en del af kontonavnet eller nummeret for at søge. Er det en kunde eller leverandør, så skriv D eller K i **D/K** foran feltet først." 3. "Vælg med pil ned og Enter, og gem linjen."
- show: function → the empty one of `debe{enSideRow}` / `kred{enSideRow}`; else `kred` of `ctx.field`'s row; label "Debet" or "Kredit", kind `field`.

**KK5 · Find kunder og fakturaer til bankposteringer**
- why: "Udlign foreslår den åbne post, der passer til hver linje."
- steps: 1. "Klik **Gem**, hvis du har ændringer." 2. "Klik **Udlign** (Alt+U). Saldi viser én linje ad gangen og de åbne poster, der passer bedst." 3. "Vælg posten, og klik **Afregn**, eller klik **Spring over**." 4. "Klik **Tilbage til kladden**, når du er igennem."
- show: `input[name="offset"]`, label "Udlign", kind `button`.

**KK6 · Bogfør kladden**
- why: "Kladden balancerer og kan bogføres."
- steps: 1. "Tryk Enter eller klik **Gem**, hvis du har ændringer." 2. "Klik **Bogfør** (Alt+B). Saldi viser først en oversigt over finansbevægelserne med kontrolsum. Intet er bogført endnu." 3. "Klik **Bogfør** igen på oversigten. Du kommer tilbage til listen, og kladden står under **Bogførte kladder**."
- show: `input[name="doPost"]`, label "Bogfør", kind `button`.

**KK7 · Vedhæft bilag**
- why: "Bilaget gemmes sammen med posteringen."
- steps: 1. "Klik på clipsen yderst til venstre på linjen. Har du ændringer, gemmer Saldi først, og dokumentpuljen åbner." 2. "Vælg bilaget i puljen, og gem." 3. "Har du mange linjer, så klik **Bilags match**. Saldi foreslår bilag med samme beløb og en dato tæt på." 4. "Du kan også trække en PDF fra computeren hen på clipsen."
- show: function → first `td.clip-cell.clip-no-doc`, else `input[name="bilagsmatch"]`; label "Clips" or "Bilags match", kind `button`.

**KK8 · Importer bankposteringer**
- why: "Hent kontoudtoget i netbanken som CSV-fil."
- steps: 1. "Klik **Importér** (Alt+I), vælg filen, og klik **Hent**." 2. "Vælg, hvad hver kolonne indeholder, og skriv bankens konto i **Posteringskonto**." 3. "Klik **Vis** for at se linjerne, og klik derefter **Importér**." 4. "Udfyld modkontiene bagefter, eller brug **Udlign**."
- show: `input[name="import"]`, label "Importér", kind `button`.

**KK9 · Skriv den første linje**
- why: "Bilag og dato er udfyldt på forhånd."
- steps: 1. "Skriv evt. en **Bemærkning** øverst, fx Bank oktober." 2. "Udfyld **Bilagstekst**, konti i **Debet** og **Kredit** og **Beløb**." 3. "Tryk Enter. Linjen gemmes, og markøren står klar på næste linje."
- show: first `input[name^="besk"]`, label "Bilagstekst", kind `field`.

**KK10 · Tjek en mulig dobbeltpostering**
- why: "Bilagsnummeret står med rødt, fordi en tilsvarende postering findes."
- steps: 1. "Hold musen over bilagsnummeret for at se, hvor den anden postering står." 2. "Er det den samme postering, så slet linjen med - i **Bilag**. Ellers kan den blive stående."
- show: first `input[name^="bila"][title]:not([title=""])`, label "Bilag", kind `field`.

**KK11 · Fakturanummeret er brugt før**
- why: "Leverandøren har allerede en faktura med det nummer."
- steps: 1. "Hold musen over advarselstegnet ved **Fakturanr.** for at se, hvor nummeret er brugt." 2. "Er det den samme faktura, så slet linjen. Er det en anden, så ret nummeret."
- show: first `.invoice-reuse-flag`, label "Fakturanr.", kind `status`.

**KK12 · Ret eller bogfør kladden**
- why: "En simuleret kladde er låst, indtil simuleringen annulleres."
- steps: 1. "Klik **Annuller simulering** nederst. Kladden bliver åben igen." 2. "Ret linjerne, og klik **Bogfør**." 3. "Vil du se de simulerede tal i rapporterne, så sæt flueben i **Simulering** under Finans » Rapporter."
- show: `input[name="cancelSimulation"]`, label "Annuller simulering", kind `button`.

**KK13 · Ret en bogført postering**
- why: "En bogført kladde kan ikke ændres, kun tilbageføres."
- steps: 1. "Klik på fortryd-pilen yderst til højre på linjen (**Tilbagefør postering**)." 2. "Klik på nummeret på den åbne kladde, modposteringen skal lægges i. Har du ingen åben kladde, så opret en med **Ny** først." 3. "Åbn den kladde, kontrollér linjen, og bogfør den."
- show: first `a[href*="ompost="]`, label "Tilbagefør postering", kind `button`.

**KK14 · Kopier eller vend hele kladden**
- why: "**Kopier til ny** laver en ny, åben kladde med de samme linjer."
- steps: 1. "Klik **Kopier til ny** nederst." 2. "Skriv det første bilagsnummer i **Skriv 1. bilagsnr**, eller = for at beholde numrene. Skriv en dato, eller = for de oprindelige datoer." 3. "Sæt flueben i **Vend fortegn (tilbagefør)**, hvis debet og kredit skal byttes på alle linjer." 4. "Klik **Kopier til ny**. Den nye kladde åbner, så du kan kontrollere og bogføre den."
- show: `#kopier-button`, label "Kopier til ny", kind `button`.

**KK14k · Udfyld kopien** (the copy dialog, state `kopier`)
- why: "Den nye kladde får de samme linjer med det nummer og den dato, du vælger her."
- steps: KK14's steps 2, 3 and 4 (the same keys `help.kk.task.kk14.step2`–`step4`, no new rows).
- show: `input[name="bilagsnr"]`, label "Skriv 1. bilagsnr", kind `field`.

**KK15 · Vælg kladden til tilbageførslen**
- why: "Modposteringen lægges i en åben kladde."
- steps: 1. "Klik på nummeret på den kladde, modposteringen skal lægges i." 2. "Klik **Fortryd** for at afbryde."
- show: first `a[href*="ompost_til="]`, label "Kladde", kind `button`.

**KK16 · Afstem banken undervejs**
- why: "Følg bankkontoens saldo linje for linje."
- steps: 1. "Skriv bankkontoens nummer i feltet **kontrolkonto** øverst, og klik **Gem**." 2. "Kladden viser saldoen i regnskabet på hver linje. Er der indlæst en saldo fra banken, vises også bankens saldo og forskellen."
- show: `input[name="kontrolkonto"]`, label "kontrolkonto", kind `field`.

Fields (labels in English and Norwegian use the column header's English and Norwegian texts; TSV keys per §8.2, e.g. `help.kk.field.kkregnaar.text`):

| key | label | text |
|---|---|---|
| `bila` | Bilag | Bilagsnummeret. Saldi foreslår kladdens næste nummer, og linjer med samme nummer er ét bilag. Skriv - (minus) og tryk Enter for at slette linjen. |
| `dato` | Dato | Skriv kort som ddmmåå, fx 151026, eller kun dagen, fx 15, for den indeværende måned. Datoen skal ligge i et åbent regnskabsår. |
| `besk` | Bilagstekst | Hvad posteringen drejer sig om, fx Faktura 2045 Kontorland. Skriv = for at kopiere teksten fra linjen over. |
| `d_ty` | D/K (Debet) | Hvad kontoen i **Debet** er: F eller tomt for en finanskonto, D for en kunde og K for en leverandør. Det styrer, hvor Saldi søger. |
| `k_ty` | D/K (Kredit) | Hvad kontoen i **Kredit** er: F eller tomt for en finanskonto, D for en kunde og K for en leverandør. |
| `debe` | Debet | Kontoen, der debiteres, fx banken ved en indbetaling. Klik i feltet for at søge på nummer eller navn. Med D eller K søges der i kunder eller leverandører (mindst 3 tegn). |
| `kred` | Kredit | Kontoen, der krediteres, fx kunden med D ved en indbetaling eller banken ved en betaling. Er **Debet** udfyldt, viser feltet de konti, der senest er brugt sammen med den. |
| `dvat` | Moms (Debet) | Momskoden for debetsiden. Den hentes fra kontoen og kan ændres her. Står der moms på kun den ene side, spørger Saldi, om det er meningen. |
| `kvat` | Moms (Kredit) | Momskoden for kreditsiden. Den hentes fra kontoen og kan ændres her. Står der moms på kun den ene side, spørger Saldi, om det er meningen. |
| `fakt` | Fakturanr. | Fakturanummeret, som en betaling udlignes mod, når kladden bogføres. Klik i feltet for at vælge blandt åbne poster. Skriv flere numre adskilt af semikolon, eller - hvis der ikke skal udlignes. |
| `belo` | Beløb | Beløbet med komma som decimaltegn, fx 1.234,56. Et beløb med punkt som decimaltegn afvises, så linjen ikke gemmes forkert. |
| `afd` | Afd. | Afdelingen, posteringen hører til. Klik i feltet for at vælge. |
| `meda` | Ansat | Medarbejderen, posteringen hører til. Klik i feltet for at vælge. |
| `proj` | Proj. | Projektet, posteringen hører til. |
| `valu` | Valuta | Valutakoden, fx EUR, når beløbet ikke er i kroner. Valutaen skal være oprettet i Saldi. |
| `forf` | Forfald | Forfaldsdatoen på kundens eller leverandørens post. Er feltet tomt, bruges posteringens dato. |
| `b_id` | Betalings-ID | Betalings-ID fra indbetalingskortet, kun selve nummeret. Det bruges til at finde fakturaen. |
| `moms` | u/m | Sæt flueben, hvis der ikke skal være moms på linjen. Momsfelterne tømmes, når du sætter fluebenet. |
| `ny_kladdenote` | Bemærkning | En note til hele kladden, fx Bank oktober. Den vises i listen over kladder og gemmes med **Gem**. |
| `kontrolkonto` | kontrolkonto | Skriv et kontonummer, fx bankkontoen, og klik **Gem**. Kladden viser så kontoens saldo linje for linje. |
| `kkRegnaar` | Regnskabsår | Skifter det regnskabsår, du arbejder i. Saldi spørger først, hvis du har ændringer, der ikke er gemt. |

Shortcuts:

| group | keys | text |
|---|---|---|
| keys | Enter | Gem. I kontolisten vælger Enter den markerede linje. |
| keys | Alt+G | Gem |
| keys | Alt+O | Opslag i feltet, du står i |
| keys | Alt+S | Simulér |
| keys | Alt+B | Bogfør |
| keys | Alt+I | Importér |
| keys | Alt+U | Udlign |
| keys | Alt+L | Tilbage |
| keys | Alt+N | Ny |
| keys | Ctrl+← / Ctrl+→ | Forrige / næste felt på linjen |
| keys | Ctrl+↑ / Ctrl+↓ | Samme felt på linjen over / under (kan ændres under tandhjulet) |
| keys | ↑ ↓, Enter, Ctrl+Enter, Esc | I kontolisten: vælg, brug, åbn kontokortet, luk |
| codes | - | I **Bilag**: sletter linjen |
| codes | -* | I **Bilag**: sletter linjen og alle under den |
| codes | = | I **Bilag**: samme nummer som linjen over |
| codes | + | I **Bilag**: næste nummer |
| codes | 12+3 | I **Bilag**: indsætter 3 tomme linjer på bilag 12 |
| codes | 12+= | I **Bilag**: indsætter en kopi af linjen |
| codes | = | I et andet felt: kopierer værdien fra linjen over |

`shortcutsNote`: `help.common.shortcuts_note`, as kladdeliste.

Questions. `questionOrder`: Q1, Q2, Q3; on `bogfoert` replace Q3 with Q3b; on `simuleret` with Q3c; on `aaben` with `enSide` with Q3d. Since 9 Nov 2026 the order is per state for the chat's start screen (§25.9): a new or starter journal Q4, Q3, Q11; `afvist` Q14, Q6, Q4; `bogfoert` Q3b, Q7, then Q16 with a document or Q4; `simuleret` Q3c, Q4, Q2; a line without an account Q12 first; `enSide` Q1, Q2, Q3d; a difference Q1, Q2, Q3; otherwise an open journal Q2, Q4, Q11. The `keys …` lists are the Danish search words (`words.da`); CONTENT adds `words.en` and `words.no`. A question whose steps are "KKn's steps" reuses that task's step keys.

**Q1 "Hvorfor står der en difference?"** keys `difference, differencen, balancerer, balance, går ikke i nul, skæv`. Intro: "Differencen er forskellen mellem debet og kredit på de gemte linjer. Kladden kan ikke bogføres, før den er 0." Steps: 1. "Find linjer med en konto i kun den ene side, fx bankposteringer fra en import. Udfyld kontoen i **Debet** eller **Kredit**, eller klik **Udlign** for at finde kunden eller leverandøren." 2. "Er et bilag fordelt på flere linjer, skal linjerne med samme nummer i **Bilag** tilsammen gå i nul." 3. "Klik **Gem**. Differencen øverst opdateres først, når kladden er gemt." 4. "Kan du ikke finde den, så klik **Simulér**. Oversigten viser, hvilke bilag der har en difference, uden at noget bogføres. Klik **Tilbage** for at rette." Show `#kk-balance-status` (status).

**Q2 "Hvordan bogfører jeg kladden?"** keys `bogfør, bogføre, bogfoer, bogføring` (not `simuler`). Intro: "Kladden kan bogføres, når den balancerer, og alle linjer med tekst eller beløb har en konto." Steps: 1. "Tryk Enter eller klik **Gem**, så alle linjer er gemt." 2. "Klik **Bogfør** (Alt+B). Saldi viser en oversigt over finansbevægelserne med kontrolsum. Der er ikke bogført noget endnu." 3. "Kontrollér oversigten, og klik **Bogfør** igen. Du kommer tilbage til listen, hvor kladden står under **Bogførte kladder**." 4. "En bogført kladde kan ikke rettes. Fejl retter du ved at tilbageføre posteringen." Show `input[name="doPost"]`.

**Q3 "Hvad skal der stå i Debet og Kredit?"** keys `debet, kredit, modkonto, modkontoen, konto, d/k`. Intro: "Hver linje har en konto i **Debet** og en i **Kredit**. Kontoen på den anden side kaldes ofte modkontoen." Steps: 1. "Indbetaling fra en kunde: banken i **Debet**, og kunden i **Kredit** med D i **D/K** foran." 2. "Betaling til en leverandør: leverandøren i **Debet** med K i **D/K** foran, og banken i **Kredit**." 3. "Køb uden leverandørkonto: udgiftskontoen i **Debet** og banken i **Kredit**. Momsen hentes fra kontoen." 4. "Klik i feltet for at se de konti, der senest er brugt, eller skriv en del af navnet eller nummeret for at søge." Show: function → `kred` of `ctx.field`'s row, else `kred1`.

**Q3b "Hvordan retter jeg en bogført postering?"** keys `ret, rette, fejl, tilbagefør, tilbageføre, bogført`. Steps: KK13's three steps, plus 4. "Saldi bekræfter med beskeden Posteringen er tilbageført på kladde og nummeret." Show first `a[href*="ompost="]`.

**Q3c "Hvorfor kan jeg ikke rette i kladden?"** keys `rette, ret, låst, simuleret, kan ikke`. Steps: KK12's three steps. Show `input[name="cancelSimulation"]`.

**Q3d "Hvordan finder jeg kunden til en indbetaling?"** keys `kunde, indbetaling, udlign, udligne, åben post`. Steps: KK5's four steps. Show `input[name="offset"]`.

### 4.4 Content checks (CONTENT)

`tests/includes/ui/saldiHelpContent.test.mjs` (run on the host with `node --test tests/includes/ui/*.test.mjs`), modelled on `tests/includes/ui/saldiAssistNavigate.test.mjs`. It loads the JS file in `vm` and reads `importfiler/tekster_keyed.tsv`, then checks:
- every key the content can produce exists as a TSV row: walk `tasks`, `fields`, `shortcuts`, `questions`, `shortcutsNote` and every `show.label`/`show.note`, and call `name`, `about`, `order` and `questionOrder` with a stub `ctx`/`st` for each state (`ny`, `aaben` with each flag, `afvist`, `simuleret`, `bogfoert`, `afbrudt`, `kopier`, `vaelgTilbagefoersel`, `opslag`, `udskrift`, `unknown` for the kassekladde; `tom`, `liste` with each flag for the list);
- every `help.kl.*` / `help.kk.*` TSV row is used by the content (no dead rows), and every key matches `/^help\.(common|kl|kk)\.[a-z0-9_.]+$/`;
- each TSV row has four non-empty fields, the same number of `%s` and balanced `**` in all three languages, and no `Assist`, `Sally` or `!` in any language;
- every id in an `order`/`questionOrder` result exists; each task has 1–4 steps; field keys match `/^[a-z_]+$|^search:[a-z_]+$|^rowcount$|^kkRegnaar$/`; every question has `words.da`, `words.en` and `words.no`; `contactWords` and `stopWords` have `da`, `en` and `no`; `guideLinks === false`;
- `detect()` on a minimal fake document (plain objects implementing `querySelector`/`querySelectorAll`, a fake `form.elements.namedItem` that returns an element or, for a repeated name, an array-like list, and a fake `win.getComputedStyle`) returns the expected state for `ny`, `aaben`+difference, `aaben`+balancerer, `afvist`, `simuleret`, `bogfoert`, **`afbrudt`** (a `revert` input together with `a[href*="ompost="]` links: must not be `bogfoert`), **`kopier`** (no `form#kassekladde`, an `input[name="vend_fortegn"]`: must not be `opslag`) and `vaelgTilbagefoersel`; the kladdeliste `kunEgne` flag is true for `?filter=egne`, false for `?filter=alle`, and follows `ctx.t('636')` without a parameter.

This test is a local check only: CI's guardrail job runs `node --test tools/guardrails/tests/*.test.mjs` and nothing under `tests/includes/ui/` (`.github/workflows/guardrails.yml:54-55`; the existing `saldiAssistNavigate.test.mjs` is not run in CI either). The guard for the live selectors is the ssl12 Playwright run (§13). Adding `node --test tests/includes/ui/*.test.mjs` to the workflow is owner question 10, not part of this branch.

`tests/includes/saldiHelpTexts.test.php` (PHPUnit, discovered by `phpunit.xml:22`): `saldi_help_text_keys()` on a TSV string returns exactly the `help.<group>.*` and `help.common.*` keys in file order, ignores other prefixes, empty lines and a `help.kkx.*` key for group `kk`, and a key in the custom TSV string comes first and only once.

### 4.5 `includes/saldi_help_texts.php` and `index/helpTexts.php` (CONTENT)

`includes/saldi_help_texts.php` (new; full header, `ver 5.1.0`, `// 20261107 CL/ASR Created: the help texts (help.* keys in importfiler/tekster_keyed.tsv) for the help panel.`):

```php
/** The help.<group>.* and help.common.* keys of the keyed TSV contents, custom file first, each key once, in file order. Pure. */
function saldi_help_text_keys(string $customTsv, string $standardTsv, string $group): array
{
	$keys = array();
	foreach (array($customTsv, $standardTsv) as $tsv) {
		foreach (preg_split('/\r\n|\n|\r/', $tsv) as $line) {
			$key = explode("\t", $line, 2)[0];
			if ((strpos($key, 'help.' . $group . '.') === 0 || strpos($key, 'help.common.') === 0) && !isset($keys[$key])) {
				$keys[$key] = true;
			}
		}
	}
	return array_keys($keys);
}
/** key => UTF-8 text for the user's language, through findtekst_utf8() (tenant rows win, session cache). [] for an unknown group. */
function saldi_help_texts(string $group, int $sprogId): array
{
	if (!preg_match('/^[a-z]{2,12}$/', $group)) return array();
	if (!function_exists('locateDir')) include_once(__DIR__ . '/stdFunc/locateDir.php');
	$dir = locateDir('importfiler');
	$read = function (string $f): string { return is_file($f) ? (string) file_get_contents($f) : ''; };
	$keys = saldi_help_text_keys($read("$dir/egne_tekster_keyed.tsv"), $read("$dir/tekster_keyed.tsv"), $group);
	$hasGroupKey = false;
	foreach ($keys as $k) { if (strpos($k, 'help.' . $group . '.') === 0) { $hasGroupKey = true; break; } }
	if (!$hasGroupKey) return array();
	saldi_help_texts_prefetch($keys, $sprogId);   // review round 1, below
	$out = array();
	foreach ($keys as $k) {
		$out[$k] = html_entity_decode(findtekst_utf8($k, $sprogId), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
	return $out;
}
```

`saldi_help_texts_prefetch($keys, $sprogId)` (review round 1): for the keys that are not in the session yet, one check that `tekster.tekst_key` exists and one query `SELECT tekst_key, tekst FROM tekster WHERE sprog_id = '<lang>' AND tekst_key IN (…) ORDER BY id` (the keys come from the TSV, never from the client). `saldi_help_text_pick()` (pure, PHPUnit) keeps per key only its first row, and only when the text is not `''`, not `'-'` and not the key itself (a placeholder), the same rules as `findTxt.php:176-183`. Each hit goes into `$_SESSION[findtekst_session_var($key, $sprogId)]` in the database encoding, exactly as `findtekst()` caches it, so the loop above is a session hit for it; misses, placeholders and `'-'` rows still go the full `findtekst()` path (including the seed and self-heal).

`index/helpTexts.php` (new; header as above, `// 20261107 CL/ASR Created: the JSON endpoint that serves the help texts to the help panel.`), modelled on `index/globalSearch.php:26-42`: `ob_start()`, `@session_start()`, `$title = 'helpTexts'`, `$webservice = true`, `$modulnr = 0`, `$permission_key = 'any'`, `$permission_post_read = true`, `$skipLogtime = true`, include `connect.php`, `online.php`, `std_func.php`, `stdFunc/findTxtUtf8.php` and `saldi_help_texts.php`; `ob_end_clean()`; the headers and answers of §3.4. GET only (405 otherwise). It reads nothing but `$_GET['group']` (a string; an array → 400).

Cost: after the first call in a session every text is a session hit. The first call in a session costs two queries in all (the column check and the `IN` query on `tekster (tekst_key, sprog_id)`, `includes/betweenUpdates.php:129`), where a plain `findtekst()` loop cost two per key (its `information_schema` query, `findTxt.php:143`, runs on every uncached call, then the keyed lookup, `:174`), about 380 for the kassekladde group. The very first call per tenant and language also runs, per key, the legacy content match and the self-heal insert (`findTxt.php:188-246`), about 300 small queries for the kassekladde group, once. `index/helpTexts.php` closes the session (`session_write_close()`) before it writes the answer. The texts are still fetched lazily on the first open and never on page load (§2.4).

---

## 5. Tours: "Ro på" (TOURS)

### 5.1 Behaviour

- No auto-start, ever. No "seen" filter, no writes to `tutorials`. `includes/tutorialAPI.php` and the table stay; old rows become inert. `rental/settings.php:161-197` still posts `restart`, which deletes nothing that matters.
- Ring instead of scrim: 2 px `#4FA0EE`, radius 6, halo `0 0 0 3px rgba(79,160,238,.18)`, 4 px offset; `pointer-events: none`; clicks reach the page.
- Card (white, 1 px `#e4e8ef`, radius 8, shadow `0 10px 34px rgba(16,30,60,.16)`, 320 px wide, 13 px system/Inter font): head "Rundvisning · 2 af 4" and × (`aria-label` "Luk rundvisningen"); the step text; foot "Spørg Saldi AI om dette" (only inside the shell with `window.parent.SaldiHelp`), Forrige (hidden on step 1), Næste / Færdig (Færdig is the filled button `#114691`).
- Ends on: ×, Færdig, Esc (`preventDefault()`), a `pointerdown` outside the card (capture, no `preventDefault()`; ignore a press on a scrollbar: `e.target === el && (e.offsetX >= el.clientWidth || e.offsetY >= el.clientHeight)`), `SaldiTour.stop()`.
- ← / → change step only when the event target is not `INPUT`, `TEXTAREA`, `SELECT` or contentEditable.
- Focus: remember `document.activeElement` and its selection at start. On ×, Færdig, Esc and `stop()`, restore it and the scroll positions. On an outside press, restore neither (the user moved on).
- Count steps by visibility (`getClientRects()` with a rect of width and height > 0), not existence. This excludes the empty `#kk-balance-status` on a posted kladde and columns hidden by the gear.
- The blue Hjælp cell (`#tutorial-help`): inside the shell with `window.parent.SaldiHelp` → `postMessage({type:'saldi:help-open', source:'classic'})`; otherwise (standalone page, or a tenant without the panel) → `SaldiTour.start()`. Never a reload. Always `preventDefault()` (the cell's `<button>` has no type). One delegated `click` listener on `document` handles it (§5.2), so it works whatever the order of the top line and the `create_tutorial()` call: `debitor/ordre.php:625-626` and `kreditor/ordre.php:411-412` print the tour before `ordreside()` prints the top line with the cell (`debitor/ordre.php:3401` → `includes/orderFuncIncludes/topLine.php:54`; `kreditor/ordre.php:1275,1838` → `includes/kreditorOrderFuncIncludes/topLine_S.php:80`). Today that works only because the old script binds at `load` (`tutorial.php:121,138-142,441`).

### 5.2 Exact changes to `includes/tutorial.php`

| Anchor | Change |
|---|---|
| after `:5` (last history line) | `// 20261107 CL/ASR "Ro på": no auto-start and no seen rows; a ring instead of the dark overlay; Esc, ×, Færdig or a press outside the card ends the tour; window.SaldiTour for the help panel; the Hjælp cell opens the help panel in the shell and starts the tour in place elsewhere (never a reload).` |
| `:6` `ob_start();` | keep |
| `:8` signature | keep `function create_tutorial($id, $steps)`. A step may carry `'first' => true` (ring only the first match). |
| `:9-10` `global $bruger_id;` | remove (no DB access any more); keep `global $sprog_id;` |
| `:13-47` markup | replace with: `<div id="tutorial-ring" aria-hidden="true"></div>` and `<div id="tutorial-tooltip" role="dialog" aria-modal="false" aria-labelledby="tutorial-step">` containing `#tutorial-header` (`span#tutorial-step`, `button#tutorial-skip` type="button" with `aria-label` 8762 and the existing ✕ svg in `#5b6b85`), `#tutorial-content`, `#tutorial-controls` (`button#tutorial-ask` hidden, `span.tutorial-grow`, `button#tutorial-prev` 2598, `button#tutorial-next` 1200, `button#tutorial-finish` 2599; all `type="button"`). `#tutorial-overlay` and `#status-text` are gone. |
| `:49-104` CSS | replace with the ring and card CSS (values in §5.1; ring `position:fixed; z-index:100000; display:none`; card `position:fixed; z-index:100010; display:none` (above the kassekladde's account list at 99999, review round 1); scoped by the ids; explicit font, colours, margins, `box-sizing`; `@media print` hides both). |
| `:106-450` script | replace with one IIFE (below). The `Tutorial` class keeps `showStep`, `position`, `visibleRect`, `rememberScroll`, `restoreScroll` and the rAF-throttled scroll/resize listeners from SST-852 (`bd77a5b9`), with the changes listed below the sketch. |

Strings for the script are printed as JSON. Latin-1 tenants: convert each string with `mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1')`, then `json_encode($x, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)` **without** `JSON_UNESCAPED_UNICODE`, so the output is ASCII and safe in both charsets. If `json_encode` returns `false`, print `[]` / `{}`.

```php
$list = array();
foreach ($steps as $step) {
	$list[] = array('selector' => (string) $step['selector'], 'content' => tutorial_utf8((string) $step['content']), 'first' => !empty($step['first']));
}
$txt = array(
	'tour' => tutorial_utf8(findtekst('8760|Rundvisning', $sprog_id)),
	'of'   => tutorial_utf8(findtekst('8761|%s af %s', $sprog_id)),
	'ask'  => tutorial_utf8(findtekst('8763|Spørg Saldi AI om dette', $sprog_id)),
	'close'=> tutorial_utf8(findtekst('8762|Luk rundvisningen', $sprog_id)),
);
```

(`tutorial_utf8()` is a small function defined once in `tutorial.php` with `if (!function_exists(...))`.)

```js
(function () {
  if (window.SaldiTour) { return; }                       // one tour per page
  var steps = /* json $list */, id = /* json (string)$id */, T = /* json $txt */;
  var ready = id.indexOf('book-') !== 0;
  if (!ready) { document.addEventListener('renderComplete', function () { ready = true; }); }
  function els(s) { try { return s.first ? [document.querySelector(s.selector)].filter(Boolean) : Array.prototype.slice.call(document.querySelectorAll(s.selector)); } catch (e) { return []; } }
  function visible(el) { return Array.prototype.some.call(el.getClientRects(), function (r) { return r.width > 0 && r.height > 0; }); }
  function available() { return ready ? steps.filter(function (s) { return els(s).some(visible); }) : []; }
  function shell() { try { return window.parent !== window && window.parent.SaldiHelp && window.parent.SaldiHelp.enabled ? window.parent : null; } catch (e) { return null; } }
  function tell(msg) { if (window.parent !== window) { try { window.parent.postMessage(msg, window.location.origin); } catch (e) {} } }
  var run = null;
  /* class Tutorial { … } — see the change list */
  window.SaldiTour = {
    id: id,
    count: function () { return available().length; },
    active: function () { return !!run; },
    start: function (o) {
      o = o || {};
      var list = available();
      if (!list.length) { return false; }
      if (run) { run.end({ restore: false }); }
      run = new Tutorial(list, { onEnd: function () { run = null; tell({ type: 'saldi:tour', id: id, state: 'end' }); } });
      run.start(o.step || 0, o.focus !== false);
      tell({ type: 'saldi:tour', id: id, state: 'start', step: (o.step || 0) + 1, of: list.length });
      return true;
    },
    stop: function () { if (run) { run.end({ restore: true }); } }
  };
  // One delegated listener: the cell may be printed before or after this script (debitor/ordre.php and
  // kreditor/ordre.php print the tour first). Bubble phase, so the shell's capture listener (§1.5) wins once bound.
  document.addEventListener('click', function (e) {
    var c = e.target && e.target.closest && e.target.closest('#tutorial-help');
    if (!c) { return; }
    e.preventDefault();
    if (shell()) { tell({ type: 'saldi:help-open', source: 'classic' }); } else { window.SaldiTour.start({ focus: true }); }
  });
})();
```

`SaldiTour` is defined while the script is parsed (not on `load`), so the shell sees it as early as possible. The Hjælp cell does not need to exist yet: the listener is on `document` and looks the cell up per click. This keeps the cell working in the old-menu mode and outside the shell on the order pages, where the tour is printed before the top line (§5.1).

Changes inside the `Tutorial` class:
1. The run flag is `this.running` (not `overlay.style.display === 'block'`, `:164,280`), because the ring hides while the target is scrolled out of view.
2. `position()`: compute the visible union box as today (`:288-302`, padding 0), then set the ring `left/top/width/height` to the box grown by 6 px on each side; no clip-path. When nothing is visible, hide the ring (`display:none`) and keep the card where it was, clamped to the viewport.
3. Remove `closeCard()`, `closeAll()`, `restart()` (`:189-238`) and the `.highlight` class (`:258`).
4. `showStep(i)`: header `T.tour + ' · ' + T.of.replace('%s', i + 1).replace('%s', n)`; Forrige hidden on step 1; Næste hidden and Færdig shown on the last step; `#tutorial-ask` shown only when `shell()`; its click: `this.end({ restore: true })`, then `tell({ type: 'saldi:help-ask', text: plain(step.content).slice(0, 200) })`, where `plain(html)` is `new DOMParser().parseFromString(html, 'text/html').body.textContent.replace(/\s+/g, ' ').trim()` (an inert document: no script runs and no image loads; never a detached element's `innerHTML`, §1.14). Every `showStep()` after the first posts `{ type: 'saldi:tour', id, state: 'step', step: i + 1, of: n }` (§3.3), so the shell can re-place its strip without polling. The step text itself keeps today's `innerHTML` (the tekster rows contain `<br>`, `kassekladde.php:5701-5735`).
5. Key handling: Esc → `preventDefault()`, `end({ restore: true })`; ← / → as in §5.1; the listener is removed in `end()`.
6. A capture `pointerdown` listener on `document` while running: outside the card and not on a scrollbar → `end({ restore: false })`.
7. `start(i, focus)`: remember focus and selection, bind listeners, `showStep(i)`, focus `#tutorial-next` (or `#tutorial-finish`) with `preventScroll` when `focus`.
8. `end({ restore })`: hide ring and card, unbind, `restoreScroll()` and restore focus/selection only when `restore`, call `onEnd`. An `INPUT` goes back through `window.focusAccountQuietly()` when the page has it (§1.12), so a kassekladde lookup field does not open its list.
9. The card gets `aria-label` "Rundvisning, trin i af n" on each step.

### 5.3 What this changes outside the pilot pages

`tutorial.php` is included by 17 pages (`finans/regnskab.php:617`, `debitor/ordreliste.php:588`, `debitor/ordre.php:626`, `debitor/debitorkort.php:3058`, `debitor/debitor_kommission.php:670`, `kreditor/ordreliste.php:241`, `kreditor/ordre.php:412`, `lager/lister/{vareliste:717,indkøb:883,ordrestatus:823,serialnumber:443}`, `rental/{index:140,edit:54,daysoff:45,items:42,settings:171}`, plus the two pilot pages). After this change none of them auto-starts, for every tenant. A tour starts from the panel's Rundvisning row (tenants with the panel), or from the Hjælp cell in place and the old Assist menu's "Rundvisning af siden" (tenants without the panel, and outside the shell). The booking tours (`rental/{index,edit,daysoff,items}.php`) have no Hjælp cell and no `page_help()`, so for tenants without the panel the old Assist menu is their only entry: `topbarAssistPage()` asks the frame's `SaldiTour.count()` whether or not the page has a cell, sets `help.tour = 'saldi:tour'` when it has none, and `topbarAssistRun('tour')` then calls `SaldiTour.start({ focus: true })` (§2.1). A `book-*` tour counts 0 until the page has sent `renderComplete` (§3.3), so the menu offers it from then on. For a pilot tenant the panel's Rundvisning row covers the same pages. On `debitor/ordre.php` and `kreditor/ordre.php` the cell keeps working although the tour is printed before the cell (delegated listener, §5.1). The four lager pages that share the id `vareliste` no longer interfere (no seen rows). This is intended (Forslag step 1).

### 5.4 Tour steps on the pilot pages

`finans/kassekladde.php`:
- `:7` → `// --- finans/kassekladde.php --- ver 5.1.0 --- 2026-11-07 ---`
- after `:215` → `// 20261107 CL/ASR Tour steps that exist on the page (bilag, debet, fakturanr., balance, Bogfør; on a posted journal Tilbagefør and Kopier til ny); #clip and #questionmark are gone. The tour no longer starts by itself (includes/tutorial.php).`
- `:5701-5735` (the `$steps` block): replace with these steps, in this order. When a `findtekst()` call is written or moved, copy the Danish fallback verbatim from `tekster.csv`, including any `<br>` (the guardrail compares them).

```php
	$steps = array();
	$steps[] = array("selector" => 'input[name="bila1"]',
		"content" => findtekst('2606|Du kan slette linjen ved at skrive \'-\' i feltet i stedet for et tal og trykke Enter', $sprog_id) . ".");
	$steps[] = array("selector" => 'input[name="debe1"]',
		"content" => findtekst('2603|Du kan slå op i Debet/Kredit-felterne:<br>Hvis D/K-feltet er tomt eller indeholder \'F\', søger systemet i kontoplanen. Hvis feltet viser \'D\' (debitor) eller \'K\' (kreditor), søges der i henholdsvis kunder eller leverandører.', $sprog_id) . "<br>"
			. findtekst('2604|Du kan også selv skrive \'D\' eller \'K\' for at vælge, hvor der skal søges.<br>Når du begynder at skrive et navn eller kontonummer, vises der kun relevante forslag.', $sprog_id) . "<br>"
			. findtekst('8764|Når du klikker i feltet, viser Saldi en liste, du kan vælge fra.', $sprog_id));
	$steps[] = array("selector" => 'input[name="fakt1"]',
		"content" => findtekst('8765|Skriv fakturanummeret. Når du klikker i feltet, viser Saldi de åbne poster.', $sprog_id));
	$steps[] = array("selector" => "#kk-balance-status",
		"content" => findtekst('8778|Differencen mellem debet og kredit på de gemte linjer.', $sprog_id) . " "
			. findtekst('8766|Den opdateres, når du gemmer.', $sprog_id));
	$steps[] = array("selector" => 'input[name="doPost"]',
		"content" => findtekst('8767|Bogfør viser først en oversigt. Der bogføres ikke noget, før du klikker Bogfør igen.', $sprog_id));
	$steps[] = array("selector" => 'a[href*="ompost="]', "first" => true,
		"content" => findtekst('2601|Klik her for at tilbageføre denne postering som en ompostering i en åben kassekladde', $sprog_id) . ".");
	$steps[] = array("selector" => "#kopier-button",
		"content" => findtekst('8768|Laver en ny, åben kladde med de samme linjer. Sæt flueben i Vend fortegn (tilbagefør) for at vende hele kladden.', $sprog_id));
	include(__DIR__ . "/../includes/tutorial.php");
	create_tutorial("kasseklad", $steps);
```

Open or new kladde → steps 1–5 are visible (`#kk-balance-status` already shows "Kladden balancerer" on a new kladde, `kassekladde.php:5197-5209`). Posted kladde → steps 6–7. Simulated kladde → none (no Rundvisning row). The `include` and `create_tutorial("kasseklad", $steps);` lines are as today.

`finans/kladdeliste.php`:
- `:7` → `// --- finans/kladdeliste.php --- ver 5.1.0 --- 2026-11-07 ---` (was `patch 5.0.0`; guardrail `file-version`)
- after `:47` (the second line of the SD-730 entry that starts at `:46`; the header block ends there, `:48` is empty) → `// 20261107 CL/ASR Tour of three steps (Ny, a journal row, Vis alle/Vis egne); it no longer starts by itself (includes/tutorial.php).`
- `:838-847` (the `$steps` block): replace with

```php
$steps = array();
$steps[] = array(
	"selector" => "#create-new",
	"content" => findtekst('2607|Opret ny kassekladde ved at klikke her', $sprog_id).".",
);
$steps[] = array(
	"selector" => "#datatable-kladdelst tbody tr:has(td[data-bogfort])",
	"first" => true,
	"content" => findtekst('8769|Klik på en kladde for at åbne den. Åbne kladder står øverst og bogførte nederst.', $sprog_id),
);
$steps[] = array(
	"selector" => "#filter-toggle-btn",
	"content" => findtekst('8770|Skift mellem dine egne kladder og alle brugeres kladder.', $sprog_id),
);

include(__DIR__ . "/../includes/tutorial.php");
create_tutorial("kladlist", $steps);
```

  Step 1 is unchanged. Step 2 rings only the first row (`'first' => true`); without it the ring would be the union box of every row. On an empty list step 2 has no target and the tour has 2 steps.

No other line of either page changes.

---

## 6. Spørg Saldi AI in phase 1

There is no engine that answers inside the panel yet. `SaldiHelp.ask(question)` hands the question the user confirmed to the Guide and says honestly what happens.

```js
function guideState() {
  const a = window.SaldiAssist;
  if (a && a.v2Ready) return 'ask';                                   // Guide v2 listens for saldi:assist:ask (guide_now.js:1436-1441)
  if (window.SALDI_CHAT && typeof window.SALDI_CHAT.open === 'function') return 'open';   // v1 widget, or v2 not ready yet
  if (document.getElementById('saldi-chat-launcher')) return 'open';
  return 'none';
}
```

`ask(q)`:
1. If `guideState()` is `'none'` and `document.readyState === 'loading'`, wait once for `DOMContentLoaded` and decide then. The Guide loader is a `defer` script (`main.php:1328`), so it has run (or failed) before `DOMContentLoaded`, and it sets `v2Ready` and `SALDI_CHAT` synchronously in `init()` (`guide_now.js:1408-1441,1444-1462`). No timer, no polling.
2. `'ask'` and `q !== ''` → `window.dispatchEvent(new CustomEvent('saldi:assist:ask', { detail: { question: q.slice(0, 1000) } }))`. No `focus` object (its shape is the Guide's; page context reaches the Guide from `location.hash`, `guide_now.js:144-158`). The loader opens its window and relays the question to the widget when it is ready (`guide_now.js:435-471`). The widget (v2 only) puts it in the composer **unsent**, focuses the composer and sends nothing until the user does (`support-sally@148a2af1:frontend/components/embed/EmbedClient.tsx:1252-1266`, test `EmbedClient.companion.test.tsx:115` "puts the question in the composer unsent"); Enter sends on a keyboard, the send button on touch (`Composer.tsx:296`). The panel shows `t('8738')` "Saldi AI åbner i sit eget vindue med dit spørgsmål. Det er ikke sendt endnu. Send det dér, når du er klar." (`role="status"`). Returns `'sent'` (meaning: handed over, not submitted). The question is also dropped by the widget if the company or user changes before it is shown (`EmbedClient.companion.test.tsx:186-207`); that is the Guide's own rule.
3. `'ask'` with `q === ''`, or `'open'` → `topbarOpenAssist()` (`main.php:992-1000`). With a question, the panel shows `t('8739')` and keeps the question in the search field so the user can copy it. Returns `'opened'`.
4. `'none'` → no hand-off. The panel shows `t('8740')` "Saldi AI kan ikke svare lige nu. Du kan skrive spørgsmålet til support i stedet." and the button `t('8741')` → the support view with the question prefilled in "Din besked" (form or mail link, §1.9). Returns `'none'`.
5. Unpinned panel: after a `'sent'` or `'opened'` hand-off it closes (D8); the notice is then announced through a polite live region (`span.hc-sr[role=status]` on `body`). Pinned: it stays open and shows the notice under the search field.
6. The Guide's `closeChat()` moves focus to its hidden launcher (`guide_now.js:742`); help does not try to take focus back.

On ssl12 (no `SALDI_ASSIST_*` variables) the Guide still loads from wuweiworkai.com and answers anonymously in Danish (`includes/saldi_assist_guide.php:23-29`; the token endpoint answers 503 `not_configured` and the widget goes on without a token). The `'none'` path is therefore tested by blocking that host (§13).

"Hvad får Saldi AI at vide?" says only what is true for this installation:
- `data-records="0"` (`SALDI_ASSIST_RECORDS_ENABLED` not `1`): `t('8737')` "Det spørgsmål, du sender, og hvilken side du står på. Saldi AI kan ikke se dine posteringer eller beløb." The shell then gives the Guide no record access (`main.php:1330-1336` is not printed).
- `data-records="1"`: `t('8771')` "Det spørgsmål, du sender, hvilken side du står på, og den gemte kladde eller faktura, du har åben. Saldi AI kan læse den, men ikke ændre den." (`javascript/saldi-assist-records.js:1,40-56`: a read-only grant for the open saved journal or invoice via `includes/saldi_assist_record.php`.)
- Always after it: `t('8772')` "Spørgsmålet sendes til den server, Saldi AI kører på."
The text does not mention the role or the language.

Symbol states `thinking` and `answering` stay reserved until the engine sends signals (Forslag §7); `SaldiHelp.setEngineState()` is the hook.

---

## 7. The symbol (summary)

- 28 px disc `#F7F4EF`, 1 px `#E3DCD2`, glyph 14 × 17.5, same size as the user avatar; label "Hjælp" (findtekst 2564, Saldi Grotesk 500, 0.8125 rem) to its right from a 1240 px viewport; hover changes only the background; bar stays 44 px.
- `aria-label` 8700 "Hjælp og Saldi AI" (8701 while signalling, which phase 1 never does, D11); `title` "Hjælp (?)", or 8755 "Hjælp, fastgjort" + " (?)" while pinned; `aria-controls="saldi-help"`; `aria-expanded` follows the panel.
- One click opens the panel; there is no menu. The old Assist icon (`topbar_assist_icon()`) and menu are not rendered while the panel is on; their functions are in the panel (§1.13).
- Dark mode: the disc keeps its light fill; everything else uses tokens (§2.3 item 4).
- SVG and CSS copied verbatim from the landing page (§1.10, §2.2).

---

## 8. Text ids

### 8.1 Numeric ids in `importfiler/tekster.csv` (chrome and tours)

Range 8700–8799 is free: checked on 7 Nov 2026 with `git for-each-ref refs/heads refs/remotes` in a full clone (706 refs) and `git grep` of each ref for `^87NN\t` in `importfiler/tekster.csv` and `87NN|` in `*.php`/`*.js`/`*.inc`: no hits; also no hits in the local working copies. Re-run the check before the PR. 8778 is used since review round 2; 8779–8799 stay reserved for this feature.

TOURS appends these rows at the end of `importfiler/tekster.csv` in ascending order (8700–8778 without 8711; 8778 since review round 2): `id<TAB>Dansk<TAB>English<TAB>Norsk`, exactly four columns, no quotes, no trailing tab or space (guardrail `tekster`). `%s` is replaced in order by the code. 8711 is not used.

| id | Dansk | English | Norsk | used by |
|---|---|---|---|---|
| 8700 | Hjælp og Saldi AI | Help and Saldi AI | Hjelp og Saldi AI | SHELL |
| 8701 | Hjælp og Saldi AI, har et forslag til siden | Help and Saldi AI, has a suggestion for this page | Hjelp og Saldi AI, har et forslag til siden | SHELL |
| 8702 | Søg i hjælpen, eller spørg Saldi AI | Search help, or ask Saldi AI | Søk i hjelpen, eller spør Saldi AI | SHELL |
| 8703 | Om siden | About this page | Om siden | SHELL |
| 8704 | Feltet du stod i: %s | The field you were in: %s | Feltet du sto i: %s | SHELL |
| 8705 | Spørg Saldi AI om feltet | Ask Saldi AI about the field | Spør Saldi AI om feltet | SHELL |
| 8706 | Sådan gør du | How to | Slik gjør du | SHELL |
| 8707 | Vis mig | Show me | Vis meg | SHELL |
| 8708 | Vis mig: %s | Show me: %s | Vis meg: %s | SHELL |
| 8709 | Rundvisning af siden (%s trin) | Tour of the page (%s steps) | Omvisning på siden (%s trinn) | SHELL |
| 8710 | Vis mig rundt, ét trin ad gangen | Show me around, one step at a time | Vis meg rundt, ett trinn om gangen | SHELL |
| 8712 | Spørg Saldi AI: »%s« | Ask Saldi AI: »%s« | Spør Saldi AI: »%s« | SHELL |
| 8713 | Spørg Saldi AI om %s | Ask Saldi AI about %s | Spør Saldi AI om %s | SHELL |
| 8714 | Kontakt support | Contact support | Kontakt support | SHELL |
| 8715 | Ring %s | Call %s | Ring %s | SHELL |
| 8716 | hverdage 9–16 | weekdays 9–16 | hverdager 9–16 | SHELL |
| 8717 | Åbent nu, til kl. %s | Open now, until %s | Åpent nå, til kl. %s | SHELL |
| 8718 | Lukket, åbner kl. %s | Closed, opens at %s | Stengt, åpner kl. %s | SHELL |
| 8719 | Lukket, åbner i morgen kl. %s | Closed, opens tomorrow at %s | Stengt, åpner i morgen kl. %s | SHELL |
| 8720 | Lukket, åbner mandag kl. %s | Closed, opens Monday at %s | Stengt, åpner mandag kl. %s | SHELL |
| 8721 | Skriv til os | Write to us | Skriv til oss | SHELL |
| 8722 | Din besked | Your message | Din melding | SHELL |
| 8723 | Send med | Include | Send med | SHELL |
| 8724 | Sidens adresse | Page address | Sidens adresse | SHELL |
| 8725 | Send | Send | Send | SHELL |
| 8726 | Tak. Vi har fået din besked og svarer på mail, normalt samme dag. | Thank you. We have received your message and will reply by email, usually the same day. | Takk. Vi har fått meldingen din og svarer på e-post, vanligvis samme dag. | SHELL |
| 8727 | Beskeden blev ikke sendt. Prøv igen, eller ring til os. | The message was not sent. Try again, or call us. | Meldingen ble ikke sendt. Prøv igjen, eller ring oss. | SHELL |
| 8728 | Tilbage til hjælpen | Back to help | Tilbake til hjelpen | SHELL |
| 8729 | Viser: %s | Showing: %s | Viser: %s | SHELL |
| 8730 | Fastgør | Pin | Fest | SHELL |
| 8731 | Løsn | Unpin | Løsne | SHELL |
| 8732 | Hold hjælpen åben i højre side, også når du skifter side | Keep help open on the right, also when you change page | Hold hjelpen åpen til høyre, også når du bytter side | SHELL |
| 8733 | Løsn: hjælpen lukker, når du skifter side | Unpin: help closes when you change page | Løsne: hjelpen lukkes når du bytter side | SHELL |
| 8734 | Vinduet er for smalt til at vise hjælpen ved siden af. Den ligger over siden. | The window is too narrow to show help beside the page. It lies over the page. | Vinduet er for smalt til å vise hjelpen ved siden av. Den ligger over siden. | SHELL |
| 8735 | Luk hjælpen | Close help | Lukk hjelpen | SHELL |
| 8736 | Hvad får Saldi AI at vide? | What does Saldi AI get to know? | Hva får Saldi AI vite? | SHELL |
| 8737 | Det spørgsmål, du sender, og hvilken side du står på. Saldi AI kan ikke se dine posteringer eller beløb. | The question you send and which page you are on. Saldi AI cannot see your entries or amounts. | Spørsmålet du sender, og hvilken side du er på. Saldi AI kan ikke se posteringene eller beløpene dine. | SHELL |
| 8738 | Saldi AI åbner i sit eget vindue med dit spørgsmål. Det er ikke sendt endnu. Send det dér, når du er klar. | Saldi AI opens in its own window with your question. It has not been sent yet. Send it there when you are ready. | Saldi AI åpnes i sitt eget vindu med spørsmålet ditt. Det er ikke sendt ennå. Send det der når du er klar. | SHELL |
| 8739 | Saldi AI-vinduet er åbent. Skriv dit spørgsmål dér. | The Saldi AI window is open. Type your question there. | Saldi AI-vinduet er åpent. Skriv spørsmålet ditt der. | SHELL |
| 8740 | Saldi AI kan ikke svare lige nu. Du kan skrive spørgsmålet til support i stedet. | Saldi AI cannot answer right now. You can write the question to support instead. | Saldi AI kan ikke svare akkurat nå. Du kan skrive spørsmålet til support i stedet. | SHELL |
| 8741 | Skriv til support med spørgsmålet | Write to support with the question | Skriv til support med spørsmålet | SHELL |
| 8742 | Her er knappen. Der sker ikke noget, før du selv klikker. | Here is the button. Nothing happens until you click it yourself. | Her er knappen. Ingenting skjer før du klikker selv. | SHELL |
| 8743 | Her er feltet. | Here is the field. | Her er feltet. | SHELL |
| 8744 | Her står det. | It is shown here. | Her står det. | SHELL |
| 8745 | Knappen ligger bag hjælpen. | The button is behind help. | Knappen ligger bak hjelpen. | SHELL |
| 8746 | Feltet ligger bag hjælpen. | The field is behind help. | Feltet ligger bak hjelpen. | SHELL |
| 8747 | Luk (Esc) | Close (Esc) | Lukk (Esc) | SHELL |
| 8748 | Til denne side | For this page | For denne siden | SHELL |
| 8749 | Åbn hjælpen (uden for felter) | Open help (outside fields) | Åpne hjelpen (utenfor felt) | SHELL |
| 8750 | Åbn hjælpen, også i et felt | Open help, also in a field | Åpne hjelpen, også i et felt | SHELL |
| 8751 | Luk hjælpen og gå tilbage til feltet | Close help and go back to the field | Lukk hjelpen og gå tilbake til feltet | SHELL |
| 8752 | Søg i Saldi | Search Saldi | Søk i Saldi | SHELL |
| 8753 | Gå et niveau tilbage | Go back one level | Gå ett nivå tilbake | SHELL |
| 8754 | Sidens egne genveje | The page's own shortcuts | Sidens egne snarveier | SHELL |
| 8755 | Hjælp, fastgjort | Help, pinned | Hjelp, festet | SHELL |
| 8756 | Forklar mere om: %s | Tell me more about: %s | Forklar mer om: %s | SHELL |
| 8757 | Skriv til %s | Write to %s | Skriv til %s | SHELL |
| 8758 | Det ligger bag hjælpen. | It is behind help. | Det ligger bak hjelpen. | SHELL |
| 8759 | Her er linjen. | Here is the line. | Her er linjen. | SHELL |
| 8760 | Rundvisning | Tour | Omvisning | TOURS, SHELL (strip label) |
| 8761 | %s af %s | %s of %s | %s av %s | TOURS |
| 8762 | Luk rundvisningen | Close the tour | Lukk omvisningen | TOURS |
| 8763 | Spørg Saldi AI om dette | Ask Saldi AI about this | Spør Saldi AI om dette | TOURS, SHELL (question link) |
| 8764 | Når du klikker i feltet, viser Saldi en liste, du kan vælge fra. | When you click in the field, Saldi shows a list you can choose from. | Når du klikker i feltet, viser Saldi en liste du kan velge fra. | TOURS |
| 8765 | Skriv fakturanummeret. Når du klikker i feltet, viser Saldi de åbne poster. | Type the invoice number. When you click in the field, Saldi shows the open items. | Skriv fakturanummeret. Når du klikker i feltet, viser Saldi de åpne postene. | TOURS |
| 8766 | Den opdateres, når du gemmer. | It is updated when you save. | Den oppdateres når du lagrer. | TOURS |
| 8767 | Bogfør viser først en oversigt. Der bogføres ikke noget, før du klikker Bogfør igen. | Post first shows an overview. Nothing is posted until you click Post again. | Bokfør viser først en oversikt. Ingenting bokføres før du klikker Bokfør igjen. | TOURS |
| 8768 | Laver en ny, åben kladde med de samme linjer. Sæt flueben i Vend fortegn (tilbagefør) for at vende hele kladden. | Creates a new, open journal with the same lines. Tick Flip sign (reverse) to reverse the whole journal. | Lager en ny, åpen kladd med de samme linjene. Kryss av for Vend fortegn (tilbagefør) for å snu hele kladden. | TOURS |
| 8769 | Klik på en kladde for at åbne den. Åbne kladder står øverst og bogførte nederst. | Click a journal to open it. Open journals are at the top and posted ones at the bottom. | Klikk på en kladd for å åpne den. Åpne kladder står øverst og bokførte nederst. | TOURS |
| 8770 | Skift mellem dine egne kladder og alle brugeres kladder. | Switch between your own journals and every user's journals. | Bytt mellom dine egne kladder og alle brukeres kladder. | TOURS |
| 8771 | Det spørgsmål, du sender, hvilken side du står på, og den gemte kladde eller faktura, du har åben. Saldi AI kan læse den, men ikke ændre den. | The question you send, which page you are on, and the saved journal or invoice you have open. Saldi AI can read it but not change it. | Spørsmålet du sender, hvilken side du er på, og den lagrede kladden eller fakturaen du har åpen. Saldi AI kan lese den, men ikke endre den. | SHELL |
| 8772 | Spørgsmålet sendes til den server, Saldi AI kører på. | The question is sent to the server Saldi AI runs on. | Spørsmålet sendes til serveren Saldi AI kjører på. | SHELL |
| 8773 | Luk hjælpen (træk ned) | Close help (drag down) | Lukk hjelpen (dra ned) | SHELL (sheet handle `aria-label`) |
| 8774 | Taster | Keys | Taster | SHELL (shortcut group `keys`) |
| 8775 | Koder i felterne | Codes in the fields | Koder i feltene | SHELL (shortcut group `codes`) |
| 8776 | Overalt i Saldi | Everywhere in Saldi | Overalt i Saldi | SHELL (global shortcuts) |
| 8777 | Spørg Saldi AI | Ask Saldi AI | Spør Saldi AI | SHELL (row without a page label) |
| 8778 | Differencen mellem debet og kredit på de gemte linjer. | The difference between debit and credit on the saved lines. | Differansen mellom debet og kredit på de lagrede linjene. | TOURS (kassekladde balance step; replaces 3369, whose Danish says "debit", review round 2) |

Reused ids (no new rows): 2564 Hjælp, 6024 Tastaturgenveje, 6455 Rundvisning af siden, 92 Vejledning, 5148 Forstået, 636 Vis alle, 641 Vis egne, 2598 Forrige, 1200 Næste, 2599 Færdig, 2601, 2603, 2604, 2606, 2607 (3369 until review round 2, now 8778). Ids 6453/6454 ("Spørg Assist …") stay in the csv for the old menu. No existing row is reworded (a reworded row needs a stale-row purge).

### 8.2 Descriptive keys in `importfiler/tekster_keyed.tsv` (the page help content, CONTENT)

All help content (§4: page names, Om siden, tasks, steps, Vis mig labels and notes, field help, shortcut texts, questions) is in `importfiler/tekster_keyed.tsv`, the descriptive-key seed file that `findtekst()` reads since 29 Oct (`includes/stdFunc/findTxt.php:52-93,171-246`; `doc/ai/convention_findtekst_keys.md`). There is no UI text in `saldi-help-content.js`.

Format, as the `tekster` guardrail checks it (`tools/guardrails/checks/tekster.mjs:140-190`): `key<TAB>Dansk<TAB>English<TAB>Norsk`, exactly four fields, none empty, no leading or trailing space, no quotes, each key once, and the file sorted by key in plain code-unit order (`LC_ALL=C sort -t "$(printf '\t')" -k1,1`; the existing `common.accept` row stays first). Keys match `^[a-z0-9][a-z0-9_.-]{0,189}$` (lower case only).

Key scheme (`<g>` = the page's `group`, `kl` or `kk`; task and question ids in lower case):

| Content | Key | Example |
|---|---|---|
| page name | `help.<g>.name`, plus `help.kk.name_new`, `help.kk.name_plain` | `help.kk.name` = `Kassekladde %s` / `Cash journal %s` / `Kontantjournal %s` |
| Om siden | `help.<g>.about.<state or flag>` | `help.kk.about.aaben_difference`, `help.kk.about.kopier`, `help.kl.about.kun_egne` |
| task | `help.<g>.task.<id>.title`, `.why`, `.step1` … `.step4`, `.show` (Vis mig label), `.note` (optional) | `help.kk.task.kk3.why` |
| field | `help.<g>.field.<field key>.label`, `.text`; the field key in lower case with `:` → `_` | `help.kl.field.search_kladdenote.label`, `help.kk.field.kkregnaar.text` |
| shortcut text | `help.<g>.key.<nn>` (two digits, in display order) | `help.kk.key.03` = `Opslag i feltet, du står i` |
| question | `help.<g>.q.<id>.q`, `.intro`, `.step1` …, `.show` | `help.kk.q.q3d.q` |
| shared | `help.common.<name>` | `help.common.shortcuts_note` |

Rules:
- A step, label or note that §4 says is "KKn's" or "as kladdeliste" reuses that key; it is not copied.
- `%s` only where the shell passes an argument (`help.kk.name`). Same count of `%s` and `**` in all three columns.
- English and Norwegian on-screen names follow §4.1. Every Norwegian text is bokmål, written for the same reader as the Danish.
- Changing a row's text later needs `deleteStaleTekst('<key>', <old texts>)` at the end of `includes/betweenUpdates.php` in the same change, because tenants keep the seeded text in their `tekster` table (`convention_findtekst_keys.md`, "Behaviour to know"; the guardrail enforces it, `tekster.mjs:176-188`). New rows need nothing.
- Expected size: about 90 rows for `kl`, about 220 for `kk`, 1 for `common`.

---

## 9. Pilot and kill switch

- Environment `SALDI_HELP_PANEL` (web server env):
  - unset or `pilot` → **phase-1 default (D12)**: the panel only for tenants with the settings row `help/panel` = `on`; every other tenant keeps the old Assist menu;
  - `on` → the panel for every tenant, unless its row says `off` (the default after the owner's approval, §2.5);
  - `off` or any other value → the old Assist menu for everyone.
- Tenant row, in the tenant database (`SELECT db FROM regnskab WHERE regnskab = '<regnskab>'` in the master database gives its name):
  ```sql
  -- switch the panel on for this regnskab (the pilot; used for the test regnskab on ssl12, §13.1):
  INSERT INTO settings (var_grp, var_name, var_value, var_description) VALUES ('help', 'panel', 'on', 'Hjælp panel (SALDI_HELP_PANEL pilot)');
  -- after the default has flipped to on: switch it off for one regnskab without a deploy:
  INSERT INTO settings (var_grp, var_name, var_value, var_description) VALUES ('help', 'panel', 'off', 'Hjælp panel kill switch (SALDI_HELP_PANEL)');
  -- undo either:
  DELETE FROM settings WHERE var_grp = 'help' AND var_name = 'panel' AND COALESCE(user_id, 0) = 0 AND COALESCE(pos_id, 0) = 0;
  ```
- Off (no panel for this tenant) means the shell of `1b8cb4d4`: the Assist button and menu (`topbar.php:399-409`), `topbarAssistKeys` on `?`, no `saldi-help.css`, no help scripts, no panel host. Two improvements are on for every tenant because they are bug fixes in shared files: the old menu's "Rundvisning" is offered only when `SaldiTour.count() > 0` (`main.php:792`), and the tours behave as §5 (no auto-start, no reload; the Hjælp cell starts the tour in place because `window.parent.SaldiHelp` is undefined, on every page including the order pages, §5.1).
- The switch is read once per shell load (`main.php` after `:117`); a user sees the change on the next login or reload.

---

## 10. Persistence

- `localStorage['saldi.help.pinned'] = '1'` when pinned, removed when unpinned. Per browser, all users of that browser profile.
- `sessionStorage['saldi.help.open'] = '1'` while a pinned panel is open, removed on close. On a shell reload in the same tab (`refresh_opener`, `main.php:127-144`; a language or year change through `topbarAction.php`), a pinned panel that was open opens again without flight and without taking focus. A new tab or a new login starts closed.
- Every read and write is wrapped in try/catch; private mode or blocked storage simply means "not pinned".
- No server-side preference in phase 1.

---

## 11. Accessibility

- Topbar button: `aria-controls`, `aria-expanded` kept in sync (and excluded from `topbarCloseAll()`), `aria-label` 8700/8701, visible focus ring `2px var(--topbar-accent)`.
- Panel: `aria-labelledby="saldi-help-title"`; `role="region"` when docked, `role="dialog" aria-modal="false"` when overlay or sheet; no focus trap (non-modal); `tabindex="-1"` on the root.
- Rows are buttons; task and question rows are accordions with `aria-expanded` and `aria-controls`. Vis mig buttons have `aria-label` 8708 with the task title. The keys list toggle has `aria-expanded`.
- Notices (hand-off, errors) use `role="status"`; the receipt takes focus after Send.
- Strip: `role="status"`.
- Busy: `aria-busy="true"` on the panel while the help texts load (§2.4) and between `pagehide` and the next bind (§1.5.2); disabled Vis mig buttons and Rundvisning row carry `disabled`.
- Sheet handle: `<button type="button">` with `aria-label` `t('8773')` "Luk hjælpen (træk ned)".
- The Vis mig callout and the tour card: `role="dialog" aria-modal="false"` with an `aria-label`; × has `aria-label`; Forstået/Næste get focus.
- Every control has `:hover` and `:focus-visible`. ↑ ↓ are never captured inside inputs. Touch targets 44 px in sheet mode. Text sizes in rem, so "Større tekst" (`data-size="large"`) applies.
- Reduced motion: no flight, no enter animation, no symbol animation, instant scroll.
- F1 and `?` (§1.12); Esc order (§1.12); focus return with caret (§1.12).

---

## 12. Builders

Three builders work at the same time on the files below. None edits a file owned by another. The interfaces in §3 (including the texts endpoint, §3.4) and the id table and key scheme in §8 are the only coupling.

### 12.1 Builder SHELL

Files: `index/main.php`, `index/mainIncludes/topbar.php`, `javascript/saldi-help.js`, `css/saldi-help.css`, `includes/saldi_help_panel.php`, `index/helpSupport.php`, `tests/includes/saldiHelpPanel.test.php`.

Scope: §1 (all), §2, §3.1, §6, §7, §9, §10, §11; calls §3.4.

Port map from the prototype (`prototype_hjaelp/help/`, outside the repository). Every port replaces the prototype's template-string HTML (`h('<…>')`, `innerHTML`) with DOM calls (§1.14):

| Prototype | Port |
|---|---|
| `core.js:15-61` utils | as is, minus `h()` and `esc()`; `reduced()` without `window.shell`; add `md()` (§1.14) |
| `core.js:63-85` icons | a constant table of SVG path strings built with `createElementNS`; no `shell.icon()`, no markup strings |
| `core.js:87-141` symbol, mood | `topBtn()` = `#topbar-help-btn`; strings from `data-txt`; no idle blink, signal gated by `data-signal` (§1.10) |
| `core.js:143-216` flight | as is; flyer class `saldi-help-flyer`; the flyer's symbol is a `cloneNode()` (§1.10) |
| `core.js:218-249` page knowledge | replace with `SaldiHelpContent` + `pageCtx()` + the texts from §3.4 (§1.6, §2.4) |
| `core.js:251-290` field tracking | rebind per frame document, adoption rule (§1.5, §1.6) |
| `core.js:292-418` open/close/minimise | as is, with `computeReturn()` reading `fdoc().activeElement` when `document.activeElement === frame()`; `open()` starts with `topbarCloseAll()` |
| `core.js:420-604` ring and tour | ring geometry moves into the frame, event-driven, no rAF loop (§1.7); tour = `SaldiTour` (§5) |
| `core.js:606-686` search | as is, page-scoped, no guides, `textContent` rows (§1.11) |
| `core.js:688-984` scripted AI, thread | drop; replaced by §6 |
| `core.js:986-1065` support | §1.9 |
| `core.js:1067-1324` render helpers | as needed by `surface-b.js`, rebuilt with DOM calls |
| `core.js:1326-1432` hint, error card | drop (phase 2) |
| `core.js:1463-1466` dock | replaced by §1.4 |
| `core.js:1468-1504` keyboard | §1.12, both documents, `defaultPrevented` checked |
| `surface-b.js` build/fill/view | minus the conversation section; title/about/context refresh on every `render()` |
| `surface-b.js:168-194` overlay geometry | `rectInTop()` |
| `surface-b.js:316-339` decide/layout | §1.4 (960, no middle zone) |
| `surface-b.js:358-400` strip avoid | §1.8, event-driven |
| `surface-b.js:466-529` listeners, Esc | §1.5, §1.12 (no `stopImmediatePropagation` ordering tricks) |
| `surface-b.js:531-535` review listener | drop |

Order of work: (1) `includes/saldi_help_panel.php` + test; (2) `topbar.php` slot and host; (3) `main.php` edits; (4) `saldi-help.css`; (5) `saldi-help.js` with a stub `SaldiHelpContent` and a stub texts map until CONTENT lands (a page without content shows the generic panel, so SHELL can test generic pages first); (6) `helpSupport.php`.

Done when: `php -l` passes on every PHP file; `node tools/guardrails/check.mjs` passes for SHELL's files (`file-history`, `file-version`, `php-syntax`, `js-syntax`, `whitespace`, `tekster` once TOURS's rows exist); the PHPUnit test passes; `grep -nE 'innerHTML|outerHTML|insertAdjacentHTML|document\.write' javascript/saldi-help.js` prints nothing (§1.14); `grep -nE 'setInterval|requestAnimationFrame' javascript/saldi-help.js` shows only the coalesced frame callback of §1.7/§1.8 and the flight animation; the generic panel works on the dashboard with no console errors; a tenant without the row `help/panel = on` gets the old menu.

### 12.2 Builder CONTENT

Files: `javascript/saldi-help-content.js`, `importfiler/tekster_keyed.tsv` (the `help.*` rows only), `includes/saldi_help_texts.php`, `index/helpTexts.php`, `tests/includes/ui/saldiHelpContent.test.mjs`, `tests/includes/saldiHelpTexts.test.php`.

Scope: §3.2, §3.4, §4, §8.2.

Header `// ---- javascript/saldi-help-content.js --- ver 5.1.0 --- 2026.11.07 ---` + `// 20261107 CL/ASR Created: help content for Kladdeliste and Kassekladde (Hjælp variant B).` One IIFE that assigns `window.SaldiHelpContent`. Data and pure functions only; no UI text (§3.2).

Before writing a selector, open the page source at the cited line and confirm it. Where the text in §4 names an on-screen label, use the bold label exactly as the page shows it (Danish) and the English and Norwegian columns of the same `tekster.csv` id (§4.1). Write the TSV rows in all three languages.

Done when: `node --test tests/includes/ui/*.test.mjs` passes on the host (this test does not run in CI, §4.4); `php vendor/bin/phpunit tests/includes/saldiHelpTexts.test.php` passes; `php -l` passes on both PHP files; `node tools/guardrails/check.mjs --only js-syntax,php-syntax,whitespace,tekster,file-history,file-version,test-discovery` passes; `curl` of `index/helpTexts.php?group=kk` on a test folder on ssl12 (logged in) returns the Danish texts, and with the user's language set to English and Norwegian returns those columns.

### 12.3 Builder TOURS

Files: `includes/tutorial.php`, `finans/kassekladde.php` (only `:7`, after `:215`, and `:5701-5735`), `finans/kladdeliste.php` (only `:7`, after `:47`, and `:838-847`), `importfiler/tekster.csv`.

Scope: §5, §3.3, §8.1 (all rows 8700–8777 without 8711, also the ones only SHELL uses).

Done when: `php -l` passes; `node tools/guardrails/check.mjs` passes for these files (`tekster`, `file-history`, `file-version`); on a local or ssl12 page the tour does not start by itself, starts from `SaldiTour.start()`, posts `saldi:tour` `start`/`step`/`end`, and the Hjælp cell outside the shell starts it in place, also on `debitor/ordre.php` (cell printed after the tour).

### 12.4 Integration

After all three: one person merges the work on the branch, runs `node tools/guardrails/check.mjs`, `php vendor/bin/phpunit tests/includes/saldiHelpPanel.test.php tests/includes/saldiHelpTexts.test.php` and `node --test tests/includes/ui/*.test.mjs`, deploys to a test folder on ssl12, inserts the pilot row (§13.1) and runs §13.

---

## 13. Test plan on ssl12 (a test folder)

### 13.1 Setup

- Update the test folder's checkout to the branch head the usual way (push, then pull in the folder). Check which branch the checkout is on before anything else; never reset it.
- Login: an isolated test regnskab on the test server; the Playwright script reads the password from the environment (`SALDI_E2E_PASSWORD`), never from a file in a repo. Creating an **unposted** test kladde there is allowed (note text "Hjælp-test"). Never click Bogfør on the overview, never Simuleret Bogføring, never Kopier til ny into a posted state.
- Mail: do not configure `SALDI_HELP_SUPPORT_TO` or `$helpSupportTo` on ssl12 unless it is the tester's own address (such as `ann@example.com`). Without it the card shows the mail link (expected). If it is set to the tester's own address, one Send is allowed.
- Pilot row: `SALDI_HELP_PANEL` is not set on ssl12, so the panel is in pilot mode (§9). Look up the tenant database in the master database (`SELECT db FROM regnskab WHERE regnskab = '<regnskab>'`) and insert the row `help/panel = 'on'` there (the first INSERT in §9). Without it the test regnskab shows the old Assist menu.
- Guide loader reference: `guide_now.js`, outside the repository (the production copy of 7 Nov, §References).
- Playwright: Python async API with a local Playwright runtime. Keep the script outside the repo; it is not a CI test. Collect console errors in every scenario; any error fails it. Keep the screenshots outside the repo too.
- Viewports: 1920×1080, 1440×900 (sidebar open → overlay; collapsed → docked), 1366×768, 390×844 (sheet).
- Frame access: `page.frame_locator('#iframe_a')`; state through `page.evaluate('SaldiHelp.state()')`.

### 13.2 Scenarios (each with screenshots)

| # | Scenario | Expected |
|---|---|---|
| S1 | Log in, land on Oversigt, wait 5 s | No tour, no panel, nothing opens. Topbar shows the disc and "Hjælp" at 1920, 1440 and 1366 (viewport ≥ 1240), only the disc at 1200 × 800. Shot like `x1`. |
| S2 | Click Hjælp on Oversigt | Generic panel: title "Hjælp Oversigt", search, Tastaturgenveje, "Spørg Saldi AI om Oversigt", Kontakt support. No Rundvisning. Mascot flies (not at reduced motion). `aria-expanded="true"`. |
| S3 | Click into the page with the panel open | Panel stays open; `aria-expanded` stays `true` (blur reset excluded). |
| S4 | With the panel open from S2, go to Finans » Kassekladde (kladdeliste) | The unpinned panel closes on the route change. Open it again: title "Hjælp Kladdeliste", "Om siden" with the own-kladder line, "Sådan gør du" with ≤ 3 tasks in the §4.2 order, "Rundvisning af siden (3 trin)" (2 trin when the list is empty, because the row step has no target). |
| S5 | Vis mig on "Opret en ny kassekladde" | Panel shrinks to the strip "Viser: Ny"; blue ring around Ny inside the frame, no dark layer; callout "Her er knappen. Der sker ikke noget, før du selv klikker." with Forstået; page clickable. Forstået → panel restored. Shot. |
| S6 | Rundvisning on kladdeliste | Tour card "Rundvisning · 1 af 3", ring, no scrim; Forrige hidden on step 1; ← → move steps; Esc ends; a click on a row ends the tour and opens the kladde; no request to `tutorialAPI.php` (Network). |
| S7 | Blue Hjælp cell on kladdeliste | Panel opens; no document request, no reload. Second click closes it. |
| S8 | Ny → type one line (Bilag/Dato prefilled, Bilagstekst "Hjælp-test", Debet a bank account, Kredit empty, Beløb 100,00) → Enter; wait 60 s without input | Kladde saved with a difference. The topbar symbol stays "Klar" (no amber dot, no blink, `aria-label` 8700): signal and idle blink are off in phase 1 (D11). Nothing opens. |
| S9 | Click Hjælp | Title "Hjælp Kassekladde N" (also in English: "Cash journal N" with a space); Om siden mentions the difference; tasks start with "Find differencen"; `SaldiHelp.state().texts === 'ready'`. Shot (cf. b5). |
| S10 | Click into Kredit of the line, press F1 | "Feltet du stod i: Kredit" above Om siden; focus in search. Esc → panel closes (overlay) or stays (docked); in overlay, focus and caret are back in Kredit. |
| S10b | Click into Kredit (its account list opens), press Esc in the field (the list closes), click Hjælp, then Esc in the empty search field; repeat with help → Rundvisning → Esc | The panel closes (or is restored after the tour) and focus is back in Kredit, but the account list stays closed. With the list open, Vis mig on "Udfyld modkontoen" closes it; the callout and its Forstået are on top. |
| S11 | Open help right after load without touching the page (topbar button, or the blue Hjælp cell) | No field box (auto-focus does not count, and a first press on the cell adopts nothing). |
| S11b | After the Enter-save in S8 the page focuses the next line by itself. Type one character there, then press F1; repeat with typing and then clicking the topbar Hjælp | Both times "Feltet du stod i" shows that field (adoption on the first keydown, §1.6). |
| S11c | Panel docked (1920) and open; press Enter in a line to save | During the reload the panel keeps its height and field box, Vis mig buttons are disabled and `aria-busy="true"`; after the load they are enabled again and the box is attached to the same field name. No console error. |
| S12 | Vis mig on "Find differencen" | Ring around the balance status; callout "Her står differencen. Den opdateres, når du gemmer." |
| S13 | Type in a field (unsaved), open help → Rundvisning → Esc | Text still there, no `unlock_order.php` beacon, no reload, caret back. Tour shows 5 steps on the open kladde. |
| S14 | Type `diff` in search | "Find differencen" and "Hvorfor står der en difference?" under "Til denne side", last row "Spørg Saldi AI: »diff«"; ↑ ↓ move rows; Enter on a task expands its steps. |
| S15 | Type `ring til support` | Contact card first, no hand-off. |
| S16 | Search "hvordan bogfører jeg" + Enter | Guide window opens with the question in its field, unsent; panel (unpinned) closes; status notice announced. Shot. Do not press Enter in the Guide. |
| S17 | S16 with `page.route('**/wuweiworkai.com/**', lambda r: r.abort())` from login | Panel shows "Saldi AI kan ikke svare lige nu…" and "Skriv til support med spørgsmålet"; that button opens the support view with the question prefilled (mail link without a configured recipient). Shot. |
| S18 | Kontakt support | Phone, opening status with blue (open) or grey (closed) dot, never green; mail; "Skriv til os" per §1.9. Shot (cf. b6). |
| S19 | Pin, go to Debitor » Ordrer, come back | Panel stays open across routes; reload the shell (F5): pinned panel reopens without focus grab; new tab: closed but still pinned when opened. |
| S20 | Docking | 1920: docked, iframe narrower by 400 px, kassekladde gets its own horizontal scroll. 1440 open sidebar: overlay with shadow, no scrim. Collapse the sidebar: docks after the transition. |
| S21 | 390×844 | Bottom sheet, no pin, handle; drag down closes; Vis mig strip at top. Shot (cf. b7). |
| S22 | Delete the test line (`-` in Bilag + Enter) | Kladde empty again. Delete the empty kladde from the list (K7) at the end. |
| S24 | Languages: set the user's language to English, reload, open help on the kassekladde; then Norwegian | All help content in English, then Norwegian (no Danish leftovers, no `[help.…]` keys); bold names match the page's buttons (e.g. **Save**/**Lagre**). |
| S25 | Kladdeliste: click **Vis alle**, open help; open the list again from the sidebar, open help | First: no "Du ser kun …" line, K4 not offered; second (`filter` gone, back to own): the line is there. |
| S26 | Posted kladde → **Kopier til ny** (only the dialog; click its **Fortryd** afterwards, never its **Kopier til ny**) | In the dialog: Om siden "Vælg første bilagsnummer og dato for den nye kladde."; task "Udfyld kopien" with KK14's steps 2–4; Vis mig rings **Skriv 1. bilagsnr**. |
| S23 | Keyboard only | Tab to Hjælp, Enter opens with focus in search, Esc closes and focus returns to the button; `?` outside fields opens; F1 inside a field opens with field help. |

### 13.3 Regression

| Page | Check |
|---|---|
| Oversigt (`index/dashboard.php`) | 48 px bar (`html.topbar-tall`), panel starts under it; Skjul/Rediger oversigt still work; no console errors. |
| Debitor » Ordrer (`debitor/ordreliste.php`) and an order (`debitor/ordre.php?id=…`, read only) | No tour auto-start; Hjælp cell opens the panel (on `ordre.php` the cell is printed after the tour script, §5.1); Rundvisning starts the order tour as a ring; Esc ends it. |
| Debitor » Kunder (`debitor/debitor.php` list) | Hjælp cell (dead before) opens the panel; no Rundvisning row. |
| Indstillinger front page (`systemdata/settings.php`) | `?` opens help; "Tastaturgenveje" → "Sidens egne genveje" opens the page's list; `/` still focuses the page search. |
| An Indstillinger section with a guide | "Vejledning" row opens the guides overlay. |
| Topbar | Bell, language, user chip and search pops open over the panel; Esc closes the pop first, then (second Esc) the panel. With the bell (or user, or language) menu open, a click on Hjælp closes that menu before the panel opens. Ctrl+K and Alt+L unchanged. |
| Print (topbar user menu) | Panel not printed. |
| Guide docked and panel docked at 1920 | Both fit or the panel switches to overlay; nothing overlaps the topbar. |
| Unreadable frame | `page.evaluate("document.getElementById('iframe_a').src = 'https://example.com/'")`, then open help: generic panel with the title "Hjælp" and the row "Spørg Saldi AI"; no field box, no Vis mig, no Rundvisning; `SaldiHelp.state().route === null`; `?` in the shell opens help; no console error. The same with `about:blank`. Navigate back to the kladdeliste: the panel binds again. |
| Another tenant in the same test folder without the pilot row | The old Assist button and menu; no `saldi-help.js`, `saldi-help.css` or `helpTexts.php` request. |
| Long kladde (200+ saved lines, if one exists in the test regnskab; otherwise skip) | Panel docked and open while typing in a line: no visible lag; the Performance panel shows no `detect()` on `focusin` (§1.6). |

### 13.4 Pilot and kill switch

1. Delete the pilot row (§9 undo), reload the shell: the old Assist button and menu are back; `?` on settings shows the page's shortcuts; no `saldi-help.js` request; the Hjælp cell on kladdeliste and on `debitor/ordre.php` starts the tour in place (no panel, no reload); "Rundvisning af siden" in the Assist menu only where the page has a visible tour, also on a page with a tour but no Hjælp cell (checked on the kladdeliste with the cell's id renamed, as on a booking page, because `rental/header.php` may create booking tables; the menu row then starts the tour through `SaldiTour.start()`).
2. Insert the row `help/panel = 'on'` again, reload: the panel is back.
3. (If the server env can be set for the test folder) `SALDI_HELP_PANEL=off` gives the old menu even with the row; `SALDI_HELP_PANEL=on` gives the panel without a row, and the old menu with a row `off`.

### 13.5 Clean-up

Delete the test kladde (or leave it empty and unposted). Keep the pilot row `help/panel = 'on'` in the test regnskab (the owner tests there); delete any `off` row. Log out (`index/logud.php`) so the next run does not hit force-logout.

---

## 14. Changelog lines

| File | Line |
|---|---|
| `index/main.php` | `// 20261107 CL/ASR Hjælp variant B: the topbar's Hjælp symbol opens one help panel docked beside the page (javascript/saldi-help.js), for now only for tenants with settings help/panel=on (SALDI_HELP_PANEL unset or pilot); everyone else keeps the Assist menu.` |
| `index/mainIncludes/topbar.php` | `// 20261107 CL/ASR Hjælp variant B: the Saldi AI symbol in a 28 px disc with "Hjælp" replaces the Assist button and its menu where the help panel is on (topbar_help_symbol(), topbar_help_host()); elsewhere the Assist button stays.` |
| `includes/tutorial.php` | `// 20261107 CL/ASR "Ro på": no auto-start and no seen rows; a ring instead of the dark overlay; Esc, ×, Færdig or a press outside the card ends the tour; window.SaldiTour for the help panel; one delegated click listener for the Hjælp cell, which opens the help panel in the shell and starts the tour in place elsewhere (never a reload).` |
| `finans/kassekladde.php` | `// 20261107 CL/ASR Tour steps that exist on the page (bilag, debet, fakturanr., balance, Bogfør; on a posted journal Tilbagefør and Kopier til ny); #clip and #questionmark are gone. The tour no longer starts by itself (includes/tutorial.php).` |
| `finans/kladdeliste.php` | `// 20261107 CL/ASR Tour of three steps (Ny, a journal row, Vis alle/Vis egne); it no longer starts by itself (includes/tutorial.php).` |
| `includes/saldi_help_panel.php`, `index/helpSupport.php`, `includes/saldi_help_texts.php`, `index/helpTexts.php`, `tests/includes/saldiHelpPanel.test.php`, `tests/includes/saldiHelpTexts.test.php` | `// 20261107 CL/ASR Created: …` with a full header (`--- <path> --- ver 5.1.0 --- 2026-11-07 ---`, licence, `Copyright (c) 2026 Danosoft ApS`) |
| `javascript/saldi-help.js`, `javascript/saldi-help-content.js`, `css/saldi-help.css`, `tests/includes/ui/saldiHelpContent.test.mjs` | header comment with `20261107 CL/ASR Created: …` |
| `importfiler/tekster.csv`, `importfiler/tekster_keyed.tsv` | no header (data files) |

Use the date of the actual change if it is later than 7 Nov; the guardrail rejects dates in the future and wants the header date moved along.

---

## 15. Open questions for the owner, and risks

Questions (the spec's default is in brackets):
1. Docking at 960 px means 1440 px screens with the sidebar open get the overlay, unlike shots b2–b6. Keep 960? [yes, D1]
2. Should an unpinned panel close on every route change? [yes, D6]
3. For the pilot tenant, should the blue Hjælp cell open the panel on every page during the ssl12 test, or only on the two pilot pages? [every page, D3; the topbar slot is the panel everywhere for that tenant anyway]
4. Is it acceptable that "Spørg Saldi AI" opens the Guide's own window (a second surface) until the engine moves to Danosoft's server? [yes, with the honest notice, §6]
5. "Har noget til dig": switch it on for a refused kassekladde save only (`afvist`), given the brief reserves it for an unread answer and the first-login hint? [off in phase 1, D11; turning it on is `data-signal="1"` in `topbar_help_host()`]
6. An idle blink of the symbol (once after a pause without input): wanted at all? [no, D11; the brief has the blink only on a thumbs-up]
7. "Skriv til os": which address in production, `support@saldi.dk` (Forslag decision 6) or `support@danosoft.dk` (error card)? Set `SALDI_HELP_SUPPORT_TO` accordingly. [support@saldi.dk]
8. The SST-915 gear hint on the kassekladde still pops up once per browser. Retire it, or persist its dismissal on the server? [unchanged in phase 1]
9. Kladdeliste's tour has 3 steps; is "Rundvisning af siden (3 trin)" wanted there, or only the tasks? [keep]
10. Should CI run the node UI tests (`node --test tests/includes/ui/*.test.mjs` as a step in `.github/workflows/guardrails.yml`, next to `:54-55`)? It would also start running the existing `saldiAssistNavigate.test.mjs`. A separate small PR, not this branch. [ask the reviewer]
11. After the ssl12 test: flip the default to `on` for everyone (§2.5), or widen the pilot tenant by tenant with the settings row first? [tenant by tenant for a week, then flip]

Risks:
- Kassekladde selectors are name-based; a renamed input or footer button silently removes a Vis mig (the shell then hides the button, it does not break). The content test covers the schema and the text keys, not the live page and it does not run in CI (§4.4); a Playwright run on ssl12 after each kassekladde change is the guard.
- The Guide sends no events; the symbol cannot show tænker/svarer, and focus lands in the Guide's window after a hand-off.
- Docking resizes the iframe; classic pages with fixed widths (kassekladde ~1000–1100 px) get horizontal scroll at the 960 px minimum.
- Making `tutorial.php` non-autostarting is global (17 pages, every tenant, also without the panel); new users no longer see tours unless they open help or click the Hjælp cell. The first-login hint (phase 2) is meant to carry that.
- `topbarCloseAll()` runs on every window blur; the `aria-expanded` exclusion at `main.php:434` must survive future topbar merges.
- Text ids: another branch could take 8700–8777 before this PR merges; re-run the check in §8.1 before opening the PR. Keyed rows cannot collide by number, but another branch could add a `help.*` key; the guardrail reports a duplicate key.
- Help texts are seeded into each tenant's `tekster` table on first use. A later rewording needs a `deleteStaleTekst()` line per key (§8.2); the guardrail enforces it. The first fetch per tenant and language runs about 300 small queries once (§4.5).
- The help texts are fetched on the first open; when the endpoint fails, the pilot pages fall back to the generic panel for that open (§2.4).

---

## Review notes (revision of 7 Nov 2026)

What the review found and what this revision did. Every point was checked in the code first; line numbers are `1b8cb4d4` unless marked.

| # | Review point | Done |
|---|---|---|
| 1 | Login details and local paths do not belong in a doc that goes into the PR | §13.1 names no login; the script reads the password from `SALDI_E2E_PASSWORD`. `guide_now.js` is kept outside the repository (sha256 in References; same file, so every `guide_now.js:N` still holds); widget behaviour cited from `support-sally@148a2af1`. |
| 2 | D10: ~150 UI strings in JS, no Norwegian, not in the tekster files | All help content moved to `importfiler/tekster_keyed.tsv` (`help.*` keys, da/en/no), served per language by `index/helpTexts.php` through `findtekst_utf8()` (§3.4, §4.5, §8.2, D10). The JS keeps selectors, detectors, key names and search vocabulary. CONTENT owns the TSV and the endpoint, so the split stays disjoint. The endpoint never accepts key names from the client (no self-heal of arbitrary keys). |
| 3 | Panel on for everyone after merge | Default is `pilot` (unset or `pilot` → only tenants with `help/panel = 'on'`), `on` → everyone (§2.5, §9, D12); PHPUnit matrix updated (§2.7); ssl12 setup inserts the `on` row (§13.1); flip described in §2.5. |
| 4 | Hjælp cell bound at parse time is dead on the order pages | One delegated `click` listener on `document` (§5.2), verified against `debitor/ordre.php:625-626,3401`, `topLine.php:54`, `kreditor/ordre.php:411-412,1275,1838`, `topLine_S.php:80`, and today's `load` binding `tutorial.php:121,138-142,441`. |
| 5 | Wrong `kopier` and `afbrudt` states | `kopier` (`!form && input[name="vend_fortegn"]`) added before `opslag`, with its Om siden text and task KK14k (KK14 steps 2–4, Vis mig on `bilagsnr`); the `revert` check moved above the `ompost` check (`kassekladde.php:2636-2652` adds undo links for `!`). Both in the §4.4 fixtures and scenario S26. |
| 6 | No rule for how text reaches the DOM | §1.14 (hard rule), owner rules, §3 intro, §1.9–§1.13 call sites, grep check in §12.1. One deviation from the proposed fix: `md()` builds DOM nodes (text nodes and `<strong>` elements) instead of escaping and returning an HTML string. It meets the same goal more strictly, and it lets the check be "no `innerHTML` at all" instead of "none outside `md()`". The tour's "Spørg Saldi AI om dette" strips tags with `DOMParser` (inert), not a detached `innerHTML`. |
| 7 | Three polling loops | (a) bind on `saldi:breadcrumb` (`pageChrome.js:7,35`, posted from `<head>`, `online.php:502-504`), fallback iframe `load`; (b) and (c) event-driven `place()`/`avoid()` with one coalesced frame callback (frame `scroll` capture, `resize`, `ResizeObserver`, `saldi:tour` incl. a new `step` message from TOURS, §3.3). Also removed a fourth poll the review did not list: §6 step 1 polled for the Guide every 250 ms; it now waits once for `DOMContentLoaded` (the loader is `defer`, so it has run by then). |
| 8 | `route()` throws on another origin | `route()`/`pageCtx()` return `null` on any throw or outside the Saldi root; the unreadable-frame state is §1.5.1 (generic panel, new text 8777, no bind); regression row in §13.3. |
| 9 | `detect()` scans the whole document per row | One pass over the hidden `id[N]` inputs, then `form.elements.namedItem()`; result cached per document, dropped on bind/load/pagehide/`change` (§1.6, §4.3). Found while checking: `kladde_id` is printed twice in the form (`:2993,4177`), so `namedItem` returns a list there; `val()` takes the first element. |
| 10 | Field box missing after Enter-save; `#tutorial-help` check on the wrong element | Adoption on the first trusted input (§1.6), F1 uses `e.target`; `!el.closest('#tutorial-help')`. Refined: a first `pointerdown` adopts only when it lands on the focused field itself (a click into an already focused field fires no `focusin`); a first press elsewhere, such as on the blue Hjælp cell, adopts nothing, so S11 still holds. Scenario S11b. |
| 11 | Dead rows between `pagehide` and load | Busy state §1.5.2 (`aria-busy`, disabled Vis mig and tour row, field key kept, re-looked-up by name on the same route). Scenario S11c. |
| 12 | Signal on every difference, idle blink | Both off in phase 1 (D11, §1.10); content signal limited to `afvist`; `data-signal="0"` on the host; owner questions 5–6 with off as default. S8 now expects no signal. |
| 13 | 8738/8737 not verified | Checked in `support-sally@148a2af1`: v2 puts the question in the composer unsent and focuses it (`EmbedClient.tsx:1252-1266`, test `EmbedClient.companion.test.tsx:115`); Enter sends on a keyboard, the button on touch (`Composer.tsx:296`). 8738 reworded to "Det er ikke sendt endnu. Send det dér, når du er klar." (no Enter promise). 8737 only with `data-records="0"`; new 8771 for records on; new 8772 says the question goes to Saldi AI's server (§6, §8.1). |
| 14 | `kunEgne` by button text in three languages | **Partly disagree with the proposed selector.** `form[name^="filter_form"]` exists on no page: the only reference is `kladdeliste.php:926`, and `includes/grid.php` prints unnamed forms (`:1265,2031`) with the filter checkboxes only in the filter-setup view (`:2094`). The toggle therefore always falls back to `kladdeliste.php?filter=…` (`:934`), and the proposed detector would never match. Instead: the URL `filter` parameter first (the server's own first source, `:520`), then the toggle text compared with the shell's own `findtekst 636` (the same tenant row and session cache the page printed it from, so a customised text still matches), else false (§4.2). Found while checking: K4's "Saldi husker dit valg" was false (nothing is saved; the next visit is back to own kladder); step 2 corrected, scenario S25. |
| 15 | `instanceof` across realms | §1.5, §3.1, §3.2 rules: `nodeType`/`tagName`, the frame window's `getComputedStyle`. |
| 16 | Missing ids, 159, `'first' => true` | 6455 in `$txt`; 8773 (sheet handle), 8774–8776 (shortcut headings), 8777 (row without label). The `other` shortcut group is folded into `codes` (its only entry is a code typed in a field), so two page headings plus the global one. 159 Fortryd added to the mapping, which now also lists the Norwegian column for every name (§4.1). Kladdeliste steps written out as PHP with `'first' => true` (§5.4). |
| 17 | `#back-btn` name breaks in English | `name()` builds from `data.kladdeId` with `help.kk.name` = "Kassekladde %s" / "Cash journal %s" / "Kontantjournal %s" (§4.3). |
| 18 | Three fact errors | `data-bogfort` holds `k.bogfort` (`V` when posted) and `.kkVisFab` is not printed in the shell (`#kk-vis-cell`, `topLineKassekladde.php:105`): corrected (§4.2, §1.4). **Disagree on the kladdeliste anchor:** the last history entry is the SD-730 entry at `:46-47` (`:46` starts it, `:47` is its continuation, `:48` is empty), so "after `:47`" was right; the anchor text now says so (§5.4). |
| 19 | `helpSupport.php` CSRF and headers | `hash_equals` as `fejlrapport.php:80-82`; `saldi_error_header_value()` on every header value and the subject, `mb_encode_mimeheader()` for the non-ASCII subject, headers and line wrapping as `includes/errorReport.php:627-630,659-661,702-723` (only included, not edited) (§2.6). |
| 20 | Open menu stays over the panel | `SaldiHelp.open()` starts with `topbarCloseAll()` (§3.1); regression row. |
| 21 | Node UI tests never run in CI | Stated in §4.4 and §12.2; the ssl12 Playwright run stays the selector guard; adding the CI step is owner question 10 (a separate PR; it would also start running `saldiAssistNavigate.test.mjs`). |
| 22 | Generic title changes after load | The generic head re-renders when `saldi:breadcrumb` arrives while the panel is open (§1.5, §1.13). |

---

## Integration notes (7 Nov 2026, ssl12)

What the integration and the §13 run on ssl12 found where the code differs from the text above. The run (Playwright, 1920×1080, 1440×900, 1366/1200 for the label, 390×844 phone; the test regnskab plus the fictive demo company Fjordlys Design, read only) is kept outside the repository.

| # | Finding | Resolution |
|---|---|---|
| I1 | §4.2 and review note 14 say the Vis alle / Vis egne choice is not saved. It is: `kladdeliste.php:509-526` reads the user's `datatables.filter_setup` and `:548-554` writes it back on every load. | K4 step 2 says that Saldi remembers the choice. S25's second expectation and S4's "own-kladder line" depend on the user's saved choice; the run checks that the line follows `kunEgne` in both directions. |
| I2 | A tour started from the panel has nothing to give focus back to: while focus is in the panel, the frame's `activeElement` is its body, so ×, Færdig and Esc left focus nowhere (S13 failed). | On the tour's `end` message the shell puts the caret back in the page field the user left when help opened, or focus back into help (as Vis mig does, §1.7). Not after a pointer press in the page (the user moved on) and not after `pagehide`. A hidden card button that is still the frame's `activeElement` counts as lost focus. |
| I3 | S21 expects the Vis mig strip at the top of the sheet. When the target and its callout are at the top (Ny on the kladdeliste), `avoid()` puts the strip at the bottom (`b`). | Intended (§1.8: the strip never covers a ring or a card). The run checks that the strip does not overlap the ring or the callout. |
| I4 | S11 opened the same kladde twice in a row and saw a field box. A reload of the same route keeps the last field by design (§1.5.2). | The scenario comes from the kladdeliste (another route), as a user opening a kladde does. |
| I5 | In a fresh browser the kassekladde's one-time gear hint (`#kkVisHint`, SST-915) lies over the blue Hjælp cell until it is dismissed. | Not changed (owner question 8). |
| I6 | `debitor/ordre.php` prints its tour only while the order's status is below 3, so an invoiced order has no Rundvisning row. | The regression row uses an open offer. |
| I7 | No settings section has an installed guide on this branch (`guides/settings/` is empty), so the "Vejledning" row was not seen. | – |
| I8 | Console noise that is not from this branch: `textinputbg.gif` 404 on classic pages; `main.php`'s inline iframe `onLoad` throws on a cross-origin frame (`this.contentWindow.document.title`, `:409-418`); `saldi_assist_token.php` 503 on ssl12 (no token configured, the Guide goes on without one). | – |

The three builders' own departures from the text (all kept): the frame's `pagehide` is bound on its window; after a same-route reload the panel is recomputed at `DOMContentLoaded`; a contact word plus Enter opens the support view, which hides the footer's "Kontakt support" row; `helpSupport.php` and `helpTexts.php` also answer 401 `session`; the tour card's `aria-label` is "Rundvisning, i af n" (8760 + 8761); Næste is a filled button as in shot `x6`; the scrollbar test for an outside press also checks overflow; a step scrolls only when its target is not fully visible; `kladdeliste.php`'s copyright line reads "Danosoft ApS" (guardrail `file-history`); several help texts were reworded after checking the code (KK4, KK7, KK13, KK14, Forfald, Betalings-ID, the moms fields), and the kladdeliste's Bemærkning label uses tekst 391.

---

## Review round 1 (7 Nov 2026)

What the first review of the built branch found, and what changed. Each point was checked in the code (and live on ssl12 where it concerns the browser) before the fix.

| # | Finding | Done |
|---|---|---|
| R1 | The booking tours (`rental/{index,edit,daysoff,items}.php`) became unreachable for tenants without the panel: `topbarAssistPage()` asked `SaldiTour.count()` only on a page with `#tutorial-help`, and those pages have neither the cell nor `page_help()`. | `topbarAssistPage()` asks the count on every readable page and sets `help.tour` to `'#tutorial-help'` or, without a cell, `'saldi:tour'`; `topbarAssistRun('tour')` starts `SaldiTour.start({ focus: true })` for the marker (§2.1, §5.3, §13.4). The panel's registry branch ignores both values (its counted Rundvisning row covers them, §1.13). |
| R2 | Closing help (and the tour's focus return, I2) put focus back in a kassekladde lookup field with a plain `focus()`, which opens the account list by itself (`accountAutocomplete.js:310-321`). | The shell's `refocus()` and `tutorial.php`'s focus restore use the page's `focusAccountQuietly()` for an `INPUT` when the frame has it (§1.12, §5.2). Checked live first: a plain `focus()` opened the list, `focusAccountQuietly()` (with or without the window focus before it) did not. Scenario S10b. |
| R3 | The Vis mig and tour layers (z-index 9000/9010) lay under the account list (99999), so an open list hid the callout and Forstået. | Ring/callout and tour ring/card at 100000/100010 (§1.7, §5.2). Vis mig and the panel's Rundvisning row also close an open list with `closeAccountAutocomplete()`. |
| R4 | The generic panel on Oversigt said "Hjælp Overblik" (the dashboard's hard-coded `<title>`), a name that is nowhere on screen and never translated. | On `index/dashboard.php` the label is the sidebar entry's text (`#dashboard .link_name`, findtekst 2224) (§1.13). |
| R5 | The token shim had light values only, so a hovered or selected row in dark mode was near white with near-white text. | Dark values under `[data-theme="dark"]` and `[data-theme="system"]` with `prefers-color-scheme: dark`; the three hard-coded focus outlines use `--focus` (§2.3). |
| R6 | The first help open per session ran about two queries per key (≈380 for `kk`, ≈0.5 s) while holding the session lock. | One prefetch query plus a column check, results put in `findtekst()`'s own session cache; `helpTexts.php` closes the session before answering (§4.5). New PHPUnit case for the row rules (`saldi_help_text_pick()`). |
| N1 | KK4's Vis mig said "Debet eller Kredit" while the ring was on one field. | A Vis mig of kind `field` on a field the page describes uses that field's own label (§2.4 item 5); no new text rows (the KK4 `show` row stays as the fallback). |
| N2 | At 1440 px with the sidebar open the overlay panel covers what two of the kladdeliste's three tasks point at, and on the kassekladde Fakturanr., Beløb, Importér and Udlign. | Not a code change: input for owner question 1 (D1). The shots (`r1ux_kl_task_expanded.png`, `r1ux_kk_1440_overlay.png`) are kept outside the repository. |
| N3 | The support number showed without a country code for English and Norwegian users; "46 90 22 08" is a valid Norwegian mobile number. | `SaldiHelpContent.support.phoneIntl` "+45 46 90 22 08" for `en` and `no` (§1.9, §3.2). |

The help assets are linked as `?v=2` from this round, so a browser that cached the first build loads the fixed one.

---

## Review round 2 (7 Nov 2026)

What the second review found, and what changed. Each point was checked in the code first, and the search and memory points live on ssl12.

| # | Finding | Done |
|---|---|---|
| R2-1 | Every Saldi AI symbol made for a search row or the generic panel's ask row was kept in `M_.avatars`, which only `applyMood()` pruned, and that never runs in phase 1. Each kept its detached results list alive: typing 25 queries left about 36 000 DOM nodes. | Only the long-lived symbols (topbar seat, panel head seat, flyer) are registered. A row symbol gets `data-ai-state` when it is made, and `applyMood()` also updates every `.ai-avatar` inside the panel. |
| R2-2 | Search ranked the wrong hits first: a task scored on all its steps plus 0.5 beat the page's own prepared question; ties kept the declared order; `kontrolkonto` matched `kontrolsum`. | Weighted parts, a bonus when every word hits what names or describes the hit, ties by the page's state order, tighter word matching (§1.11). Checked against every query of the review, in Danish, English and Norwegian, and added as ranking checks to the S14 run. |
| R2-3 | Search never found the field help, the shortcuts and codes, or a question's steps (`moms`, `dato`, `slet linje`, `afstemning`). | Fields, shortcuts and codes, and question steps are search hits now (§1.11). This goes beyond §1.11 as first written. |
| R2-4 | `topbar_help_host()` ran two queries per chrome text (about 150) on the first shell load after each login. | `saldi_help_chrome_prefetch()`: one query into `findtekst()`'s session cache (§2.2), PHPUnit cases for the row rules and the id list. |
| R2-5 | `S.behind` kept every search hit's Vis mig note (and with it the old results tree): hits were pushed as `task`, the search filter dropped `hit`. | Search hits are pushed as `hit`. |
| R2-6 | "Om siden" and the first task's why said the same thing on the kassekladde (difference) and the kladdeliste (open kladder at the top). | KK3's and K3's why reworded in da/en/no (§4.2, §4.3). Tenants on ssl12 that already seeded these two keys keep the old text in their `tekster` table; no real tenant has the keys before the merge. |
| R2-7 | An opened Tastaturgenveje list (or a task near the bottom) opened below the visible part of the panel. | The panel body scrolls the opened list into view without pushing its row out at the top. |
| R2-8 | The kassekladde tour's balance step said "debit" (tekst 3369). | New text 8778 "Differencen mellem debet og kredit på de gemte linjer." (§5.4, §8.1). |
| R2-9 | `helpTexts.php` let `includes/online.php` build a whole page head for a JSON answer. | `$header = 'nix'; $bg = 'nix'; $css = '';` as `helpSupport.php`. |

The help assets are linked as `?v=3` from this round.

---

## 16. The finance module (8 Nov 2026)

The owner tested Kladdeliste and Kassekladde and asked for the whole finance module: help tailored to each page, with the "Sådan gør du" suggestions thought through for both a business owner who does their own books and a trained bookkeeper.

### 16.1 Files and structure

- `javascript/saldi-help-content.js` keeps the shared data (support, search words, global shortcuts) and shares its helpers as `SaldiHelpContent.lib` (`task`, `showKey`, `fieldKeys`, `fieldLabel`, `stepKeys`, `unique`, `attr`, `byName`, `rowOf`). `pages` starts empty.
- `javascript/saldi-help-finans.js` registers every finance page in `SaldiHelpContent.pages`, Kladdeliste and Kassekladde included (moved unchanged apart from the review fixes in §16.4). One file per module: the next modules (Debitor, Kreditor, Lager, …) get their own file loaded the same way.
- `javascript/saldi-help-debitor.js` and `javascript/saldi-help-kreditor.js` hold the Debitor and Kreditor pages, built the same way. `index/main.php` loads them after `saldi-help-finans.js` and before `saldi-help-state.js`, and the three help tests (`saldiHelpContent`, `saldiHelpState`, `saldiHelpMerged`) load them wherever they load `saldi-help-finans.js`, so the generic checks and the caps walk every page of them.
- `javascript/saldi-help-lager.js` and `javascript/saldi-help-settings.js` hold the Lager and Indstillinger pages (§24), loaded after `saldi-help-kreditor.js` in `index/main.php` and in the three help tests. The settings file also holds the dispatcher of the settings sections (§24.3).
- Every page object now also has `states` and `flags`: the lists of state and flag names `detect()` can return. The content test walks every page's states and flags generically (§16.5).
- 1 837 `help.*` rows in `importfiler/tekster_keyed.tsv` (Danish, English, Norwegian), 1 534 of them new.

### 16.2 Pages

| Route | Group | States | Tasks | Fields | Questions |
|---|---|---|---|---|---|
| `finans/kladdeliste.php` | `kl` | tom, liste | 9 | 7 | 3 |
| `finans/kassekladde.php` | `kk` | ny, aaben, afvist, simuleret, bogfoert, afbrudt, kopier, vaelgTilbagefoersel, opslag, udskrift | 17 | 21 | 6 |
| `finans/regnskab.php` | `rg` | tom, oversigt, graf | 9 | 0 | 3 |
| `finans/budget.php` | `bu` | tom, budget | 6 | 3 | 2 |
| `systemdata/kontoplan.php` | `kp` | tom, liste | 8 | 7 | 3 |
| `systemdata/kontokort.php` | `kt` | ny, kort, ukendt | 9 | 9 | 3 |
| `finans/kontospec.php` | `ksp` | konto, bilag, alle | 10 | 11 | 3 |
| `finans/rapport.php` | `rp` | forside, ingenAar and one state per report (regnskab, kontokort, kontokortUbogfort, kontokortMoms, momsangivelse, momsAfstemning, momsRubrik, momsOss, momsTransaktioner, emballage) | 59 | 17 | 7 |
| `finans/kontrolspor.php` | `ks` | liste, tom, csv | 8 | 14 | 3 |
| `finans/moms_periode.php` | `mp` | oversigt, ikkeKlar | 8 | 4 | 2 |
| `finans/listeangivelse.php` | `la` | liste, ingenPoster, ingenPeriode | 5 | 0 | 2 |
| `finans/saft.php` | `saft` | mangler, klar | 6 | 0 | 3 |
| `finans/bankReconcile.php` | `ba` | start, upload, kolonner, afstemt, fejl | 10 | 5 | 3 |
| `finans/regnskabbasis.php` | `rb` | mangler, klar | 6 | 0 | 2 |
| `finans/kontokort_standalone.php`, `finans/kontokort_moms_standalone.php` | `kks` | kontokort, ubogfoert, simuleret, udskrift / moms | 6 / 5 | 1 | 2 |
| `finans/bogfor.php` | `bf` | bekraeft, simuler, fejl, stoppet, videre | 11 | 1 | 3 |
| `finans/autoudlign.php` | `au` | linje, sprunget, faerdig, ingen | 8 | 2 | 2 |
| `finans/journalDocuments.php` | `jd` | tom, liste | 4 | 1 | 2 |
| `finans/importer.php` | `im` | valg, kolonner, fejl, videre | 12 | 6 | 3 |
| `finans/bankimport.php` | `bi` | upload, kolonner, tom, videre | 9 | 9 | 3 |
| `finans/danlonimport.php`, `finans/datalonimport.php` | `lon` | vis, fejl, videre | 4 | 1 | 2 |
| `finans/pbsm602import.php`, `finans/pbswebimport.php` | `pbs` | vis, fejl, videre | 6 | 2 | 2 / 1 |
| `finans/loppeafregning.php` | `lop` | form, faerdig | 4 | 6 | 1 |

`finans/rapport.php` is one route for every report: a GET always shows the selection form and only a POST with OK runs a report (`rapport.php:93,278`), so `detect()` tells the reports apart by the `rapportart=` of each report's Tilbage link. On the selection form the task order follows the chosen Rapporttype.

`finans/moms_periode.php` has no menu link in this branch (the sidebar link went in July, `index/main.php:32`, and `systemdata/regnskabsaar.php` only redirects now); it is registered so that the help is there when the page is reached.

No page object, and why:

| File | Why |
|---|---|
| `finans/hentordrer.php` | **Hent** is printed only when a settings row (`DIV`/3) is missing, which every new regnskab has (`opretfunc.php:718`); its actions call `split()`, gone in PHP 8 (`:400`). |
| `finans/upload.php` | A background endpoint without links. |
| `finans/pulje_review.php`, `finans/pulje_upload.php` | They only link each other; the kassekladde's clip opens `includes/documents.php`. |
| `finans/ordre.php`, `finans/simuler.php`, `finans/genberegn.php` | Not linked from anywhere. |
| `finans/openpostdato.php` | No screen and no link (a maintenance script). |
| `finans/provisionsrapport.php` | Its buttons are commented out (`rapport_includes/forside.php:577,588,600`); it fails on PHP 8 (`:46`). |
| `finans/saftCreator.php` | An action that writes the file and returns to `saft.php`. |
| `finans/docubizzimport.php` | FTP fetch, an alert and a redirect back to the kladde; no screen. |
| `finans/pbsimport.php` | Nothing links to it. |
| `finans/rapport_includes/bankReconcile.php`, `saft_old.php` | Never included: **Afstem bank** redirects to `finans/bankReconcile.php`, SAF-T to `saft.php`. |

### 16.3 How the content was made

1. Five authors each took a group of pages and wrote the page objects from the code, with file:line evidence for every state, step and Vis mig target, and checked them on HTML built from the pages' own output.
2. An independent review against the code, from a bookkeeper's view (accounting terms, VAT, sign conventions, reverse charge, OSS, Listeangivelse, SAF-T) and from a business owner's view (is the top-3 what they want next; is it clear). About 64 findings: corrected steps and orders, missing tasks, and the tone rule below.
3. A live run on ssl12 (§16.5).

**Tone rule.** The help never says that Saldi's own buttons or features are broken or do nothing; it gives the right way to do the job, and the defect goes on the list for the owner (§16.6). A short neutral warning is given only where a silent trap can cost data or money: Enter in Bemærkning on the posting overview posts the journal, the imports do not check for lines imported before, the BS preview already saves agreement changes, running Dan liste twice credits the sellers twice.

### 16.4 Changes to Kladdeliste and Kassekladde

- K8 and KK13 ("correct a posted entry") now end with entering the correct entry, and KK13 says the reversal keeps the original date.
- An open kassekladde without saved lines starts with KK9 "Skriv den første linje".
- Q3b: the confirmation step comes right after the target journal is chosen (`kassekladde.php:5132`).

These rows are new on the branch, so no `deleteStaleTekst()` is needed; the two pilot tenants on ssl12 had their seeded `help.*` rows cleared.

### 16.5 Tests

- `node --test tests/includes/ui/*.test.mjs`: 18/18. The content test loads both files, walks `states` × `flags` × data variants for every page, checks every produced or literal key against the TSV, and finds no unused row.
- Guardrails `file-history, js-syntax, tekster, whitespace, test-discovery`: ok.
- Live on ssl12 (scripts outside the repository, `finans_probe.py` and `finans_checks.py`, read only, in the fictive demo company Fjordlys Design): 33 page states (Kladdeliste, Kassekladde, Regnskab, Budget, Kontoplan, Kontokort, Kontospecifikation, the Rapporter form and ten reports, Kontrolspor, Momsperioder, Listeangivelse, SAF-T, Regnskab Basis, Bankafstemning, the Bogfør and Simulér overviews, Udlign, Dokumenter, Import, Bankimport, Kommissionssalg) open the panel with the expected state, three tasks, no missing key and no console error from the help; every Vis mig target of the shown tasks resolves, except those that need data the page does not have (no summary table, no file yet), where the shell hides Vis mig. Field help with F1 on Rapporttype, Kontrolspor's Bilag and Kontoplan's search; English and Norwegian on Regnskab, Rapporter and Kontoplan; the phone sheet on Rapporter.

### 16.6 Product defects found on the way (not fixed on this branch)

For the owner; each is from the code, with file:line. Security-relevant findings are not listed in this public repository; they went to the owner directly.

1. **The VAT return of new tenants sums the wrong accounts.** The signup template sets the Momsrapport range to 6780–7740 (`importfiler/grupper.txt:6`); the VAT and duty accounts of the standard chart are 7680–7830. The range takes in equity, provisions, debt and sum accounts and leaves out the duties, and the total adds sum accounts twice (`rapport_includes/momsangivelse.php:297-328`). 15 tenants on ssl12 have 6780–7740. The dashboard's VAT tile uses the same range (`index/dashboardIncludes/dashData.php:224`). Fix: the template, a guarded migration for tenants with 6780/7740 and the standard chart, and leave Z/H accounts out of the total.
2. **The momsangivelse prints a placeholder "Momsindberetning" section:** a hard-coded 2018 SKAT calendar and the buttons Hent kalender and Indberet moms without a handler (`rapport_includes/momsangivelse.php:347-459`). A user can believe the VAT was filed. Hide it until the SKAT integration works.
3. **SAF-T** has no postings, customers or suppliers (`saftCreator.php:234-265`), its balances leave out primo (`saft.php:617-632`), and `RegistrationNumber` is `globalId`, not the CVR.
4. **Bogfør:** Enter in Bemærkning posts the journal (`bogfor.php:489,737`); a refusal for a closed VAT month sends the user to the list (`:256-296`).
5. **Listeangivelse:** the period is empty (`:174`), the goods/services split compares with the Rubrik A purchase accounts (`:77-81,151-157`), Bulgaria and Croatia are missing from `cvrnr_omr()` (`includes/std_func.php:972-1066`).
6. **VAT reports:** the Q1–Q4 shortcuts, Tolerance and the Momskode filter in Momsafstemning, OSS and Posteringer pr. momskode send a GET and land on the selection form; OSS drops kassekladde sales from the summary once a debtor group has an EU zone (`moms_oss.php:220-226`); Rubrik A/B come only from invoiced orders (`moms_rubrik.php:203-266`); there is no VAT settlement function and no menu link to the period lock.
7. **Imports:** `kredit(konto)` sets the debit type and per-line values are not reset (`importer.php:601-666`); the SAF-T import posts to a missing `xmlimport.php` (`:295`); the English separator choices break the split (`importer.php:363-379`, `bankimport.php:253-264`); a bank file over 100 KB gives a blank page; the payroll and BS pages lose the kladde id on Tilbage (`bilag=` instead of `bilagsnr=`); Kommissionssalg's total line has no account and holds only the first group's sum (`loppeafregning.php:59,133-142`).
8. **Kontokort and Kontoplan:** Slet ignores postings on the year's first and last day and primo (`systemdata/kontokort.php:125,357-360`); renumbering an account without saldo creates a copy (`:134-139`); searching Map til likely fails (`kontoplan.php:307-318`); the mapping file `stdAccountMap.csv` is not in the repository, yet the one-click import reports success (`systemdata/importAccountMap.php:50,75,122`).
9. Smaller: Kontospecifikation's Fakturanr. always opens a sales order (`kontospec.php:261,466-471`), Udlign's Tab always skips the line (`autoudlign.php:1275-1295`), and wrong Norwegian/English texts in `tekster.csv` (805, 443/515, 600, 1198, 1202).

## 17. Round 2: the bookkeeping flow for new customers (8 Nov 2026)

The owner, after §16: the whole bookkeeping part, from the Kladdeliste through the kassekladder to Kassebilag, is where new customers most often need more guidance to understand what is going on and how to go about it. This round rewrites Kladdeliste, Kassekladde and the Bogfør overview around a new customer's first day, and adds the Kassebilag page. The help stays correct and professional for a bookkeeper (§16).

### 17.1 How it was made

1. Six read-only researchers mapped the Kladdeliste and a new tenant's setup, the journal line (fields, codes, VAT), the journal's actions and posting, the Kassebilag page's modes, the document pool, and what new customers ask. The last one used the June 2026 user manual, the saldi.dk guide and knowledge articles, the wiki, about 400 Jira issues and the support chat's question set.
2. Two independent content plans, one newcomer first and one bookkeeper first, merged by an editor into one plan with 42 recorded decisions.
3. Three authors implemented it (Kladdeliste and Bogfør; Kassekladde; Kassebilag). Each author's work had two independent reviews: correctness against the code, and a reading as a new customer, as a bookkeeper and as the owner. About 115 findings were applied and 6 rejected with reasons.
4. A live run in a fresh tenant created with the same code a signup uses (§17.6).

### 17.2 Pages

| Route | Group | New states and flags | Tasks | Questions | Fields |
|---|---|---|---|---|---|
| `finans/kladdeliste.php` | `kl` | flag `faa` (1-5 kladder, first page only); `tom` and `laast` detected more robustly | 13 (K10-K13 new) | 8 | 7 |
| `finans/kassekladde.php` | `kk` | flags `udenBilag`, `harBilag`, `mange` (over 15 saved lines); helper `kkNewRow()` (the row of the blank new line) | 35 (KK17-KK20, KK22-KK35 new) | 21 | 21 |
| `finans/bogfor.php` | `bf` | none | 11 | 5 | 1 |
| `includes/documents.php` (Kassebilag, new) | `kb` | states `pulje`, `ordrePulje`, `bilag`, `ordreBilag`, `link`, `visning`, `klassisk`, `skifter`; 19 flags for the pool and the viewer | 25 | 10 | 19 (`fieldKey()` maps the pool's `row_<id>_<Field>` ids) |

Top three in the main situations:

| Situation | Top 3 |
|---|---|
| A new tenant's empty Kladdeliste | K11 Bogfør dine første bilag, K10 Tjek regnskabsåret, K13 Lav én kladde pr. kontoudtog eller måned |
| The first kladde just posted | K12 Se, hvad der er bogført, K2, K8 |
| A new kladde | KK9 Skriv den første linje, KK8 Importer bankposteringer, KK27 Bogfør hver betaling kun én gang |
| Balanced, lines without a bilag | KK6 Bogfør kladden, KK7 Vedhæft bilag (KK34 Bilags match with over 15 lines), KK31 Kontrollér kladden, før du bogfører |
| Refused save | KK1, KK28 Ret en dato uden for regnskabsåret, KK26 Find den rigtige konto |
| Posted | KK20 Se posteringerne på kontiene, KK13, KK14 |
| Bogfør overview | BF1, BF2, BF3 (BF7 first when a single bilag does not net to zero) |
| Pool from a saved line | KB1 Sæt bilaget på linjen, KB9, KB21 (KB2 first when the list has a match group) |
| Pool, new line | KB4 Lav en linje til hvert bilag, KB5 Vælg, om bilaget er betalt, KB9 (KB6 when Saldi read the bilag) |
| Empty pool | KB9 Upload bilag til puljen, KB10 Send bilag til puljen med mail, KB16 |
| A line's bilag (viewer) | KB19 Læg et forkert bilag tilbage i puljen, KB18, KB25 |

Kassebilag modes and the frame: the pool and the viewer opened with **Administrér bilag** load in the shell's frame and get the help. The paper icon on a line with a bilag opens a separate tab (`saldiBilag`, `viewOnly=1`, `kassekladde.php:668-694`), which the panel cannot reach. Seeing, moving or removing a bilag on a line is therefore also explained on the kassekladde page (KK29, KK30).

### 17.3 Rules this round adds to §3.2 and §16.3

- **Gem versus Bogfør.** Every text says that Gem saves the lines in the kladde and nothing reaches the accounts until the kladde is posted. The first **Bogfør** only opens the overview, so the texts say "før du bogfører kladden", not "før du klikker **Bogfør**".
- **No account numbers.** Accounts are named by kind and search word (udgiftskontoen, bankkontoen; kontor, gebyr, rente, salg), because a tenant's own chart has other numbers. The standard chart's name appears only where the kind is not obvious.
- **Practice is labelled as advice** (one kladde per bank statement or month, bank first and then the bilag, check before posting), never as Saldi behaviour.
- **Neutral warnings** for silent traps that cost money or data, in addition to §16.3:
  - each click on the undo arrow adds a reversal;
  - **Kopier til ny** and **+** drop the line VAT codes;
  - check **Valuta** on a foreign invoice;
  - **Slet** removes the file, and bilag must as a rule be kept for 5 years (the saldi.dk article's wording);
  - Delete in the pool archives without asking;
  - typed pool values are kept only with **Gem og næste**, **Gem alle** or **Indsæt valgte**;
  - **Overfør data** overwrites a bank line.
- **The regnskabsår.** Only the year selected in **Regnskabsår** is accepted. The settings path is Indstillinger, Finans, Regnskab & moms, **Regnskabsår** (`includes/settings/hub.php:109-115`).
- **Plain-text lines.** A task's title and why line, a question and a field label are shown as plain text (`rowButton()`), so they carry no `**`. Seventeen why lines from §16 had it; the content test now refuses it.

### 17.4 Changes outside the content

- `javascript/saldi-help.js` (`bindFrame()`): the page is detected again on the pool's `poolswitch` event. The pool shows the next document without a page load (`javascript/docPoolSwitch.js:151`), so without this the panel kept the previous line's state.
- `finans/kassekladde.php` tour (`$steps`, spec §5.4): the line's fields in screen order (Bilag, Dato, Debet/Kredit, Moms, Fakturanr., Beløb), the clip of a saved line, the balance, Gem and Bogfør; on a posted kladde the account link, Tilbagefør and Kopier til ny. New texts 8780-8787 (2603, 2604, 2606 stay in `tekster.csv`). Steps whose target is not visible are skipped: 9 steps on a new kladde, 10 on an open one.
- `finans/kladdeliste.php` tour step 1: new text 8779, which says that lines reach the accounts only when the kladde is posted.
- `index/main.php`: `saldi-help-finans.js?v=5`, `saldi-help.js?v=4`.

### 17.5 Not shipped

- A tour for the document pool: it would need `create_tutorial()` in `includes/docsIncludes/docPool.php`, which another developer is changing. The panel's KB tasks cover the pool.
- Attaching a bilag to a line of a posted kladde (KK21, kb Q9): the pool lets it, but whether that is intended is not settled.
- Kontrolkonto against the bank statement's closing balance (KK16 step 3), and the opening balance from an earlier system (kl Q8): both wait for a live check.

### 17.6 Tests

- `node --test tests/includes/ui/*.test.mjs`: 23/23. New tests cover the Kladdeliste and Kassekladde flags and every Kassebilag state and flag on the fake DOM.
- Guardrails `file-history, file-version, js-syntax, tekster, test-discovery, whitespace`: ok. `php -l` on ssl12 for both pages: ok.
- Live on ssl12, scripts outside the repository (`bog_probe.py`, `bog_checks.py`):
  - **A new customer's first day** in a fresh test tenant (`test_838`), built with `create_regnskab()` and the standard chart. 14 page states with the expected state and top three, every shown Vis mig target found, no missing key and no console error from the help:
    - the empty Kladdeliste;
    - a new kladde and the first line;
    - a refused date from last year;
    - the pool from the line (with a match), and attaching with **Indsæt valgte**;
    - the overview, posting, and the list after posting;
    - the posted kladde;
    - the viewer;
    - a new pool line, **Gem og næste**, and the pool after the last bilag.
  - English and Norwegian on the Kladdeliste, a kladde and the pool; the settings hub path; the kassekladde tour (10 steps).
  - Regression in Fjordlys Design (`finans_probe.py`): 29 finance pages, all ok.

### 17.7 Product defects found on the way (not fixed on this branch)

For the owner; the full list with file:line went to the owner directly, with the security-relevant ones.

1. **A new tenant cannot use the document pool.**
   - `opret_build_tenant()` creates `pool_files` without the 13 columns and the unique filename index that `includes/opdat_5.0.php` adds to older tenants (`includes/opretfunc.php:620`). That update never runs for a tenant created at 5.1.0.
   - The pool then shows **Noget gik galt**, and files in the pool never reach the list, because `docPool.php` inserts `norm_amount` with `ON CONFLICT (filename)`.
   - Seen live on ssl12. The same `opretfunc.php` is on `origin/master` and `origin/live`.
   - Fix: the full schema and indexes in `opret_build_tenant()`, plus an update for tenants that miss them.
2. An unreadable date is saved as today (`includes/std_func.php:519-523`). An unknown currency is saved as kroner, and the pool never fills **Valuta** (`kassekladde.php:4682-4686`, `docPool.php:6705-6741`).
3. **Tilbagefør postering** adds a reversal on every click or reload, and loses the currency (`kassekladde.php:906-911, 5100-5134`). **Kopier til ny** and **+** drop the line VAT codes (`copy2new.php:38-66`, `duplicate_line.php`).
4. VAT is split from the account's code even when the business is not VAT registered (`bogfor.php:408-420`). A single bilag that does not net to zero does not block posting (`bogfor.php:592-610`).
5. The pool's arrow keys and **Spring over** leave a saved line for a new one, so the same expense can be entered twice (`docPoolSaveNext.js:96-110, 322-329, 406-429`). Delete archives without asking, and archived bilag are deleted after 12 months (`docPoolArchive.js:209-220`, `poolArchive.php`).
6. The first save of a new kladde empties the user's kontrolkonto (`kassekladde.php:1115, 1149-1150`). There is a debug line on the Bogfør overview (`bogfor.php:612`).
7. Wrong translations in `tekster.csv`:
   - Norwegian: 600 Finansiere, 1093 Skrevet utkast.
   - English: 3341 Batch invoice, 1066 Equalize, 1086 Simulated Accounting.
   - Danish: 1586 Kladden en IKKE gemt.

## 18. Ask first, then guide (8 Nov 2026)

The owner tested the panel with an average Saldi user, alongside the old help. Her first impression: the old help ("Spørg Assist", which opens with a question) felt like being received when you have an urgent problem. The new panel felt more like a guide for new users. Both matter, at different times. Help must be quick to reach when you need it, and you must know that you can ask about anything if your problem is not one of the suggestions. The owner added that the keyboard shortcuts must be quick to reach for new and experienced users alike.

### 18.1 The panel's order

1. **The ask block** (`.hsb-ask`):
   - The heading **Hvad kan jeg hjælpe med?** (8711).
   - The field, with the placeholder "Skriv dit spørgsmål med dine egne ord" (8788) and a send button (8792). Enter and the button do the same thing, `submitQuery()`: the selected hit, a person (support), or else the question to Saldi AI.
   - The line "Du kan spørge om alt, også det, der ikke står herunder." (8789).
   - Two quiet buttons: **Tastaturgenveje** (6024) unfolds the page's keys, codes and the global keys under the field, and stays open until the panel closes. **Kontakt support** (8714) opens the support view.
   - While the user types, the line, the two buttons and the keys hide, and the search results show as before (§1.11).
2. **The problem card** (`.hsb-problem`, heading **Se her først**, 8791). When the page shows an error or something that stops the work, `page.problem(ctx, st)` names a task. The task comes first, with its steps open, and is left out of "Sådan gør du". The card has one calm accent (`--bad` on the left edge) and no scrim.
3. **Feltet du stod i**: after the problem card, or first after F1 from a field (§1.6).
4. **Lær siden at kende** (8790): Om siden, Sådan gør du, Rundvisning and Vejledning under one heading that folds. It is open the first three times the panel shows a page. From the fourth time it is folded, unless the user opened or closed it, which is remembered per page in `localStorage` (`saldi-help.learn.<route>`; the count is `saldi-help.seen.<route>`).
5. The foot keeps "Hvad får Saldi AI at vide?". Kontakt support moved up to the ask block.

A page without content shows the ask block alone, plus Rundvisning/Vejledning under "Lær siden at kende" when the page has them. The old "Spørg Saldi AI om <side>" row is gone, because the ask block replaces it.

The design rules of the kit hold: no chips or pills (the two buttons are `hc-btn--ghost`), no scrim, and one accent colour for a problem.

### 18.2 `problem()` (content)

An optional page function: `problem(ctx, st)` returns a task id or ''. In `javascript/saldi-help-finans.js` one table sets it per route. It returns the first task of the page's own `order()` in these states:

| Route | Problem states |
|---|---|
| `finans/kassekladde.php` | `afvist`; `aaben` with `manglerKonto`, `dublet`, `fakturaBrugt` or `difference` |
| `finans/bogfor.php` | `fejl`, `stoppet`; `bekraeft`/`simuler` with `kontoMangler` or `bilagDiff` |
| `includes/documents.php` | `pulje` with `obligatorisk`, `skaev`, `dublet` or `fakturaBrugt` |
| `finans/rapport.php` | `ingenAar` |
| `finans/bankReconcile.php` | `fejl` |
| `finans/bankimport.php` | `tom` |
| `finans/importer.php`, the payroll and BS imports | `fejl` (these states have no task, so there is no card; Om siden says what to do) |

### 18.3 Files

- `javascript/saldi-help.js`: `build()`, `applyView()`, `fillProblem()`, `fillKeys()`, `fillLearn()`, `submitQuery()`, the send icon. `keysList()` is shared by the ask block and the "genvej" search hit.
- `css/saldi-help.css`: the ask block, the send button, the problem card, "Lær siden at kende".
- `index/mainIncludes/topbar.php` (`topbar_help_host()`) and `includes/saldi_help_panel.php` (`saldi_help_chrome_ids()`): texts 8711 and 8788-8792 in `importfiler/tekster.csv`.
- `index/main.php`: `saldi-help.js?v=5`, `saldi-help.css?v=4`, `saldi-help-finans.js?v=6`.

### 18.4 Tests

- `node --test tests/includes/ui/*.test.mjs`: 24/24, including a new test of `problem()`. `problem()` must name an existing task in every state.
- Live (`ask_first.py`, the fresh test tenant):
  - the first view (ask block first, the guidance open, three tasks);
  - Tastaturgenveje under the field;
  - the fifth view (the guidance folded);
  - a refused save (the problem card after the ask block with its steps open, KK1 not repeated below);
  - a page without content (the ask block alone);
  - the phone sheet.


## 19. Hjælp inside Saldi Assist (8 Nov 2026)

The owner tested the panel with an average user and decided that Hjælp and the chat (Saldi Assist, the Guide v2 widget) become one thing, and that every function of both is kept. The chat is a separate app in a cross-origin iframe (`DANOSOFT/support-sally`), so Saldi computes the page help, sends it to the chat as data, and runs the chat's actions in its own page. The agreed interface is the merge contract v1 (host, loader `frontend/app/widget.js/route.ts`, widget `frontend/components/embed/EmbedClient.tsx`), with the additive v1.1 of §19.9 that both sides built after the contract review. This section is the host side, in this repository; the loader and the widget are built in `support-sally`.

### 19.1 Merged mode and fallback

`saldi-help.js` uses the merged mode only when all of these hold, read at the time of use (`merged()`), because the Guide loader is `defer`:
- the tenant has the help panel on (the help scripts are printed only then, §9);
- `window.SaldiAssist.helpReady === true` (the new loader sets it next to `v2Ready`);
- `window.SALDI_CHAT.open` exists;
- `window.SaldiHelpState.build` exists (`saldi-help-state.js` loaded);
- the chat has not failed to connect (below).

Otherwise the panel works exactly as in §1–§18. Every new path is guarded by `merged()`. The one difference: a pinned panel that reopens after a shell reload (§10) waits for `DOMContentLoaded` when the Guide v2 script tag (`data-assistant="v2"`) is in the page, so it does not flash open in merged mode. Without that tag it opens at once, as before. `DOMContentLoaded` waits for the deferred loader from the chat's server, so a slow or hanging loader delays that pinned panel by the same time.

**When the chat does not come up.** A loader that sets `SaldiAssist.helpConnected = false` (and `true` plus the window event `saldi:help:ready` on the widget's first `saldi:ready`, §19.9) gets 4 s (`READY_MS`) from each open by the host. If it has not connected by then, the host closes the empty chat, opens the panel instead (keyboard focus in its ask field) and stays in the fallback until `saldi:help:ready` arrives. A loader that does not set `helpConnected` (contract v1) is trusted as before: the host cannot tell a slow chat from a dead one.

In merged mode:
- The topbar **Hjælp** button, the classic Hjælp cell, `?` outside a field and F1 open the chat (`saldi:help:open`) instead of the panel. Focus is `field` after F1 in a field with field help, else `help`. The panel stays in the code as the fallback, and `SaldiHelp.open()` still opens it.
- The page group's texts load on every page bind (`computeP(true)`, cached per group in `TX`), so the state is ready before the chat opens. A failed group is retried on the next open.
- The tour card's **Spørg Saldi AI om dette** ends the tour, as in the panel, and hands the question to the chat (`saldi:assist:ask`, §6).
- The topbar button has `aria-haspopup="dialog"` and the title "Hjælp (?)" (no "fastgjort"). With a loader that has `SALDI_CHAT.isOpen()` (v1.1) it has `aria-controls="saldi-chat-container"` and an `aria-expanded` that follows `saldi:chat:open` and `saldi:chat:close`; with a v1 loader it has neither, because the host would not learn when the chat opens from its own launcher. In the fallback it points at the panel again.
- The chat's open state and layout come from `SALDI_CHAT.isOpen()` and `SALDI_CHAT.layout()` when the loader has them, else from `#saldi-chat-container` (`style.visibility`, `data-layout`).

### 19.2 Events in the host window

| Direction | Event | `detail` | When |
|---|---|---|---|
| out | `saldi:help:state` | a HelpState. A frame Saldi cannot read, and a page that is going away (`pagehide`), give route `''` with the keys that work everywhere and the support facts, so the chat drops the old page's help and Vis mig at once. The host no longer sends `null`. | page bind and load, the pool's `poolswitch`, a `change` in the page, a move to a field with another key (not every row of a column), texts loaded, the breadcrumb, a fold choice. Debounced 150 ms; sent only when the JSON differs from the last one. Once more after the loader has run (`DOMContentLoaded` or `load`), because states sent before it listened are lost. Not while the frame is loading, except the blank state at `pagehide`. |
| out | `saldi:help:open` | `{ focus: 'help' \| 'field' \| 'problem' }` | Hjælp, `?`, F1, the classic cell, and the way back after Vis mig or a tour. The state is sent first (no debounce). `field` and `problem` fall back to `help` when the state has no such card. |
| in | `saldi:help:action` | `{ action: 'showme', target }`, `{ action: 'tour' }`, `{ action: 'learn', open }`, and the additive `run`, `back` and `seen` of §19.9 | from the chat through the loader. `target` must match `/^(task\|problem\|q\|field):[A-Za-z0-9_]{1,24}$\|^field$/` (`field:<id>`, a field of the search, is v1.1) and be at most 64 characters. Anything else is ignored, as is every action outside merged mode. |
| in | `saldi:help:ready` | – | §19.9: the loader connected to its widget. Ends the 4 s wait (§19.1) and leaves the fallback. |
| in | `saldi:chat:open` | – | §19.9: the chat opened, any way. While the way back is shown it ends the ring or tour quietly; until the loader sends it, the host watches the chat box's `style` (a `MutationObserver`, only while the strip is up). It also updates the topbar button (§19.1). |
| in | `saldi:chat:close` | – | §19.9: the chat closed or stepped aside (`hide()`). Updates the topbar button. |

### 19.3 The HelpState (`saldi-help-state.js`)

`window.SaldiHelpState.build(input)` is pure: no DOM writes, focus, clicks, network, timers or storage. It calls the page's read-only functions and finds Vis mig targets through the resolver the shell hands it (`resolveShow()` and `showLabel()`, the same as the panel's Vis mig). Without a readable frame it returns route `''`, `hasContent: false`, no tour and no page rows, with the keys that work everywhere (contract §5); `null` only without an input.

| Field | From |
|---|---|
| `route`, `lang` | `ctx.route`, `data-lang` |
| `title` | `page.name()`, or the generic label (`genericLabel()`) for a page without content |
| `hasContent` | a page object whose texts are loaded |
| `problem` | the task `page.problem()` names, steps open (§18) |
| `field` | "Feltet du stod i": `fieldInfo()`, with `canShow` when the field is visible |
| `about` | `page.about()` |
| `tasks` | the first three existing ids of `page.order()`, never the problem task |
| `learnOpen` | the user's choice for the route (`saldi-help.learn.<route>`), else open for the first three views (`saldi-help.seen.<route>`, shared with the panel). A view counts when the user opens the chat (Hjælp, `?`, F1, the classic cell) or a new route's state is built while the chat is open. The way back after Vis mig or a tour, and the reopen after a failed Vis mig, are not views. Once the chat sends `seen` (§19.9), only `seen` counts. |
| `tour` | `SaldiTour.count()` in the frame; `null` at 0 |
| `shortcuts` | the page's keys (8774) and codes (8775), the keys that work everywhere (8776) without Esc, and the page's note |
| `search` | every task and prepared question of the page, the page's order for its state first. A question's `words` are its `words[lang]`; a task's are the words of its why line and Vis mig label, without stop words. For a v1.1 loader (it sets `SaldiAssist.helpConnected`, `v11()`) the page's fields follow, as the panel's search found them: `kind: 'field'`, `title` the label, `intro` the text, no steps or words, `canShow` when `fieldEl(key)` finds the field on the page; the field the user stood in leads them. The id is the field key when it is a valid id, else the key with `_` for other characters ('search:kladdenote' → `search_kladdenote`), else `f<n>` (`SaldiHelpState.fieldIds()`, which the shell uses to map `field:<id>` back). A v1 widget refuses a whole state with another kind, so a v1 loader never gets them. |
| `questions` | additive (§25): the ids of the state's top three prepared questions for the chat's start screen, in the search's order for `q` |
| `labels` | 8793, 8791, 8704, 8790, 8703, 8706, 8707, 8708, 8709, 8710, 6024, 8748 |

Titles, why lines, questions, labels and Vis mig labels are plain text (`**` dropped); steps, paragraphs and field texts keep `**bold**`. The largest state of the finance content is about 41 KB (Kassekladde, refused save, every Vis mig shown). A state over 96 KB keeps the page and cuts the search list from its end, with a console warning.

**Bounds.** The chat refuses a whole state when one string or list breaks its parser's bounds (support-sally `frontend/lib/page-help.ts`), and then shows no page help for the page. `bound()` keeps them on the host (`SaldiHelpState.caps`), with one console warning (`help state bounds: …`) that names what changed:

| What | Bound |
|---|---|
| tasks (Sådan gør du) | 3, after dropping a task with a bad id, no title or no steps; the next ids of `page.order()` fill in |
| steps of a task or the problem | 1–4, 600 characters each, empty steps dropped |
| search items | 120 (Kassekladde has 66 without its fields), 0–6 steps, 40 words of 40 characters at most, intro 1000; a bad id, an unknown kind or no title drops the item |
| about | 2 paragraphs of 1500 |
| field card | label 1–160 (no label: no card), text 1500 |
| shortcuts | 12 sections, 40 items, text 1–300, keys 60, note 600 |
| tour | 1–99 steps |
| title, why, label, route | 200, 400, 160, 200 |
| ids | `[A-Za-z0-9_]{1,24}` |

Lengths are counted as the chat counts them: UTF-16 units, after control characters and runs of white space became one space. A clipped string ends in "…". An empty label cannot be repaired on the host and is only warned about. The whole state is measured in UTF-8 bytes (≤ 98 304), which are never fewer than the characters of `JSON.stringify` that the loader and the chat count against the same number, so the host's limit is the stricter one.

With the fields, `finans/rapport.php` has the most search items, 83 (59 tasks, 7 questions, 17 fields), and Kassekladde 77, so every page fits in the 120. Should a page outgrow it, the tasks and questions come first and only fields are cut.

### 19.4 Vis mig, Rundvisning and the fold from the chat

- **showme**: the target is resolved again on the current page: `task:<id>` and `problem:<id>` by the task's `show`, `q:<id>` by the question's, `field` by the field the user stood in. When nothing is found, a new state goes out (`canShow` false) and nothing else happens. Otherwise the panel's ring and callout show it (`showMe()`, §1.7).
  - A docked chat stays open, and no strip is shown. When the ring ends by Forstået, × or Esc, focus goes back into the chat (its iframe); a press in the page leaves focus where the user put it.
  - A chat over the page (floating or full screen) steps aside first, and where it stood is kept. With a v1.1 loader that is `SALDI_CHAT.hide()`, which changes only the visibility, so the widget is not told it closed and an open bug card in it stays; the way back opens it again. A v1 loader is closed (`SALDI_CHAT.close()`). It closes even when it does not cover the target, because the panel also shrank to its strip for every Vis mig (`minimise()` in `showMe()`), and the callout beside the target could otherwise lie under the chat. The panel's strip then shows alone, "Viser: **<label>** · Tilbage til hjælpen": the host is shown with `is-min`, as an overlay strip or, at 600 px or less, a sheet strip, and moves out of the ring's way as in §1.8.
  - **Tilbage til hjælpen**, **Forstået**, × and Esc end the ring and open the chat again, on the card the user came from (`help`, `problem` or `field`). The callout's own buttons do not make the host forget the field the user stood in.
  - A press in the page ends the ring and drops the strip. The chat comes back without the help focus (`SALDI_CHAT.show()`, else `open()`), as the panel's `restore()`, unless the press lay where the chat stood (a full-screen chat always) or in a field the user now types in. A page change or a new ring drop the strip and leave the chat closed.
  - The chat opened from its own launcher while the strip is up ends the ring quietly (§19.2, `saldi:chat:open`).
- **showme `field:<id>`** (v1.1): a field of the search, found by `fieldEl(key)` (the field the user stood in when it has that key, else the first visible one) and rung as the field card's Vis mig; the way back goes to `help`.
- **tour**: `SaldiTour.start({ focus: true })`; any chat over the page steps aside (as above), because a tour moves over all of it. The strip shows when the frame says `start`, because a tour that was already running says `end` first. ×, Færdig, Næste on the last step and Esc end the tour and reopen the chat, with the keyboard or the mouse (a press on the tour card is not a press in the page). The card's **Spørg Saldi AI om dette** does not reopen it, because its own question follows. A press in the page is handled as for a ring. A tour beside a chat that stayed open gives focus back to the chat when it ends with focus nowhere.
- **learn**: the fold choice is stored per route in `localStorage` (`saldi-help.learn.<route>`, the panel's key), and a new state follows.

### 19.5 Text

New chrome texts (`importfiler/tekster.csv`, `topbar_help_host()`, `saldi_help_chrome_ids()`):
- 8793 **Hjælp til siden** / Help for this page / Hjelp til siden: the page help's button and sheet title in the chat;
- 8794 **Tilbage til samtalen** / Back to the conversation / Tilbake til samtalen: `labels.back` (§19.9), the sheet's way back in all three languages.

The other labels reuse existing ids (§19.3, §19.9).

### 19.6 Files

- `javascript/saldi-help-state.js` (new): the HelpState builder.
- `javascript/saldi-help.js`: `merged()`, `buildState()`, `pushState()`, `queueState()`, `catchUp()`, `openChatHelp()`, `mergedOpen()`, `helpTarget()`, `mergedShowMe()`, `mergedTour()`, `onHelpAction()`, `showBack()`, `endBack()`; the triggers in `bindFrame()`, `onFrameLoad()`, `loadTexts()` and the breadcrumb message; the merged branches in `toggle()`, `onKey()`, `onF1()`, `helpAsk()`, `endShowMe()`, `endRingOrTour()`, `onTour()`, `onPageHide()`, `avoid()`, `schedule()` and `motionCheck()`; `SaldiHelp.helpState()`; `globalKeys()` and `learnOpenFor()` split out of `keysList()` and `fillLearn()`. After the merge review also `chatLayout()`, `chatRect()`, `chatCovers()`, `focusChat()`, `reopenQuiet()`, `pressAside()`, `watchChat()`, `chatReopened()`, `watchReady()`, `onHelpReady()`, `sendState()`, `pushBlank()`, `moreRows()`, `supportState()`, `contactList()`, `mergedRun()`, `mergedBack()`, `hideChat()`, `v11()`, the `field:<id>` branch of `helpTarget()`, the merged branch of `setTopbar()` and the `ahead` of `learnOpenFor()`. In `saldi-help-state.js` also `bound()` and `fieldIds()`.
- `index/main.php`: `saldi-help-state.js?v=2` after `saldi-help-finans.js`, `saldi-help.js?v=7`.
- `index/mainIncludes/topbar.php`, `includes/saldi_help_panel.php`, `importfiler/tekster.csv`: texts 8793 and 8794.
- `tests/includes/ui/saldiHelpState.test.mjs`, `tests/includes/ui/saldiHelpMerged.test.mjs` (new).

### 19.7 Tests

- `node --test tests/includes/ui/*.test.mjs`, one file at a time with a cap (`timeout 180 node --max-old-space-size=2000 --test <file>`): 60/60 (saldiAssistNavigate 5, saldiHelpContent 19, saldiHelpMerged 21, saldiHelpState 15).
  - `saldiHelpState.test.mjs`, the builder on the real finance content and TSV texts:
    - the shape of contract §5;
    - Kassekladde refused (KK1 as the problem card, not repeated in the top three), the empty Kladdeliste (K11, K10, K13), the Kassebilag pool with a refused Gem og næste (KB13);
    - English and Norwegian;
    - the field card, the fold state and the shortcut sections;
    - a page without content;
    - every page in every state and language within 96 KB with a complete search list, and the cut when a state is too large;
    - every state within the chat parser's bounds (`assertBounds()` with `SaldiHelpState.caps`), and content beyond them clipped, cut or dropped with one warning;
    - an unreadable frame (route `''`), and the additive fields of §19.9;
    - the fields in the search (v1.1): ids, order, Vis mig, none without `fields: true`, and every page within the 120 with its tasks and questions first;
    - the source only reads.
  - `saldiHelpMerged.test.mjs`, the shell scripts on a fake DOM with a Kladdeliste in the frame:
    - without `helpReady`, the panel opens and no `saldi:help:*` event goes out;
    - with it, the state goes out on load and again when the texts arrive, only when it changed;
    - Hjælp, `?` and F1 open the chat, and F1 in a field opens it on the field's card;
    - Vis mig beside a docked chat, and over the page with the strip and the way back;
    - Rundvisning, the fold choice, and invalid actions;
    - the pinned reopen with and without the merge;
    - a tour ended with the mouse on its card, its Spørg button, and a press in the page;
    - Vis mig on the field over the page keeps the field for the way back;
    - the way back is not a new view;
    - `pagehide` sends route `''`, and Vis mig of the old page does nothing;
    - a press outside the chat's place brings it back without focus, under it or into a field it does not;
    - focus goes back into a docked chat after Forstået, not after a press;
    - `SALDI_CHAT.isOpen()`/`layout()` come first, and `saldi:chat:open` ends the ring;
    - the topbar button's ARIA with and without the merge;
    - a loader that says `helpConnected: false` and does not connect hands the help to the panel, and one that connects does not;
    - the actions `run`, `back` and `seen`;
    - a column of the same field builds the state once;
    - a v1.1 loader gets the fields in the search and `field:<id>` rings the field; a v1 loader gets none;
    - Vis mig and a tour use the loader's `hide()`, a press outside uses `show()`, Esc back to the field closes.
  - The merged tests compare nodes with `assert.ok(a === b)`. A failed `assert.equal()` on a fake node prints both values 1000 levels deep with their getters, and the linked fake nodes grew that message until the process ran out of memory (20 GB) instead of failing; that was the "endless loop" of the first review run. `saldi-help.js` had no loop.
- Every page × state (and `unknown`) × language × flags on/off × every field card × fields on/off, with every Vis mig and every additive field, plus the unreadable state, was run through the chat's real `parseHelpState()` (transpiled, outside this repository): 14 127 states. The v1.1 parser (support-sally working tree of 8 Nov) refused none and dropped no search item or optional part; the largest state is 47 691 characters. The v1 parser (58a60a4) refuses each state with field items, which is why only a v1.1 loader gets them.
- Guardrails `file-history, file-version, js-syntax, tekster, test-discovery, whitespace`: ok.
- Not run live: the loader and the widget of the contract are not deployed yet. Until they are, ssl12 shows the fallback.

### 19.8 What merged mode does not carry yet

The owner's decision is that every function of both is kept. Contract v1 and the chat of 8 Nov do not carry these panel functions; in merged mode the panel is never shown, so until the chat has them they are gone. The host side of each is in §19.9 where it could be built without breaking a v1 chat.

| Panel function (`saldi-help.js`) | In merged mode now | Host side | Chat side still to do |
|---|---|---|---|
| **Kontakt support** in the quick row: phone with today's opening status, mail, **Skriv til os** (`supportCard()`) | the v1 chat offered support only under an answer that escalates | `support`, `labels.support/call/write` (§19.9) | v1.1 parses them; a quiet Kontakt support next to Tastaturgenveje, with the phone line (`tel:`), the status and the chat's own support form without a prior question |
| contact words send a typed question to a person, not Saldi AI (`contactRoute()`) | the question goes to Saldi AI | `contactWords` | show the support card above the composer when a typed word is one of them |
| **Spørg Saldi AI om feltet** (8705) and **Spørg Saldi AI om dette** (8763) | missing | `field.ask` ("Forklar mere om: <label>"), `labels.askField/askThis` | the two links; the question goes into the composer (as `saldi:ask`) or is sent |
| **Vejledning**, a page's own registered tour, **Sidens egne genveje** (`fillMore()`, `keysList()`) | missing | `more`, action `run` | the rows (under Lær siden at kende and in Tastaturgenveje), posting `run` |
| search hits for the page's **fields** (`search()`) | v1 searched tasks and questions only | built for a v1.1 loader: `kind: 'field'` items and `field:<id>` (§19.3, §19.4) | v1.1 parses and shows them; both sides allow 120 search items (Rapport has 83 with its fields) |
| search hits for the page's **shortcuts and codes**, the **tour** row and the **Tastaturgenveje** row (`search()`) | the chat searches tasks, questions and fields | not sent: the v1.1 parser knows no `key`, `tour` or `keys` kind (it would drop such items one by one) | contract v2: those kinds, or the chat matches its own shortcut list and tour row |
| Esc **Luk hjælpen og gå tilbage til feltet** (8751) after F1, with the caret | missing; Esc is left out of the chat's shortcut list | action `back` | Esc in the start screen or sheet opened by `saldi:help:focus` posts `back`; then Esc can go back in the list |
| **Hvad får Saldi AI at vide?** (8736, 8737/8771, 8772) | the chat's own AI disclosure at the composer; v1.1 drops `disclosure` on purpose | `disclosure` (kept, a few hundred bytes, in case the chat takes it up) | – (the chat's decision) |
| the "Knappen ligger bag hjælpen" notes next to Vis mig | not needed: the chat closes or stands beside the page, and the ring is never under help | – | – |
| keyboard focus in the ask field on Hjælp, `?` and F1 | the chat moves focus to a card heading | – | keep focus in the composer, scroll the card into view |
| Tastaturgenveje one click away at the top | the button is last, below the fold for new users | – | a quick row after the page card and Se her først |
| the help panel itself when the chat app does not load | an empty chat | the 4 s wait (§19.1) | the loader's `helpConnected` and `saldi:help:ready` |

The claim of the first version that Kontakt support and Skriv til os were covered by the chat's own escalation was wrong: the chat shows them only after an answer that escalates.

Other open points:
- With `hide()`, a chat the user does not come back to (a press under it, a page change) stays hidden without the widget being told; its error capture for an open bug card stays armed until the next close. That is the loader's choice (support-sally `hideChat()`).
- A floating chat steps aside for every Vis mig, even when it does not cover the target (contract review m11). Rejected for now: the panel also shrank for every Vis mig, and the callout beside the target could lie under the chat.
- The open status of the support facts is computed when the state is built (every open by the user builds it again); it is not refreshed on the hour by itself.
- A live run on ssl12 once the loader and the widget of the contract are deployed to a test instance (`SALDI_ASSIST_WIDGET_URL`).

### 19.9 Additive fields and actions (contract v1.1)

Built on the host so the chat can take them up without a new host release. A v1 chat drops unknown fields and labels, and its loader drops unknown actions, so nothing changes until the chat uses them. The shapes follow the contract review; the chat's v1.1 (support-sally `frontend/lib/page-help.ts` and `route.ts`, working tree of 8 Nov) parses `support`, `contactWords`, `more`, `field.ask`, the six labels and `field` search items as optional parts (a bad one is dropped, not the state), relays `run`, `back` and `seen`, and drops `disclosure` on purpose. Its caps are those of `SaldiHelpState.caps` or looser (mail 254, status and hours 160, `more` titles 200).

```ts
type HelpState = HelpStateV1 & {
  field: { label: string; text: string; canShow: boolean; ask: string } | null   // ask: 'Forklar mere om: Debet' (8756), ≤ 200
  support: { phone: string; tel: string; hours: string; open: boolean; status: string; mail: string } | null
           // phone as shown ('46 90 22 08', '+45 46 90 22 08' for en/no), tel '+4546902208', hours 'hverdage 9–16' (8716),
           // status 'Åbent nu, til kl. 16' (8717–8720), mail 'support@saldi.dk'; phone/tel ≤ 40, others ≤ 160
  contactWords: string[]   // lower case, ≤ 60 words of ≤ 40
  more: { kind: 'guide' | 'tour' | 'shortcuts'; title: string }[]   // ≤ 3: Vejledning (92), Rundvisning af siden (6455, a registered
                                                                    // tour that is not SaldiTour), Sidens egne genveje (8754); [] without a readable page
  disclosure: { title: string; lines: string[] } | null            // 8736; 8737 or 8771 (records on), 8772; ≤ 3 lines of ≤ 600
  labels: HelpLabelsV1 & { back: string; support: string; call: string; write: string; askField: string; askThis: string }
           // 8794 Tilbage til samtalen, 8714 Kontakt support, 8715 Ring %s, 8721 Skriv til os, 8705, 8763; ≤ 160
}
```

| Action (widget → loader → host) | Host does |
|---|---|
| `{ action: 'run', kind: 'guide' \| 'tour' \| 'shortcuts' }` | only when the page has that row now: closes a chat that lies over the page, ends a ring or the strip, then `topbarAssistRun(kind)` as the panel's row |
| `{ action: 'back' }` | Esc in the chat after Hjælp, `?` or F1: closes a chat that lies over the page and puts the caret back in the field the user stood in (8751) |
| `{ action: 'seen' }` | the chat shows the page help now (start screen or sheet opened): counts the view of the route. From the first `seen` on, the host stops counting its own opens, and `learnOpen` is computed one view ahead |

| Loader (host window) | Host does |
|---|---|
| `SaldiAssist.helpConnected = false` at init, `true` and `saldi:help:ready` on the widget's first `saldi:ready` | the 4 s wait and the fallback (§19.1) |
| `SALDI_CHAT.isOpen()`, `SALDI_CHAT.layout()` (`'docked' \| 'floating' \| 'fullscreen'`) | read before the box's style and attribute |
| `saldi:chat:open` on the window when the chat opens | ends a ring or tour shown with the chat closed, quietly |

Search items of `kind: 'field'` with the `field:<id>` target go only to a v1.1 loader (`SaldiAssist.helpConnected` is a boolean), because a v1 widget refuses a whole state with them. Not built: the search kinds `key`, `tour` and `keys` (contract v2, §19.8).

| Loader (host window), v1.1 | Host does |
|---|---|
| `SALDI_CHAT.hide()`, `SALDI_CHAT.show()` | Vis mig and a tour step the chat aside with `hide()`; a press outside where it stood brings it back with `show()`. A page row (`run`) and `back` leave the chat for the page and use `close()` |
| `saldi:chat:close` on the window | the topbar button's `aria-expanded` |

## 20. The front page (dashboard) (8 Nov 2026)

The owner tested the help on Oversigt, the front page (`index/dashboard.php`), and it did not work well there. Oversigt is the frame's first page after login (`index/main.php:1214-1225`), and it had no page object: the panel showed only the ask block, and the chat got `hasContent: false` (§1.13, §19.3). This round gives it one. It is written for a new customer on day one (an empty company), for a business owner in daily use, and for a bookkeeper who wants to know where the numbers come from.

### 20.1 Files

- `javascript/saldi-help-content.js`: `PAGES['index/dashboard.php']`, group `fs`, at the end of the shared file. §16.1 keeps one file per module; the front page belongs to no module, so it sits next to the shared data instead of in a file of its own (no new script, no change to the pilot condition). Its helpers (`fsQ`, `fsQa`, `fsShown`, `fsNoRevenue`, `fsVisibleBlocks`, `fsDueRow`, `fsUdlign`) are local to the block.
- `importfiler/tekster_keyed.tsv`: 130 new `help.fs.*` rows in Danish, English and Norwegian.
- `javascript/saldi-help.js`: the message `saldi:dash-hidden` from the frame drops the cached detect, renders an open panel again and queues a new state (§20.4).
- `index/main.php`: `saldi-help-content.js?v=5`, `saldi-help.js?v=8`, inside the same `helpPanel` condition (§9).
- Tests: `tests/includes/ui/saldiHelpContent.test.mjs`, `saldiHelpState.test.mjs`, `saldiHelpMerged.test.mjs` (§20.6).

### 20.2 What the page is (the code is the truth)

The front-page specs (`Kravspecifikation_forside_redesign.md` and its addenda of 8 and 9 Oct) describe more than this branch has. What the help covers is what `index/dashboard.php` prints now:

- **Blocks** in `#grid`, in the user's own order (`dash_order()`, `:120-138`): Nøgletal (`.db-kpis`: Omsætning denne uge, Omsætning denne måned, Omsætning for året, and Momsangivelse for perioden when the company is VAT registered and the user may open Finansrapport, `:194-201, :285-303`), Ubetalte salgsfakturaer (`:376-421`, `dashboardIncludes/unpaidInvoices.php`), Små tal with Ufakturerede ordrer (`:424-437`), Omsætning pr. måned, Kunder pr. time (off by default), Omsætning pr. dag and Omsætning pr. varegruppe. A block or card switched off in Rediger oversigt keeps its markup with the class `gone` (`:265-267`, `dashboard.js setWidget()`).
- **Revenue** is credit minus debit on the accounts strictly between `kontomin` and `kontomaks` (`dashData.php:40`, `kontonr > min and kontonr < max`), 0 and 2000 when nobody set them (`:187-188`). Only posted entries (`transaktioner`) count. Omsætning pr. varegruppe counts invoiced order lines per item group instead (`dashData.php:267-296`). With no revenue the charts print `.db-ph` **Ingen omsætning endnu** (`:280-281`).
- **Ubetalte salgsfakturaer**: the due groups (`#unpGrp`, over 15 days, 1-15 days, not due), the oldest invoices (`#unpTbl`), **Vis alle** to the open items report, and, with unapplied payments or credit notes, their amount and **Udlign** (`:414-419`). With nothing open: **Ingen ubetalte salgsfakturaer** (`:382-384`).
- **POS-muligheder** (`.db-pos`, `:450-461`) when the year has a POS group; a role without any block gets `.db-empty` with links to its modules (`:462-479`).
- **Hidden** by the user: `#grid.all-hidden` and **Vis oversigt** (`#showDash`, `:481-482`). Hiding and showing happen in place (`dashboard.js setHidden()`), with neither a page load nor a `change` event.
- **Rediger oversigt** is the drawer `#drawer`, opened from the user menu in the topbar (`index/mainIncludes/topbar.php:485-490`, the message `saldi:dash-edit`); open it has the class `open`. It holds the switches, the order (`.blist`) and **Omsætningskonti** with two inputs without name or id (`.acct`, `dashboard.js buildDrawer()`).
- **Gennemgå roller** (`.db-notice`) for an administrator after the move to roles, until every role is reviewed or the administrator hides it (`dashboardIncludes/roleReview.php`).

Not on the page any more, so not in the help as page elements: the card "Forslag klar til bekræftelse" and Aktive medarbejdere (removed 1 Nov, `dashboard.php:51-52`), the news and batch-expiry banners (in the bell since 27 Sep, `includes/notifications.php`), and the Kassesystem and Sagsstyring buttons (a topbar icon and a user-menu item, `topbar.php:438-440, :502-505`). The help says where they went (FS9, Q4, Q5). The greeting with "opdateret for …" is in the topbar; the figures are fetched when the page loads (FS1).

The branch for "Intet aktivt regnskabsår" (`:235-243`) needs `$regnaar === null`, which `includes/online.php` never leaves (`:166, :273-280`), so it has no state.

### 20.3 States and flags

| State | When (DOM) | Om siden |
|---|---|---|
| `tom` | `#grid`, no revenue (no month or day chart, which the page draws when this or last fiscal year has revenue, `:280`; then the year's figure is 0, or without Nøgletal the charts show their placeholder), no unpaid invoice on the page, no uninvoiced order | `about.forside`, `about.tom` |
| `oversigt` | `#grid` otherwise | `about.forside`, `about.kilde` |
| `begraenset` | `.db-empty` (a role without blocks) | `about.begraenset` |
| `skjult` | `#grid.all-hidden` | `about.skjult` |

Flags: `roller` (the roles card), `noegletal` (Nøgletal with at least one figure switched on), `ubetalte` (`#unpTbl`), `forfaldne` (a list row of the late or 1-15 days group), `ikkeUdlignet` (the unapplied amount under the list), `ingenUbetalte`, `ordrer` (Ufakturerede ordrer is not 0), `moms`, `pos`, `ingenOmsaetning`, `ingenBlokke` (every block switched off). A card or block switched off in Rediger oversigt gives no flag.

No state calls for `problem()`: nothing on the front page stops the work.

### 20.4 Content

Twelve tasks, eight questions, five fields, four keys.

| Task | Title | Vis mig |
|---|---|---|
| FS1 | Se, hvad nøgletallene viser | the key figures (`.db-kpis`) |
| FS2 | Få dine første tal på forsiden | – (the menu is in the shell) |
| FS3 | Følg op på ubetalte fakturaer | the most urgent due group with invoices (`#unpGrp`) |
| FS4 | Fakturér de leverede ordrer | **Gå til ordrer** |
| FS5 | Vælg, hvad din forside viser | the block list, only while Rediger oversigt is open |
| FS6 | Vælg, hvilke konti der er omsætning | **Fra kontonr.**, only while Rediger oversigt is open |
| FS7 | Find det, du skal arbejde med | – (sidebar, search) |
| FS8 | Start dagens arbejde (advice) | – |
| FS9 | Find beskeder og indstillinger | – (bell, user menu) |
| FS10 | Vis oversigten igen | **Vis oversigt** |
| FS11 | Gennemgå brugernes roller | **Gennemgå** on the roles card |
| FS12 | Gå til dit arbejde | the module buttons of a role without blocks |

Top three:

| Situation | Top 3 |
|---|---|
| A new company (`tom`) | FS2, FS1, FS7 |
| Daily use, nothing pending | FS1, FS8, FS7 (FS1 only with `noegletal`) |
| Overdue invoices and orders to invoice | FS3, FS4, FS1 |
| An administrator with the roles card | FS11 first, then the state's own order |
| Every block switched off | FS5 first |
| Hidden | FS10, FS7, FS9 |
| A role without blocks | FS12, FS7, FS9 |

Questions: Q1 Hvorfor står der 0 kr eller Ingen omsætning endnu?, Q2 Hvorfor passer omsætningen ikke med regnskabet?, Q3 Hvorfor står en betalt faktura stadig som ubetalt? (Vis mig **Udlign**), Q4 Hvor finder jeg nyheder og advarsler? (until 9 Nov 2026 "Hvor er nyhederne og advarslerne fra den gamle forside?"), Q5 Hvor åbner jeg kassesystemet eller sagsstyringen? (Vis mig POS-muligheder), Q6 Hvordan læser jeg graferne? (Vis mig **Vis som tabel**), Q7 Hvad viser momstallet på forsiden? (Vis mig **Se angivelse**), Q8 Hvorfor mangler en blok på min forside?. `questionOrder()` (since the review of 9 Nov 2026, §25.9): `tom` Q1, Q5 with POS, Q7 with VAT, Q4, Q8; `oversigt` Q3 with an unapplied amount, Q5 with POS, Q2, Q7 with VAT, Q3 with unpaid invoices, Q4, Q8, Q6, Q1; `begraenset` Q8, Q5, Q4; `skjult` Q4, Q8, Q5.

Fields (`fieldKey()`): `sammenlign` (`#cmpSel`), `uge` (`#weekSel`), `periode` (`#heatSel`), and `kontomin`/`kontomaks`, the first and second input of `.acct`. Keys: Tab to the bars (their tooltip shows on focus), Enter on a due group or an invoice, Alt+↑ / Alt+↓ on a block's grip and Esc in Rediger oversigt. There are no access keys, so no `shortcutsNote`.

Text decisions:
- Every Vis mig is inside the frame. The sidebar, the bell, the user menu and the Kassesystem icon are in the shell, so those tasks name them in their steps ("klik på dit navn øverst til højre") and have no Vis mig.
- The revenue accounts get a neutral warning (§16.3), because a silent trap costs correct figures: the two numbers themselves do not count (FS6, the two field texts), and with the default 0 to 2000 the standard chart's purchases and sales costs count as negative revenue (FS6 step 3). The advice uses kinds, not numbers (§17.3): the heading above the sales accounts and the next heading.
- **Omsætning denne uge** is compared with the whole of the same week last year, the month and the year with the same days (FS1 step 2), so an early-week drop is not misread.
- The VAT figure is called provisional, with **Se angivelse** to check the return before filing and the settings path of the VAT report's accounts (Q7).
- Labels hard-coded in Danish on another page stay Danish in all three columns (**Opret rykker**, `includes/reportFunc/showOpenPosts.php:1307`). **Udlign** uses 1066 (Equalize / Utjevner) as everywhere (§4.1).

**The page re-read.** `dashboard.js setHidden()` posts `{ type: 'saldi:dash-hidden', hidden }` to the shell (Skjul oversigt in the user menu, **Vis oversigt** on the page, the switch in Rediger oversigt). `saldi-help.js` now handles it like `poolswitch` (§17.4): `dropDetect()`, `render()` when the panel is open, `queueState()`. Without it the help kept `skjult` after **Vis oversigt** until the next page load.

### 20.5 Read as a new customer and as a bookkeeper

The texts were read once as a business owner on day one and once as a bookkeeper. Changed after the read: the empty page's Om siden said "nøgletallene står på 0" while the live VAT figure of the new company was −75 kr (now "omsætningen står på 0 kr"); FS2 sent a new customer to post purchases, which put no revenue on the page (now: sell by invoice, or post the sale to a sales account in a kassekladde); Q2 step 3 read awkwardly.

### 20.6 Tests

- `node --test tests/includes/ui/*.test.mjs`, one file at a time with a cap: 65/65 (saldiAssistNavigate 5, saldiHelpContent 22, saldiHelpMerged 22, saldiHelpState 16).
  - `saldiHelpContent.test.mjs`: a fake front page (`fsPage()`) for every state and flag, cards switched off, every block off, no Nøgletal, the roles card; the order, Om siden and the questions per state; every Vis mig target, Rediger oversigt open and closed, and the field keys. The generic checks walk the new page too (keys, dead rows, text rules, bounds).
  - `saldiHelpState.test.mjs`: the HelpState of a new company (FS2, FS1, FS7; Vis mig only on the key figures), an administrator with the roles card in English, and the hidden overview in Norwegian.
  - `saldiHelpMerged.test.mjs`: the shell with Oversigt in the frame (`shell({ frame })`): hidden, shown again with `saldi:dash-hidden` (the state follows), and the message from anything but the frame ignored. Without the change in `saldi-help.js` this test fails.
- Guardrails `file-history, file-version, js-syntax, tekster, test-discovery, whitespace`: ok.
- Live on ssl12 (`forside_probe.py`, the fresh test tenant `test_838` as a test user, the branch's help files and texts served in place of the server's, the Guide blocked so the panel shows): 17/17.
  - The new company is `tom` (flags `ingenOmsaetning`, `ingenUbetalte`, `moms`); the panel shows Om siden and FS2, FS1, FS7; the panel's Vis mig rings Nøgletal.
  - Every Vis mig whose element is on the page rings exactly that element (FS1, FS4, Q1, Q7; FS5, FS6 and Q2 with Rediger oversigt open).
  - F1 in **Fra kontonr.** shows its field help.
  - Skjul oversigt in the user menu: `skjult` in place, FS10 first, its Vis mig rings **Vis oversigt**; **Vis oversigt**: back to `tom` without a reload.
  - English and Norwegian; no console error from the help.

### 20.7 Not checked live, open

- A company with figures, unpaid and overdue invoices, orders and POS: not run live; covered on the fake page only.
- `begraenset` and the roles card need another role or a pending review; covered on the fake page only.
- The chat (merged mode): the HelpState is tested, not run against the deployed chat (as §19.7).
- A field card from Rediger oversigt stays in the panel after the drawer closes, as for any field that is no longer visible (shell behaviour, §1.6); its Vis mig is not offered then.

### 20.8 Product defects found on the way (not fixed on this branch)

1. **Revenue leaves out the boundary accounts.** The daily revenue behind the week, month and year figures and both charts uses `kontonr > kontomin and kontonr < kontomaks` (`dashData.php:40`); the VAT sum uses `>=` and `<=` (`:65`). Setting **Fra kontonr.** to the first sales account leaves that account out. Fix: inclusive bounds, as the labels promise.
2. **The default revenue range counts costs.** With no setting the range is 0 to 2000 (`dashboard.php:187-188`); in the standard chart that takes in Varekøb, Ydelseskøb and the sales costs (1610-1990, `importfiler/kontoplan.txt`), so a new company's revenue goes negative after its first purchases. Fix: a default from the chart (the Nettoomsætning range) for new companies.
3. **Omsætning denne uge compares the week to date with the whole same week last year** (`dashData.php:155`), while the month and the year compare the same days. Early in the week the note reads "mindre end samme uge sidste år".
4. **The VAT figure** sums the VAT report's range, which is wrong for new companies (§16.6 item 1); the live new company shows −75 kr.
5. **"Intet aktivt regnskabsår" never shows** (`dashboard.php:235-243` tests `is_null($regnaar)`; online.php leaves a string), and its text 2575 names the old path System » Indstillinger.
6. With quick invoicing (`grupper` DIV 3 `box4`), Ufakturerede ordrer counts `status <= 3` (`dashData.php:300-302`), which takes in offers (status 0) and status 3, which the order list shows as invoices (`debitor/ordreliste.php:548-552`).
7. Texts: 5604 and 6995 are the same Danish **Vis oversigt** but English "Show dashboard" and "Show overview".

### 20.9 Independent check (8 Nov 2026)

Every claim and every bold name of the page help was held against `index/dashboard.php`, `index/dashboardIncludes/*`, `javascript/dashboard.js`, `index/mainIncludes/topbar.php`, the sidebar in `index/main.php` and `importfiler/tekster.csv` (the English and Norwegian bold names use the same ids; **Opret rykker** is hard-coded Danish). Read as a new customer, a bookkeeper and a restricted user. Fixed:

- **A company in the first days of a new fiscal year read as new.** `tom` looked at the year's figure first, so 0 kr this year with last year's months in the charts gave Om siden "graferne viser Ingen omsætning endnu" and FS2 "Få dine første tal". The charts now decide first (`fsNoRevenue()`): they are drawn when this or last year has revenue (`dashboard.php:280`).
- **FS1 for users without key figures.** A role without Nøgletal, or a user who switched every figure off, got "Se, hvad nøgletallene viser" in the top three. New flag `noegletal`; FS1 is ordered only with it.
- **Hidden: FS5 could not be followed.** The user menu has no **Rediger oversigt** when the page loaded hidden (`topbar.php:488-490`), checked live after a reload. The hidden top three is now FS10, FS7, FS9.
- **FS10 step 2** named **Vis oversigt** in the user menu, but after hiding in place the item still reads **Skjul oversigt** (defect 8 below). The step now says the choice is saved for the user and stays until switched on again.
- **FS9 step 2** listed **Rediger oversigt** and **Skjul oversigt** for everyone; a role without blocks has neither (`main.php:193-198`, `$dashBlocks`), a page loaded hidden has no Rediger oversigt. It now names **Personlige indstillinger** and **Regnskabsår** and says the overview can be shown, hidden or edited there when the user has blocks.
- **FS4 step 1** said Ufakturerede ordrer counts the sales orders not invoiced yet; without quick invoicing it counts the delivered ones only (`status = 2`, `dashData.php:300-302`), as the drawer's own text 7097 says. Now "dine leverede salgsordrer".

Checks: the UI tests 65/65 (5, 22, 22, 16; new cases for last year's revenue, `noegletal`, a role without Nøgletal and the hidden order); live `forsidechk_probe.py` 17/17 (every flag against the live DOM, the bold names on the page, in Rediger oversigt, the user menu, the bell, the sidebar, the search, Personlige indstillinger and the settings hub, hidden in place and after a reload, Vis mig on **Vis oversigt**) and `forside_probe.py` again 17/17. Only the test user's own `hide_dash` was written, and it is back to shown.

Further product defects (continuing §20.8):

8. **The user menu keeps "Skjul oversigt" after the overview is hidden in place.** `topbarDashIcon()` swaps the label only from `data-show`/`data-hide` (`index/main.php:931-935`), which the span does not carry (`topbar.php:487`); only the icon changes. Clicking it still shows the overview again.
9. **Rediger oversigt is missing from the user menu when the shell loaded with the overview hidden**, also after **Vis oversigt** on the page, until the whole shell reloads (`topbar.php:488-490` prints it only when not hidden at load).

## 21. Vis mig audit (8 Nov 2026)

The owner tested the help and found that Vis mig sometimes points at the wrong thing: on Kassekladde, "Kontrollér kladden, før du bogfører" (KK31) rang the clip of a line. Round 2 (§17) renumbered and rewrote tasks, so text and target could have drifted apart elsewhere too. This round checked every target and fixed the ones that did not ring what their task is about.

### 21.1 Method

- Every Vis mig target of every page object (`javascript/saldi-help-finans.js`; `saldi-help-content.js` only holds the helpers): 258 task targets (problem tasks are tasks) and 73 prepared questions, 331 in all, on 27 page objects. For each: the Danish title, why and steps, the show label, the target code and the page's PHP markup it selects.
- The 150 field shows are built by the shell from the field key (`fieldEl()`), so they ring the field the label names by construction; the labels were read against the field names.
- The rule: Vis mig rings the element the task is about. 1) The element the title names. 2) Else the control the titled action is done with. 3) Else the control of the task's first step on this page. Never a control of a side step or a precondition, never a control no step names.
- The search of Saldi Assist offers every task and question of the page in every state (`saldi-help-state.js`, `build()`), so a target must also ring nothing wrong in the states that do not list it.
- Live, `vismig_probe.py` serves the branch's help files to a test folder on ssl12 (scripts, css, `helpTexts.php` from `tekster_keyed.tsv`, chrome text 8795 in `main.php`'s `data-txt`), builds the states in test_838 as a test user (a kladde with a balanced line, a one-sided line, a line without accounts, a refused date, a pool bilag, the posting overview, the posted kladde, the copy dialog, the reversal pick, Udlign, the pool, a line's documents, the kladde's documents, and every report) and resolves every target in every state, recording what it rings (tag, name or id, value, text, title, the label, the column header, the cell or text beside it). A fixed target must ring what it was fixed to; any other must carry its label on the element or beside it, or be in a list checked by hand against the markup (icons, fields without their own label, lists).

### 21.2 Fixed targets

| Page | Task | Was | Now |
|---|---|---|---|
| Kassekladde | KK31 Kontrollér kladden, før du bogfører | the clip (else the kladde's clip by Bemærkning), label Clips | **Bogfør** (`doPost`), label Bogfør, with a note: check lines, bilag and dates first; Bogfør shows the overview before anything is posted |
| Kassekladde | KK17 Bogfør et køb fra udlandet | the Moms code by Debet | Debet (step 1). Q11 (how VAT is computed) keeps the Moms field |
| Kassekladde | KK32 Bogfør et salg uden faktura, Q18 kreditnota | Kredit | Debet (step 1 of both) |
| Kassekladde | KK23 Ret bemærkningen på en bogført kladde | Opdatér (step 2) | the Bemærkning field (title, step 1) |
| Kassekladde | KK13, Q3b Ret en bogført postering | on the reversal pick page: a kladde number, whose click writes the reversal | only the undo arrow (`a[href*="ompost="]:not([href*="ompost_til="])`) |
| Kladdeliste | Q4 Hvad er en kassekladde? | Ny | no Vis mig (the answer explains the model) |
| Kladdeliste | Q6 Skal jeg have én kladde eller flere? | the list's search field under Bemærkning | Ny (step 1) |
| Kontokort | Q1 Hvorfor kan jeg ikke ændre kontonummeret? | Lukket (step 3) | Ny in the card's head (step 1), label Ny |
| Udlign | AU1 Find den åbne post til linjen, AU2 Vælg fakturaen | Afregn | the list of open items, label Åbne poster |
| Rapporter, Momsafstemning | MS5 Kontrollér periode og momskoder | Tilbage, which no step names | the period block at the top, label Periode |
| Rapporter, Emballage | EM1 Vælg periode, land og visning | Visning (step 2) | Fra dato (step 1) |
| Kladdens dokumenter | Q2 Hvorfor kan jeg ikke slette dokumentet? | another document's Slet | the upload field (step 1) |
| Kassebilag | KB10, Q3 bilag by mail | the whole upload header (also Vælg bilag fra enhed) | the mail address under Bilag kan sendes til |
| Rapporter, every report | MR2, MT1, KM1, OS2 (guard also on KU2, MS1, MS5, MR3, EM1) | generic selectors on the one page object for all reports: MR2 rang the Regnskab table as Nettobeloeb, KM1 the Kontokort head as Moms, MT1 and OS2 other reports' tables | `rpOnly(states, target)`: the element only on its own report |

Kept after a check, with the reason in this round's audit (outside the repository): KK1/Q14 Gem, KK9 Bilagstekst, KK18 Bilag, KK19/Q8 x, KK27/Q15 Importér, KK3/Q1 the balance status, BU1 OK, RP2 Konto (fra), KS7 OK, BF1 Kontrolsum, KB4 Gem og næste, AU8 Tilbage til kladden, MA2 Tilbage, KO3 the first posting line, Kontoplan Q1 the Type search field, SAFT5 Vis XML-fil, Kassebilag Q4 the search field, Løn Q1 Flyt.

Texts (`importfiler/tekster_keyed.tsv`): `help.kk.task.kk31.show` Clips becomes Bogfør/Post/Bokfør, `help.au.task.au1.show` Afregn becomes Åbne poster/Open items/Åpne poster; new `help.kk.task.kk31.note`, `help.kt.q.q1.show` (Ny/New/Ny), `help.rp.task.ms5.show` (Periode in all three, as the report prints it), `help.rp.task.em1.show` (Fra dato/From date/Fra dato, as the report prints it); `help.kk.task.kk23.show` is gone.

### 21.3 The callout's button: Ok

The Vis mig callout's button said Forstået (5148, a text Saldi uses elsewhere, e.g. the kassekladde's gear hint). The owner wants Ok. New chrome text 8795 Ok/OK/OK (`importfiler/tekster.csv`), read in `topbar_help_host()` and listed in `saldi_help_chrome_ids()` like 8793 and 8794; `saldi-help.js` sets the callout's button from 8795. 5148 stays in the host's list, so a cached older `saldi-help.js` still finds its text during the rollout; the tour and the other Saldi places are unchanged. `index/main.php` links `saldi-help-finans.js?v=7` and `saldi-help.js?v=8`.

### 21.4 Live result and tests

- Live on ssl12, test_838 as a test user: 46 page states, 605 resolved targets (221 distinct page/task pairs, every fixed target but EM1 among them), 605 ring what their task is about, 0 mismatches (the reports ran once more, read only, after MS5's period match was fixed for the firm name and the period that a `<br>` joins). Before the fix the same probe found KK31 on the clip, KK13/Q3b on a kladde number of the reversal pick, and MR2, MT1, KM1, OS2 on other reports' tables. Vis mig of KK31 through the panel: the ring on Bogfør, the callout "Vis mig · Bogfør", the note, and the button Ok. Emballage is not offered in test_838, so EM1 is covered by the unit test only.
- `tests/includes/ui/saldiHelpContent.test.mjs`: the fixed targets on the fake DOM (KK31, KK17, KK32, Q18, Q11, KK23, KK13 on the reversal pick, Kladdeliste Q4/Q6, Kontokort Q1, MS5, EM1, AU1/AU2, Kladdens dokumenter Q2, KB10/Q3), and the report targets ring nothing on another report. `tests/includes/ui/saldiHelpMerged.test.mjs`: the callout's button says Ok, after a Vis mig from the chat and after one from the panel's own row without the merge.

## 22. The Debitor and Kreditor modules (8 Nov 2026)

The owner asked for the treatment of the finance module (§16, §17) in Debitor (customers, quotes, orders, invoices, credit notes, reminders, customer payments, sales reports) and Kreditor (suppliers, purchase orders, supplier invoices, receiving, supplier payments, purchase reports): help tailored to every page and to the state the page is in, read once as a business owner who does their own books and once as a trained bookkeeper; a `problem()` task ("Se her først") where something on the page stops the work.

### 22.1 Files

- `javascript/saldi-help-debitor.js` (23 routes) and `javascript/saldi-help-kreditor.js` (10 routes), the module files of §16.1. Each is one IIFE in sections, one per author; every top-level helper name carries its section's prefix (`d1`, `d2`, `d3`, `k1`, `k2`), so the sections share a scope without clashes.
- `importfiler/tekster_keyed.tsv`: 2 734 new `help.*` rows in Danish, English and Norwegian (5 194 in all), groups `dol dord deb dkort dhist djob dfus drap dryk dbet dgen dstat kol kord kubl kred kkort krap kbet kfus kstat`. Bold names are the on-screen names from `importfiler/tekster.csv` in each language; a name the page hard-codes in Danish stays Danish in all three columns.
- `tests/includes/ui/saldiHelpContent.test.mjs`: 22 new blocks (`D1 …` to `K2 …`) on fake pages: every state and flag, the orders, `problem()`, the field keys, and that targets ring nothing in states that do not list their task (§19.3).
- `index/main.php`: both module files `?v=2`; `?v=3` after the two reviews (22.4, 22.8) and `?v=4` after the live check (22.9).

### 22.2 Pages and groups

| Route | Group | States | Tasks | Questions | Fields | `problem()` |
|---|---|---|---|---|---|---|
| `debitor/ordreliste.php` | `dol` | tilbud, ordrer, faktura, bs, opsaetning | 20 | 3 | 9 | – |
| `debitor/ordre.php` | `dord` | ny, kundeopslag, tilbud, ordre, kreditnota, faktura, kreditnotaFaktureret | 23 | 5 | 30 | OR20 credit limit exceeded |
| `debitor/debitor.php`, `debitor_historik.php`, `debitor_kommission.php` (one page object) | `deb` | liste, tom, historik, kommission, kolonner, filtre | 15 | 4 | 11 | – |
| `debitor/debitorkort.php` | `dkort` | ny, kort | 19 | 4 | 23 | DK13 EAN without a valid CVR number |
| `debitor/historikkort.php` / `historik.php` | `dhist` | tom, liste, ret / liste, tom | 7 / 2 | 2 / 1 | 6 / 0 | – |
| `debitor/jobliste.php` / `jobkort.php` | `djob` | liste, tom / kort, vaelgKunde | 3 / 5 | 2 / 1 | 3 / 2 | – |
| `debitor/kontofusion.php` | `dfus` | bekraeft | 2 | 1 | 0 | – |
| `debitor/rapport.php` (form, Åbne poster, Kontokort, Kontosaldo, one customer's kontokort) | `drap` | forside, openpost, kontokort, kontosaldo, kontodetalje | 40 | 6 | 9 | OPP pop-up blocked |
| `debitor/rykker.php` | `dryk` | aaben, bogfoert | 9 | 3 | 9 | RK8 Send pr. mail without a usable address |
| `debitor/betalinger.php` / `betalingsliste.php` / `pbs_import.php` | `dbet` | tom, aaben, lukket / liste, tom, slet / upload, resultat | 9 / 6 / 4 | 2 / 1 / 1 | 13 / 0 / 1 | BT2 red fields; PI3 PBS number missing |
| `debitor/genfakturer.php` / `ret_genfakt.php` / `massefakt.php` | `dgen` | valg / ret, fejl / koerer, faerdig | 6 / 8 / 2 | 1 / 2 / 1 | 6 / 13 / 0 | RG7 a check failed (Fakturer hidden) |
| `debitor/top100.php` / `salg_postnr.php` / `kassespor.php` / `includes/salgsstat.php` | `dstat` | liste, periode / liste / liste, retBetaling / liste, soeg | 3 / 5 / 6 / 4 | 1 each | 1 / 4 / 13 / 7 | – |
| `kreditor/ordreliste.php` | `kol` | forslag, ordrer, faktura, opsaetning | 14 | 3 | 15 | – |
| `kreditor/ordre.php` | `kord` | ny, forslag, ordre, klar, kreditnota, bogfoert, bogfoertKreditnota, opdel, opslag, kontaktopslag | 28 | 5 | 33 | KO23 date, KO24 batch, KO10 invoice number |
| `kreditor/ublimport.php` | `kubl` | upload, opretLeverandoer, opretVare, klar, fejl | 6 | 2 | 5 | KU6 upload failed |
| `kreditor/kreditor.php` | `kred` | tom, liste, filtre, kolonner | 12 | 3 | 9 | – |
| `kreditor/kreditorkort.php` | `kkort` | ny, kort | 15 | 3 | 25 | KO5 unconfirmed bank details |
| `kreditor/rapport.php` (form, Åbne poster, Kontosaldo, Kontokort, the card's Kontokort, the account card from Åbne poster) | `krap` | forside, aabnePoster, kontosaldo, kontokort, kontokortLev, kontoudtog | 29 | 4 | 6 | – |
| `kreditor/betalingsliste.php` / `betalinger.php` | `kbet` | tom, liste, sletBekraeft / tom, aaben, fejl, sendt, fil, kladdevalg | 6 / 11 | 1 / 3 | 0 / 13 | BT4 red fields, BT6 unconfirmed bank details |
| `kreditor/kontofusion.php` | `kfus` | bekraeft | 2 | 1 | 0 | – |
| `kreditor/top100.php` | `kstat` | liste, tom, periode | 4 | 1 | 1 | – |

31 page objects on 33 routes: 324 tasks, 69 questions, 267 fields. `dlev`, `dkom`, `kmod` and `dubl` have no pages (below). `debitor.php?valg=historik|kommission` includes the two other files (`debitor.php:101-108`), so one page object serves the three routes and tells the views apart by the grid id.

A new tenant has quick invoicing and immediate posting on (`includes/opretfunc.php:718`, DIV 3 box4 and box5): no Tilbud tab, no Godkend or Levér, and Fakturér posts at once. The Debitor texts are written for that path first and name the other one ("Uden hurtigfakturering …") where it differs. Kreditor has no such switch for receiving; `alleModtaget` covers the order that still shows **Modtag** after the last receipt until the next **Gem** (`kreditor/ordre.php:822, 902`).

Not covered, and why:

| File | Why |
|---|---|
| `debitor/levering.php` | An action: prints `__line__`, delivers and redirects (`levering.php:30`). |
| `debitor/fakturadato.php` | Opens only when `bogfor()` finds no invoice date, and every caller sets one first (`ordre.php:3268`, `massefakt.php:369`, `genfakturer.php:209`). No `dlev` rows. |
| `debitor/ny_rykker.php`, `afslut_rykker.php` | Pop-ups that act and close (`rapport.php:169-205`); explained on Åbne poster (OP2, OPP). |
| `debitor/inkasso.php`, `rykkertjek.php` | Unfinished and only redirects; a switched-off login include (`index/login.php:980`). |
| `debitor/betalinger_settings.php`, `mailTxt.php` | Redirects to Indstillinger. |
| `debitor/pbsliste.php` | Printed inside the order list's BS tab (`ordLstIncludes/topLine.php:107`), so it is the `dol` state `bs`. |
| `debitor/salgsstat.php` | Linked from nowhere; **Salgsstat** opens `includes/salgsstat.php` (`rapport.php:479-492`), which is covered. |
| `debitor/easyUBL.php`, `peppol.php` | A webhook; a new tab from `debitor/bogfor.php:97-120`. Group `dubl` stays empty. |
| `debitor/debitorvisning.php`, `grpvisning.php`, `kreditor/kreditorvisning.php` | Only linked from the old top-menu layout or the old history list. |
| `debitor/ansatte.php`, `kreditor/ansatte.php` | The contact-person card (not payroll); worth a small page object in a later round. |
| `kreditor/modtag.php`, `kreditor/bogfor.php`, `debitor/bogfor.php` | Actions that return to the order; their refusals are explained on the order (KO9, KO23, KO24, `kord` Q2, Q5). Group `kmod` stays empty. |
| `includes/udlign_openpost.php`, `includes/ret_valutadiff.php` | Shared with both report pages, reached by a click the report tasks explain (KO1, KK1, KK6); not assigned. |
| `kreditor/kreditorFromCvr.php`, print, mail, label, CSV and lookup endpoints | No screen in the frame. |

### 22.3 States and the first task per main page

| Page, state | Se her først | Top three |
|---|---|---|
| Ordreliste (salg), Ordrer, quick invoicing | – | OL4 Åbn og fakturér en ordre, OL1 Opret en ny ordre, OL3 Find en ordre eller faktura |
| Ordreliste, Fakturaer with a red (unsettled) sum | – | OL6 Se, hvilke fakturaer der ikke er betalt, OL3, OL5 |
| Kundeordre, new | – | OR1 Vælg kunden til ordren, OR2 Opret en ny kunde fra ordren |
| Kundeordre, order with lines (quick invoicing) | OR20 when the credit limit is exceeded | OR6 Fakturér ordren, OR4, OR11; OR9 Levér without quick invoicing; OR17 with VAT rate 0; OR18 for an EAN customer |
| Kundeordre, invoice / credit note | – | OR19, OR12 Kreditér fakturaen, OR14 (questions Q1 Hvordan retter jeg en faktura?, Q5 Hvordan registrerer jeg, at kunden har betalt?, Q2) / OR13 Gør kreditnotaen færdig, OR5, OR23 |
| Debitorer, list / empty | – | DB1 Find en kunde, DB3, DB2 / DB2 Opret en ny kunde, DB8 Importér kunder fra en fil |
| Debitorkort, new business customer / saved card | DK13 (EAN, no valid CVR) | DK1 Hent navn og adresse fra CVR-registeret, DK2, DK4 / DK17, DK8, DK19 Lav en ordre eller faktura til kunden (not for a closed customer); DK14 first when the card was opened from an order |
| Debitorrapporter, form | OPP when the browser blocked the pop-up | RF1 Se, hvem der skylder dig penge, RF2, RF3 Afstem kunderne med regnskabet |
| Åbne poster (debitorer), overdue / open reminders | OPP | OP2 Send rykkere, OP1, OP5 / RY1, RY2 Bogfør rykkerne |
| Rykkerbrev, open / posted | RK8 Ret modtagerens e-mailadresse, when Send pr. mail has no usable address | RK1 Send rykkeren med mail, RK2, RK3 / RK9 Følg op på en bogført rykker (RK6 Inkasso first when it shows) |
| Leverandørordrer, Ordrer / empty | – | KOL7 Modtag varer på en ordre, KOL2 Bogfør en regning på varer, KOL1 / KOL1, KOL2, KOL3 |
| Leverandørordre, approved / all received / ready | KO23 date before Ordredato, KO24 batch fields, KO10 no Fakturanr. | KO9 Modtag varerne, KO10, KO13 / KO12 Gør ordren klar til bogføring / KO14 Bogfør fakturaen |
| Kreditorkort, new / saved | KO5 unconfirmed bank details | KO1, KO2, KO3 / KO6 Se leverandørens kontokort, KO3, KO4; KO12 first when opened from a purchase order |
| Kreditorrapporter, form / Åbne poster | – | RP1 Se, hvad du skylder dine leverandører, RP3, RP2 / AP1 Find de forfaldne regninger, AP2, AP4 (AP3 first when a supplier nets to 0) |
| Betalinger til bank (kreditor), red lines | BT4, or BT6 for unconfirmed bank details | BT2, BT3, BT7 Bogfør betalingerne, når banken har betalt |

The full digest (Om siden and the top three for every state and flag) is in the integration notes outside the repository.

### 22.4 How the content was made, and the quality bar

1. Five authors (D1 sales flow, D2 customer accounts, D3 money and reports, K1 purchase flow, K2 supplier accounts, money and reports) wrote the page objects from the code, with a file:line evidence comment for every state, flag, step and target, and checked them on fake pages built from the pages' own markup.
2. The quality bar of §16.3 and the finance brief: think as two users (a "Normal bruger" and a "Bogholder" line per task in the notes), outcome-first titles, a why line from the detected state, bold only for exact on-screen names, steps true in the code, blockers first, the tone rule of §16.3, Vis mig by the rule of §21 (the element the task is about, never a side step), and every target guarded so it rings nothing in the states that do not list its task (§19.3). Detectors and targets only read the DOM. Many classic forms are printed inside a `<table>`, so the parser closes the `<form>` at once; no detector relies on `form … input` there.
3. Integration (this section): the five parts checked together (names, routes, groups, keys, the content and state tests), then one Danish term per concept across both modules, in Saldi's own words:
   - **debitorkort** and **kreditorkort** (tekster 356, 1184, the page titles), not kundekort or leverandørkort; **flueben**, not hak; **fx**, not for eksempel;
   - a supplier's invoice is a **regning** (as the finance help, KK33), and **købsfaktura** (580) is a purchase order once posted (the **Faktura** tab); **Kreditér** (2014) and **Opdatér** (898) as the buttons write them; recurring invoicing is an **abonnement** (1437, the page Ret abonnementsordrer);
   - **udlign … mod** (as the finance help); a payment file is **lavet**, not dannet;
   - the same task on a Debitor and a Kreditor page has the same title: the reports (Afstem … med regnskabet, Afstem saldoen med samlekontoen, Afgræns rapporten …, Se … og betalingerne bag et beløb, Udlign … hvor posterne går i nul, Find en bestemt … i listen, Ophæv en udligning, Udskriv et kontoudtog, Send kontoudtoget til …, Åbn kassekladden bag en postering), the lists and cards (Vælg kolonnerne i listen, Vælg, hvilke … listen viser, Importér … fra en fil, Eksportér … til et regneark, Kan jeg slette …?, Vælg …s betalingsbetingelse, Luk en …, du ikke handler med mere, Brug en lukket … igen, Læg to kort for samme … sammen), the merge pages (Fusioner debitorer/kreditorer, Læg kortene sammen, Fortryd sammenlægningen), the payment lists (Se andre brugeres lister, Lav betalingsfilen til banken, Hvorfor er nogle felter røde?) and the order pages (Vælg leverandøren til ordren, Opret en ny leverandør fra ordren, Tilføj en vare til ordren);
   - the grid menu (`.dropdown-trigger`, `includes/grid.php:1420`, `grid_order.php:1570`) is "pilen yderst til højre i rækken med søgefelter" with the Vis mig label **Pilen ved søgefelterne** on every list;
   - settings paths as the hub has them (`includes/settings/hub.php`): Brug betalingslister under Finans » Bogføring og bank » Kassekladde og betalinger, Rykkere og inkasso under Salg » Debitorer, Stamdata under Virksomhed » Firma, the reminder letter under Virksomhed » Fakturaformularer og e-mail » Formularer;
   - the customer payment step on the order list names the sides (bank in **Debet**, the customer in **Kredit** with D in **D/K**), as the supplier side does.
4. The digest was read once as a new customer and once as a bookkeeper after these changes.
5. Independent review of the Kreditor pages (the ten `k*` groups), page by page against the PHP and `tekster.csv`:
   - Vis mig on the purchase order (`kord`) rang controls that mean something else in other states, which the chat's search reaches (§19.3): the head link is the credited invoice on a credit note but the credit note on a credited invoice (KO17, KO22), Bogfør posts an invoice or a credit note (KO14, KO16; Q5 serves both), Udskriv/Send of an open order and Print of a closed invoice share `name="print"` (KO6, KO20), Opslag finds suppliers only on a new order (KO2), Gem is KO12 only on an order waiting for its invoice, and a closed order keeps `posn1` as a hidden field (KO8). They now go through `k1only(states, target)`. On the Kreditorkort, KO12 and KO13 ring Tilbage only when it leads back to the purchase order or the cash journal (flags `fraOrdre`, `fraKladde`).
   - Texts: Serienr. is a button that opens the serial numbers, not a field (KO9, `kord` Q2; `openOrderLines.php:278-281`); a supplier's freight or fee needs an item, since a line without Varenr. gets no price (KO4; `ordre.php:725`); the supplier payment in the cash journal names both sides, the supplier in Debet with K in D/K and the bank in Kredit, as KK33 (AP4, BT7, so `krap` Q2 and `kbet` QB1); a new Kreditorkort stands on ERH351, FI-kort 71 (`kreditorkort.php:494-514`), so KO4 and the ERH kode field say so, and BT4 says what to do with a red ERH351 line; Fra konto on Betalinger til bank comes from Stamdata and shows zeros when the company has no bank account; the card opened from a purchase order explains that Tilbage, after Gem/opdatér, opens the order with the new supplier (`kreditorkort.php:221-222`, `ordre.php:214-226`).
   - Checked and kept: the supplier filter on the order list (`about.leverandoer`; topLine.php resets `$konto_id` from the URL, so a menu entry is not filtered by the saved supplier, and Ryd søgning clears the grid's own stored search), the Modtag and Bogfør refusals (`modtag.php:103-181`, `kreditor/bogfor.php:122-147, 217`), the settings paths (`includes/settings/hub.php`, `settingsDefinitions.php:839, 901`), Hent ordrer for status-3 invoices (KO21), the sign of the open items (`showOpenPosts.php:419-431`), Udlign/Opdater (`udlign_openpost.php:567-572`), the payment list's checks (`payListFunc.php:29-80`) and the Top 100 query.

### 22.5 Tests

- `node --test tests/includes/ui/*.test.mjs`, one file at a time with a cap: 89/89 (saldiAssistNavigate 5, saldiHelpContent 45, saldiHelpMerged 23, saldiHelpState 16). The generic checks walk every new page's states and flags (keys, dead rows, text rules, bounds, `problem()` names an existing task).
- Guardrails `file-history, file-version, js-syntax, tekster, test-discovery, whitespace`: ok.
- Run live on ssl12 on 8 Nov 2026 (22.9): 815 of 815 ringing Vis mig targets match their task, no console errors from the help.

### 22.6 Live checks still to run (ssl12, read only)

The list as written before the live check; 22.9 says which were run and what stays open.

In test_838 (quick invoicing, a new tenant):

- Debitor » Ordre: the tabs, an empty Fakturaer tab (`tom`, OL1, OL3, OL10), a red unsettled sum (`ikkeUdlignet`, OL6 rings it), Redigér » Kolonner (`opsaetning`, OL20 rings Gem).
- Ny: `ny`, OR1 rings the editable Kontonr.; Enter in an empty Kontonr. gives `kundeopslag`; an unknown number shows **Opret ny kunde** (`opret` via the computed display) and OR2 first; an order with lines: OR6, OR4, OR11; the invoice: OR19 and OR12 ring Udskriv/Send and Kredit; Kredit → `kreditnota`, Kreditér → `kreditnotaFaktureret`, OR22 rings the title link.
- Debitorer: DB3 rings the first row, DB4/DB5/DB6 the arrow, DB2 Ny; Kolonner and Filtre are detected; Historik (`debitor_historik.php`); Kommission with Mit salg on (a customer without e-mail sets `udenMail`, DB11 rings the first enabled Inviter box).
- Debitorkort: the footer buttons appear up to 4 s after load (`debitorkort.php:3690-3700`), so DK8/DK9 resolve after that; DK7 rings Ny under Kontaktpersoner; opened from an order (existing and new customer): DK14/DK15 first; an EAN customer without CVR: DK13.
- Debitor » Rapporter: the reports render in the frame itself only when the browser sends `Sec-Fetch-Dest: iframe` (`rapport.php:614-615`), else the report sits in `#rapportAsyncFrame` and the help sees `unknown`; Åbne poster with overdue amounts (the red-span detector, OP2 rings Opret rykker), each view of the select, Opret rykker with pop-ups allowed and blocked (OPP), the reminder buttons found by their confirm text, Kontokort (KO3, KO5, KO6), Kontosaldo (KS1 rings I alt), a Kontonr. (`kontodetalje`).
- Rykkerbrev: Udskriv and Slet valgte found by position among the buttons; Send after Send pr. mail + Opdatér; a posted letter.
- Betalinger til bank (debitor), Betalingslister, Genfakturer/Ret abonnementsordrer (a Fakturadato outside the year gives `fejl`), Top 100, Salg pr. postnummer (`#dato_range_picker`, `#postnr_range_input` exist only after the page's JavaScript), Salgsstat on `includes/salgsstat.php`.
- Kreditorer » Ordre: an empty Ordrer tab (KOL1, KOL2, KOL3); a purchase order through Godkend → Modtag of every line: does it still show **Modtag** until **Gem** (`alleModtaget`, KO12)? A partial receipt (`delvis`, KO11 second); Kreditér a posted invoice: the credit note keeps the old Forventet lev. dato (KO23 first); `klar` without Fakturanr. (KO10); a batch item (KO24); the supplier lookup overlay (`getComputedStyle`), Enter in a head field saves.
- Kreditorer, Kreditorkort (new, saved, from a purchase order; `td[colspan=2] > i` must not match a normal card; a new card saved without touching ERH kode stores ERH351, and Tilbage after Gem/opdatér opens a new order with the supplier), Kreditorrapporter in each Åbne poster mode (read from the select's option URLs), the card's Kontokort (KK4 E-mail), Betalingslister and Betalinger til bank (red cell, Bekræft, the file link; a supplier on ERH351 without FI number gives a red Modtager konto, and Fra konto without a bank account in Stamdata is ten zeros), a purchase line with a serial-numbered item (Serienr. opens the serial window), Top 100, the merge page (do not click OK on test data).
- English and Norwegian on the order page, Åbne poster, Rykkerbrev, the Kreditor report form and card.

Need other data or another tenant: quick invoicing off (Tilbud tab, Godkend → Levér → Del ordre), a credit limit exceeded (OR20), a debtor group without VAT code (OR17), an EAN customer (OR18), a user on the old menu (no `#ny`), a PBS agreement (BS tab, `pbs_import.php`), Brug jobkort on, a POS company (Kassespor), Omgående bogføring af købsordrer off (KO21), an OIOUBL file of a known supplier and one of an unknown CVR in a tenant with one creditor group (KU3).

### 22.7 Product defects found on the way (not fixed on this branch)

For the owner; each is from the code, with file:line. Security-relevant findings are not listed in this public repository; they went to the owner directly.

Debitor, sales:
1. **Fakturér alt stops after ten orders**: `explode($rest_id)` with one argument is an error in PHP 8 (`debitor/massefakt.php:109`); the first ten are already posted.
2. Alt+L is both Tilbage (`includes/orderFuncIncludes/topLine.php:42,47`) and Levér/Modtag (`debitor/ordre.php:6419-6420`).
3. After the first Gem, a quick-invoicing order without lines shows Fakturér (`ordre.php:6401-6402`), which then refuses; Udskriv on an unsaved quick-invoicing order prints a Tilbud (`ordre.php:2784-2788`).
4. An empty tab without a search shows the red "Ingen ordrer matcher de angivne søgekriterier" (text 2730, `grid_order.php:1445-1452`); the list's Slet confirm is English with the tab's Danish name (`ordreliste.php:2775`).
5. Hard-coded Danish or English texts: Genfakturer, Send mails, Fakturér alt, CSV import, O, Handlinger (`ordreliste.php:2694-2698, 2766-2767, 1643, 1660`), Kreditér, Del ordre, Print/Send plukliste (`ordre.php:6492, 6498, 6544, 6551`); the Genfakturer label's tooltip is "Mail emne" (`ordre.php:5138-5139`); Udskriv til is two texts with different Norwegian (`ordre.php:5034` vs `:4107`).
6. Debug output: `levering.php:30`, `debitor/bogfor.php:57`; the order list tutorial step `#visning` has no target in the new top line (`ordreliste.php:582-585`).

Debitor, customers:
7. Closed customers are never shown in red in the lists: the row style reads `lukket`, which the query does not select (`debitor.php:700-705`, `debitor_historik.php:521`, `debitor_kommission.php:591`).
8. Historik: Send and Vælg alle below the list post no customer ids, so nothing happens (`debitor_historik.php:70-73, 546-553`).
9. Mit salg invitations hard-code subject and text (`debitor_kommission.php:146-153`) and ignore the texts under Indstillinger (`settingsDefinitions.php:1452-1457`), which the Mailtekst button leads to.
10. A Kundenr. with other characters than digits gets the alert, but is saved with each such character replaced by 0 (`debitorkort.php:520-531`); changing Kundetype on a card with contacts is refused with a hard-coded English alert (`:1102`); the CVR lookup tooltip on an existing card promises a lookup that only loads on a new card (`:1528, 1905, 2712-2715`).
11. The history card's button reads "Deibitorkort" (`historikkort.php:417`); `historik.php:36-37` Luk returns to the old menu inside the frame; `kontofusion.php` has the typo "debotirer" (`:52`) and leaves `kontakt_emails` and delivery addresses of the deleted card behind (`:58-68`).
12. Job cards: Slet deletes without asking (`jobkort.php:149-153`); a broken JavaScript string for a customer without job cards (`jobliste.php:258`); undefined icons in `debLstIncludes/topLineJobkort.php`.

Debitor, money and reports:
13. **Inkasso with a lawyer** requires `../phpmailer/class.phpmailer.php`, which is not in the repository (`debitor/inkassoprint.php:196-198`), after the case is already marked inkasso (`rykker.php:48-51, 162-167`). Verify on a server.
14. Rykkerbrev: the amount field of an open reminder is never saved (`rykker.php:155, 428`); changing the currency clears the inkasso mark (`:67, 103`).
15. Salgsstat: Adresse searches a column that does not exist (`includes/salgsstat.php:200`); Postnr., Bynavn, Varenr. and Varetekst lower-case the search text but compare case-sensitively (`:201-204`).
16. The Åbne poster view is stored for all users (`includes/rapportfunc.php:170-209`); every load of the report form writes `grupper` box10 (`frontPageOldMenu.php:32`) and opens a 1×1 pop-up to test the blocker with a Danish-only alert (`debitor/rapport.php:304-329`); Genfakturer prints complete billing in a pop-up that blockers stop (`genfakturer.php:150`).
17. Kassespor: Ret kasse does not save (the select is `newcashNo`, the code reads `newCashNo`, `kassespor.php:243-258`); Betalinger til bank prints a literal `$2_bg` attribute (`debitor/betalinger.php:521`) and the closed-list link has a stray quote (`betalingsliste.php:193`).

Kreditor, purchasing:
18. **The OIOUBL import cannot create a supplier or an item in a new tenant**: with one creditor group (`importfiler/grupper.txt:8`) the group select is not printed (`kreditor/ublimport.php:233`), so Opret posts no group and the form returns (`:107, 167`); the same for items with one item group (`:321`). The help sends the user to the card instead (KU3). Tilknyt without Salgspris alerts first, with a Salgspris it links twice (`:251-289`); there is no check for an invoice imported before (`:337-363`).
19. After choosing an item in the autocomplete, Antal shows the quantity only as a placeholder, and a Gem without typing saves Antal 0 (`openOrderLines.php:90-91`, `openOrder.php:120`, `ordre.php:790`).
20. After the last receipt the order stays status 1, so Modtag shows again and Bogfør only after the next Gem (`ordre.php:822, 902`; `modtag.php` never sets it).
21. Kreditér and Kopiér set today's Ordredato but keep the old Forventet lev. dato (`closedOrder.php`, `ordre.php:652`, `insertAccount.php:141-146`), so Returnér/Modtag is refused until the date is changed.
22. A purchase invoice closed without immediate posting (status 3) can only be posted with Hent ordrer, which is printed only when the DIV 3 settings row is missing (`finans/kassekladde.php:4281-4287`).
23. `splitOrder.php` prints a debug line (`:3`) and uses an undefined `$languageId` (`:21, 118, 127`); the paperflow tab Skan bilag only opens the Faktura tab (`topLine.php:96-106`); the saved supplier filter is never restored (`ordreliste.php:72-86` vs `topLine.php:5`).

Kreditor, suppliers and payments:
24. **A new supplier card saves ERH kode ERH351 (FI-kort 71)** when the supplier has none (`kreditorkort.php:494-515`), so its payment-list lines become FI-card lines and turn red without an FI number (`betalinger.php:357-362`).
25. **Ubetalte on a payment list adds every unsettled bill again on every choice** and checks neither this nor other lists (`kreditor/betalinger.php:302-398`, the checks commented out `:346-347`): a bill can be paid twice. The help warns (BT1, QB3).
26. Betalingsbetingelse saves the translated option text, which `forfaldsdag()` does not know (`kreditorkort.php:401-416`, `includes/forfaldsdag.php:8-22`): English and Norwegian users get wrong due dates. The due date uses the stored date only when it is empty (`betalinger.php:365-368`).
27. `includes/payListFunc.php:29-80`: the error slots are shifted one cell, and `$erh='SDCK020'` (`:45`), `$erh='SDC3'` (`:61`) are assignments, not comparisons.
28. Payment lists: Indstillinger links a file that does not exist (`kreditor/betalingsliste.php:83,91`); a stray quote (`:204`); paging in the kladde choice drops `find` (`betalinger.php:292-299`); Ny creates a list on every click and the one-hour lock never applies (`:194-204`, `betalingsliste.php:147`); the `paylist` form is never closed (`betalinger.php:173,423`).
29. `kreditor/kontofusion.php:65-76`: the merge leaves items (`vare_lev`) and `openpost.konto_nr` on the old supplier; the "no other supplier" alert also shows for a closed one (`:56-58`).
30. Kreditorkort: Slet deletes without asking (`:202-208`); Telefon's tooltip promises a lookup (`:435`); the Kontokort's Bilag link opens the kladde in a new tab, the card's Kontokort in the frame (`includes/rapportfunc.php:1745`, `generalLedger.php:381`); Top 100's period hint says mmddåå and the code reads ddmmåå (texts 1167, 1168, `top100.php` in both modules), and its tooltip is English (`kreditor/top100.php:115`).
31. Betalinger til bank: without a bank account in Stamdata, Fra konto becomes ten zeros (`kreditor/betalinger.php:209-223`), which `betalingskontrol()` accepts because it only checks an empty or non-numeric account (`includes/payListFunc.php:36-39`), so the file can go to the bank without a valid debit account; a line's Betalingstype offers only its own type and ERH356 (`betalinger.php:525-528`).

Found in the live check (22.9):
32. **New users' order lists hide the O and Handlinger columns**: a column setup migrated from `grupper` OLV box3 (`debitor/ordreliste.php:1355-1404`) has neither, and `merge_column_setup()` keeps code columns out of such a legacy setup until it is saved (`includes/orderFuncIncludes/grid_order.php:910-940`), so the mass actions (Udskriv, Genfakturer, Send mails, Slet, Ret) have no box to tick and the rows have no print icons. In the editor they stand with Pos `-` (`:1964-1995`). The help says how to show them (`skjulteKolonner`).
33. **Customer and supplier payment lists share one table**: `betalingsliste` has no module column (`debitor/betalinger.php:217`, `kreditor/betalinger.php:198`), so Debitor » Betalingslister lists the supplier lists and Kreditor the customer lists, with Slet on each (`debitor/betalingsliste.php:140`, `kreditor/betalingsliste.php:138`); a list opened from the other module runs that module's page.
34. Quick invoicing: adding the lines leaves a new order or credit note at status 0, so **Fakturér** or **Kreditér** shows only after one more **Gem** (`debitor/ordre.php:1722` is not reached when a line is added, `:2280-2445`; buttons `:6440-6493`). The help now says so (`gemFoerst`).
35. The Fakturér confirm "Fakturadato sættes til <i dag>!" is printed when the saved Fakt. dato is empty (`debitor/ordre.php:6452`), also when the user has typed a date without Gem; the typed date is the one used.
36. Salgsstat prints "Company Name:" in English on a Danish page (`includes/salgsstat.php:294`, hard-coded on purpose, see the comment).
37. Kreditmax saved empty becomes 0,00 on the Debitorkort (no limit, as before).

Texts in `importfiler/tekster.csv` met on the way: 440 Norwegian "Regnskap" for Konto, 1138 "Exit" for Afslut, 81, 221, 72/74, 671, 1145, 1164, 922 (Danish "Salgstatsstik"), 2704; 885 English "Invoice_sum", 1442, 1198, 2612, 593; 1959 "Spilt", 937 "Komme tilbake"; 133 "General ledger" for a customer's statement, 1386, 1387, 365; 502, 925/926, 531, 185, 2189, 600 "Finansiere". The help uses the on-screen names as they are.

### 22.8 Independent review: Debitor (8 Nov 2026)

A second reader went through every Debitor page object (`javascript/saldi-help-debitor.js`, groups `dol dord deb dkort dhist djob dfus drap dryk dbet dgen dstat`) against the pages' PHP and JS and `importfiler/tekster.csv`, once as a business owner who invoices and pays but is no accountant and once as a bookkeeper. A script held every **bold** name in the three languages against the ids of the Danish text: no name is wrong; the open order's **Udskriv til** is 880 and 904 (Norwegian Skriv ut til), the invoiced view's is 2104, and both are kept as printed. What changed:

- Corrected: a full credit note settles the invoice only while the invoice is unsettled and the amounts match (`includes/ordrefunc.php:2713-2722`), so OR12, OR13, Q1 and Om siden of a finished credit note now say before it is paid, and what to do with a credit note that stays open. Om siden of an open credit note said it takes the amount back from the customer; it now says it cancels all or part of an invoice, so the customer owes less or gets money back.
- Corrected: without quick invoicing a credit note shows **Kreditér** only after **Godkend**, **Gem** and **Modtag** (`debitor/ordre.php:6401-6402, 6418-6421, 6442`); OR13 said Modtag was only for returned goods. OR17 sent a customer in the wrong group to the group settings; it now says to change **Debitorgruppe** on the card and names the settings for the groups themselves. OR9 names the button as printed (**Følgeseddel** with the delivery's number, `:5717-5731`); OR20 names **Kontokort** on the card; OL8 says the Genfakturer date is the next invoice's date on the invoice; OL19 bolds **Vælg datafil** as printed; Q3 says the order must be saved with a line.
- Added: OR6 step 2 says the invoice date must lie in the active financial year and not in a closed VAT period, the two refusals of Fakturér (`includes/ordrefunc.php:1321-1322, 1575-1580`; `includes/db_query.php:680-691`). New question `dord` Q5 Hvordan registrerer jeg, at kunden har betalt? (the payment in the kassekladde with the invoice number, settled when the amount matches, several invoices with ;, `finans/bogfor.php:1172-1215`), second on an invoice instead of Q4, which OR19 covers there. New task DK19 Lav en ordre eller faktura til kunden on a saved, open customer card (**Fakturaliste** » **Ny**, `debitorkort.php:2636-2641`, `ordLstIncludes/topLine.php:113-124`), third after DK17 and DK8; its target rings only on a saved card. Rykkerbrev gets `problem()`: **Send pr. mail** with no usable address puts RK8 first in both states (`rykker.php:71-74, 453-482`).
- Kept after a check: the order lists, the customer list, the history and job pages, the merge page, the reports with Åbne poster and the reminders overview, the payment lists, PBS, recurring and mass invoicing and the statistics pages; their states, orders and targets follow §21. Not added: a warning on **Fakturér alt** about defect 1 (it would go stale with the fix).
- Counts after the review: 325 tasks and 70 questions on the 31 page objects of 22.2. Tests: the D1 ordre, D2 debitorkort and D3 rykkerbrev blocks check the payment question, DK19 and its target, and the reminder's problem card.

### 22.9 Live check on ssl12 (8 Nov 2026)

Run with `debkred_probe.py` (outside the repository): a test folder on ssl12, the fresh test tenant (test_838), one test user only. The browser served the branch's `javascript/saldi-help*.js`, `css/saldi-help.css`, the help texts (the answer of `index/helpTexts.php` built from `importfiler/tekster_keyed.tsv`) and the help script tags of `index/main.php` in place of the server's, so nothing was deployed. The Guide was blocked, so the help showed in its own panel. For every page with a page object, in every state the run could build, it recorded the state and flags, Om siden, the problem card and the top three as the panel showed them, and resolved EVERY Vis mig target of the page (tasks and questions, also those not shown) with what it rings (tag, name/id, value/text/title, label, column header, cell to the left), held against the task by §21.

Data made through the app, every typed text marked "(debkred testdata)": three customers and two suppliers without e-mail addresses (one customer with an EAN number and no CVR), six sales orders (four invoiced, one dated 1 October so it is overdue), two credit notes (one posted, one left at status 0), two reminders (one posted), five purchase orders with their receipts (two posted supplier invoices and one posted supplier credit note), a payment list in each module. Nothing was sent: the mail and e-invoice endpoints were refused in the browser, and Send, Mail kontoudtog, Send mails and Ryk alle were never clicked; the merge pages were opened and left without OK; the Åbne poster view, which is stored for all users of the tenant, was put back to the one found (Vis kun rykkere, Skjul åbne poster); Kreditmax and Momssats were put back after their checks.

Result: 30 of the 33 routes (not reached: `debitor_kommission.php` without Mit salg, `jobkort.php` without Brug jobkort, `massefakt.php` without MFAKT), 120 visits in the final runs (the empty lists in the first run), 2 427 targets resolved for the 364 tasks and questions with a Vis mig, 815 of them ringing in their state (275 distinct), every one the element its task is about; no console error from the help files.

States reached: Ordreliste (salg) ordrer, faktura, opsaetning, empty, a search with no hits, a customer's own list, an unsettled invoice, the hidden O column; Kundeordre ny, kundeopslag (with Opret ny kunde), ordre (no lines, status 0 with lines, ready, credit limit exceeded, VAT 0, EAN customer), faktura, krediteret faktura, kreditnota (status 0 and saved), kreditnotaFaktureret; Debitorer liste, tom, kolonner, filtre, historik; Debitorkort ny, ny from an order, kort, kort from an order, EAN without CVR; Historik (card and old list), Opgaveliste (empty), Fusioner debitorer; Debitorrapporter forside, Åbne poster in all five views with overdue amounts and with open and posted reminders, Kontokort, Kontosaldo, a customer's kontokort; Rykkerbrev open (with and without Send pr. mail) and posted; Betalingslister and Betalinger til bank (empty); Genfakturer, Ret abonnementsordrer; Top 100 (list, Periode), Salg pr. postnummer, Salgsstat, Kassespor, Import fra PBS. Leverandørordrer ordrer, faktura, forslag, opsaetning, empty, the supplier's own list; Leverandørordre ny, opslag (with Opret), forslag, ordre (approved, part received, all received), klar (with and without Fakturanr.), kreditnota, bogfoert, krediteret, bogfoertKreditnota, opdel; Kreditorer liste, tom, kolonner, filtre; Kreditorkort ny, kort, from a purchase order; Kreditorrapporter forside, Åbne poster in all five views, Kontosaldo, Kontokort, the card's Kontokort, a supplier's account card; Betalingslister, Betalinger til bank tom, fejl (ERH351 without FI number, BT4) and the kladde choice; Top 100 (list, Periode, empty), OIOUBL-import upload, Fusioner kreditorer.

Fixed after the live check, each with a test in `saldiHelpContent.test.mjs`:
1. Kundeordre: a new order prints id 0, so it was read as an open order (Tilføj en vare first, Om siden about Fakturér). It is `ny` now, with OR1 and OR2.
2. Kundeordre and credit note with quick invoicing: at status 0 with lines there is no **Fakturér** or **Kreditér** until the next **Gem** (defect 34). New flag `gemFoerst`: an Om siden line, OR6 first, OR6 step 3 names Gem.
3. Ordreliste: the shell rewrites the row links to `main.php#/debitor/ordre.php?…` (`includes/order-row-clickable.js.php`), so OL4, OL7 and OL16 rang nothing; they match the rewritten link now.
4. Ordreliste: the hidden O and Handlinger columns (defect 32) get the flag `skjulteKolonner` and an Om siden line that says how to show them.
5. Debitorer: in Kolonner and Filtre the grid's first row is a setting, which DB3, Q2 and Q3 rang; only list rows now.
6. Debitorkort: DK1 (the CVR lookup loads only on a new card) only on a new card; DK11/Q3 and DK12/Q4 only on a saved card; DK14 only on a saved card opened from an order, DK15 only on a new one; Q2 keeps Kundenr. on a new card.
7. Kundeordre: OR14 Kopier and OR23 Debitorkort ring nothing on a new order without a customer.
8. Åbne poster (debitorer): Vis kun rykkere without any reminder is an empty page; Om siden says so and OP8 (the view) comes first. With only posted reminders open, RY3 and RY4 lead.
9. Rykkerbrev: without an e-mail address Udskriv (RK2) comes before Send med mail; RK7 Bogfør rykkeren rings nothing on a posted reminder.
10. Leverandørordre: KO1 rang the Kontonr. of the Opret ny leverandør overlay; KO11 Opdel rang on a proposal and with everything received; KO12 Gem rang after a part receipt; `delvis` was set on a ready order. KO1 only on a new order, KO11 only after a part receipt, KO12 only with everything received or on a ready order.
11. Kreditorkort: Kontokort, Fakturaliste, Slet and the merge field (KO6, KO7, KO10, KO11) only on a saved card; a new card prints the buttons without a supplier behind them.

Confirmed live as written: the reports render in the frame itself; Åbne poster views and their flags; the Debitorkort footer within 4 s (DK8, DK9, DK19); DK13 for an EAN customer without CVR; OR20 as the problem card with the red credit-limit line; OR17 first with Momssats 0; OR18 first for an EAN customer; RK8 as the problem card when Send pr. mail is ticked without an address; the purchase order after the last receipt shows Modtag until Gem (`alleModtaget`, KO12 first, defect 20); KO10 as the problem card on a ready order and on a supplier credit note ("Fakturanummer mangler"); a new Kreditorkort stores ERH351 and its payment line is red (BT4 problem, defects 24, 31); OR22, KO17 and KO22 ring the head link of the related document; Opslag on a new purchase order (KO25, KO26); the split view (KO27); the merge pages (DF1, FU1).

Not checked live, and why: quick invoicing off (Tilbud, Godkend, Levér, Del ordre; a tenant setting), a PBS agreement (the BS tab), Brug jobkort (jobkort.php), Mit salg (Kommission), a POS setup (bons in Kassespor), the MFAKT setting (Fakturér alt, massefakt.php), Omgående bogføring af købsordrer off (KO21), the old top menu, a batch or serial-numbered item (no items may be made; KO24, Serienr.), an OIOUBL file (the import states after upload), a blocked pop-up (OPP; the test browser allows them), the credit-note date trap KO23 (it needs an invoice delivered on an earlier day than the credit note), a Genfakturer or Ret abonnementsordrer run (it would invoice), the payment file and a sent list (making the file closes the list), and English and Norwegian.

### 22.10 The CRM replaces the customer history (12 Nov 2026, ssl12)

On the CRM line `debitor/historikkort.php`, `historik.php`, `debitor_historik.php` and `debitor.php?valg=historik` only redirect to `debitor/crm.php` (`?tab=cust&drawer=<id>` from the card, `?tab=act` from the list), behind the permission `crm.access` (`includes/crmLegacyRoute.php`). The help merged in from the help branch still had page objects for those pages, which the help never reaches after the redirect, and no page for `crm.php`. Fixed on the help side only; the CRM code is unchanged.

- `debitor/historikkort.php`, `historik.php` and `debitor_historik.php` have no page objects, the Debitorer list has no `historik` state, and the group `dhist` is gone (78 rows); DB13 is gone.
- A reworded keyed text gets a new key, because `findtekst()` keeps serving the row a tenant has cached (`tools/guardrails/checks/tekster.mjs`). So DB14 is now DB18 (Historik on the list opens the CRM's **Aktivitet**, where **Ny note** picks the customer), DK9 is now DK20 (Historik on the card, which needs `crm.access`, opens the customer's drawer; the step says so), and DB3's second step is `help.deb.task.db3.step3`.
- `debitor/crm.php` has the page `dcrm`. Its states are `opfoelgning`, `sager`, `kalender`, `kunder` and `aktivitet` (the selected tab) and `kunde` (the drawer `#drawer.open`). Its flags are `kunLaes` (`#newBtn` hidden: no write access), `forsinket` (the tab's overdue count) and `soegning` (text in `#q`). It has six tasks: CR1 write a note, CR2 plan a follow-up, CR3 find a customer, CR4 tick or postpone a follow-up, CR5 book a meeting and CR6 Mine. It also has three questions (Q1 where the history went, Q2 a customer that is not shown, Q3 read-only access), seven fields keyed by the controls' ids and four keys. List controls ring only in the tab states, and the composer and drawer links ring only in `kunde`.
- `index/main.php`: `saldi-help-debitor.js?v=6`. Tests: the D2 blocks of `saldiHelpContent.test.mjs` (the list, the card and a new D2 CRM block).
- Not checked live yet: the CRM states and targets on ssl12, and English and Norwegian.

Review of the CRM help (12 Nov 2026):

- **The page went stale.** The shell caches the state per frame document and reads it again only on a load, a `change` event, `poolswitch`, `saldi:dash-hidden` or `SaldiHelp.refresh()`. `crm.js` changes the tab, opens and closes the drawer and opens its dialogs in place, without any of those, so the panel and Saldi Assist kept the state of the page load. A page object can now say `inPlace: true` (§3.2): for such a page `saldi-help.js` `bindFrame()` reads the page again after every click in the frame (capture phase, since `crm.js` stops some clicks at the button, e.g. `#newBtn`) and after Esc, Enter and N, once the page's own handler has run (`setTimeout` 0). `dcrm` is the only such page. Its Vis mig targets also read the live state (`d2CrmShow()`), so a ring never uses a stale one.
- **Dialogs.** Ny note, Ny opfølgning, Nyt møde, Book møde, Ny sag, Ny kunde and Ret open `#dlg` in `#scrim.open`, a fixed `.sk-modal` over the whole page. While one is open, the tab and drawer targets are under it, so `d2CrmShow()` only rings the dialog's own target: CR1 `#nNote` and CR2 `#nchips` (the composer of Ny note and Ny opfølgning once the customer is picked), nothing else. The flag `dialog` says a dialog is open.
- **Texts against `crm.js`.** Enter in the search opens a single hit, does nothing with several, and with none searches the customers that are not closed by name, customer number or contact name (`crm_search()`, at most 10); CRM shows only the most recently active customers when there are many, and says so. Writing needs write access to CRM (`crm_can_write()`): the card's Historik button and the drawer only need read access, so DK20, DB18 and the drawer's about text say so. Q3 no longer says the role is read-only as a fact, and its third step says why **Gem** is grey with write access (no text and no date, or a date and no one at **til** for a user without an employee). The Danish texts say note, as the CRM does (NB keeps notat). Nyt møde under Kalender picks the customer first. The owner's message offers to move follow-ups only when someone else has them, and can undo. The Sager card shows the open cases' total. A next-contact date from before the CRM is now a follow-up (Q1). The composer's contact field is labelled **med** on screen (field label "Kontaktperson (med)"). Kalender's button reads **Abonnement** once the user has a link.
- **Indstillinger > E-mail** (`smail`): on this line nothing sends the mail to customers from the old history list (`documents.email.customer_subject/customer_text` are read only by the settings page; the CRM only mails meeting invitations). ML3 is gone, and the two fields' texts say so under new keys (`.text2`).
- `index/main.php`: `saldi-help.js?v=12`, `saldi-help-debitor.js?v=7`, `saldi-help-settings.js?v=7`. Tests: the D2 CRM block (dialogs, the live state) and a merged test that switches the tab and opens the drawer in place.

Follow-up findings (13 Nov 2026):

- **A drawer opened after a fetch.** The server search's pick (`openOutside()`) opens the drawer only once the customer has been fetched, after the click was read again, so the panel kept the tab's state. `crm.js` `openDrawer()` now dispatches `crmdrawer` on its document when the drawer is open (`crm.js?v=31`), and `saldi-help.js` reads the page again on it, as on `poolswitch`.
- **Typing in the search.** `#q` filters every tab on `input`, with no `change` until the field is left, so the flag `soegning` lagged. For an `inPlace` page `bindFrame()` reads the page again 150 ms after the typing pauses (`STATE_MS`) and draws the panel and sends the state only when the detected state changed.
- **"Mine".** `mine` is a stop word (`saldi-help-content.js`), so the CRM's view Mine could not be searched. A page may list `ownWords` (§3.2); `stopWords()` leaves them out on that page only. `dcrm` has `mine`; every other page keeps it as a stop word, and CR6's vocabulary for the chat now has it.
- **Texts** (reworded in place, the cached rows removed with `deleteStaleTekst()` in `includes/betweenUpdates.php`): CR2 step 2 and Q3 step 3 name **til** only for a company with employees (`crm.js` draws the field only when `DATA.emps` is not empty); the Sager text also covers an empty board (no customer with one of the statuses, or Mine and the search hide them; statuses and groups under the gear).
- **The panel head** (`css/saldi-help.css?v=6`): the title wraps, at most three lines, instead of cutting "Indstillinger: Brugere og ansatte" off with an ellipsis.
- `index/main.php`: `saldi-help.js?v=13`, `saldi-help-debitor.js?v=8`, `saldi-help.css?v=6`; `debitor/crm.php`: `crm.js?v=31`. Tests: merged tests for `crmdrawer`, typing in `#q`, the panel search for "mine" on the CRM and on the Kladdeliste, and CR6's vocabulary; the D2 CRM block (`ownWords`, the texts); the head's CSS and the cache versions in `topbarAssistShell.test.mjs`.

## 23. Saldi Assist in the shell (8 Nov 2026)

The owner asked for the chat to feel like part of Saldi, so that users use it instead of phoning support, as a natural part of their work, and so that Saldi is ready for an AI that is fully integrated later. His three points, as agreed with him:
1. "Frigør fra siden" should not jump to the left side: the chat stays where it is and lies over the page instead of squeezing it. The user can move it and make it larger or smaller by dragging its corners.
2. The chat should slide in under the topbar, and the Saldi AI symbol should glide from the topbar down into the chat when it opens.
3. The chat's top bar and Saldi's topbar belong together: "+ Ny chat" always stands in Saldi's topbar next to the AI symbol and always starts a new conversation; the symbol (Hjælp) opens the chat where the user left off. The chat's other items stay in the chat.

The interface between the three parts is the shell contract v1 (8 Nov 2026): Saldi (this repository), the loader (`DANOSOFT/support-sally` `frontend/app/widget.js/route.ts`, the script in Saldi's page) and the chat window (`frontend/components/embed/*`, the cross-origin iframe). The full contract, both sides, is `docs/saldi-shell-integration.md` in support-sally. This section is Saldi's side and what Saldi promises the loader. Saldi only renders markers and a hidden button and changes what Hjælp does; every pixel of the chat's geometry, motion, storage and focus is the loader's.

### 23.1 What the user sees (with a loader in shell mode)

- **Docked** (the default): the chat stands on the right **under** the topbar, from the topbar's bottom edge to the bottom of the window. The topbar keeps its full width and is never covered or narrowed; only the page area below it narrows (`margin-right` on `.home-content`). Opening slides the chat in from the right under the topbar and over the page (240 ms); the page area narrows when it has landed, so no strip of the shell's background shows beside the sliding chat. Meanwhile the Saldi AI symbol glides in a straight line from the Hjælp button into the chat's header (and back on close). With reduced motion: a fade, no flight.
- **Width**: dragging the chat's left edge sets the docked width (360 px up to 720 px, the page keeps at least 560 px); the edge is also a keyboard separator (arrows, Home, End). This replaces the menu items "Bredere panel" / "Smallere panel".
- **Frigør fra siden**: the chat stays at the same place on the right under the topbar, now over the page with a shadow and rounded corners; the page gets its full width back. The user moves it by its header and resizes it from every corner and edge, grabbing on or just outside the outline (at least 340 × 400 px, always inside the page area under the topbar). Dragging it against the right edge of the window (pushing it a little past where it stops, or the pointer to the edge) shows where it will dock and docks it on release; a double-click on the header (or Enter on it) docks it too. "Nulstil placering" in the chat's menu puts a floating chat back at its default place. Size, place and docked/floating are remembered per browser.
- **Below 640 px** the chat fills the page area under the topbar, so the topbar with "+ Ny chat" stays usable on a phone. Saldi's phone sidebar and the topbar's menus open above the chat.
- **"+ Ny chat"** in Saldi's topbar, right after Hjælp: always a new conversation, without the confirmation the chat's own button asks for. The previous conversation goes to Samtalehistorik. A closed chat opens on the new conversation (with the symbol's flight). The chat's own "+ Ny chat" is not shown on Saldi.
- **Hjælp** (the symbol): opens the chat on the conversation the user left, or on its start screen with the page help when there is none (after "+ Ny chat", or when the conversation is no longer in the history). Pressed while the chat is open, it closes the chat. Esc in the chat after Hjælp or "+ Ny chat" goes back to the page (a floating chat closes, the caret returns to the field), as after `?` and F1. `?`, F1, field help, the classic Hjælp cell and "Spørg Saldi AI" still open the page help in the chat (§19).
- The chat keeps its place, size and conversation across Saldi pages (the shell does not reload). A shell reload (language or fiscal-year switch) closes it, as before.

### 23.2 Markers (Saldi renders them, with the help panel on)

| Marker | Element | Meaning for the loader |
|---|---|---|
| `data-saldi-assist-topbar` | `<header class="topbar" id="topbar">` (`topbar.php` `topbar_render()`) | The chat docks under this element's bottom edge. It is never covered or resized. |
| `data-saldi-assist-page` | `<div class="home-content">` (`main.php`) | The page area, the only element the loader narrows. Its parent `.home-section` is where the loader mounts the chat (right after the page area). |
| `data-saldi-assist-new` + `hidden` | `<button id="topbar-assist-new">` (`topbar.php`, Hjælp branch only) | "+ Ny chat". The loader binds the first one inside the marked topbar: it removes `hidden`, sets the attribute to `bound` and handles the click. |
| `data-saldi-assist-symbol` | the Hjælp button's symbol (unchanged, §19) | Where the symbol's flight starts and ends, and the state mirror. |

The markers are printed only when the tenant has the help panel on (`$topbar['helpPanel']`, the pilot switch of §9). Without it a Guide tenant keeps today's chat layout with the Assist menu, because the shell mode belongs with the Hjælp symbol and "+ Ny chat", and the owner approves the pilot before everyone gets it.

Saldi promises the loader: `.home-section` (position fixed, a stacking context) has no `transform`, `filter`, `contain`, `perspective` or `will-change`; the topbar's `z-index` in that context is above the chat's 40 and below its menus' 60 (today 50, `position: relative`); `.home-content` has no `z-index`. The chat therefore lies above the page and below the topbar's menus, the sidebar (z 100), the phone sidebar's backdrop and the guides modal. Saldi's own help strip (`#saldi-help` overlay, z 45) is shown only while the chat has stepped aside (Vis mig, a tour), so they never overlap.

### 23.3 "+ Ny chat" (topbar.php, topbar.css, tekster.csv)

```html
<button type="button" class="topbar-assist-new" id="topbar-assist-new" data-saldi-assist-new hidden
  aria-label="<?= $t('8796|Ny chat') ?>" title="<?= $t('8797|Start en ny samtale med Saldi AI') ?>">
  <i class='bx bx-message-square-add topbar-assist-new-ic' aria-hidden="true"></i><i class='bx bx-plus topbar-assist-new-plus' aria-hidden="true"></i><span class="topbar-assist-new-lbl"><?= $t('8796|Ny chat') ?></span>
</button>
```

- Directly after the Hjælp item (`.topbar-help-item`), as a child of the cluster; not in the Assist-menu branch (no Hjælp symbol there). Saldi has no click handler on it. A click outside `.topbar-pop, .topbar-item, .topbar-search` closes the topbar's menus (Saldi's document handler), so an open menu closes.
- Texts: 8796 Ny chat / New chat / Ny chat; 8797 Start en ny samtale med Saldi AI / Start a new conversation with Saldi AI / Start en ny samtale med Saldi AI.
- `css/topbar.css` (always loaded) mirrors `.topbar-help` (`css/saldi-help.css`): 34 px high, radius 10 px, transparent, `var(--topbar-txt)`, icon 18 px, hover `var(--topbar-hover)`, focus ring `var(--topbar-accent)`, the label in `var(--font-display)` 500 .8125rem from 1240 px (padding `0 11px 0 8px`) with the plus icon: "+ Ny chat". Below 1240 px, without the label, the button shows Boxicons' `bx-message-square-add` (a chat bubble with a plus) instead, because a bare "+" in an accounting topbar reads as "create new (invoice, entry)". No new colours, fonts or measures; the topbar's height, gaps and other items are unchanged. `.topbar [hidden]` hides it until the loader binds it; `.topbar-assist-new[data-saldi-help-dead]` hides it while Saldi's panel has taken over (23.4).
- Known cost: the button is rendered hidden and appears when the deferred loader runs, which can be after the first paint, so the cluster can move once by 34 px (about 90 px from 1240 px). Rendering it visible would leave a button that does nothing with an older loader.

### 23.4 Hjælp resumes the conversation (saldi-help.js)

The loader in shell mode sets `window.SaldiAssist.shellVersion = 1` (next to `v2Ready`, `helpReady`). `shellResume()` is `merged() && SaldiAssist.shellVersion === 1`. In `toggle(o)`, when `o.source === 'topbar'`, the panel is closed and `shellResume()`:
- with a way back shown (`H.back`: Vis mig or a tour with the chat stepped aside): as before, `mergedOpen('help')`, back to the help;
- else a ring left from a docked Vis mig ends first (`endShowMe({ silent: true, noRestore: true })`), then the button is a toggle: an open chat closes (`SALDI_CHAT.close()`), a closed one opens where the user left off (`mergedResume()`): the topbar's menus close, a failed text group is retried, the signal is acknowledged, the state goes out at once as a view of the page, `SaldiAssist.pageContext` is set, `SALDI_CHAT.open()`, and the 4 s watchdog (§19.1) is armed. No `saldi:help:open`, so the page help sheet never covers a conversation. The chat shows its live conversation, or after a reload the latest one from its history (the chat restores it once Saldi's token has named the user), or its start screen with the page help.

Unchanged: `?`, F1, field help, the classic cell (`source: 'classic'`), "Spørg Saldi AI", `SaldiHelp.toggle()` from other callers, and every path without `shellVersion` (`saldi:help:open` as in §19). The topbar button's `aria-expanded` follows the chat (`saldi:chat:open` / `saldi:chat:close`, §19.1).

The watchdog covers every open in shell mode, not only Saldi's own: the `saldi:chat:open` listener arms it when `shellResume()`, so a "+ Ny chat" whose chat never connects falls back to Saldi's panel like Hjælp does. While the panel has taken over (`H.dead`), Saldi sets `data-saldi-help-dead` on `[data-saldi-assist-new="bound"]` (the CSS hides it) and removes it on `saldi:help:ready`. Saldi never writes the button's `hidden`: the loader alone owns it (it hides the button again for an older chat window that cannot start a new chat from the host), so the two never undo each other.

### 23.5 Who owns what

| Saldi | Loader | Chat window |
|---|---|---|
| the markers, the hidden button, its texts and CSS; what Hjælp does (23.4); the dead state's attribute | shell detection (`!mobile && v2 && both markers`), the mount in `.home-section`, docked/floating/fullscreen geometry, the page margin, the dock edge, the eight resize handles, the drag over a capture layer, the snap to dock, storage (`localStorage['saldi-assist:panel:v1:saldi']`, per browser), motion and the symbol's flight, focus on open and close, binding "+ Ny chat" and its `hidden`, `SALDI_CHAT.newChat()` | `shell: 1` in `saldi:ready`, the header without its own "+ Ny chat" and without Bredere/Smallere, "Nulstil placering", the grip area it reports to the loader, `saldi:new-chat` (a new conversation without the confirm step, kept when the user's token arrives later) |

### 23.6 The four combinations

| | loader with shell mode | older loader |
|---|---|---|
| **this Saldi, help panel on** (markers) | Shell mode as in 23.1. | Markers ignored: today's layout (the dock narrows `.home-section` with the topbar). "+ Ny chat" stays hidden. No `shellVersion`, so Hjælp opens the page help as in §19. The help panel takes over as before when the chat does not come up. |
| **this Saldi, help panel off**, older Saldi (the live servers), the saldi.dk landing page, any other host | Exactly today (no markers). | Today. |

The mobile app (`data-host="mobile"`) and a v1 widget never use the shell mode. A chat window older than its loader (the loader is cached for an hour) does not say `shell: 1`: the loader hides "+ Ny chat" again, so the button never does nothing; Hjælp still resumes.

### 23.7 Files

- `index/mainIncludes/topbar.php`: the header marker, the button.
- `index/main.php`: the page marker; `css/topbar.css?v=24`, `javascript/saldi-help.js?v=9`.
- `css/topbar.css`: the button's rules (23.3).
- `javascript/saldi-help.js`: `shellResume()`, `shellToggle()`, `mergedResume()`, `markNewChat()`; `prepareChatOpen()` is the common start of every host open (`openChatHelp()` uses it too, unchanged in effect).
- `importfiler/tekster.csv`: 8796, 8797.
- Not touched: `css/saldi-help.css`, the sidebar, colours, fonts, the topbar's measures and other items.

### 23.8 Acceptance

Saldi (run one file at a time: `timeout 180 node --max-old-space-size=2000 --test tests/includes/ui/<file>`):
- `saldiHelpMerged.test.mjs`, three `shell:` cases: with `shellVersion: 1` the topbar toggle while closed calls `SALDI_CHAT.open()` once, sends no `saldi:help:open`, sends the state before the open (a fourth view folds "Lær siden at kende", so the state differs) and counts the view; while open it calls `close()`; `?`, F1, the classic cell and `SaldiHelp.toggle()` still send `saldi:help:open`; without `shellVersion` the topbar sends `saldi:help:open`; without `helpReady` the panel opens. A ring left with the chat closed ends on Hjælp and the chat opens; with the floating chat stepped aside Hjælp goes back to the help (`saldi:help:open`). A `saldi:chat:open` with `helpConnected === false` arms the watchdog; when it fires, the bound button gets `data-saldi-help-dead` and its `hidden` is untouched (also for a button the loader hid); `saldi:help:ready` removes the attribute; without `shellVersion` a chat opened elsewhere is not watched.
- `topbarAssistShell.test.mjs` (source checks): both markers, once, only with the help panel on; the button's markup right after the Hjælp item and absent from the Assist-menu branch, without a click handler, never touched by `saldi-help.js`; the tekster rows; the cache versions; the button's CSS (variables only, `[data-saldi-help-dead]`); the topbar's z-index between 40 and 60; no `transform`/`filter`/`contain` on `.home-section` and no `z-index` on `.home-content` in the shell's CSS.
- The loader's and the chat window's tests (jsdom, unit) and the Playwright run on a fixture that copies this shell are in support-sally (contract §7: docked rect and topbar unchanged, dock edge, float/move/resize/snap, keyboard, focus, motion, "+ Ny chat" race, resume).

On ssl12 (a test folder with the help panel on) before the owner tests, with a loader and chat built from the support-sally branches: 1440 × 900 with the sidebar open and closed (the topbar keeps its width through the sidebar's 0.5 s transition; the chat's top follows 44/48 px on Oversigt); "+ Ny chat" as the first action after a login (the start screen stays when the token arrives); Hjælp after a reload (the last conversation); Vis mig and a tour beside a docked and over a floating chat (§19); the topbar's menus over the chat; the phone width; a tenant with the help panel off (today's layout); reduced motion; Chrome, Firefox and Safari with mouse and touch.

### 23.9 Known costs and open points

- The cluster can move once when the loader shows "+ Ny chat" (23.3).
- The loader keeps the page at least 560 px wide; Saldi's own panel docks only with 960 px left. Wide classic pages can scroll sideways inside the frame next to a docked chat, as today.
- While the docked width is dragged, the page's width changes once on release; until then a band of the shell's background shows between page and chat when narrowing.
- The panel's place and size are per browser, last write wins across tabs.
- A Hjælp open counts a view of the page for "Lær siden at kende" although the chat may show a conversation (§19.3); the chat's `seen` takes over counting once it sends it.

## 24. Lager and Indstillinger (9 Nov 2026)

The owner wants Lager and Indstillinger covered like Debitor and Kreditor (§22). 24.1-24.4 are the ground for that work: the two module files, a small general change to the core so that one route can show several pages, and the dispatcher of the settings sections. 24.5-24.12 are the content and its integration.

### 24.1 Files

- `javascript/saldi-help-lager.js` (Lager) and `javascript/saldi-help-settings.js` (Indstillinger), built like the module files of §16.1: one IIFE in sections, one section per author (L1-L3, S1-S3), every top-level helper name with its section's prefix. `index/main.php` loads them after `saldi-help-kreditor.js` and before `saldi-help-state.js` (`?v=1`, `?v=2` with the content); the three help tests load them in the same order.
- `javascript/saldi-help-state.js`: `resolve()` and `allPages()` (24.2). `javascript/saldi-help.js`: the page for the frame comes from `resolve()`, and the page id (24.2).
- `importfiler/tekster_keyed.tsv`: the 35 `help.sset.*` rows of the generic section page (24.3).

### 24.2 Variants: a route that shows several pages

`systemdata/settingsSection.php` serves every section of `getSettingsSections()` (`systemdata/settingsDefinitions.php:103`, 43 sections) from one script. The section is the address's `s=` (`settingsSection.php:103`), and every save, action and redirect keeps it, because the forms post to `selfUrl`, which starts with `settingsSection.php?s=<key>` (`:173`, `:644`, `includes/settings/rowsView.php:130`, `includes/settings/listView.php:371`). The help found a page by the frame's path only and loaded one text group per page, so one page object for all sections would put every section's tasks in the chat's search on every section (§19.3) and every section's texts in one fetch.

A page object may therefore name variants:

- `variants: { [key]: Page }` and `variant(ctx) → string`. `SaldiHelpState.resolve(page, ctx, warn)` gives `{ page, key }`: the variant that `variant(ctx)` names, when it is a page object; otherwise, also when `variant()` throws (one warning), the route's own page with key `''`. One level only: a variant's own variants are not looked at.
- The shell takes the page for the frame from `resolve()` (without the state builder, which `index/main.php` always loads first, the route's own page serves). The variant's text group is the one fetched, its functions run, and its tasks and questions go to the chat. The HelpState's `route` stays the path, as for every page.
- The page id is the route, with `?<key>` for a variant. It keys the counters and the choice of "Lær siden at kende" (`saldi-help.seen.<id>`, `saldi-help.learn.<id>`), the order signature and route changes, so another section is another page (D6: an unpinned panel closes, the field and the open rows are forgotten). `SaldiHelp.state()` also gives `pageId` and `group`.
- `variant()` reads the address first: the shell first asks while the document may still be loading.
- `SaldiHelpState.allPages(pages)` lists every route's page followed by its variants (`{ route, key, page }`). The state test walks it within the caps; the content test walks every variant like a page of its own.

### 24.3 The settings sections

- `saldi-help-settings.js` registers the dispatcher as the page of `systemdata/settingsSection.php`. Its `variants` is the map of sections; its `variant()` reads `s=`, else the `s=` in the action of the page's form, and accepts only a key of the form `group.section`.
- An author registers the page of a section with `addSection('<group>.<section>', page)`: a full page object (§3.2) with a text group of its own. The first registration of a key wins. A malformed key, a second registration or a missing page object is recorded in the dispatcher's `errors` list instead of thrown, so one mistake cannot stop the other pages; the content test wants the list empty.
- A section without a page of its own gets the dispatcher's generic page (group `sset`). States `formular` (`#st-form`), `liste` (`.st-irow`), `kunLaes` (`.st-flash-info`, text 5755); flag `historik` (`button[data-restore]`). Tasks SS1 Gem dine ændringer (`#st-save`), SS2 Fortryd, før du gemmer (`#st-undo`), SS3 Sæt en gemt værdi tilbage (`button[data-restore]`, only with `historik` in the order), SS4 Ret en række i listen (`.st-irow button[data-open]`), SS5 Gå til en anden indstilling (`a[data-back]`); question Q1 for read-only access; Ctrl+S. Its name is `Indstillinger: <heading>`, from the heading's own text without the ? beside it. It does not repeat the section's own ? help.
- The hub `systemdata/settings.php` (`?g=`), personal settings and the pages a section leads to (`regnskabskort.php`, `formularkort.php`, `posmenuer.php`, the import and export pages, …) are ordinary routes.
- Each section's page has the text group listed here, so the authors of different sections never write the same rows:

| Area | Sections and their text groups |
|---|---|
| Virksomhed, Finans, Dokumenter, Organisation, Import & eksport | `company.data` sstam, `company.localisation` ssprog, `company.account` skonto, `company.gdpr` sgdpr, `company.fiscal_years` sregnaar, `finance.vat` smoms, `finance.currencies` svaluta, `finance.cash_journal` skladde, `finance.document_storage` sbilag, `documents.email` smail, `documents.print` sprint, `documents.backgrounds` sbaggrund, `organisation.departments` safd, `organisation.employees` sansat, `import_export.data` simport, `import_export.backup` sbackup |
| Salg, Køb | `sales.debtor_card` sdebkort, `sales.debtor_groups` sdebgrp, `sales.orders` sordre, `sales.reminders` srykker, `sales.discounts` srabat, `sales.mysale` smitsalg, `purchase.creditor_groups` skredgrp, `purchase.orders` skoeb, `purchase.pricelists` sprisliste, `organisation.commission` sprovision, `organisation.projects` sprojekt, `items.consignment` skommission |
| Varer & lager, Integrationer, Kasse | `items.stock` slager, `items.warehouses` slagre, `items.units` senhed, `items.variants` svariant, `items.item_groups` svaregrp, `items.packaging` semballage, `integrations.connections` sintegr, `integrations.pickup` safhent, `pos.tills` skasse, `pos.cash` skasseopt, `pos.cards` skort, `pos.receipt` sbon, `pos.screen` sskaerm, `pos.tables` sborde, `pos.kitchen` skoekken |

### 24.4 Tests

- `saldiHelpContent.test.mjs`: the generic checks walk every variant (`route?key` in the messages; a variant has no variants of its own). Two settings blocks: `variant()` reads `s=`, else the form's action, and refuses anything else; every registered section is a section of `getSettingsSections()` with a group of its own and `errors` is empty; the generic page's states, orders and targets on a form, a list and a read-only section.
- `saldiHelpState.test.mjs`: every page and variant (`allPages()`) within the caps; `resolve()` with a registered section, an unknown one, a variant that is not a page and a `variant()` that throws.
- `saldiHelpMerged.test.mjs`: a section with a page of its own in the frame loads that page's group and sends its page to the chat, with the page id `systemdata/settingsSection.php?<key>`; another section gets the generic page with the `sset` texts and the section's heading in its name. The tests use keys no section has (`zz_test.*`), so the authors' sections never meet them.


### 24.5 Files and pages

The content of this round, written by six authors (L1 items, L2 stock movements, L3 stock reports, S1 the hub and the company, finance and document settings, S2 sales and purchase settings, S3 stock, connections and POS settings) and integrated on top of 24.1-24.4:

- `javascript/saldi-help-lager.js`: 29 routes. Helpers carry the author's prefix (`l1`, `l2`, `l3`).
- `javascript/saldi-help-settings.js`: 28 routes and 43 settings sections, each section registered with `addSection()` as a variant of `systemdata/settingsSection.php` (24.3). Helpers carry `s1`, `s2`, `s3`.
- `importfiler/tekster_keyed.tsv`: 4 106 new `help.*` rows in Danish, English and Norwegian (9 351 in all). Bold names are the on-screen names of `importfiler/tekster.csv` in each language; a name the page hard-codes in Danish stays Danish in all three columns. The settings hub's names hold `&` on screen (Køb & lager); the rows write them with "og" and without bold, as earlier rounds did.
- `tests/includes/ui/saldiHelpContent.test.mjs`: 30 new blocks (`L1 …` to `S3 …`) on fake pages. A route may now lie in a sub-directory and carry percent-encoding (`lager/lister/vareliste.php`, `lager/lister/indk%C3%B8b.php`): the frame's `location.pathname` below the Saldi root is the route, and the browser encodes the ø of `indkøb.php`.
- `index/main.php`: both module files `?v=2`.

In the shell `lager/varer.php` never renders: with the sidebar layout it redirects to the goods tab the user had last (`lager/varer.php:121-133`), so the goods tabs under `lager/lister/` are the routes.

**Lager** (route → group: tasks / questions / fields; `problem()` where the page has one)

| Route | Group | States | T / Q / F | `problem()` |
|---|---|---|---|---|
| `lager/lister/vareliste.php` | `lvar` | liste, tom, kolonner, filtre | 19 / 3 / 10 | – |
| `lager/lister/ordrestatus.php`, `indk%C3%B8b.php` (Indkøb; `ind%C3%B8b.php` until the live check, 24.15), `serialnumber.php`, `styklister.php` | `lvar` | as Vareliste | 10/1/4, 9/2/5, 9/1/3, 8/1/1 | – |
| `lager/varekort.php` | `lkort` | ny, kort, bekraeftLager, bekraeftBeskrivelse, leverandoropslag, vareopslag (18 flags) | 40 / 6 / 36 | VK36 the item's group does not exist in the fiscal year |
| `lager/productCardIncludes/stockLog.php` (Lagerlog, from Lagerhistorik on the card) | `lkort` | liste, tom | 4 / 1 / 0 | – |
| `lager/ret_varenr.php`, `ret_varenralias.php`, `varefoto.php`, `emballage.php`, `happyhour.php` | `lvx` | form; upload, foto | 3/1/1, 2/1/1, 3/0/1, 3/0/7, 3/0/7 | – |
| `lager/modtageliste.php` | `lmod` | tom, liste | 7 / 3 / 0 | – |
| `lager/modtagelse.php` (Varemodtagelse) | `lmod` | ny, aaben, ret, modtaget | 7 / 3 / 2 | VM4 the page refused the last step |
| `lager/lagerflyt.php` (Flyt vare) | `lflyt` | flyt, kunLaes | 7 / 2 / 2 | FL3 too many, FL4 nothing on the warehouse, FL5 only one warehouse |
| `lager/optalling.php` (Lageroptælling) | `lopt` | start, vare, liste, ikkeOptalt, import, kontrol, gentael, udfoert | 15 / 3 / 6 | LO11 count again, LO13 variants, LO14 item not found |
| `lager/batch_oversigt.php`, `batch_udfyld.php` | `lbat` | liste, tom | 7/3/0, 8/3/4 | BO2 expired batches; BU7 save refused |
| `lager/stykliste.php`, `vareproduktion.php` | `lprod` | liste, tom; bekraeft | 5/2/0, 3/2/0 | – |
| `lager/rapport.php` (Varerapporter, Varesalg, Detaljeret) | `lrap` | forside, varesalg, detaljeret | 22 / 4 / 13 | RV9 Beregn lagerbeh./værdi without a stock-kept group |
| `lager/lagerstatus.php` | `lstat` | liste | 10 / 3 / 11 | LS2 a red stock figure |
| `lager/varespor.php`, `salg_rapport.php`, `beholdningsliste.php`, `stockLog.php` (the Log link), `minmaxstock.php` | `lstat` | spor; liste; vaelgLager, liste; log; liste | 4/1/0, 7/1/7, 4/1/0, 2/1/0, 3/1/0 | – |
| `lager/udlobsrapport.php` | `ludl` | liste | 7 / 3 / 0 | UD1 batches without expiry date or batch number |
| `lager/pricelist.php` | `lpris` | liste | 7 / 2 / 10 | – |

29 page objects: 238 tasks, 55 questions, 131 fields (after the review, 24.13, and the live check, 24.15). `lreg` and `limp` have no rows (24.7).

**Indstillinger, routes**

| Route | Group | States | T / Q / F | `problem()` |
|---|---|---|---|---|
| `systemdata/settings.php` (the hub, every `?g=`, the search), `settingsMoved.php` | `shub` | oversigt, soeg, kunLaes, ingenAdgang; liste | 9/4/1, 2/0/1 | HB7 a row the hub has marked |
| `systemdata/personalSettings.php` | `spers` | profil, sikkerhed, adgang, notifikationer, udefra | 9 / 2 / 12 | PS9 a refused save |
| `systemdata/regnskabskort.php` (Åbningsbalance) | `sregnkort` | aar1, aarX, aarX1 | 7 / 2 / 14 | RK2 debit and credit differ |
| `systemdata/formularkort.php`, `formeditor.php`, `logoupload.php` | `sformular` | valg, redigering, mailtekst; editor; upload, ingenRet | 5/2/3, 6/0/2, 3/0/4 | – |
| `systemdata/tekster.php` | `stekster` | liste | 1 / 1 / 1 | – |
| `admin/backup.php`, `admin/restore.php` | `sbackup` | start, klar; upload, bekraeft | 4/0/0, 4/0/2 | RS4 a copy of another ledger or without details |
| six `systemdata/importer_*.php`, five `exporter_*.php` (one page object each), `importAccountMap.php` | `simpexp` | upload, kolonner; valg, fil | 3/1/6, 2/0/1, 2/0/1 | – |
| `systemdata/posmenuer.php`, `importer_posmenu.php`, `exporter_posmenu.php` | `sposmenu` | vaelg, menu, knap; upload; fil | 5/1/0, 1/0/0, 1/0/0 | – |
| `systemdata/importer_varianter.php` | `svariant` | upload, ingenAdgang | 2 / 0 / 0 | – |
| `systemdata/barcodescan.php` (Vis QR-kode til app-login), `labels.php` (Mærkater) | `sxlager` | kode; visuel, raa, kunLaes | 1/0/0, 5/0/0 | LB5 changed elsewhere, LB1 name taken |

**Indstillinger, sections** (`settingsSection.php?s=<key>`, tasks / questions / fields; every section has `kunLaes` with no tasks)

| Hub group | Sections (group: T/Q/F) |
|---|---|
| Virksomhed | `company.data` sstam 5/1/14, `company.localisation` ssprog 3/1/5, `company.account` skonto 2/0/1, `company.gdpr` sgdpr 1/1/0, `documents.email` smail 4/2/11, `documents.print` sprint 1/0/2, `documents.backgrounds` sbaggrund 3/0/2, `integrations.connections` sintegr 11/3/4, `integrations.pickup` safhent 3/0/1, `organisation.employees` sansat 2/0/0 |
| Finans | `company.fiscal_years` sregnaar 8/3/8, `finance.vat` smoms 10/3/12, `finance.currencies` svaluta 4/1/6, `finance.cash_journal` skladde 4/0/4, `finance.document_storage` sbilag 6/2/6, `organisation.departments` safd 3/0/3, `organisation.projects` sprojekt 5/1/3, `import_export.data` simport 6/0/0, `import_export.backup` sbackup 3/1/0 |
| Salg | `sales.debtor_card` sdebkort 6/1/0, `sales.debtor_groups` sdebgrp 11/5/13, `sales.reminders` srykker 7/4/4, `sales.orders` sordre 13/4/5, `sales.discounts` srabat 8/3/15, `organisation.commission` sprovision 6/1/1, `sales.mysale` smitsalg 6/1/1, the seven `pos.*` sections (skasse, skasseopt, skort, sbon, sskaerm, sborde, skoekken) 2/0/0 each |
| Køb og lager | `purchase.creditor_groups` skredgrp 11/5/11, `purchase.orders` skoeb 5/1/0, `purchase.pricelists` sprisliste 6/2/6, `items.item_groups` svaregrp 10/4/11, `items.variants` svariant 5/1/2, `items.units` senhed 5/1/2, `items.stock` slager 8/2/5, `items.warehouses` slagre 4/1/2, `items.consignment` skommission 7/1/1, `items.packaging` semballage 2/0/0 |

43 sections with a page of their own (220 tasks, 56 questions, 161 fields) and 28 routes (95 tasks, 18 questions, 82 fields; Tekster's csv task left out after the live check, 24.15). No section falls back to the generic page (24.3); it stays for sections added later. `sxfirma` and `sxsalg` have no rows: no other page in the frame is reached from those sections.

### 24.6 The settings design in use

- **One page object per section, built by a small per-author factory** (`s1section()`, `s2Page()`, the S3 section pages), each with its own text group. Detectors read the section's own markup: the form view `#st-form`, the rows view `.st-rows[data-table] tbody tr[data-row]`, the list view `.st-irow[data-item]`, the read-only note `.st-flash-info` (5755), the error list `.st-errorlist` / `.st-errors` and the conflict flash.
- **Blockers first**: a refused save or a conflict is `problem()` (S2's FE, S1's MO10, VA4, BG3, AF3, S3's VG10, LS8, IN10) and comes as "Se her først"; an empty fiscal year in Varegrupper puts the copy or seed button first (VG3, VG2); the hub's marked row is HB7.
- **Read-only**: `kunLaes` has no tasks, an Om siden that says who can change it, and the read-only question first.
- **Field keys**: `f[<group>.<section>.<name>(.<n>)]` and the field ids `f-<key with - for .>` map to the setting's name; table cells `r[<table>][<row>][<column>]` map to the column's name; `#st-year` to the fiscal year. Switches are buttons and never "Feltet du stod i".
- **Vis mig** rings the setting's row `.st-field[data-key=…]` (label and control together), a table's add button `.st-rows[data-table=…] .st-rfoot button[data-add]`, a row action `button[data-row-action=…]`, a checkbox cell's visible box (never its hidden twin), the open drawer's action, else the row's Indstil. Targets that depend on the state go through `s1only()` / `l2only()` style guards, so a target rings nothing in a state that does not list its task (§19.3).
- **What a setting changes elsewhere** is said in the section, with the page where it shows: the posting switches on the order pages, the debtor group's VAT code and EU zone on the order and the VAT return, the item group's accounts and Lagerført on the item card and the stock reports, batch control against quick invoicing, the document storage against the cash journal's pool.

### 24.7 Not covered, and why

| File | Why |
|---|---|
| `lager/varer.php` | In the shell it only redirects to a goods tab (`varer.php:121-133`). |
| `lager/varevisning.php` | Linked only from the old list behind that redirect. |
| `lager/lister/view_performance_log.php` | Debug viewer, not linked. |
| `lager/varescanimport.php` | Linked from the item card (Masse vare scan, `varekort.php:1760`) but not in the repository. |
| `lager/labelprint.php`, `lager/fuld_stykliste.php`, the CSV and print views of the reports | Open in a new tab, a window or a download, never as the frame's page. |
| `lager/lagerregulering.php` | Nothing links to it; its role is taken by the item card and the stock count. Group `lreg` stays empty. |
| `lager/csv_set_import.php`, `vvsimport.php` | Linked from nowhere. Group `limp` stays empty. |
| The item card's confirm pages and lookups | States of `varekort.php` (`bekraeftLager`, `bekraeftBeskrivelse`, `leverandoropslag`, `vareopslag`), not routes. |
| `modtagelse.php` opened by Ny or an open receipt on the list | A new window; the page reaches the help only in the frame (a received list, its own Ny). |
| `importer_debitor.php`, `_kreditor.php`, `exporter_debitor.php`, `_kreditor.php`, `*_saet_hms.php` | Nothing in Indstillinger links to them. |
| `organisation.employees` embedded in the person dialog (`embed=person&popup=1`) | Not the frame's own page; a plain visit redirects to `usersRoles.php`, which is not in this round. |
| `fiscalYearInc/*`, `backup_download.php`, `view_logoupload.php`, `settingsLookup.php`, `settingsSearch.php` | Includes or endpoints. |
| `stripe_valg`, the bank connection, the floor planner, the old PoS-valg page, `debitor/cashCountHistory.php` | Operator only; "Kommer senere" behind a feature flag; a new tab; retired; Debitor. |

### 24.8 States and the first task per main page

| Page, state | Se her først | Top three |
|---|---|---|
| Vareliste, list / empty / a search with no hits | – | VL2 Find en vare, VL3 Åbn et varekort, VL1 Opret en ny vare / VL15 Tjek varegrupperne, før du opretter varer, VL1, VL10 Importér varer fra en fil / VL9 Ryd søgningen first |
| Varekort, new / fresh / service / stock item | VK36 when the item's group does not exist in the year | VK1 Opret varen med et varenummer, VK2 / VK4 Vælg den rigtige varegruppe, VK3, VK5 / VK5 Sæt salgsprisen, VK6 Sæt kostprisen, VK4 / with stock VK9 Se varens lagerbevægelser, VK8, VK5; at 0 VK39 Skriv, hvor mange du har på lager, VK10, VK11; bill of materials VK13 (in a stock-kept group VK40 Producér varen ud fra styklisten second, first with stock), variants VK15, closed VK18, copy VK21, opened from an order VK28 first |
| Modtageliste, empty / list / received | – | ML1 Modtag varer, der er kommet, ML7 Opret en købsordre, så varerne kan modtages / ML2 (unfinished) or ML1, ML4 / ML6 Bogfør regningen for de modtagne varer |
| Varemodtagelse, open / received | VM4 the page refused the last step | VM2 Læg varerne på lager, VM1, VM3 / ML6, VM5 |
| Lageroptælling, start / item / list / preview / done | LO11 count again; LO13 variants; LO14 not found | LO1 Tæl en vare, LO7, LO5 / LO2 / LO1, LO5, LO3 Bogfør optællingen / LO4 Gennemfør lagerreguleringen, LO12 Afstem lagerværdien med regnskabet / LO12 |
| Batch oversigt / Batchoplysninger | BO2 expired; BU7 save refused | BO1, BO6, BO7 / BU1, BU3, BU6 |
| Varerapporter, form / Varesalg / Detaljeret | RV9 no stock-kept group | RA1 Se køb og salg pr. vare i en periode, RA3, RA2 / RV1 Find varer med lav dækningsgrad, RV5, RV2 (RV10 first with Beregn lagerbeh./værdi) / RD1, RD2, RD3 |
| Lagerstatus / Udløbsrapport | LS2 a red figure; UD1 batches without details | LS1 Find lagerværdien på en dato, LS10 Bogfør lagerets ændring i regnskabet, LS8 / UD2, UD5, UD4 (UD3 first with expired rows) |
| Indstillinger (hub), overview / Grundopsætning mangler / read-only | HB7 a marked row | HB1 Find en indstilling, HB4 Opret det næste regnskabsår, HB5 Sæt momsen op / HB2 Sæt et nyt regnskab op i den rigtige rækkefølge, HB8, HB1 / HB1, HB9 |
| Regnskabsår, empty / list / the newest year has ended / several open | – | FY2 / FY1 Opret det næste regnskabsår, FY3, FY6 / FY1, FY6, FY3 / FY1, FY5 Luk et regnskabsår for bogføring, FY3 |
| Moms, empty year / codes | MO10 a refused save | MO1 Opret de danske momskoder, MO4, MO3 (MO2 first when an earlier year exists) / MO3, MO5, MO4 (MO6 first without report accounts) |
| Valuta / E-mail / Bilagsopbevaring | VA3 the rate's exchange differences; ML1 from the hub's warning; BI5 a failed FTP test | VA2, VA1 / ML1 Sæt afsenderen på mails, ML4, ML2 / BI2 Mail bilag til dokumentpuljen, BI1, BI6 |
| Varegrupper, list / empty year | VG10 a refused save; VG3, VG2 or VG1 in an empty year | VG1 Opret en ny varegruppe, VG4, VG6 / VG9 |
| Debitorgrupper / Kreditorgrupper | FE a refused save | DG1, DG7, DG2 (DG2 first with one group, DG3 first without an EU zone) / KG1, KG7, KG2 |
| Ordrer og fakturering, a new company / immediate posting off / quick invoicing locked | FE | OF1, OF3, OF4 / OF3 Vælg, hvornår fakturaer bogføres, OF2, OF4 / OF3, OF4, OF5 |
| Integrationer, without / with the POS module | IN9 the MobilePay webhook; IN10 a refused save | IN4 Send e-fakturaer via NemHandel, IN1, IN5 / IN8, IN11, IN4 (IN3 first right after a new API key) |
| Personlige indstillinger / Åbningsbalance / Indlæs sikkerhedskopi | PS9; RK2; RS4 | PS1, PS3, PS4 / RK1, RK3, RK7 / RS2, RS3 |

The full digest (Om siden and the top three for every state and flag) is in the integration notes outside the repository.

### 24.9 Integration: one word per concept, and the settings against the pages they change

1. The six parts were checked together: top-level names, routes, groups, section registrations, every key against the rows, the content and state tests (harness `check.py L1 L2 L3 S1 S2 S3 --tree all`: PASS). No names, keys or test titles clashed.
2. One Danish term per concept across Lager, Indstillinger and the earlier modules, in Saldi's own words: **beholdning** (not lagerbeholdning, except in on-screen names such as Tillad negativ lagerbeholdning); **købsordre** (not indkøbsordre, except the Ordrevisning columns Indkøbsforslag and Indkøbsordre); **batch** is common gender (en batch, batchen), as in Saldi's own Ny batch and the purchase-order help (KO24), while Batchoplysninger keeps its own word **parti** for a lot on stock; **debitorgruppe** (not kundegruppe); **flueben** (not afmærk); **kostpris** is "det, én enhed koster dig", so Købspris stays the Lagerstatus column of its own; shared titles as in §22.4 (Vælg periode, Eksportér listen til et regneark, Find en bestemt vare i listen).
3. Settings paths name the hub's own rows (`includes/settings/hub.php`): a step that walks there keeps the earlier modules' form (Åbn **Indstillinger** i menuen til venstre, klik på Varer under Køb og lager, og vælg **Varegrupper**); a reference in a sentence is written `Indstillinger » Køb og lager » Varer » Varegrupper`. Module pages are `Lager » Rapporter`, `Kreditorer » Ordre`, `Finans » Kassekladde`, as in the finance help.
4. Settings help against the page help where it takes effect:
   - Omgående bogføring af salgsordrer / købsordrer: the settings said an unposted invoice is "bogført gennem kassekladden". The cash journal offers Hent only when the company has no DIV/3 settings row (`finans/kassekladde.php:4283-4288`), which every company has (`includes/opretfunc.php:718`), so they are not reachable there. The sections now say what Kreditor KO21 says: turn it on, and contact support about the invoices already made. They no longer advise turning it off for a bookkeeper's review.
   - The VAT of a line: Varegrupper and the item card said the VAT comes from the item group's sales account; Debitor OR17 says it comes from the customer's debtor group. Both are right: the line takes the account's rate, never above the order's rate from the debtor group (`includes/ordrefunc.php:1955-1975`). VG6 and the card's question Q1 now say so.
   - Lagertilgang and Lagertræk: Varegrupper and Lagerstatus implied that with the two accounts Saldi posts the stock value as you go. Since 2015 nothing is posted to them (`$aut_lager`, `includes/ordrefunc.php:1985-1986`, `2155-2164`); the account specification shows the movements computed from stock (`finans/kontospec.php:100-113`, finance help `ksp`). The field help, VG5 and Lagerstatus LSQ1 now say that the change in stock is posted by hand, as the stock count's LO12 already did.
   - Bekræft ved ændring af beholdning på varekort (Købsordrer KB2): the item card asks for a reason (`lager/varekort.php:323-338`); Modtag on a purchase order asks nothing (`kreditor/modtag.php:272`), so KB2 no longer promises a confirmation at goods receipt.
   - Bilagsopbevaring: the pool opens from the paper clip on a journal line, the paper icon opens a line's own voucher (finance help KB24, JD4).
   - Åbningsbalance: 1. modtagelsesnummer numbers posted purchase orders (`kreditor/bogfor.php:291-299`), not the Varemodtagelse page; the field help says so.
   - Moms: a code goes on an account in **Momssats** on the account card (`systemdata/kontokort.php:545`), as the finance help.
   - Debitorgrupper B2B: the group's tick needs **B2B Salgspris** on the item cards (DG7), as the item card's field help.
   - The Log link's page (`lager/stockLog.php`) is named Ændringer af beholdningen, apart from the card's Lagerhistorik button, which opens Lagerlog (`productCardIncludes/stockLog.php`).
5. The digest was read once as a new customer and once as a bookkeeper after these changes.

### 24.10 Tests

- `node --test tests/includes/ui/*.test.mjs`, one file at a time with a cap: saldiAssistNavigate 5/5, saldiHelpContent 77/77, saldiHelpMerged 27/27, saldiHelpState 17/17, topbarAssistShell 7/7. The generic checks walk all 101 new pages and variants (keys, dead rows, text rules, bounds, `problem()` names an existing task).
- Guardrails `file-history, file-version, js-syntax, tekster, test-discovery, whitespace`: ok.

### 24.11 Live checks still to run (ssl12, read only; test_838 only through the web app)

Run on 9 Nov 2026; the result, and what could not be checked, is in 24.15.

- Routes: `SaldiHelp.state().route` on Indkøb is `lager/lister/indk%C3%B8b.php`; each goods tab in list, empty, Kolonner and Filtre; Lagerlog and the Log link get their own names.
- Varekort: a service, a stock item with and without stock, a FIFO-locked cost price, a fiscal year without the item's group (VK36), a read-only role, both lookups, both confirmations, and whether the card's other changes survive the stock-change confirmation (24.12); a bill of materials in a stock-kept group (VK40 rings Aktuel, Vareproduktion ends with Afbryd); a fresh card saved without a group lands in group 1 (24.13 item 2).
- Lager pages: Modtageliste's Ny opens a window; a received list in the frame; the alert-based flags on Flyt vare and Lageroptælling (do not click the final "her" on a real count); the batch rows; Vareproduktion (end with Afbryd); Varerapporter's three buttons in print order, RV9 in a tenant without stock-kept groups; the red figure on Lagerstatus; the search boxes of Salg pr. postnummer after the page's script; UD1 with and without the right to change items.
- Indstillinger: the hub's marked row and the search state while typing; the field box on `f[…]` fields and `r[…]` cells (Vis mig of those fields from the chat stays hidden: the shell's `fieldEl()` looks for `[name^=key]`); fiscal years ended and several open; Moms empty (`tom`) and MO3 on Tilføj momskode; the currency confirmation; a failed FTP test; E-mail from the hub's Tilføj afsender; personal settings per tab, a refused save, a revisor login; Åbningsbalance out of balance; a restore of another ledger; the group tables' B2B and OB boxes and the `fee_item_1.0` row; the price-list drawer; Lagerstyring's Opdater kostpriser after a saved method; Integrationer with and without the POS module; Kasser without tills; POS-menuer's three views; Mærkater after a duplicate name.
- Integration facts: a company with immediate posting off shows no Hent in the cash journal; Modtag on a purchase order writes a line in the Log link's list once the confirmation setting has been saved, also when it is off (24.12).

### 24.12 Product defects found on the way (not fixed on this branch)

For the owner; each is from the code, with file:line. Security-relevant findings are not listed in this public repository; they went to the owner directly.

Items and the item card:
1. Alt+L opens Ny on Vareliste (`lager/lister/topLineVarer.php:146`) against the shell's Alt+L back; on the card Tilbage (`varekort.php:1338`) and Leverandøropslag (`:2098`) share the key.
2. Udgået is saved as `lukket='on'` (`productCardIncludes/notesEtc.php:53-54`), but the list paints only `'1'` red (`lister/vareliste.php:605-612`) and the item lookup on orders hides only `'1'` (`includes/vareopslag.php:68-70`): items closed on the card are still offered on orders.
3. Slet on the card compares the translated button text with `'Slet'` (`varekort.php:312`), so it does nothing in English or Norwegian.
4. Kopier shows the copy as `kopi_af_<nr>` (`:1062`, `:1480`) while the row is saved as `<nr>_copy` (`:1069`, `:1075`).
5. The stock-change confirmation is included after `transaktion('begin')` (`:554`) and before the commit (`:1267`) and ends with `exit` (`productCardIncludes/confirmStockChange.php:69`): the other changes of that save are probably rolled back. Live check.
6. `lagerreguler()`: lowering stock by less than the first purchase batch's rest adds to the rest instead of subtracting (`includes/std_func.php:1741-1746`).
7. Offers: an empty Tid start resets both times to 00:00 (`varekort.php:957-985`), and one order path never applies an offer saved without times (`includes/ordrefunc.php:4550-4552` against `:4192-4196`).
8. B2B: a customer in a B2B debtor group may get price 0 on an item without B2B Salgspris (`ordrefunc.php:4104-4105`, `:4215-4216`). Live check.
9. The lookups' sort links send `funktion=kontoOpslag` / `vareopslag`, but only `kontoopslag` is dispatched (`varekort.php:270`).
10. Masse vare scan links to `lager/varescanimport.php`, which is not in the repository (`varekort.php:1760`).
11. HappyHour stores Tilbudspris × 0,8 and shows ÷ 0,8, assuming 25 % VAT (`happyhour.php:70`, `:115`); Indkøb/Bestil casts the supplier's cost price to an integer (`lager/orderapi.php:59`).
12. Vareliste's VAT label looks inverted against the card's setting, and the two read `vatOnItemCard` with different defaults (`lister/vareliste.php:78`, `:384-405`; `varekort.php:1516-1520`).
13. Ret varenummer alias says the alias changes in open orders, but that update is commented out; Ret varenummer's merge text prints an undefined `$varenummer`.

Stock movements:
14. Modtageliste's Ny passes the literal text `<?php echo $jsvars ?>` to `window.open` (`lager/modtageliste.php:74-75`, `:87`); Alt+N only reloads the list.
15. Varemodtagelse's title says Varekort (566) (`modtagelse.php:217`, `:233`); a new list's id is guessed as max(id)+1 before the insert (`:157-160`); it never asks for serial numbers (`:380`).
16. Stock that arrives without a purchase order gets a batch without batch number and expiry date, so it sells last and misses the expiry report: a move (`lagerflyt.php:163-166`), production (`vareproduktion.php:115-118`), a count up (`optalling.php:848-851`), stock raised on the card (`includes/std_func.php:1724-1727`).
17. Flyt vare cannot move variant stock (`lagerflyt.php:66`, `:79`, `:212`); a quantity under 1 is ignored silently (`:70`); Luk ignores returside (`:233`).
18. Lageroptælling: looking up an item rewrites its stock status row from the count date, also a past date (`optalling.php:289-340`); the red X deletes the item's counts on every warehouse (`:112`); the not-counted links drop the warehouse (`:612`); a count down takes batches by purchase date while sales use the expiry date (`:855-856`); the bad-file redirect goes to `?import=1` (`:1268`); typos "Vi du nulstille" (`:564`), "Vargruppe" (`:726`).
19. Vareproduktion: the voucher field is commented out (`vareproduktion.php:195-199`), so the posting code (`:162-183`) never runs; a debug echo before the redirect (`:213`).
20. `lager/lagerregulering.php` is linked from nowhere and still says "Indstillinger -> Varegrp." (`:136`).
21. Many messages are hard-coded Danish (`modtagelse.php:125-183`, `lagerflyt.php:189-233`, `optalling.php:124-1396`, `vareproduktion.php:186-204`, `includes/stykliste.php:50-69`); `fuld_stykliste.php:39` is English for everyone.

Stock reports:
22. Kun salg / DB takes sales only from the period's last day (`lager/rapport.php:707`); the help sends users to the full report for DB and DG.
23. The report's CSV is not rewritten when the report comes from the 20-minute cache, and that cache checks only the item count (`rapport.php:776-805`): the CSV or the figures can be from an earlier run.
24. Prisliste with a Lager other than Alle queries a table `pricelist` that no schema creates (`pricelist.php:127-148`); its tooltip on Varerapporter says "Se lagerstatus på vilkårlig dato" (971, `rapport.php:467`).
25. Salg pr. postnummer ignores the filters the form sends (`rapport.php:205` against `salg_rapport.php:72-98`), adds credit notes with ABS() and multiplies an amount already in kroner by the order's rate once more (`salg_rapport.php:115`, `:120`; unverified for foreign currency).
26. Lagerstatus with Fakturadato: paging, Linjer pr. side, Print side and CSV reset the date type to Leveringsdato (`lagerstatus.php:158-162`, `:370`).
27. Varespor sums the full line quantity under BESTILT and ORDREBEHOLDNING, and the amount uses the running quantity (`varespor.php:177-183`, `:265-270`).
28. Udløbsrapport: Eksporter CSV on Vis kun udløbne exports the days view (`udlobsrapport.php:107`); Beholdningsrapport is hard-coded to one database (`rapport.php:456-459`, `minmaxstock.php:156`).
29. Sales draw the batch that expires first, also an expired one (`includes/ordrefunc.php:1206-1213`): a product question, no write-off procedure is offered.

Indstillinger:
30. Debitorgrupper, Rabat % is never applied to order lines (`debitor/ordre.php:814`, `:1669`, `:1821`); discounts come from the matrix (`includes/ordrefunc.php:4308-4327`); the rate only feeds the commission report (`finans/provisionsrapport.php:166-178`). Its help 6482 says otherwise.
31. `debitor/ordre.php:812`, `:931`, `:1668` read the debtor group without the fiscal year (unlike `:1818`).
32. Rykkeransvarlig, Mailadresse and Påmindelse for rykker 1-3 have no effect: the login include that reads them is commented out (`index/login.php:980`, `includes/login.php:248`); the fee rows are read without the form language (`debitor/ny_rykker.php:72`, `:257`, `:267`).
33. The commission report cannot be opened from Finans » Rapporter (`finans/rapport_includes/forside.php:577`, `:588`, `:600`).
34. Varegrupper's price-group button says Åbn den gamle side (6504) but opens the new Rabatter og prisgrupper page (`systemdata/settingsDefinitions.php:1183`); Kvittering og print's help 6272 names the retired PoS-valg page (`:491-496`).
35. Importér POS-menuer deletes every POS menu and button before reading the file, without saying so (`includes/posmenu_import.php:31-33`, `importer_posmenu.php:76-85`); Importér varianter without the right says "Du har ikke adgang til nogen indstillinger" (`importer_varianter.php:44-47`); Importér formularer replaces every form without a confirmation (`includes/formularimport.php:32-33`).
36. `kreditor/modtag.php:272` checks only that a `confirmStockChange` row exists, not its value, so receipts are logged also with the setting off; the stock value is never posted to Lagertilgang/Lagertræk since 2015 (`$aut_lager`), and the report options that would include the computed movements are switched off (`finans/rapport_includes/forside.php:388`; `finans/regnskab.php:88` has no form field): a product question for the owner.
37. Hard-coded or poor texts: `importAccountMap.php:65-86` (Danish), `importer_adresser.php:122`, `:191`, `:224` and `importer_variantvarer.php:204` (buttons), `formeditor.php:641-642` (English for Norwegian users), `fiscalYearInc/year1.php:28-36`, `:102-104` and `yearX.php:137`, `:197` (Danish), `barcodescan.php:80-100` (English), the Emballage button (`lager/varekort.php:2110`), `lager/stockLog.php:53` and `salg_rapport.php:186-322` (Danish headings).
38. `tekster.csv`: Norwegian in Swedish (2077-2080, 2088-2099, 2302), 980 NO Inventar and 2243 NO Ny inventar (Beholdning), 605 NO Ordrene, 1190 EN Delivered, 963/960 NO till-receipt sense, 2096 EN "0 in stock price", 2235 NO "Samlet lagerværdi per", 172/174 EN "year differences", 1225 EN "prime number", 1229 EN "First", 1086 EN "Accounting", 440 NO "Regnskap", 995 NO "Vurdere", 994 NO "Moms (utgående mva)", 2336 EN lower case, 1370 NO as an upload error, 202/204/206 cut off in every language, 2450 EN/NO garbled, 1281 "Kommisionsvarer", 2545 contradicts its label, 2317 EN "Virant", 2277 NO "Oprett", 701 EN wrong.

### 24.13 Review of the Lager help (9 Nov 2026)

An independent reviewer held every Lager page (`javascript/saldi-help-lager.js`, groups `lvar` to `lpris`) against its PHP and `importfiler/tekster.csv`, read once as a small business owner who sells goods and keeps stock and once as a trained bookkeeper (valuation at cost, kostpris, lagerregulering, varekøb, VAT, fiscal years, the order of setting up a company). Every bold name was checked against the on-screen source of each language; the names left in Danish in English and Norwegian are hard-coded in the markup (Aktuel, Serienr., Fakturadato, the stock log's columns, Emballage's Gem and Slet for Norwegian users).

Corrected (the help said something the code does not do):
- Lagerstatus' **Kostpris** is today's card price times the quantity for any date (`lager/lagerstatus.php:344`, `:773`); only Varerapporter with Beregn lagerbeh./værdi takes the cost price on the date (`lager/rapport.php:1046-1082`). **Købspris** is the FIFO value of the purchase batches up to the date (`lager/lagerstatusValue.php:15-35`), and a batch has no price until its purchase order is posted (`kreditor/bogfor.php:229`, `kreditor/modtag.php:229`, `lager/modtagelse.php:380`). LS1, LSQ2 and Varerapporter Q3 now say so.
- Kopier copies the suppliers and the bill of materials (`lager/varekort.php:1081-1113`); VK20 said they were not copied. The stock is not copied: the card rebuilds it from the copy's own batches (`productCardIncludes/showLocations.php:52-87`, `:185-187`).
- A bill of materials can stay in a stock-kept group and be produced from the card; only a change of group is limited to groups without stock (`productCardIncludes/showGroups.php:19`). The card's Q3 and Vareliste's Q2 said a bill of materials can only be in a group without stock or has no stock.
- Udgået does not stop sales: the order lookups hide only `lukket='1'` (`includes/vareopslag.php:68-70`) and the order line takes any item (`includes/ordrefunc.php:4065`, `:4529`). VK17 and the card's about for a closed item say the item can still be put on orders.
- The red X of Lageroptælling deletes the item's unposted counts on every warehouse (`lager/optalling.php:111-112`); LO10 now says so and asks for a recount on each warehouse.
- Stykliste SL5: production needs Aktuel, which only an item in a stock-kept group has (`productCardIncludes/showLocations.php:40-41`).
- The card's shortcuts are one list for every state, and the confirmations reuse two keys: Alt+O is OK of the stock confirmation (`productCardIncludes/confirmStockChange.php:61`) and Alt+N is Nej of the description confirmation (`productCardIncludes/changeDescription.php:43`). Key texts 03 and 04 name them.

Added (what a user of the page in that state needs):
- VK40 Producér varen ud fra styklisten on the card of a bill of materials in a stock-kept group (a changed Aktuel opens Vareproduktion, `lager/varekort.php:1245-1248`): second after VK13, first when the item has stock (Vareopslag is then locked). Vis mig rings Aktuel; without a stock-kept group the target is null.
- A fresh card (no description) starts with VK4: the first Gem saves group 1 when no group is chosen (`productCardIncludes/showGroups.php:5`, `:15`; `lager/varekort.php:1033`), in the standard groups a group without stock. The about says so.
- VK6 step 3: Aut. regulering af kostpriser (gennemsnitspris or genanskaffelsespris) rewrites the card's cost price when a purchase order is posted (`kreditor/bogfor.php:86-87`, `:254-262`), without locking the field.
- VK39: save the cost price before the stock (with the stock confirmation the save that carried the price ends before its commit and OK values at the saved price, `lager/varekort.php:329-331`), and step 4: the stock is not posted, its value belongs in the opening balance or the next stock adjustment.
- LS10 Bogfør lagerets ændring i regnskabet on Lagerstatus, second in the list: the value on the balance date by Fakturadato under Købspris, compared with the stock account and posted in a cash journal between the stock account and the stock-adjustment account. Vis mig rings Dato (step 1), as LS1.
- English and Norwegian prose name the card item card and varekort, as every other module (L2 wrote product card and produktkort, 19 rows); bold names keep the on-screen text.

Rejected after a check: the ordering of Modtageliste (ML6 already leads a received list), VK8's "klik Gem først" (right, given 24.12 item 5), Lageroptælling's backup advice (the page itself asks for it, `lager/optalling.php:633`), FIFO and expired batches on Udløbsrapport (UD3 already warns).

Product defects found in the review (not fixed on this branch; continuing 24.12):
39. Lagerstatus values **Kostpris** at today's card price also for an earlier date (`lager/lagerstatus.php:344`), while Varerapporter uses the price on the date: two answers for the same statusdag.
40. A new item saved without choosing a group silently gets group 1 (`productCardIncludes/showGroups.php:5`, `lager/varekort.php:1033`); in the standard groups (`importfiler/grupper.txt`) that is Ydelser, without stock.
41. Kopier copies `varer.beholdning` with every other column (`lager/varekort.php:1066-1077`); the card corrects it on the next view (`productCardIncludes/showLocations.php:185-187`), but lists read before that view show the original's stock for the copy.

Checks: the five UI tests one file at a time (saldiAssistNavigate 5/5, saldiHelpContent 77/77, saldiHelpMerged 27/27, saldiHelpState 17/17, topbarAssistShell 7/7) and the guardrails as in 24.10. `index/main.php` still links `saldi-help-lager.js?v=2`; the integrator bumps it with the other reviewer's file (done in the merge: `?v=3`).

### 24.14 Indstillinger review (9 Nov 2026)

An independent read of every Indstillinger page object (the dispatcher, the 43 sections, the 28 routes) against the PHP and JS of its page and `importfiler/tekster.csv`, once as a small business owner who sells goods and keeps stock and once as a trained bookkeeper. Only `javascript/saldi-help-settings.js`, the Indstillinger groups of `importfiler/tekster_keyed.tsv` and two S1/S3 test blocks changed.

Corrected against the code:

- **The order of setting up a new company** (hub HB2, also the answer of Q3): the chart of accounts comes first under Regnskab og moms (it is the hub's first row there, `includes/settings/hub.php:112`), then the fiscal year and the VAT codes on its accounts; then the debtor, creditor and item groups, and for a company with stock the cost price method before the items and their stock; the opening balance last, when the old system's balances are ready, with the stock account equal to the items' stock at cost price; e-mail and forms at the end.
- **Moms**: the VAT return sums only the accounts between **fra** and **til** of the Momsrapport table (`finans/rapport_includes/momsangivelse.php:244-249`), so a VAT account outside that range is missing from the return. MO3, MO5 and MO6 say so.
- **Regnskabsår**: a year with stock movements cannot be deleted with data yet (`includes/settings/rowHooks.php:122-131`, text 7023). FY7 and Q2 say so.
- **Åbningsbalance**: the first year's opening balance names the stock at cost price among the assets (RK1); the year columns are headed Bogføring/Tilladt only on the first and later year cards, so RK6 names **Tilladt** alone (`fiscalYearInc/yearX1.php:21`); with a later year the whole first-year period is read only, not only its start (`fiscalYearInc/year1.php:44-50`, RK7).
- **Varegrupper**: cost price is used for every item, stock-managed or not (`lager/productCardIncludes/showPrices.php`); what only stock-managed groups have is stock and a share of the stock value (`lager/lagerstatus.php:206`). VG5 and the Lagerført field say so, and VG5 says how the change in stock value is posted by hand (the stock count's LO12, Lagerstatus' LSQ1). VG1 says flueben (24.9).
- **Lagre**: without warehouses the item card keeps all stock on one warehouse, number 1 (`lager/varekort.php:253-255`); the help no longer repeats the "lager 0" of text 6402.
- **Abonnement og konto**: Nulstil regnskab with Behold varer keeps the items with stock 0 and deletes their stock movements and cost price history (`systemdata/resetAccount.php:22-31`, `:74-75`). AC2 said so until 14 Nov 2026, when Nulstil regnskab left the section and AC2 with it: Saldi's staff reset a ledger from the admin panel (`admin/admin_panel.php`).
- **Import og eksport**: the item import has no stock column (`systemdata/importer_varer.php:346`, `:801`); the page says the stock is set afterwards on the item card or with a stock count, and that the file can carry the cost price. The shared column step no longer gives the chart of accounts' column names as the example on every import page.
- **Indlæs sikkerhedskopi**: RS2 lists Regnskab, der overskrives, too (`admin/restore.php:851`); RS4 (Kontrollér, at det er det rigtige regnskab) rings the warning it is about (`.restore-other-company`, `.restore-no-info`, `:843-847`), not Afbryd of a side step (§21).
- **Bilagsopbevaring** Q2: five years from the end of the fiscal year the vouchers belong to.
- **Lagerstyring og kostpris**: the order puts the cost price method (LS2) first, then low stock (LS1), Opdater kostpriser when a method that uses it is saved (LS3), then FIFO (LS6), the other valuation choice.
- **Names**: the Varer og lager, Integrationer and Kasse sections are named `Indstillinger: <section>` like every other section and the generic page.

Checked and kept: the fiscal-year creation (copies the chart with the balance accounts' saldo as primo and the year's groups, makes the year active, opens the opening balance, `rowHooks.php:242-292`, `:798-804`); the archive rules (5 years, oldest first, closed, nobody's active year, `:99-121`); the currency rate confirmation; the EU sales accounts by the customer's CVR (`includes/ordrefunc.php:1947-1952`); FIFO from the oldest purchase with rest (`:1993-2010`); Debitorrabatgrp. on the item card (`lager/productCardIncludes/showGroups.php:29`); the hub's search keys (`javascript/settingsHub.js:429-481`); the company bank account as the payer's account in supplier payments (`kreditor/betalinger.php:209-210`).

Product defects (continuing 24.12 and 24.13):

42. Text 6402 (Lagre, empty) says every item is in warehouse 0, but the item card moves stock without a warehouse to warehouse 1 (`lager/varekort.php:253-255`, `lager/productCardIncludes/showLocations.php:45-50`).
43. `tekster.csv` 600 NO "Finansiere" is the Norwegian name of the hub group Finans (a verb, "to finance"); the help writes the on-screen name.
44. A fiscal year with stock movements cannot be deleted with data until a stock re-basing rule exists (`includes/settings/rowHooks.php:122-131`): a product decision for every company that keeps stock.

### 24.15 Live check on ssl12 (9 Nov 2026)

The live checks of 24.11, 24.13 and 24.14 were run on a test folder on `ssl12.saldi.dk` in the fresh test tenant (test_838), as one test user only, with nothing deployed: the browser served the branch's `saldi-help*.js`, `css/saldi-help.css`, the help texts (the answer of `index/helpTexts.php` built from `importfiler/tekster_keyed.tsv`) and the help script tags of `index/main.php` in place of the server's, and the Guide was blocked, so the help showed in its own panel (§19.1). The probe (outside the repository, like the earlier rounds') records for every page and settings section, in every state it could build: the detected state and flags, Om siden and the top three as the panel shows them, every Vis mig target of the page, shown or not, with what it rings (tag, name or id, value or text, label, column header, row), the verdict against the task (§21, §19.3), and console errors from the help files.

Data made through the app, every typed text marked "(lager testdata)": six items (two stock items, one stock item kept without stock, a service, a closed item, a bill of materials with two parts) and the card's own copy of one of them; a purchase order of two lines, approved and received in two goods receipts in the frame; two stock counts, the second at the quantity the card showed. One setting was saved and restored at once: Bekræft ved ændring af beskrivelse på varekort (Lagerstyring og kostpris) on, to see the card's description confirmation, then off again; the confirmation was answered Nej and the description is unchanged. A backup was made with the section's own link and deleted again with Annullér. No backup was restored, no import run, nothing sent by mail.

Coverage: 137 visits, 116 page states. Lager: all 29 routes (Vareliste in list, search, search without hits, Kolonner and Filtre; the other four tabs; the item card new, fresh, service, stock item with and without stock, closed, with supplier, bill of materials with and without parts, copy, the supplier and item lookups, the description confirmation; Lagerlog and the Log link; Ret varenummer, alias, Varefoto, HappyHour; Modtageliste empty and with open and received lists, Varemodtagelse new, line being changed, open and received; Flyt vare with one warehouse; Lageroptælling start, item, list, not counted, the preview and after the regulation; Batch oversigt, Batchoplysninger, Stykliste, Vareproduktion ended with Afbryd; Varerapporter's form, Varesalg, with stock figures and Detaljeret; Lagerstatus, Varespor, Salg pr. postnummer, Beholdningsliste, Prisliste, Udløbsrapport; Emballage and Beholdningsrapport by address). Indstillinger: the hub (overview, the four `?g=` groups, a search), the moved map, personal settings (four tabs), 42 of the 43 sections with the first drawer of a list section, and 27 of the 28 routes. 687 ringing targets (381 distinct) all ring the element of their task after the fixes below; 27 shown tasks ring nothing for a reason (Gem ændringer before a change, fields shown only when a feature is on, Slet only on a deletable item or label, Tilknyt billede only with internal storage, Q2 of Flyt vare with one warehouse). No console errors from the help.

Fixed on this branch (each with a test in `saldiHelpContent.test.mjs`):

1. **Indkøb had no help**: the route was registered as `lager/lister/ind%C3%B8b.php`; the file is `indkøb.php`, so the frame's path is `lager/lister/indk%C3%B8b.php` (24.5, 24.11 corrected).
2. **FIFO locks Aktuel**: with Anvend FIFO på lagervarer (on in test_838) the card prints Aktuel readonly for every item but a bill of materials (`showLocations.php:190`, `:233`), yet the card put VK39 Skriv, hvor mange du har på lager first (with stock VK8). New flag `aktuelLaast` and task VK41 Ret beholdningen med en lageroptælling (cost price first while the stock is 0, Lageroptælling, Bogfør and her; no posting; Vis mig rings the locked field), and an about line naming the setting.
3. VK8 and VK39 rang the Aktuel of a bill of materials, where a change produces the item (VK40); VK38 rang a part's link on the bill itself. Both now ring only in their own state (§19.3).
4. VK23: Tilknyt billede is printed only with Intern opbevaring under Bilagsopbevaring (`showInfo.php:12-21`); step 3 says so.
5. Vareliste: VL7 rang nothing in Filtre (now the Vis udgået box); VL19 rang the Vareliste tab on Vareliste itself (no Vis mig there, Ny is VL1's); VL3 is left out when a search has no hits.
6. Varemodtagelse: VM3 rang OK on a line being changed; the title names the quantity, so it rings Antal (`antal_ny`, §21).
7. Flyt vare with one warehouse: FL1, FL3 and Q2 rang Opdater and Antal though there is nowhere to move to (FL5); they ring nothing there now.
8. Lageroptælling LO4 step 4 promised the message Lagerregulering udført; in the browser no message appears and the count shows again after a second (defect 46). The step says that.
9. Varegrupper VG5 and VG6 (and every table checkbox cell of S3) took the hidden twin the rows view prints before a checkbox, so Vis mig was hidden; they ring the visible box now.
10. Regnskabsår with one year, a new company's usual state, put Skift til et andet regnskabsår second; now FY1, FY6, FY8.
11. Lagerstyring og kostpris with FIFO on put Styr lageret efter FIFO third (turn it on); it goes last, the about says what FIFO does on the item card, and LS6 step 3 says Aktuel is locked.
12. Formularer FK1 and FK2 rang nothing: the form tag opens inside the table, so in the browser the selects are not its descendants; they are found by name.
13. Tekster TX2 Hent teksterne som fil is removed: the csv link has no file (defect 45).
14. Baggrunds- og bilagsfiler offers LU3 only when there is a background or department to choose; Lagre without warehouses offers only Opret et lager.

Confirmed live: a fresh item saved without a group lands in group 1, Ydelser (24.13 item 2, defect 40); the copy made by Kopier (VK20) gets the suppliers and the item number `<nr>_copy`; Importér kontoplan is refused once the newest year has postings, as the import's help says; VK40 rings Aktuel on a bill of materials in a stock-kept group and a changed Aktuel opens Vareproduktion (left with Afbryd); LS10 rings Dato on Lagerstatus; the restore page's upload step; the hub's search state; Lagre's empty text: stock without warehouses is warehouse 1 on the card and in Flyt vare.

Not checked live, and why: RS4 and the restore's confirmation (only after uploading a backup, the first step of a restore, which this run does not take; no other ledger's backup is at hand); Nulstil regnskab with Behold varer and the other Farezone actions (destructive); the stock confirmation (Bekræft ved ændring af beholdning på varekort): saving it leaves a settings row that changes what Modtag on a purchase order logs (24.12 item 36), so it is not a setting that can be restored without trace; batch and expiry states, variants, serial numbers, several warehouses and Flyt vare's move, the POS sections with tills and cards: each needs a setting or warehouses saved (not allowed in this run); refused saves and conflicts (FE, MO10, VG10, LS8 and the like) and the read-only role (the test user has full rights); Medarbejdere, which opens Brugere og roller (24.7); Beholdningsrapport's button, printed for one database only (`lager/rapport.php:459`), so the page was opened by address.

Product defects found live (continuing 24.12-24.14):

45. Tekster's csv link has an empty address: `$csvfile` is never set since the lines that set it were commented out (`systemdata/tekster.php:172-173`, `:200`), so the link reloads the page.
46. After the final stock regulation `lager/optalling.php:999` prints a second `<body onLoad=alert(...)>` in the middle of the page; Chromium shows no message, and the meta refresh (`:1000`) returns to the count after a second, so the user gets no confirmation that the regulation ran.
47. The first view of the copy made by Kopier carried beholdning 20 for an original with 10 (the help read it as an item with stock); the next view showed 0. This extends defect 41.
48. Beholdningsrapport is hard-wired to one database (`lager/rapport.php:459`, also `lager/minmaxstock.php` adds 1 to the minimum for it); opened by address in another tenant it says Vare tilhører ikke nogen varegruppe.
49. `SettingsService::hasModule('pos')` is true on every install without a `pos_licence` settings row, because it only checks that `debitor/pos_ordre.php` exists (`includes/settings/SettingsService.php`). So companies that never use a till see the POS sections and the till payment providers under Integrationer. The help now puts till payments first only when a provider is set up (flag `kasseIBrug`, found in the chat check of 9 Nov 2026).

## 25. Page questions in the chat (9 Nov 2026)

The owner approved this design on 9 Nov 2026. This section is Saldi's side of it and the contract both sides build. The chat side is the frontend of `DANOSOFT/support-sally` (`frontend/lib/page-help.ts`, `frontend/lib/page-suggestions.ts`, the embed start screen, `docs/saldi-page-help.md`); the chat's server is run separately and does not change.

### 25.1 Why

Today the start screen of Saldi Assist shows the same three general starter questions on every page: "Hvordan opretter jeg en faktura?", "Hvordan importerer jeg bankposteringer?" and "Hvor finder jeg momsrapporten?". It shows them on the stock pages too, while "Lær siden at kende" below them is about the page. The chat has starter lists per module (`page-suggestions.ts`), but inside Saldi it never gets a `screenId`, so the general three always show. Meanwhile the page help holds 318 prepared questions (353 after the content reviews, 25.9) in 162 page objects (119 routes and 43 settings sections, §24.2). Each has a written answer that was checked against the code. They are the `kind: 'q'` items of the HelpState's search list (§19.3), and today the user only finds them through the local search.

### 25.2 What the user sees

On a page with page help, the start screen reads in this order:

1. **Se her først**, only when the page has a problem (§18).
2. The page's questions for its **current state**: the state's top three, under the heading **Spørgsmål om <title>**. The wording is honest: we do not measure how often a question is asked, so the heading never says "Ofte spurgt". The general sentence "Find næste trin i SALDI. Stil et spørgsmål, eller start her." is replaced by this heading here.
3. **Lær siden at kende**. The tasks stay here and are never repeated as suggestions.

Clicking one of these questions does **not** call the AI. It adds a turn to the conversation:

- The question as the user's bubble.
- An answer card built from the page help: the item's intro and steps, plus **Vis mig** when the item can show in the current state. The card is clearly marked as coming from the page help, either with the wording of `labels.fromHelp` (8748 "Til denne side") or with the chat's own source-line style.
- **Spørg Saldi AI om mere**. It puts a follow-up question into the composer without sending it, as the existing ask links do.
- Up to two related questions from the same page, under **Flere spørgsmål om siden**. They are the next ones in the state's order that this conversation has not shown yet, then the page's other questions. They answer the same way.

The answer is instant and costs nothing. It works before the user consents to AI, because nothing is sent anywhere. The turn stays in the conversation history like any other turn: it is kept when the panel closes, and Hjælp resumes the conversation with it (§23.4). It is also part of the history the chat sends with the next AI question (`buildHistory`), so the AI knows what the user was shown and builds on it instead of contradicting it. It goes there as the user's message (the question, then "Vist i chatten fra Hjælp til siden · <title>, ikke skrevet af Saldi AI:", the intro and the steps), not as the assistant's: the chat's server drops an assistant message it has no trace of (25.8).

The answer is a frozen copy of the text at the time of the click. Its Vis mig shows only while the user is still on the same route and the item still exists as the same question (the same id and question text) with `canShow` in the current state. Otherwise it is hidden. The text is part of the check because the settings sections share one route (`systemdata/settingsSection.php`, §24.2) and several reuse an id such as `Q1`. The related questions follow the same rule.

Accessibility (§11): every question and link is a button, focus stays in the composer as the spec's conventions say (§19.8), and a polite live region announces the new answer. The texts are in Danish, English and Norwegian.

### 25.3 The field (contract, additive)

```ts
type HelpState = HelpStateV11 & {
  questions?: string[]   // at most 3 ids of kind 'q' items of this state's search, in the order to show; [] without any
  labels: HelpLabelsV11 & { questions?: string; askMore?: string; related?: string }   // optional, ≤ 160 each
}
```

The host side (`saldi-help-state.js`):

- **The order.** The search list's own order for `q` items (`ordered()` in `build()`): `page.questionOrder(ctx, st)` first, then the declared order of `page.questions`. The field holds the first three that `bound()` keeps. The ids are the same in every language. If `questionOrder()` throws, that is one warning and the declared order is used.
- **When it is empty.** `[]` for a page without prepared questions, for a page without help content and for an unreadable frame (route `''`).
- **The sanitiser** (`bound()`) keeps `questions` consistent with the search list it sends. It drops an id that is not a `q` item of that list, for example a question whose item was dropped for a bad id or an empty title; that is one `bounds` warning, "question without a search item dropped", and the next question fills in. It also removes repeated ids and keeps at most 3 (`SaldiHelpState.caps.questions`).
- **The cuts.** When the search list is cut to the chat's 120 items, the questions' items are kept and the other items are taken in the page's order. The size cut (`fit()`, 96 KB) never drops an item that `questions` names. Only if those three items alone break the limit does the last of them go, together with its id. The real content cannot do that; a test builds such a state.
- **No new item fields.** The answer is the item's `intro` and `steps`, which are already sent. The v1.1 fields of the search (§19.3) come after the questions and never change them.

The chat side reads the field by the same rules. It ignores ids that are not a `q` item of the search list, removes repeats and keeps at most 3. When the field is absent or empty, it falls back to the first three `q` items of the search list, and when there are none of those, to the module fallback (25.5). The chat that is live today ignores the field and the labels.

### 25.4 The labels

New chrome texts (`importfiler/tekster.csv`, `topbar_help_host()`, `saldi_help_chrome_ids()`, `stateLabels()` in `saldi-help.js`):

| Id | Dansk | English | Norsk | Label |
|---|---|---|---|---|
| 8825 | Spørgsmål om %s | Questions about %s | Spørsmål om %s | `labels.questions`, the heading (`%s` is the state's `title`, which the chat fills in; when the title is empty the chat uses its own "Spørgsmål om siden" without `%s`) |
| 8826 | Spørg Saldi AI om mere | Ask Saldi AI for more | Spør Saldi AI om mer | `labels.askMore`, the answer card's follow-up link |
| 8827 | Flere spørgsmål om siden | More questions about this page | Flere spørsmål om siden | `labels.related`, the heading of the related questions |

The three labels are in `MORE_LABELS`: the state carries them only when the caller hands them, and a missing label falls back to the widget's own words, as for the other optional labels (`HelpOptionalLabel`). The heading uses the state's title as it stands, for example "Spørgsmål om Kassekladde 12", "Spørgsmål om Ny kassekladde" and "Spørgsmål om Indstillinger: Moms".

### 25.5 Fallbacks

| Where | Start screen |
|---|---|
| A Saldi page with page help and prepared questions | the page's questions (25.2), answered from the page help |
| A Saldi page without page help (`hasContent: false`), or without prepared questions (`questions` empty and no `q` item) | the module's starter questions: `pageSuggestions` keyed on the HelpState's `route` when there is no `screenId`. The chat adds lists for the modules that lack one, such as `systemdata/` (settings) and `index/` (the front page), phrased as real user questions. These still go to the AI. |
| No page context (the saldi.dk launcher, the mobile app) | the three general questions, as today |
| v2 with access to the books | "Hvad skal jeg i dag?" stays first |

Of the 162 page objects, 35 have no prepared questions and get the module fallback:
- 19 routes: `lager/varefoto.php`, `emballage.php` and `happyhour.php`; the import and export pages of `systemdata/`; `admin/backup.php` and `restore.php`; `settingsMoved.php`, `formeditor.php`, `logoupload.php`, `barcodescan.php` and `labels.php`.
- 16 settings sections: `company.account`, `finance.cash_journal`, `documents.print`, `documents.backgrounds`, `organisation.departments`, `organisation.employees`, `import_export.data`, `items.packaging`, `integrations.pickup` and the seven `pos.*` sections.

A settings section without a page of its own gets the generic page (§24.3), whose single question Q1 is about read-only access.

### 25.6 Files

- `javascript/saldi-help-state.js`:
  - `questions` in `build()`, `bound()` and `fit()` (`named()`), and `caps.questions`;
  - `questions`, `askMore` and `related` in `MORE_LABELS`.
- `javascript/saldi-help.js`: the three labels in `stateLabels()`.
- `index/mainIncludes/topbar.php`, `includes/saldi_help_panel.php` and `importfiler/tekster.csv`: texts 8825–8827.
- `index/main.php`: `saldi-help-state.js?v=4`, `saldi-help.js?v=11`.
- Tests:
  - `tests/includes/ui/saldiHelpState.test.mjs`: four new tests (a page with its own order, the declared order and no questions, the sanitiser, the two cuts), plus `questions` in the shape check and on every page in every state;
  - `saldiHelpMerged.test.mjs`: the Kladdeliste's questions and the labels in the state that goes out;
  - `topbarAssistShell.test.mjs`: `?v=11`.

### 25.7 How to check

- **Saldi.**
  - Run `node --test` on `tests/includes/ui/*.test.mjs`, one file at a time with a cap, as in §19.7.
  - Run the guardrails `file-history, js-syntax, tekster, whitespace, test-discovery, file-version`.
  - `tests/includes/saldiHelpPanel.test.php` compares `saldi_help_chrome_ids()` with the `findtekst()` lines of `topbar_help_host()`; it needs PHP.
- **The content.**
  - The question digest prints the questions as the user will see them first. It lives outside the repository, like the earlier digests: `DIGEST_ROOT=<worktree>/ node qdigest.mjs [group,...]`. It loads every module file and builds each state with the real builder, so the questions are exactly those of the `questions` field.
  - It covers every page object, variants included, and resolves each variant as `resolve()` does. For each one it prints every state, each flag alone, all flags at once (`stCombos()` of the module tests) and the combinations given in `DIGEST_COMBOS`.
  - For each it shows the route, the state, the flags, Se her først and the heading with the questions: the Danish title and the first sentence of the intro (the first step when there is no intro).
  - Read it as a new customer and as a bookkeeper. Is the first question the one a user asks on that page in that state? Does the first line of its answer answer it? Does the heading read well?
- **The chat.** Run its lint, type check and jest. Then, on a test instance with the frontend deployed, check:
  - a page in two states: the empty Kladdeliste gives Q4, Q1, Q7, and one with journals gives Q1, Q2, Q3;
  - a click answers at once, with no request to the AI in the network panel, also before AI consent;
  - the turn is still there after closing the chat and pressing Hjælp;
  - the next AI question carries the turn in its history;
  - Vis mig shows on the same route and is hidden after a page change, also after a move to another settings section;
  - a page without questions (`lager/varefoto.php`) shows the module's starter questions;
  - the saldi.dk launcher shows the general three;
  - the keyboard alone and a screen reader's announcement;
  - English and Norwegian.

### 25.8 Code review (9 Nov 2026)

An independent review of both sides' code (Saldi's branch and the chat's branch) found the contract the same on both sides: the field name, the cap of 3, the three labels with `%s` only in `questions`, `[]` without questions, the fallbacks, and a chat that ignores what it does not know. The size cut and the cut to 120 items keep the questions' items, and `bound()` keeps the ids consistent with the search it sends. Three defects were fixed in the chat (`frontend/components/embed/EmbedClient.tsx`, `frontend/lib/page-help.ts`):

| Severity | Defect | Fix |
|---|---|---|
| High | The AI never saw the prepared answer. The chat sent it as an assistant message, and the chat's server rebuilds every assistant message from its own trace by turn id and drops one it has none of (support-sally `backend/app/routes/chat.py` `_resource_history`, `backend/app/assistant/history.py` `trusted_history`). The AI got the question without an answer and could answer it again, against the owner's "builds on it instead of contradicting it". | The turn goes as one user message: the question, the line "Vist i chatten fra <mark>, ikke skrevet af Saldi AI:" in the answer's language, the intro and the numbered steps. The server keeps user messages as they are (trimmed to 1000 characters from the start), so it does not change. |
| Medium | Vis mig and the related questions under an earlier answer were matched on route and id only. The settings sections share their route, and 5 ids recur across them (`Q1`, `Q2`, `Q3`, `Q4`, `QL`), so after a move to another section an old answer's Vis mig rang that section's element and its related questions came from that section. | `liveHelpAnswer()`: the same route, id and question text. After a language change an old answer, a copy in the old language, loses them too. |
| Low | The polite announcement was not read again when the same answer came twice in a row (the same question after Ny chat), since the live region's text did not change. | A no-break space tells the two apart. |

Not changed: the state's `route` stays the path for a variant (24.2), so the chat's other per-route behaviour (the fold of Lær siden at kende, `seen`) still treats the settings sections as one page; that predates this section. The shared fixture `saldi-help-states.json` in the chat is still the one built before `questions`; the chat's tests use it for the fallback.

### 25.9 Content reviews and merge (9 Nov 2026)

Four reviewers read every state's top three with the question digest (25.7), once as a small business owner and once as a bookkeeper, and fixed the order, the titles and the answers in their module files (`saldi-help-content.js`, `-finans.js`, `-debitor.js`, `-kreditor.js`, `-lager.js`, `-settings.js`). The rule throughout: a state with a problem leads with the question about it, an empty page with the newcomer's question, and no state leads with a question about another state.

| Module | Orders changed | New questions | Answers fixed |
|---|---|---|---|
| Finans and Oversigt (28 page objects, 133 states) | 38 states on 12 pages | 2 (Kontokort: the account type and the number) | Rapporter Q4's intro, Udlign Q2; Oversigt Q4 reworded |
| Debitor and Kreditor (31 page objects, 146 states) | about 31 states on 10 pages | 11 | the order's Slet task; drap Q1 reworded as a question |
| Lager (29 pages, 98 states) | 38 states on 11 pages | 8 | 7 |
| Indstillinger (72 page objects, 238 states) | 14 pages; six sections no longer lead with the read-only question for users who can edit | 13 | Landekonfiguration, GDPR, Ordrer og fakturering |

The page help now holds 353 prepared questions. The 35 page objects of 25.5 still have none. Two finance orders were kept because the tests above pin them and both are defensible: the empty Kladdeliste (Q4, Q1, Q7) and a new Kassekladde (Q4, Q3, Q11). `index/main.php` loads `saldi-help-content.js?v=6`, `saldi-help-finans.js?v=8`, `saldi-help-debitor.js`, `saldi-help-kreditor.js` and `saldi-help-lager.js?v=5`, and `saldi-help-settings.js?v=6`. The product defects the reviewers found are listed in their reports, not here; the security findings went to the owner privately.

### 25.10 Live check on ssl12 (9 Nov 2026)

Run on a test folder on `https://ssl12.saldi.dk` without a deploy, in the fresh test tenant (test_838) as one test user, read only. The probe (`askq_probe.py`, outside the repository) served this branch's `javascript/saldi-help*.js`, `css/saldi-help.css`, the help texts built from `importfiler/tekster_keyed.tsv` and `main.php`'s help script tags in place of the server's, added texts 8825–8827 to the help host, and served the chat from a local build of the chat's branch; the chat's `/api/` calls went to the real chat server and were counted.

- **Pages:** Oversigt, Kladdeliste, a posted Kassekladde, Regnskab; Fakturaer, Debitorer, Debitorkort; Leverandørordrer, Kreditorer, Kreditorkort; Vareliste, Varekort, Lagerstatus; the settings hub and the sections Moms, Regnskabsår and Kassekladde og betalinger (no questions); Brugere (`systemdata/usersRoles.php`, no page help).
- **Passed on every page:** the start screen's order and heading, the three questions equal to the state's `questions`, a click answers with no `/api/` call, the mark, Vis mig exactly when the item can show (it rang the question's own target on all 13 pages where one of the three could show), the related questions, Spørg Saldi AI om mere in the composer, focus in the composer and the polite line, close and Hjælp again, + Ny chat, Samtalehistorik, the old answers' Vis mig gone after a move (also from Moms to Regnskabsår on the same route), the module fallback for the last two pages, Enter on a page question, English and Norwegian.
- **One AI follow-up** on Varekort ("Forklar mere om: Hvorfor kommer der ikke moms på varen?") agreed with the prepared answer and built on it (varegruppe, Momsfri, the sales account's VAT code, the customer's debtor group) without repeating or contradicting it. The request the chat sends carries each prepared answer as a user message with the "Vist i chatten fra …" line and the numbered steps, and no assistant message (read from the request in the browser).
- **Fixed in the chat** (`page-help.module.css`): the steps of a prepared answer, of Se her først and of the tasks had no numbers (the base styles reset every `ol`), and the related rows were only as wide as their text.
- **Not from this feature:** `includes/saldi_assist_token.php` answers 503 on the test folder, which has no `SALDI_ASSIST_CONTEXT_SECRET`, and a few old pages miss `overlib.js`, `standard.css` and `search-white.png`.
