// tools/guardrails/tests/doors-php-html.test.mjs - HTML's reading of a PHP page's inline HTML (lib/doors.mjs phpHtml,
// htmlReading, htmlCodes), a literal NUL and pieces of PHP at HTML's boundaries included.
//
// phpHtml finds a page's <script> elements in PHP's T_INLINE_HTML tokens as HTML's tokenizer does: names folded A to Z
// alone (asciiLower), a tag's "=" and "/>" as HTML reads them, a literal NUL in a name as U+FFFD, foreign content as
// HTML's own. Where HTML's reading is not known (the two readings of foreign content part, a <frameset>, a raw text
// element in a <select>, a literal NUL, a piece of PHP outside HTML's data state) the block leaves the plain subset and
// every later line that names a password counts. Inline HTML outside a script's code is also read as the file's code,
// since PHP's output may print a <script> tag around it.
//
// History:
// 20261107 CL/LH Created.
import test from "node:test";
import assert from "node:assert/strict";
import { randomBytes } from "node:crypto";
import { tokenize } from "../lib/php.mjs";
import { markerHits, stationReport } from "../lib/station.mjs";
import { addedWithContext } from "../lib/changeset.mjs";

// Built at run time, so this file holds no literal for the marker to find.
const NAME = ["pass", "word"].join("");
const GETTER = `get${NAME[0].toUpperCase()}${NAME.slice(1)}`;
const B = "`";
const NUL = "\u0000";
const literal = () => `'v${randomBytes(6).toString("hex")}'`;
const NONE = Object.freeze({ hits: [], humanOnly: false });
const hitAt = (...n) => ({ hits: n, humanOnly: true });
const PAGE = "includes/page.php";

/** The password marker's lines and humanOnly, as check.mjs routes `entries` of `path` (markerHits and stationReport). */
function routed(path, entries) {
  const hits = markerHits(path, entries).filter((hit) => hit.row === "secrets").map((hit) => hit.line);
  const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, entries]]) });
  return { hits, humanOnly: report.humanOnly };
}
/** The whole of `text` added as a new file, its lines as git's diff hands them: split at LF. */
const asNew = (text) => text.split("\n").map((line, i) => ({ line: i + 1, text: line }));
/** Only git's line `n` of `text` changes; the lines above it are unchanged context (changeset.mjs addedWithContext). */
const onlyLine = (text, n) => addedWithContext(new Map([[n, text.split("\n")[n - 1]]]), `${text}\n`);
/** Git's last line of `text` that names a password, in any case, 1-based: the line a case is about. */
const passwordLine = (text) => text.split("\n").findLastIndex((line) => line.toLowerCase().includes(NAME)) + 1;
/**
 * `text`'s last line that names a password counts, whole and where only that line changes; whole, so do the lines of
 * `also` (a line where a name's text touches a piece of PHP: doors.mjs edgeNames).
 */
function counts(text, what, also = []) {
  const n = passwordLine(text);
  assert.deepEqual(routed(PAGE, asNew(text)), hitAt(...new Set([...also, n].sort((a, b) => a - b))), `${what}: whole: ${JSON.stringify(text)}`);
  assert.deepEqual(routed(PAGE, onlyLine(text, n)), hitAt(n), `${what}: only line ${n}`);
}
/** `text` is no hit, whole and where only its last line that names a password changes. */
function clear(text, what) {
  const n = passwordLine(text);
  assert.deepEqual(routed(PAGE, asNew(text)), NONE, `${what}: whole: ${JSON.stringify(text)}`);
  if (n) assert.deepEqual(routed(PAGE, onlyLine(text, n)), NONE, `${what}: only line ${n}`);
}
/** PHP's tokens of `text`: one T_INLINE_HTML over all of it, so phpHtml reads the case as written. */
function wholeInlineHtml(text) {
  assert.deepEqual(tokenize([text]), [[["T_INLINE_HTML", text.length]]], `PHP reads ${JSON.stringify(text)} as one piece of inline HTML`);
}

/** A template whose interpolation holds a regular expression: JavaScript's plain subset ends there. */
const UNPLAIN = `const t = ${B}\${/x/.test("x")}${B};`;
const REF = `const ${NAME} = document.getElementById('pw').value;`;
const getter = () => `function ${GETTER}() { return ${literal()}; }`;
/** Password getters that return a literal, which no "=" gives a value: a class's method, an object's getter, a function. */
const getters = () => [
  ["a method", `class Login {\n  ${GETTER}() { return ${literal()}; }\n}`],
  ["an object's getter", `const login = { get ${NAME}() { return ${literal()}; } };`],
  ["a function", `function ${GETTER}() { return ${literal()}; }`],
];

test("inside <svg> and <math>, <style/> is an element a \"/>\" closes, no raw text, so the <script> after it is found: a function that names a password and returns a literal counts, on the tag's line and below it, whole and where only its line changes; where HTML's reading of foreign content parts from HTML's own (a script, a raw text element's name, an integration point, a breakout tag, CDATA, an end tag of no open element) the block leaves the plain subset, so a reference after it counts too", () => {
  // Read as raw text, <style/> and <title> in foreign content would run to the end and hide the script; an SVG script's
  // code is read with its character references, not as an HTML script's.
  const probe = `<svg><style/><foreignObject><script>\n${getter()}\n</script></foreignObject></svg>`;
  wholeInlineHtml(probe);
  counts(probe, "the probe");
  counts(`<svg><style/><foreignObject><script>${getter()}</script></foreignObject></svg>`, "the probe on one line");
  counts(`<math><style/><mi><script>\n${getter()}\n</script></mi></math>`, "MathML: <style/>, then a text integration point");
  // Foreign content's own script: its code is read with its character references (&quot; is a quote) and CDATA.
  counts(`<svg><script>\nvar ${NAME} = &quot;v0123&quot;;\n</script></svg>`, "an SVG script's character references");
  counts(`<svg><script><![CDATA[\nvar ${NAME} = ${literal()};\n]]></script></svg>`, "an SVG script's CDATA");
  // A reference after a construct HTML's reading of foreign content reads otherwise than phpHtml: the plain subset ends.
  for (const [label, open] of [
    ["<style/> and foreignObject", "<svg><style/><foreignObject>"],
    ["<desc>", "<svg><desc>"],
    ["<title>", "<svg><title>"],
    ["a breakout tag, <p>", "<svg><p>"],
    ["an end tag of no open element", "<svg><g></div>"],
    ["CDATA", "<svg><![CDATA[ x ]]>"],
    ["an unquoted value's slash, which closes nothing (<svg a=b/>), then <style>", "<svg a=b/><style>x</style>"],
  ]) {
    counts(`${open}<script>\n${REF}\n</script>`, `${label}: a reference`);
    counts(`${open}<script>\n${UNPLAIN}\nconst ${NAME} = t;\n</script>`, `${label}: a template holding a regular expression`);
  }
});

test("controls for <svg>: an icon (<svg> with <g>, <path/>, a nested <svg/>, a comment, its end tag) or a self-closed <svg/> leaves HTML's reading known, so a page script after it reads in JavaScript's plain subset: a reference is no hit, a literal counts, a template holding a regular expression counts", () => {
  const icon = `<svg viewBox="0 0 24 24" fill="none"><g><path d="M0 0h24v24H0z"/><circle cx="1" cy="1" r="1"></circle></g><svg/><!-- icon --></svg>`;
  for (const [label, before] of [
    ["an icon, then a line break", `${icon}\n`],
    ["an icon on the script's line", icon],
    ["<svg/>, then a style element", "<svg/><style>a { color: red }</style>"],
    ["an icon in a link", `<a href="?x=1">${icon}</a>\n`],
  ]) {
    clear(`${before}<script>\n${REF}\n</script>`, `${label}: a reference`);
    counts(`${before}<script>\nconst ${NAME} = ${literal()};\n</script>`, `${label}: a literal`);
    counts(`${before}<script>\n${UNPLAIN}\nconst ${NAME} = t;\n</script>`, `${label}: a template holding a regular expression`);
  }
  // An <svg> the text ends in, and PHP's code after it.
  clear(`<svg><path d="M0 0"/>\n<?php\n$${NAME} = getenv('X');`, "PHP after an <svg> left open: a reference");
  counts(`<svg><path d="M0 0"/>\n<?php\n$${NAME} = ${literal()};`, "PHP after an <svg> left open: a literal");
});

test("HTML's names are folded A to Z alone, so U+0130 (which toLowerCase() makes two characters) moves no place: ten of them before <script></script> do not throw, and a page script after them, in an attribute or in its own code reads where it stands", () => {
  // toLowerCase() makes each U+0130 two characters, which puts every later place one off for each: with ten of them
  // before <script></script>, markerHits would throw (TypeError: Cannot read properties of undefined).
  const probe = `${"\u0130".repeat(10)}<script></script>`;
  wholeInlineHtml(probe);
  assert.deepEqual(routed(PAGE, asNew(probe)), NONE, "ten U+0130, then <script></script>");
  const dotted = "\u0130".repeat(10);
  counts(`${dotted}<script>\nconst ${NAME} = ${literal()};\n</script>`, "a literal after them");
  clear(`${dotted}<script>\n${REF}\n</script>`, "a reference after them");
  counts(`${dotted}<script>\n${UNPLAIN}\nconst ${NAME} = t;\n</script>`, "a template holding a regular expression after them");
  counts(`<p title="${dotted}">x</p>\n<script>\nconst ${NAME} = ${literal()};\n</script>`, "in an attribute, then a literal");
  clear(`<p title="${dotted}">x</p>\n<script>\n${REF}\n</script>`, "in an attribute, then a reference");
  counts(`<script>\nvar a = "${dotted}"; const ${NAME} = ${literal()};\n</script>`, "in the script's code, a literal after them on the line");
  clear(`<script>\nvar a = "${dotted}"; ${REF}\n</script>`, "in the script's code, a reference after them on the line");
});

test("a string's text folds A to Z alone too (namesInText): a password a connection string gives a value after U+0130 counts, a reference there stays no hit", () => {
  // toLowerCase() would move the name's place, so the text after it would not be read as its value.
  const path = "includes/db.php";
  const text = `<?php\n$dsn = "\u0130 host=db ${NAME}=secret";`;
  assert.deepEqual(routed(path, asNew(text)), hitAt(2), "a literal");
  assert.deepEqual(routed(path, onlyLine(text, 2)), hitAt(2), "a literal, only its line");
  const ref = `<?php\n$dsn = "\u0130 host=db ${NAME}=$pw";`;
  assert.deepEqual(routed(path, asNew(ref)), NONE, "a reference");
  assert.deepEqual(routed(path, onlyLine(ref, 2)), NONE, "a reference, only its line");
});

test("a tag is read as HTML's tokenizer reads it: a \"=\" before an attribute's name is the name's, so <a =\"x> ends at its first \">\" and the <script> after it is found; a quoted value's \">\" stays the value's", () => {
  // Read as the attribute's quoted value, "x><script>..." would hide the script.
  counts(`<a ="x><script>\n${getter()}\n</script>">`, "<a =\"x>, a literal");
  counts(`<a ="x><script>\n${UNPLAIN}\nconst ${NAME} = t;\n</script>">`, "<a =\"x>, a template holding a regular expression");
  counts(`<p class=x ="y><script>\n${getter()}\n</script>">`, "a \"=\" after an unquoted value and a space");
  // Controls: a quoted value that holds ">" ends where its quote does.
  clear(`<a title="x>y" href="?a=1"><script>\n${REF}\n</script>`, "a quoted value holding \">\": a reference");
  counts(`<a title="x>y" href="?a=1"><script>\n${UNPLAIN}\nconst ${NAME} = t;\n</script>`, "a quoted value holding \">\": a template holding a regular expression");
});

test("inline HTML outside a script's code is read as the file's code too, as a comment's text is, since PHP's output may print a <script> tag around it: a function that names a password and returns a literal counts after PHP echoes <script>, after a tag name PHP splits, and in a statement over lines; ordinary HTML that names a password (a form, a label, a link, prose) stays no hit", () => {
  // Inline HTML read only for a password's name before "=", "=>" or ":" would miss each counting case. "function"
  // touches the first piece (its closing tag takes the line break), and "scr" and "ipt" the second, so line 1 counts too.
  counts(`<?php echo '<script>'; ?>\n${getter()}\n</script>`, "after PHP echoes <script>", [1]);
  counts(`<scr<?php ?>ipt>\n${getter()}\n</script>`, "after <scr<?php ?>ipt>", [1]);
  counts(`<p>Don't keep it here</p> ${getter()}`, "after prose with an apostrophe, on its line");
  const overLines = `<?php echo '<script>'; ?>\nvar ${NAME}\n  = ${literal()};\n</script>`;
  // "var" touches the piece, so its line counts too.
  assert.deepEqual(routed(PAGE, asNew(overLines)), hitAt(1, 2), "a statement over lines");
  // Controls: ordinary markup that names a password needs no person.
  for (const [label, html] of [
    ["a label and an input", `<label for="pw">Password</label>\n<input type="${NAME}" name="${NAME}" value="">`],
    ["prose", `<p>Don't share your ${NAME} with anyone.</p>`],
    ["a table cell", `<td>Password</td><td><input type=${NAME} name=pw></td>`],
    ["a link", `<a href="?side=${NAME}">Skift ${NAME}</a>`],
    ["a cell PHP prints", `<td><?= htmlspecialchars($v) ?></td>\n<script>\n${REF}\n</script>`],
  ]) {
    clear(html, label);
  }
  // A piece of PHP in a tag leaves HTML's state after it unknown, so the line it stands on and every later line that
  // names a password count.
  for (const [label, html, also] of [
    ["an input whose value PHP prints", `<input type="${NAME}" name="${NAME}" value="<?= htmlspecialchars($pw) ?>">`],
    ["PHP's attributes in a tag's name position", `<tr<?php echo $hidden; ?>><td>x</td></tr>\n<script>\n${REF}\n</script>`, [1]],
  ]) {
    // "tr" touches the piece in the tag's name position, so its line counts too.
    counts(html, label, also);
  }
});

test("where HTML's tree may ignore a raw text element or a script (a raw text element inside a <select>, which older parsers ignore there, and a <frameset>), its reading is not known: a reference after it counts; a <select> closed before them changes nothing", () => {
  // Read as raw text that ends at </style>, the <style> in a <select> would leave the reading known, and so would a
  // <frameset> with the script after it read as a page script: the reference would be no hit.
  for (const [label, open] of [
    ["a style element in a <select>", `<select name="pw"><style>a { color: red }</style>`],
    ["a title element in a <select>", "<select><title>x</title>"],
    ["a <frameset>", `<frameset cols="50%,50%"><frame src="a.php"></frameset>\n`],
  ]) {
    counts(`${open}<script>\n${REF}\n</script>`, `${label}: a reference`);
  }
  // Controls: a <select> closed before them, and a textarea in one (which closes it), leave the reading known.
  for (const [label, before] of [
    ["a <select> closed before", `<select name="pw"><option>Password</option></select><style>a { color: red }</style>\n`],
    ["a textarea in a <select>", `<select name="pw"><textarea>x</textarea>\n`],
  ]) {
    clear(`${before}<script>\n${REF}\n</script>`, `${label}: a reference`);
    counts(`${before}<script>\nconst ${NAME} = ${literal()};\n</script>`, `${label}: a literal`);
  }
});

test("a <plaintext> element runs to the end of the page: a <script> after it is its text, which counts where a password's name stands before \"=\"", () => {
  // Ended at "</plaintext", with the script after it read as JavaScript, the reference would be no hit.
  counts(`<plaintext></plaintext><script>\n${REF}\n</script>`, "a reference in <plaintext>'s text");
});

test("a literal NUL in a tag's name is HTML's U+FFFD, no end of the name, so <style\\0><script> is no style element and the script after it is found: a password getter that returns a literal (a method, an object's getter, a function) counts, whole and where only its line changes", () => {
  // A name that ended at the NUL would make <style\0> a <style> and the script its raw text: the method and the
  // object's getter would be no hit, humanOnly false. The function counts either way (inline HTML is read as PHP's code).
  const probe = `<style${NUL}><script>\n${getters()[0][1]}\n</script>`;
  assert.deepEqual(tokenize([probe]), [[["T_INLINE_HTML", probe.length]]], "PHP reads the probe as one piece of inline HTML");
  counts(probe, "the probe");
  for (const name of ["style", "title", "textarea", "xmp", "iframe", "noembed", "noframes", "noscript", "plaintext"]) {
    for (const [label, code] of getters()) counts(`<${name}${NUL}><script>\n${code}\n</script>`, `<${name}\\0>: ${label}`);
  }
  for (const [label, code] of getters()) {
    counts(`<style ${NUL}><script>\n${code}\n</script>`, `a NUL after the name and a space: ${label}`);
    counts(`<style${NUL}><script>${code}</script>`, `on one line: ${label}`);
  }
});

test("HTML's reading is not known from a literal NUL in inline HTML on (browsers have read one otherwise than HTML's tokenizer): <script\\0>, <svg\\0>, </style\\0> and a NUL in a comment or an attribute leave the plain subset, so a getter and a reference after one count; a page without one reads as before", () => {
  // HTML's tokenizer reads <script\0> and <svg\0> as elements of no special kind (U+FFFD in the name), so a U+FFFD
  // reading alone would hide the code after them; after </style\0> the style element's text would run on and hide the
  // script.
  for (const [label, before] of [
    ["<script\\0>", `<script${NUL}>\n`],
    ["<svg\\0><style/>", `<svg${NUL}><style/><script>\n`],
    ["</style\\0>", `<style>a { color: red }</style${NUL}><script>\n`],
    ["a comment", `<!-- ${NUL} --><script>\n`],
    ["an attribute's name", `<p a${NUL}b="x"><script>\n`],
  ]) {
    for (const [what, code] of getters()) counts(`${before}${code}\n</script>`, `${label}: ${what}`);
    counts(`${before}${REF}\n</script>`, `${label}: a reference`);
  }
  // Controls: no NUL, so the plain subset holds: a getter counts and a reference is no hit.
  for (const [what, code] of getters()) counts(`<style>a { color: red }</style><script>\n${code}\n</script>`, `no NUL: ${what}`);
  clear(`<style>a { color: red }</style><script>\n${REF}\n</script>`, "no NUL: a reference");
});

test("a piece of PHP that finishes a tag's name or a comment's end where it prints nothing, or whose output may finish a name HTML's reading turns on, leaves the plain subset: a password getter after <scr<?php ?>ipt>, <style<?php ?>>, <sty<?php ?>le>, </sty<?php ?>le>, </style<?php ?>>, <<?php ... ?> or --<?php ?>> counts, whole and where only its line changes", () => {
  // Where the piece's character ends the name, <style<?php ?>> reads as a style element, </sty<?php ?>le> as no end
  // tag, <scr<?php ?>ipt> as no script and "--<?php ?>>" as no comment's end, and the method and the object's getter
  // after them would be no hit. Where a tag's name touches the piece (all but "<<?php" and "--<?php"), line 1 counts too.
  for (const [label, page, also] of [
    ["<scr<?php ?>ipt>", (code) => `<scr<?php ?>ipt>\n${code}\n</script>`, [1]],
    ["<style<?php ?>>", (code) => `<style<?php ?>><script>\n${code}\n</script>`, [1]],
    ["<style<?php echo 'x'; ?>>", (code) => `<style<?php echo 'x'; ?>><script>\n${code}\n</script>`, [1]],
    ["<sty<?php ?>le>", (code) => `<sty<?php ?>le><script>\n${code}\n</script>`, [1]],
    ["</sty<?php ?>le>", (code) => `<style>a</sty<?php ?>le><script>\n${code}\n</script>`, [1]],
    ["</style<?php ?>>", (code) => `<style>a</style<?php ?>><script>\n${code}\n</script>`, [1]],
    ["<<?php echo $tag; ?>>", (code) => `<<?php echo $tag; ?>>\n${code}\n</script>`, []],
    ["--<?php ?>>", (code) => `<!-- a --<?php ?>><script>\n${code}\n</script> -->`, []],
  ]) {
    for (const [what, code] of getters()) counts(page(code), `${label}: ${what}`, also);
  }
});

test("controls: pieces of PHP in HTML's data state (an element's text, after a \"<\" and a space) leave the reading known: a reference in the script after them is no hit and a literal counts; pieces in a tag's attributes, a tag's name position, an end tag, a comment, a style or a script's code leave it unknown, so a reference after them counts, and so does a password PHP prints into a script", () => {
  for (const [label, before] of [
    ["a cell PHP prints", `<td><?php echo $x ?></td>\n`],
    ["a \"<\" and a space before a piece", `<p>a < <?php echo $b ?></p>\n`],
  ]) {
    clear(`${before}<script>\n${REF}\n</script>`, `${label}: a reference`);
    counts(`${before}<script>\nconst ${NAME} = ${literal()};\n</script>`, `${label}: a literal`);
  }
  // Where a tag's name touches the piece ("tr", "b"), line 1 counts too.
  for (const [label, before, also = []] of [
    ["a script whose code PHP prints", `<script><?php echo $js ?></script>\n`],
    ["an option's attributes", `<option value="1"<?php echo $sel ?>>One</option>\n`],
    ["an attribute's value and an element's text", `<div class="<?php echo $c ?>"><?php echo $t ?></div>`],
    ["a comment", `<!-- <?php echo $note ?> -->\n`],
    ["a style element", `<style><?php echo $css ?></style>\n`],
    ["a script's operand", `<script>var a = <?php echo json_encode($a) ?>;</script>\n`],
    ["<tr<?php echo $hidden; ?>>", `<tr<?php echo $hidden; ?>><td>x</td></tr>\n`, [1]],
    ["</b<?php ?>>", `<b>x</b<?php ?>>\n`, [1]],
  ]) {
    counts(`${before}<script>\n${REF}\n</script>`, `${label}: a reference`, also);
  }
  // A password input PHP fills: its own line names a password where the reading stops being known, so it counts in the
  // whole page, and the reference after it counts too.
  const input = `<input type="${NAME}" name="pw" value="<?php echo $v ?>">\n<script>\n${REF}\n</script>`;
  assert.deepEqual(routed(PAGE, asNew(input)), { hits: [1, 3], humanOnly: true }, "a password input PHP fills: whole");
  assert.deepEqual(routed(PAGE, onlyLine(input, 3)), hitAt(3), "a password input PHP fills: only line 3");
  counts(`<script>\nconst ${NAME} = <?php echo json_encode($pw) ?>;\n</script>`, "a password PHP prints into the script");
});
