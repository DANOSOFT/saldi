// tools/guardrails/tests/doors-php-split-over-lines.test.mjs - a name a piece of PHP splits over lines is read with its
// escapes, with and without PHP's tokenizer, and a value still open where the lines a caller hands stop short of the
// file reads as going on (lib/doors.mjs nameSpans, textNames, cutAt; lib/changeset.mjs addedWithContext;
// checks/pr-body.mjs).
//
// What it pins:
// 1. The inline HTML joined across pieces of PHP is read with its escapes, as a line's own name is: a getter
//    getPass<?php<line break>?>word() returning a literal, after PHP prints <script>, counts whole and as a
//    changed line.
// 2. Where PHP's tokenizer does not run, a piece over lines (getPass<?php<line break>?>word()) is taken out whole, not a
//    line at a time, so the name it splits is read.
// 3. Callers that pass a partial patch hand fewer lines than the file holds, so the end of the lines handed is not the
//    file's end where an entry is marked cut or the line numbers leave a gap: a changed line that gives a password's
//    name a reference and no ";", above three blank lines of context and an unseen literal fallback, counts there, as
//    the whole file counts it.
// A piece of PHP that a name's text touches leaves that name unresolved, whatever the text holds (doors.mjs edgeNames),
// and without the tokenizer a piece whose end the text does not tell may run over every later line (doors.mjs
// textReading).
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
const SCRIPT = "javascript/login.js";
const CODE = "includes/login.php";

/** The password marker's lines and humanOnly, as check.mjs routes `entries` of `path` (markerHits and stationReport). */
function routed(path, entries) {
  const hits = markerHits(path, entries).filter((hit) => hit.row === "secrets").map((hit) => hit.line);
  const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, entries]]) });
  return { hits, humanOnly: report.humanOnly };
}
/** The whole of `text` added as a new file, its lines as git's diff hands them: split at LF. */
const asNew = (text) => text.split("\n").map((line, i) => ({ line: i + 1, text: line }));
/** Only git's line `n` of `text` changes, as check.mjs hands it (changeset.mjs addedWithContext). */
const onlyLine = (text, n, after = Infinity) => addedWithContext(new Map([[n, text.split("\n")[n - 1]]]), `${text}\n`, Infinity, after);
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

/** The names a piece of PHP splits over lines, spelt with escapes, NFKC letters and the line break PHP's closing tag takes: [label, text, lines]. */
const overLines = () => {
  const fullwidthP = String.fromCodePoint(0xff30);
  return [
    ["the rest spelt with an escape", printed(method(`${GETTER}<?php\n?>\\u0077ord`)), [1, 2]],
    ["the start spelt with an escape", printed(method(`get\\u0050ass<?php\n?>${TAIL}`)), [1, 2]],
    ["a braced escape, a blank line in the piece", printed(method(`${GETTER}<?php\n\n?>\\u{77}ord`)), [1, 2, 3]],
    ["an escape split by the piece", printed(method(`${GETTER}\\u00<?php\n?>77ord`)), [1, 2]],
    ["a fullwidth letter, NFKC", printed(method(`get${fullwidthP}ass<?php\n?>${TAIL}`)), [1, 2]],
    ["in a page's <script> element", element(method(`${GETTER}<?php\n?>\\u0077ord`)), [2, 3]],
  ];
};

test("with PHP's tokenizer, a name a piece of PHP splits over lines is read with its escapes and NFKC, as a line's own name is: each line it stands on counts, whole and as a changed line", () => {
  // Read as written, the joined text names no password in any of these forms.
  assert.ok(tokenize(["<?php $x;"])[0], "PHP's tokenizer runs (lib/php.mjs: PHP 8.2 or Docker)");
  for (const [label, text, named] of overLines()) countsOn(text, named, label);
});

test("beside names split over lines: the line break PHP's closing tag takes joins a name, a short tag (short_open_tag decides) splits one, and a block handed below the file's first line ends one a line above starts; controls stay clear", () => {
  countsOn(printed(method(`${GETTER}<?php ?>\n${TAIL}`)), [1, 2], "the line break PHP's closing tag takes");
  countsOn(printed(method(`${GETTER}<? ?>${TAIL}`)), [1], "a short tag");
  // Only a line below the file's first is handed, PHP's code before its piece: what stands above is not known.
  const lit = literal();
  assert.deepEqual(routed(PAGE, [{ line: 5, text: `?>${TAIL}() { return ${lit}; } }` }]), hitAt(5), "a block below the first line, a name's end after its piece");
  assert.deepEqual(routed(PAGE, [{ line: 5, text: `?>\\u0077ord() { return ${lit}; } }` }]), hitAt(5), "the same, spelt with an escape");
  // What a piece prints may complete the name's text that touches it, whatever that text holds, so a name across the
  // piece that is no password's counts on each line of it, and so does the text after the piece a block below the
  // first line starts in.
  countsOn(printed(method(`${GETTER}<?php\n?>port`)), [1, 2], "a name across a piece that is no password's");
  assert.deepEqual(routed(PAGE, [{ line: 5, text: `?>kr() { return ${lit}; } }` }]), hitAt(5), "a block below the first line, a name's text after its piece");
  // Controls: a block below the first line with markup after its piece, and a name no piece touches beside a piece.
  assert.deepEqual(routed(PAGE, [{ line: 5, text: `?></td><td>${literal()}</td>` }]), NONE, "a block below the first line, markup after its piece");
  clearAll(printed(" " + method("getTotal <?php\n?> port")), "a piece no name's text touches");
});

test("where PHP's tokenizer does not run, a name a piece over lines splits counts, and so does one across a piece whose end the text does not tell (a ?> in a string), in a chain, after the closing tag's line break, a short tag and below the first line", () => {
  // Taking the "<?php" and "<?=" pieces out a line at a time, to the first "?>", would miss every form here: the pieces
  // over lines, the ?> in a string or a comment, the chains, the closing tag's line break, a short tag, and a block
  // below the first line. A one-line "<?php ?>" piece with no string is in doors-php-split-names.test.mjs.
  withoutTokenizer(() => {
    countsOn(printed(method(`${GETTER}<?php\n?>${TAIL}`)), [1, 2], "a piece over lines");
    for (const [label, text, named] of overLines()) countsOn(text, named, `${label}, no tokenizer`);
    // A piece whose end the text does not tell, after a name's text, may run to the "?>" on line 2, whose
    // PHP then prints the name's rest, so line 2 counts too.
    countsOn(printed(method(`${GETTER}<?php echo '?>'; ?>${TAIL}`)), [1, 2], "a ?> in the piece's string");
    countsOn(printed(method(`${GETTER}<?php echo '?>';\n?>${TAIL}`)), [1, 2, 3], "a ?> in the piece's string, over lines");
    countsOn(printed(method(`${GETTER}<?php /* ?> */\n?>${TAIL}`)), [1, 2, 3], "a ?> in the piece's block comment, over lines");
    countsOn(printed(method(`${GETTER}<?php ?>wo<?php echo '?>'; ?>rd`)), [1, 2], "a chain, the second piece's end not told");
    countsOn(printed(method(`${GETTER}<?php echo '?>'; ?>wo<?php ?>rd`)), [1, 2], "a chain, the first piece's end not told");
    countsOn(printed(method(`${GETTER}<?php ?>\n${TAIL}`)), [1, 2], "the line break PHP's closing tag takes");
    countsOn(printed(method(`${GETTER}<? ?>${TAIL}`)), [1], "a short tag");
    assert.deepEqual(routed(PAGE, [{ line: 5, text: `?>${TAIL}() { return ${literal()}; } }` }]), hitAt(5), "a block below the first line");
  });
});

test("controls and cost without the tokenizer: a piece no name's text touches names none, and where a name's text touches a piece, or one whose end the text does not tell, from the piece over all it may run to counts", () => {
  // What a piece prints may complete the name's text that touches it, whatever that text holds, and a piece whose end
  // the text does not tell may run to any later "?>", or to the text's end.
  withoutTokenizer(() => {
    countsOn("<td><?= $x ?></td>\n<td><?php echo 'a'; ?>kr</td>", [2], "pieces between tags, a word after the second");
    countsOn(printed(method(`${GETTER}<?php\n?>port`)), [1, 2], "a name across a piece that is no password's");
    countsOn("<p>row<?php echo 'x'; ?>dage</p>", [1], "a word on each side of a piece whose end is not told");
    countsOn("<p>top<?php echo 'x'; ?></p>\n<p>x<?= $a ?></p>", [1, 2], "a word before a piece whose end is not told, and all it may run over");
    assert.deepEqual(routed(PAGE, asNew("<p>top<?php echo 'x'; ?></p>\n<p><?= $a ?>dage</p>\n<p>x</p>")), hitAt(1, 2, 3), "the refusal, to the text's end");
    // Controls: no name's text touches a piece, whether its end is told or not.
    clearAll("<td><?= $x ?></td>\n<td><?php echo 'a'; ?> kr</td>", "pieces between tags");
    clearAll("<p>row <?php echo 'x'; ?> dage</p>\n<p>x <?= $a ?></p>", "a piece whose end is not told, between words with spaces");
    clearAll(printed(" " + method("getTotal <?php\n?> port")), "a piece no name's text touches");
  });
});

test("the readings are linear: names across pieces read with escapes, and the fallback's pieces whose end is not told, over a page of many, grow no faster than linearly (linear-time.mjs)", () => {
  // nameSpans reads the joined text once per reading, textNames the text once and each piece's text once.
  const page = (n) => {
    const rows = [];
    for (let i = 0; i < n; i++) rows.push(`<td>${GETTER}<?php\n?>\\u0077ord${i}</td><td>top<?php echo 'x'; ?>dage</td><td>x\\u00e6<?= $i ?>y</td>`);
    return rows.join("\n");
  };
  const tokens = linear("escapes across pieces", (n) => routed(PAGE, asNew(page(n))).hits.length, 8000);
  assert.ok(tokens >= 8000, `every name across a piece counts: ${tokens}`);
  withoutTokenizer(() => {
    const hits = linear("the fallback's pieces", (n) => routed(PAGE, asNew(page(n))).hits.length, 8000);
    assert.equal(hits, 16000, "every line counts there");
  });
});

/** A partial patch's lines of one hunk as a caller hands them: the added line at `line`, then the context below, the last marked cut where the patch stops short of the file. */
const excerpt = (added, below, cut, line = 10) => [
  ...(line > 1 ? [{ line: 1, text: "<?php", context: true }] : []),
  { line, text: added },
  ...below.map((text, i) => ({ line: line + 1 + i, text, context: true, ...(cut && i === below.length - 1 ? { cut: true } : {}) })),
];

test("where the lines a caller hands stop short of the file (an entry marked cut, or a gap in the line numbers), a value still open there reads as going on unseen and counts; where they reach the file's end it has ended", () => {
  // The end of the lines handed is not the file's end where an entry is marked cut or a gap follows.
  const open = `const ${NAME} = input`;
  assert.deepEqual(routed(SCRIPT, excerpt(open, ["", "", ""], true)), hitAt(10), "three blank lines of context, cut");
  assert.deepEqual(routed(SCRIPT, excerpt(open, ["", "", ""], false)), NONE, "the same lines, the file's end");
  assert.deepEqual(routed(SCRIPT, [...excerpt(open, ["", "", ""], false), { line: 40, text: "next();" }]), hitAt(10), "a gap: the next hunk further on");
  assert.deepEqual(routed(SCRIPT, [{ line: 10, text: open, cut: true }]), hitAt(10), "no context below, cut");
  assert.deepEqual(routed(CODE, excerpt(`$${NAME} = $input`, ["", "", ""], true, 2)), hitAt(2), "PHP, an open value, cut");
  // Controls: a statement that ends inside the lines handed (a plain reference needs no person).
  assert.deepEqual(routed(SCRIPT, excerpt(`${open};`, ["", "", ""], true)), NONE, "the statement ends on its line");
  assert.deepEqual(routed(SCRIPT, excerpt(open, ["next();", "", ""], true)), NONE, "the next line starts a statement");
  assert.deepEqual(routed(CODE, excerpt(`$${NAME} = $_POST['pw'];`, ["$a = 1;", "", ""], true, 2)), NONE, "PHP, a request's field");
  assert.deepEqual(routed(CODE, excerpt(`function ${GETTER}${TAIL}()`, ["{", `  return $this->${NAME};`, "}"], true, 2)), NONE, "PHP, a header whose reference body the lines hold");
  // A declaration still open where the lines stop counts, cut or not.
  assert.deepEqual(routed(CODE, excerpt(`function ${GETTER}${TAIL}()`, ["{", "  log_it();", "  log_it();"], true, 2)), hitAt(2), "PHP, a header whose body the lines cut");
});

test("in changeset.mjs: addedWithContext marks its last entry cut where the file goes on below it (fewer lines below handed, or no head), and the reading of the entries it builds counts a value open there", () => {
  // With no entry marked, the open value would read as ended.
  const text = `const ${NAME} = input\n\n\n\n  || ${literal()};`;
  const cut = onlyLine(text, 1, 3);
  assert.deepEqual(cut.map((entry) => [entry.line, entry.cut === true]), [[1, false], [2, false], [3, false], [4, true]], "three lines below, the last cut");
  assert.deepEqual(routed(SCRIPT, cut), hitAt(1), "cut: the open value goes on unseen");
  assert.deepEqual(routed(SCRIPT, onlyLine(text, 1)), hitAt(1), "every line below: the literal fallback");
  assert.ok(!onlyLine(text, 1).some((entry) => entry.cut), "every line to the file's end: none cut");
  assert.deepEqual(routed(SCRIPT, onlyLine(`const ${NAME} = input;\n\n\n\nnext();`, 1, 3)), NONE, "a statement that ends on its line, cut");
  const none = addedWithContext(new Map([[10, `const ${NAME} = input`]]), null);
  assert.deepEqual(none, [{ line: 10, text: `const ${NAME} = input`, cut: true }], "no head: the added line alone, cut");
  assert.deepEqual(routed(SCRIPT, none), hitAt(10), "no head: the open value goes on unseen");
  // The PR's door cross-check marks its fallback's last added line so too (checks/pr-body.mjs), but the password
  // spelling is the only marker that reads on, and its row (secrets) is out of the cross-check (crossCheck: false), so
  // no finding shows it today.
});
