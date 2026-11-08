// tools/guardrails/tests/doors-php-split-escapes.test.mjs - a password name a piece of PHP splits leaves the state
// unknown whatever stands beside the piece, an identifier's escape ending or open there included, and where PHP's
// tokenizer does not run a "<?" that only short_open_tag makes a piece's start hides no later piece (lib/doors.mjs
// phpHtml, escapeBefore and insidePiece; textNames and textReading; phpBlock).
//
// What it pins:
// 1. A piece with an identifier's character on each side is inside an identifier, but a braced escape's "}" right
//    before it (get\u{50}<?php ?>assword()) is no such character. So any password name read across a piece leaves the
//    state unknown from its start, whatever stands beside the piece: after PHP prints <script>, a method of that name
//    returning a literal counts, on one line and with the piece over two, whole and as a changed line.
// 2. Where PHP's tokenizer does not run, the text is read twice, once with every "<?" a piece's start and once with
//    only those that open PHP's code with short_open_tag off. So a "<?" in a script's string (const x = '<?';) that
//    runs to the "?>" of a piece splitting a name over lines does not take the name's start with it.
// A piece of PHP that a name's text touches leaves that name unresolved, whatever the text holds (doors.mjs edgeNames);
// a symbol past U+FFFF is no name's text (doors.mjs nameTextBack reads a surrogate pair whole).
//
// History:
// 20261107 CL/LH Created.
import test from "node:test";
import assert from "node:assert/strict";
import { randomBytes } from "node:crypto";
import { tokenize } from "../lib/php.mjs";
import { markerHits, stationReport } from "../lib/station.mjs";
import { addedWithContext } from "../lib/changeset.mjs";
import { phpFailure, usePhpTokenizer } from "../lib/doors.mjs";
import { linear } from "./linear-time.mjs";

// Built at run time, so this file holds no literal for the marker to find.
const NAME = ["pass", "word"].join("");
const TAIL = NAME.slice(4);
/** "assword": the name's rest after its first letter, which an escape spells. */
const REST = NAME.slice(1);
const GETTER = `get${NAME[0].toUpperCase()}${NAME.slice(1, 4)}`;
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
/** Only git's line `n` of `text` changes, as check.mjs hands it (changeset.mjs addedWithContext). */
const onlyLine = (text, n) => addedWithContext(new Map([[n, text.split("\n")[n - 1]]]), `${text}\n`);
/** A page whose PHP prints a <script> tag, so its inline HTML may run as the script's code: `code` between, on lines of its own. */
const printed = (code) => `<?php echo '<script>'; ?>${code}\n<?php echo '</script>'; ?>`;
/** A page's <script> element that holds `code`, on lines of its own. */
const element = (code) => `<script>\n${code}\n</script>`;
/** A class whose method `name` (its text as written, pieces of PHP and escapes in it) returns a literal. */
const method = (name) => `class Login { ${name}() { return ${literal()}; } }`;

/**
 * `text` counts on exactly the lines `named` (git's, 1-based) whole, and where any one of them alone changes it counts
 * there, as check.mjs routes it.
 */
function countsOn(text, named, what) {
  assert.deepEqual(routed(PAGE, asNew(text)), hitAt(...named), `${what}: whole: ${JSON.stringify(text)}`);
  for (const n of named) assert.deepEqual(routed(PAGE, onlyLine(text, n)), hitAt(n), `${what}: only line ${n}: ${JSON.stringify(text)}`);
}
/** `text` is no hit whole, and where any one of its lines alone changes. */
function clearAll(text, what) {
  assert.deepEqual(routed(PAGE, asNew(text)), NONE, `${what}: whole: ${JSON.stringify(text)}`);
  text.split("\n").forEach((_, i) => assert.deepEqual(routed(PAGE, onlyLine(text, i + 1)), NONE, `${what}: only line ${i + 1}: ${JSON.stringify(text)}`));
}
/** Run `fn` as where PHP's tokenizer cannot run (lib/php.mjs not loaded: phpFailure), then put the tokenizer back. */
function withoutTokenizer(fn) {
  const before = usePhpTokenizer(null);
  try {
    fn();
    assert.match(phpFailure() ?? "", /lib\/php\.mjs/, "phpFailure says why");
  } finally {
    usePhpTokenizer(before);
  }
}

/** A name a piece of PHP splits beside or inside an identifier's escape, `<?php ?>` standing for the piece: [label, name]. */
const escapes = (piece) => [
  ["a braced escape's } right before the piece", `get\\u{50}${piece}${REST}`],
  ["the piece inside a braced escape, after its {", `get\\u{${piece}50}${REST}`],
  ["the piece inside a braced escape, before its }", `get\\u{50${piece}}${REST}`],
  ["the piece after \\u, a braced escape's digits after it", `get\\u${piece}{50}${REST}`],
  ["a braced escape on each side of the piece", `get\\u{50}${piece}\\u{61}${REST.slice(1)}`],
  ["a braced escape for the name's first letter", `\\u{70}${piece}${REST}`],
];

test("with PHP's tokenizer, a password name split by a piece of PHP beside or inside a braced escape leaves the state unknown, so a method of that name returning a literal counts, one line and over lines, in a printed script and a <script> element, whole and as a changed line", () => {
  // In a script PHP prints, the piece stands in HTML's data state: the name read across it is what leaves the state
  // unknown there.
  assert.ok(tokenize(["<?php $x;"])[0], "PHP's tokenizer runs (lib/php.mjs: PHP 8.2 or Docker)");
  for (const [label, name] of escapes("<?php ?>")) countsOn(printed(method(name)), [1], `${label}, printed`);
  for (const [label, name] of escapes("<?php\n?>")) countsOn(printed(method(name)), [1, 2], `${label}, the piece over lines, printed`);
  // A variable and a getter of that name given a literal, a braced escape before the piece.
  countsOn(printed(`var \\u{70}<?php ?>${REST} = ${literal()};`), [1], "a variable given a literal");
  countsOn(printed(`const login = { get \\u{70}<?php ?>${REST}() { return ${literal()}; } };`), [1], "an object's getter");
  // In a page's <script> element a piece in the script's code is outside HTML's data state, so the state there is not
  // known already, and the name read across it counts.
  for (const [label, name] of escapes("<?php ?>")) countsOn(element(method(name)), [2], `${label}, a <script> element`);
  for (const [label, name] of escapes("<?php\n?>")) countsOn(element(method(name)), [2, 3], `${label}, the piece over lines, a <script> element`);
});

test("in general: any password name read across a piece of PHP (its escapes, NFKC letters and noise read) leaves the state unknown from its start, whatever stands beside the piece; a name across a piece that is no password's, and a braced escape beside a piece in no name, count where a name's text touches the piece, and are no hit where no name's text touches it", () => {
  // Python's named escape (\N{...}), which namesPassword reads as its letter, ends with a "}" that is no JavaScript
  // escape's, so by its neighbours the piece stands inside no identifier: the name found across it is what leaves the
  // state unknown.
  countsOn(printed(method(`get\\N{LATIN CAPITAL LETTER P}<?php ?>${REST}`)), [1], "a named escape before the piece");
  countsOn(printed(method(`get\\N{LATIN CAPITAL LETTER P}<?php\n?>${REST}`)), [1, 2], "a named escape before the piece, over lines");
  // A line separator beside the piece is a line terminator JavaScript's reading leaves the plain subset at.
  countsOn(printed(method(`${GETTER}\u2028<?php ?>${TAIL}`)), [1], "a line separator before the piece");
  // What a piece prints may complete the name's text that touches it, whatever that text holds (an escape's, a name
  // that is no password's).
  countsOn(printed(method(`get\\u{50}<?php ?>ort`)), [1], "a braced escape before a piece, no password's name across it");
  countsOn(printed(method(`${GETTER}<?php ?>port`)), [1], "a name across a piece that is no password's");
  // As written, "class" touches the echo's piece: the name there is not resolved, and the getter's line counts.
  countsOn(printed(`class Login { get\\u{50}${REST}() { return this.x; } }`), [1], "an escaped name no piece splits, returning a reference, after a piece \"class\" touches");
  // In data too: the escape's line counts, and from it the state is not known, so a line below it counts where it alone
  // changes (outsidePlain: below an unchanged line that names a password).
  const escapeInData = "<td>\\u{50}<?= $x ?></td>\n<?php\n$a = 1;";
  assert.deepEqual(routed(PAGE, asNew(escapeInData)), hitAt(1), "an escape's text before a piece in data: whole");
  assert.deepEqual(routed(PAGE, onlyLine(escapeInData, 1)), hitAt(1), "an escape's text before a piece in data: only line 1");
  assert.deepEqual(routed(PAGE, onlyLine(escapeInData, 3)), hitAt(3), "an escape's text before a piece in data: only line 3");
  // Controls: no name's text touches the piece, an escape a space before it or not.
  clearAll(printed(" " + method("get\\u{50}ort <?php ?> x")), "a braced escape a space before a piece, no password's name");
  clearAll(printed(` class Login { get\\u{50}${REST}() { return this.x; } }`), "an escaped name no piece splits, returning a reference");
  clearAll("<td>\\u{50} <?= $x ?></td>\n<?php\n$a = 1;", "an escape's text a space before a piece in data, no name");
});

/** A page whose line 1 holds `html` before PHP's code, which gives a password a plain reference on line 3. */
const beforeReference = (html) => `${html}\n<?php\n$${NAME} = $_POST['pw'];`;

test("insidePiece: an identifier's character beside a piece is read a code point at a time and as JavaScript's ID_Continue, so a letter past U+FFFF or a combining mark puts the piece inside an identifier, as a letter does: a plain reference below then counts, as below any piece inside one (doors-php-split-names.test.mjs)", () => {
  // Read a UTF-16 unit at a time, a lone surrogate and a mark would be no identifier's characters, so the piece would
  // be text and the reference below no hit.
  for (const [label, html] of [
    ["a letter past U+FFFF before the piece", "<p>row\u{1D41A}<?= $i ?>x</p>"],
    ["a letter past U+FFFF after the piece", "<p>row<?= $i ?>\u{1D41A}x</p>"],
    ["a combining mark before the piece", "<p>rowe\u0301<?= $i ?>x</p>"],
    ["a zero-width joiner after the piece", "<p>row<?= $i ?>\u200dx</p>"],
  ]) {
    const text = beforeReference(html);
    // The word touches the piece, so the name there is not resolved and its line counts too.
    assert.deepEqual(routed(PAGE, asNew(text)), hitAt(1, 3), `${label}: whole`);
    assert.deepEqual(routed(PAGE, onlyLine(text, 3)), hitAt(3), `${label}: only line 3`);
  }
  // A word touches each piece ("row", "x"), so the name there is not resolved, its line counts, and the reference below
  // it counts too.
  for (const html of ["<p>row<?= $i ?></p>", "<p>\u{1F600}<?= $i ?>x</p>", "<p>x<?= $i ?>\u{1F600}</p>"]) {
    const text = beforeReference(html);
    assert.deepEqual(routed(PAGE, asNew(text)), hitAt(1, 3), `${JSON.stringify(html)}, a word touching the piece: whole`);
    assert.deepEqual(routed(PAGE, onlyLine(text, 3)), hitAt(3), `${JSON.stringify(html)}, a word touching the piece: only line 3`);
  }
  // Controls: a piece between a space and markup, or beside a symbol past U+FFFF (no name's text, read as a code
  // point), is text.
  for (const html of ["<p>row <?= $i ?></p>", "<p>\u{1F600}<?= $i ?> x</p>", "<p>x <?= $i ?>\u{1F600}</p>", "<p>\u{1F600}<?= $i ?>\u{1F600}</p>"]) {
    const text = beforeReference(html);
    assert.deepEqual(routed(PAGE, asNew(text)), NONE, `${JSON.stringify(html)}: whole`);
    assert.deepEqual(routed(PAGE, onlyLine(text, 3)), NONE, `${JSON.stringify(html)}: only line 3`);
  }
});

/** A page whose PHP prints <script>, then `before` (a "<?" no "?>" follows before the piece), then a method whose name a piece over lines splits. */
const afterShort = (before, piece = "<?php\n?>") => `<?php echo '<script>'; ?>${before} ${method(GETTER + piece + TAIL)}`;
/** The getter's name split by a piece over lines. Built apart, as no template literal here holds another: the readers leave the plain subset at a nested one. */
const SPLIT_OVER = `${GETTER}<?php\n?>${TAIL}`;

test("where PHP's tokenizer does not run, a \"<?\" that only short_open_tag makes a piece's start (in a script's string or comment) hides no later piece: a name split by a piece over lines or on one line counts, whole and as a changed line", () => {
  // Read only with every "<?" a piece's start, the "<?" runs to the "?>" of the piece that splits the name and takes
  // the name's start with it.
  withoutTokenizer(() => {
    countsOn(afterShort("const x = '<?';"), [1, 2], "a single-quoted string");
    countsOn(afterShort('const x = "<?";'), [1, 2], "a double-quoted string");
    countsOn(afterShort("/* <? */"), [1, 2], "a block comment");
    countsOn(afterShort("const x = '<?xml';"), [1, 2], "an XML declaration's start in a string");
    countsOn(afterShort("const x = '<?phpx';"), [1, 2], "\"<?php\" with no whitespace after it");
    // A comment to the line's end: the "<?" on the line above the name. Where every "<?" opens a piece, the "<?xml" one
    // runs to the "?>" of the piece that splits the name, and the name's end after it stands with it (a name a piece
    // completes from one side), so its line counts too.
    countsOn(`<?php echo '<script>'; ?>// <?xml\n${method(SPLIT_OVER)}`, [1, 2, 3], "a line comment above");
    // On one line: the piece that splits the name opens with "<?php" or "<?=", which both readings take as a piece's
    // start.
    countsOn(afterShort("const x = '<?';", "<?php ?>"), [1], "a piece on one line");
    countsOn(afterShort("const x = '<?';", "<?= '' ?>"), [1], "an echo tag on one line");
  });
});

test("with PHP's tokenizer the same pages count, and a \"<?\" read as a piece's start still hides nothing; without it controls stay clear", () => {
  // PHP's tokens show the pieces here.
  countsOn(afterShort("const x = '<?';"), [1, 2], "with the tokenizer, a single-quoted string");
  countsOn(afterShort("/* <? */"), [1, 2], "with the tokenizer, a block comment");
  withoutTokenizer(() => {
    // Short_open_tag on reads "<? ?>" as a piece: a name it splits counts, in the reading with every "<?" a piece's start.
    countsOn(printed(method(`${GETTER}<? ?>${TAIL}`)), [1], "a short tag, no tokenizer");
    // What a piece prints may complete the name's text that touches it, whatever that text holds: "port" after a
    // piece, "const" after the echo's, "kr" after one.
    countsOn(afterShort("const x = '<?';").replace(TAIL, "port"), [1, 2], "a \"<?\" in a string, a name across a piece that is no password's");
    countsOn("<?php echo '<script>'; ?>const x = '<?'; var total = 1;\n<?php echo '</script>'; ?>", [1], "a \"<?\" in a string, \"const\" touching the echo's piece");
    countsOn("<td><?= $x ?></td>\n<td><?php echo 'a'; ?>kr</td>", [2], "pieces between tags, a word after the second");
    // Controls: a "<?" in a string and pieces no name's text touches, and no piece.
    clearAll(`<?php echo '<script>'; ?> const x = '<?'; ${method("getTotal <?php\n?> port")}`, "a \"<?\" in a string, a piece no name's text touches");
    clearAll("<?php echo '<script>'; ?> const x = '<?'; var total = 1;\n<?php echo '</script>'; ?>", "a \"<?\" in a string, no name");
    clearAll("<td><?= $x ?></td>\n<td><?php echo 'a'; ?> kr</td>", "pieces between tags");
  });
});

test("the readings stay linear: escapes beside pieces and the fallback's two readings of \"<?\", over a page of many, grow no faster than linearly (linear-time.mjs)", () => {
  // escapeBefore reads back at most NAME_WINDOW characters a piece; textReading reads the text once, twice in all. The
  // fallback's part guards against reading a name's text back on the text joined so far at each piece whose text holds
  // a quote, after a text that may start no password name ("x = '<?'"): reading a joined string's characters copies all
  // of it, 4.7 s of CPU at 32,000 rows against 0.3 s at 8,000.
  const page = (n) => {
    const rows = [];
    for (let i = 0; i < n; i++) rows.push(`<td>get\\u{50}<?php\n?>${REST}${i}</td><td>x = '<?'; y<?= $i ?>z</td>`);
    return rows.join("\n");
  };
  const hits = linear("escapes beside pieces", (n) => routed(PAGE, asNew(page(n))).hits.length, 8000);
  assert.ok(hits >= 8000, `every name across a piece counts: ${hits}`);
  withoutTokenizer(() => {
    // At 32,000 rows a quadratic reading's growth stands clear of the bound (14 to 16 times for the reading above).
    const fallback = linear("the fallback's two readings", (n) => routed(PAGE, asNew(page(n))).hits.length, 32000);
    assert.ok(fallback >= 32000, `every name across a piece counts without the tokenizer: ${fallback}`);
  });
});
