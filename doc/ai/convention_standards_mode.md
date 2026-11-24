---
name: convention_standards_mode
description: "Pages stay in quirks mode unless they opt in with $standardsMode = true before including includes/online.php; opt in only with a before/after visual check"
metadata:
  type: project
---

`includes/online.php` prints the doctype for about 500 pages. By default it prints `<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">` without a system identifier, which puts the browser in **quirks mode** (`document.compatMode === "BackCompat"`). A page that sets `$standardsMode = true;` before it includes `online.php` gets `<!DOCTYPE html>` and **standards mode** (`CSS1Compat`). The doctype comes from `saldi_page_doctype()` in `includes/stdFunc/pageDoctype.php`.

**Why not switch every page:** MB-143 tried the global switch on 30 module pages (2026-11-24). Even with `html, body { height: 100% }` added, the chart of accounts (`systemdata/kontoplan.php`) went blank, 21 pages got an 8px overflow scrollbar from the body margin, and 17 rendered differently (column widths in Kassekladde, row heights in Vareliste). In standards mode `height="100%"` tables collapse, tables inherit the body font size, and unitless CSS lengths (`width: 100`) are ignored. Quirks mode also has the WebKit-only percentage-height differences behind SST-932, so standards mode is still the goal, one page at a time.

**How to apply:**

- A new page, or a page rewritten with modern CSS, sets `$standardsMode = true;` before `include("../includes/online.php")`.
- To move an existing page over, compare it before and after in Chrome and Safari/WebKit at a normal window size: full-height tables and scroll areas, font sizes in tables, column widths, the page's popups, and print. Put the before/after screenshots in the PR.
- A page that prints any markup or text before `online.php` runs (a `<script>`, a `<title>`, a `<link>`) stays in quirks mode whatever it sets: the doctype must come before any element. Only whitespace and comments may come before it. `debitor/debitor.php`, `debitor/debitorkort.php`, `debitor/ordreliste.php`, `kreditor/kreditor.php` and `finans/kontrolspor.php` do this today; move that output below the include first.
- Don't add `html, body { height: 100% }` globally: with the default 8px body margin it makes every page scroll. Size full-height areas on the page itself.
