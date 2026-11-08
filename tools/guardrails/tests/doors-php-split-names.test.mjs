// tools/guardrails/tests/doors-php-split-names.test.mjs - a piece of PHP inside an identifier or a string leaves the state
// unknown, and the text joined across it is read for a password's name (lib/doors.mjs phpHtml: insidePiece, splitNames;
// namedOf).
//
// A password's name a piece of PHP splits (getPass<?php ?>word()) names no password on any one line, so a getter of
// that name returning a literal, in a script PHP prints or a page's <script> element, must be read across the piece.
// And a piece inside a string must not let the string's close read as an opening that hides a getter after it. So a
// piece of PHP inside an identifier (an identifier's character on each side of it) or a string (a quote opened before
// it on its line, or a template over lines, not closed) leaves the state unknown from there, so the block leaves the
// plain subset at it, and a line names a password where its text with its pieces of PHP taken out does, or a name spelt
// across a piece over lines stands on it. A piece in data that is neither (between tags, after a closed string, after
// a prose apostrophe) is text; one that a name's text touches on either side leaves that name unresolved, and the
// state is not known from its start (doors-php-edge-names.test.mjs).
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
const HEAD = NAME.slice(0, 4);
const TAIL = NAME.slice(4);
const GETTER = `get${HEAD[0].toUpperCase()}${HEAD.slice(1)}`;
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
/**
 * `text`'s line `n` counts, whole and where only that line changes; whole, so do the lines of `also` (the lines a
 * piece of PHP and a name's text that touches it stand on, doors.mjs edgeNames).
 */
function counts(text, n, what, also = []) {
  assert.deepEqual(routed(PAGE, asNew(text)), hitAt(...new Set([...also, n].sort((a, b) => a - b))), `${what}: whole: ${JSON.stringify(text)}`);
  assert.deepEqual(routed(PAGE, onlyLine(text, n)), hitAt(n), `${what}: only line ${n}: ${JSON.stringify(text)}`);
}
/** `text` is no hit, whole and where only its line `n` changes. */
function clear(text, n, what) {
  assert.deepEqual(routed(PAGE, asNew(text)), NONE, `${what}: whole: ${JSON.stringify(text)}`);
  assert.deepEqual(routed(PAGE, onlyLine(text, n)), NONE, `${what}: only line ${n}: ${JSON.stringify(text)}`);
}
/** A page whose PHP prints a <script> tag, so its inline HTML may run as the script's code: `code` between, on lines of its own. */
const printed = (code) => `<?php echo '<script>'; ?>${code}\n<?php echo '</script>'; ?>`;
/** A page's <script> element that holds `code`, on lines of its own. */
const element = (code) => `<script>\n${code}\n</script>`;
/** A page whose line 1 holds `html` before PHP's code, which gives a password a plain reference on line 3. */
const beforeReference = (html) => `${html}\n<?php\n$${NAME} = $_POST['pw'];`;

test("a password's name a piece of PHP splits counts where it gives a password a value, in a page's <script> element, in a script PHP prints, in a string, over two lines and spelt with an escape", () => {
  // Read a line at a time with the pieces left in, no line names a password in any of these forms.
  assert.ok(tokenize(["<?php $x;"])[0], "PHP's tokenizer runs (lib/php.mjs: PHP 8.2 or Docker)");
  const split = `${GETTER}<?php ?>${TAIL}`;
  counts(element(`class Login { ${split}() { return ${literal()}; } }`), 2, "a method in a <script> element");
  counts(printed(`class Login { ${split}() { return ${literal()}; } }`), 1, "a method in a printed script");
  counts(printed(`const login = { get ${HEAD}<?= '' ?>${TAIL}() { return ${literal()}; } };`), 1, "a getter, the piece printing nothing");
  counts(printed(`var dsn = '${HEAD}<?= '' ?>${TAIL}=v${randomBytes(4).toString("hex")}';`), 1, "a connection string's key");
  counts(printed(`class Login { ${GETTER}<?php ?>\\u0077ord() { return ${literal()}; } }`), 1, "the name's rest spelt with an escape");
  // A piece over two lines: the name stands on both, and each counts where it changes.
  const over = printed(`class Login { ${GETTER}<?php\n?>${TAIL}() { return ${literal()}; } }`);
  assert.deepEqual(routed(PAGE, asNew(over)), hitAt(1, 2), "a piece over lines, whole");
  assert.deepEqual(routed(PAGE, onlyLine(over, 1)), hitAt(1), "a piece over lines, its first line changed");
  assert.deepEqual(routed(PAGE, onlyLine(over, 2)), hitAt(2), "a piece over lines, its second line changed");
  // A reference split so is outside the plain subset (the piece is inside an identifier): it counts as any name there.
  counts(printed(`var ${split} = document.getElementById('pw').value;`), 1, "a reference split by a piece");
});

test("a piece of PHP inside a string or an identifier in HTML's data state leaves the state unknown: a getter after a string's piece in a printed script counts, and so does a plain reference below such a piece", () => {
  // Read as text, the string's close after the piece would read as an opening and hide the getter, and the reference
  // below a piece in data would be no hit.
  const getter = `class Login { ${GETTER}${TAIL}() { return ${literal()}; } }`;
  counts(printed(`var prefix = '<?= $x ?>'; ${getter}`), 1, "after a piece in a single-quoted string");
  counts(printed(`var prefix = "a<?= $x ?>b"; ${getter}`), 1, "after a piece in a double-quoted string");
  const template = printed(`var prefix = \`\n<?= $x ?>\`; ${getter}`);
  // "var" touches the echo's piece, and "row", "x" and "a" the pieces in data, so line 1 counts too.
  counts(template, 2, "after a piece in a template over lines", [1]);
  counts(beforeReference("<p>row<?= $i ?>x</p>"), 3, "a reference below a piece inside an identifier", [1]);
  counts(beforeReference("<p>'a<?= $x ?>'</p>"), 3, "a reference below a piece inside a string", [1]);
  counts(beforeReference("<p>'a\\' <?= $x ?></p>"), 3, "a reference below a piece after an escaped quote");
});

test("controls: a piece of PHP in data that is neither inside an identifier nor a string is text, so a plain reference below it is no hit (a plain password reference needs no person); a name no piece splits is read as written", () => {
  for (const [label, html] of [
    ["between tags", "<td><?= $x ?></td>"],
    ["after a closed string", "<p>'a' <?= $x ?></p>"],
    ["after a prose apostrophe", "<p>Don't <?= $x ?></p>"],
    ["a quote on the line above", "<p>'</p>\n<p><?= $x ?></p>"],
    ["between two words with spaces", "<p>pass <?= $x ?> word</p>"],
  ]) {
    const text = beforeReference(html);
    clear(text, text.split("\n").length, label);
  }
  // A word touching a piece on one side: what the piece prints may complete it as a password name, so the name is not
  // resolved, its line counts, and the state is not known from it: the plain reference below counts too (where a
  // name's text touches a piece, default deny outweighs the rule that a plain reference needs no person).
  for (const [label, html] of [
    ["touching a word on one side", "<p>Total<?= $x ?></p>"],
    ["touching a word on the other side", "<p><?= $x ?>kr</p>"],
  ]) {
    const text = beforeReference(html);
    assert.deepEqual(routed(PAGE, asNew(text)), hitAt(1, 3), `${label}: whole`);
    assert.deepEqual(routed(PAGE, onlyLine(text, 1)), hitAt(1), `${label}: only line 1`);
    assert.deepEqual(routed(PAGE, onlyLine(text, 3)), hitAt(3), `${label}: only line 3`);
  }
  // The same getter's name written whole, after a piece in data: the getter counts.
  counts(printed(`var a = 1;<?= '' ?> class Login { ${GETTER}${TAIL}() { return ${literal()}; } }`), 1, "a whole name after a piece");
  // A space after the echo's piece, so no name's text touches it. As written ("class" touching it) the name there is
  // not resolved, and the getter's line counts.
  clear(printed(` class Login { ${GETTER}${TAIL}() { return this.x; } }`), 1, "a whole name returning a reference, no piece");
  counts(printed(`class Login { ${GETTER}${TAIL}() { return this.x; } }`), 1, "a whole name returning a reference, no piece, as written");
});

test("where PHP's tokenizer does not run, a line counts where its text with each piece the text shows taken out names a password", () => {
  // The line's own text names no password; with the piece taken out it does.
  const before = usePhpTokenizer(null);
  try {
    const text = printed(`class Login { ${GETTER}<?php ?>${TAIL}() { return this.x; } }`);
    assert.deepEqual(routed(PAGE, asNew(text)), hitAt(1), "whole");
    assert.deepEqual(routed(PAGE, onlyLine(text, 1)), hitAt(1), "only its line");
    assert.match(phpFailure() ?? "", /lib\/php\.mjs/, "phpFailure says why");
  } finally {
    usePhpTokenizer(before);
  }
});

test("the pieces are read in linear time: a page of many pieces in data, in strings, inside identifiers and splitting names grows no faster than linearly (linear-time.mjs)", () => {
  // insidePiece reads the joined inline HTML once, splitNames once, and namedOf each line's pieces once.
  const page = (n) => {
    const rows = [];
    for (let i = 0; i < n; i++) rows.push(`<td><?= $a${i} ?></td><td>it's <?= $b ?></td>`);
    rows.push(`<p>'<?= $x ?>'</p>`);
    for (let i = 0; i < n; i++) rows.push(`<td>${GETTER}<?php ?>${TAIL}${i}</td><td>x<?= $i ?>y</td>`);
    rows.push("<?php", `$${NAME} = $_POST['pw'];`);
    return rows.join("\n");
  };
  // At 16,000 rows a side the reading spends more than FLOOR_MS of CPU, so its growth is judged.
  const hits = linear("pieces", (n) => routed(PAGE, asNew(page(n))).hits.length, 16000);
  // Every line from the string's piece on names a password (split) or is the reference: 16,000 split lines and the reference.
  assert.equal(hits, 16001);
});
