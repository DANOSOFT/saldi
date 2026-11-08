// tools/guardrails/tests/doors-php-edge-names.test.mjs - a piece of PHP that a name's text touches leaves that name
// unresolved, whatever the text holds: it counts as naming a password for the secrets row and humanOnly, and the state
// is unknown from it (lib/doors.mjs edgeNames, phpHtml, textReading; NAME_TEXT, nameTextBack, nameTextOn).
//
// In a script PHP opens, a method named getPass<?= 'word' ?>() returning a literal emits a password getter, though
// taking the piece out leaves no whole password name on the line and no identifier's character stands on each side of
// the piece. What a piece prints is never read, but it may print any part of a password name, the whole word too, so a
// name's text that touches a piece (an identifier's, a string's letters, an escape's characters) may be any part of
// one, whether or not it holds part of a password word: the name is not resolved. It stands over the piece and that
// text, and the state is not known from its start. Without the tokenizer each "?>" after a piece whose end the text
// does not tell is read so too, the next piece's text included. Every character a JavaScript identifier holds is a
// name's text (NAME_TEXT is built from IDENTIFIER_CHAR), the connectors (U+203F), ZWNJ, ZWJ and the characters Unicode
// adds to identifiers (U+00B7, U+2118) too; which characters those are is asked of the engine running this file
// (function a<c>b() {} parses), not of a list kept here. The rule's cost on Saldi is the census test.
//
// History:
// 20261107 CL/LH Created.
import test from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import vm from "node:vm";
import { randomBytes } from "node:crypto";
import { fileURLToPath } from "node:url";
import { tokenize } from "../lib/php.mjs";
import { markerHits, stationReport } from "../lib/station.mjs";
import { addedWithContext } from "../lib/changeset.mjs";
import { phpFailure, usePhpTokenizer } from "../lib/doors.mjs";
import { linear } from "./linear-time.mjs";

const ROOT = fileURLToPath(new URL("../../../", import.meta.url));
// Built at run time, so this file holds no literal for the marker to find.
const NAME = ["pass", "word"].join("");
/** "pass" and "word": a name's start and end, which a piece may complete. */
const HEAD = NAME.slice(0, 4);
const TAIL = NAME.slice(4);
const UPPER = `${NAME[0].toUpperCase()}${NAME.slice(1)}`;
const GETTER = `get${UPPER.slice(0, 4)}`;
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
 * A page whose PHP prints a <script> tag, so its inline HTML may run as the script's code: `code` between, on lines of
 * its own. A space after the echo's piece, so no name's text touches it and only `code`'s pieces are read.
 */
const printed = (code) => `<?php echo '<script>'; ?> ${code}\n<?php echo '</script>'; ?>`;
/** A page's <script> element that holds `code`, on lines of its own. */
const element = (code) => `<script>\n${code}\n</script>`;
/** A class whose method `name` (its text as written, pieces of PHP in it) returns a literal. */
const method = (name) => `class Login { ${name}() { return ${literal()}; } }`;
/** A page whose line 1 holds `html` before PHP's code, which gives a password a plain reference on line 3. */
const beforeReference = (html) => `${html}\n<?php\n$${NAME} = $_POST['pw'];`;

/** `text` counts on exactly the lines `named` (git's, 1-based) whole, and where any one of them alone changes it counts there. */
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

/**
 * Names a piece of PHP completes, each given a literal, on one line: [label, code, lines]. Code for a script PHP prints
 * (printed) or a page's <script> element (element). `lines`: where it counts in a printed script where PHP's tokenizer
 * did not run, 1 or 1 and 2: a piece whose end the text does not tell (a quote in it) after a name's text may run to the
 * "?>" on line 2, whose PHP then prints the name's rest (textReading).
 */
const touching = () => [
  ["a method, the piece its name's end (a suffix piece)", method(`${GETTER}<?= '${TAIL}' ?>`), [1, 2]],
  ["a method, the piece its name's start (a prefix piece)", method(`<?= '${GETTER}' ?>${TAIL}`), [1]],
  ["a method, the piece printing nothing", method(`${GETTER}<?= '' ?>`), [1, 2]],
  ["an object's getter, a suffix piece", `const login = { get ${HEAD}<?= '${TAIL}' ?>() { return ${literal()}; } };`, [1, 2]],
  ["a string key, a suffix piece", `var cfg = { '${HEAD}<?= '${TAIL}' ?>': ${literal()} };`, [1, 2]],
  ["a string key, a prefix piece", `var cfg = { "<?= '${HEAD}' ?>${TAIL}": ${literal()} };`, [1]],
  ["a property name, a suffix piece", `login.${HEAD}<?= '${TAIL}' ?> = ${literal()};`, [1, 2]],
  ["an object's property name, a prefix piece", `const login = { <?= '${HEAD}' ?>${TAIL}: ${literal()} };`, [1]],
  ["a variable, one letter before the piece", `var ${NAME[0]}<?= '${NAME.slice(1)}' ?> = ${literal()};`, [1, 2]],
  ["a variable, one letter after the piece", `var <?= '${NAME.slice(0, -1)}' ?>${NAME.slice(-1)} = ${literal()};`, [1]],
  ["a variable, a part between two pieces", `var <?= '${NAME[0]}' ?>${NAME.slice(1, 3)}<?= '${NAME.slice(3)}' ?> = ${literal()};`, [1, 2]],
  ["a variable, an escape before the piece", `var \\u0070<?= '${NAME.slice(1)}' ?> = ${literal()};`, [1, 2]],
  // The piece prints the whole word, beside a text that holds no part of it.
  ["a method, the piece the whole word after get", method(`get<?= '${UPPER.slice(0, 4)}' . '${TAIL}' ?>`), [1, 2]],
  ["a method, the piece the whole name before a word", method(`<?= '${GETTER}' . '${TAIL}' ?>Value`), [1]],
  ["a method, a name that holds no part of the word", method(`getTotal<?= 'Sum' ?>`), [1, 2]],
  ["a method, a variable's output after a word", method(`get<?= $name ?>`), [1]],
  ["a string key, a variable's output after a letter", `var cfg = { 'k<?= $key ?>': ${literal()} };`, [1]],
  ["a property name, a variable's output after a word", `login.field<?= $i ?> = ${literal()};`, [1]],
  ["a variable, a variable's output before a word", `var <?= $prefix ?>Value = ${literal()};`, [1]],
];

test("a name a piece of PHP may complete counts where it is given a literal, whatever the text that touches the piece holds, in a script PHP prints and in a page's <script> element, whole and as a changed line, with PHP's tokenizer", () => {
  // With the piece taken out no line names a password, so a block left plain where the piece stands in data would read
  // getPass_() as no password's. The forms whose text beside the piece holds no part of a password word (get, Value,
  // getTotal, k, field, the word beside a variable's output) count too: the piece may print the whole word.
  assert.ok(tokenize(["<?php $x;"])[0], "PHP's tokenizer runs (lib/php.mjs: PHP 8.2 or Docker)");
  for (const [label, code] of touching()) {
    countsOn(printed(code), [1], `${label}, printed`);
    countsOn(element(code), [2], `${label}, a <script> element`);
  }
});

test("over lines: a name a piece over lines completes counts on each line of the piece and on its text, so a change to the PHP that prints the name's rest counts too; the closing tag's line break joins the name", () => {
  countsOn(printed(method(`${GETTER}<?php\necho '${TAIL}';\n?>`)), [1, 2, 3], "the start before a piece over lines");
  countsOn(printed(method(`<?php\necho '${GETTER}';\n?>${TAIL}`)), [1, 2, 3], "the end after a piece over lines");
  countsOn(printed(method(`<?= '${GETTER}' ?>\n${TAIL}`)), [1, 2], "the end after the line break PHP's closing tag takes");
  countsOn(printed(method(`get<?php\necho '${UPPER}';\n?>`)), [1, 2, 3], "the whole word printed over lines after get");
});

test("where PHP's tokenizer does not run: the same names count, and so does a piece whose end the text does not tell after a name's text, over all it may run to", () => {
  // Reading only the names the text spells once a piece is taken out would miss these, and so would letting a piece
  // whose end the text does not tell run no further than its own "?>": every form that counts on line 2 here (and the
  // piece over lines on line 4) stands on that.
  withoutTokenizer(() => {
    for (const [label, code, lines] of touching()) countsOn(printed(code), lines, `${label}, no tokenizer`);
    countsOn(printed(method(`${GETTER}<?php\necho '${TAIL}';\n?>`)), [1, 2, 3, 4], "a piece over lines, no tokenizer");
    countsOn(printed(method(`<?php echo '?>'; echo '${GETTER}'; ?>${TAIL}`)), [1], "a ?> in the piece's string, the name's end after it");
  });
  // With the tokenizer PHP's tokens show where that piece ends: its text after it ends the name.
  countsOn(printed(method(`<?php echo '?>'; echo '${GETTER}'; ?>${TAIL}`)), [1], "a ?> in the piece's string");
});

test("where PHP's tokenizer does not run, a part of a name after a later ?> of a piece whose end the text does not tell, before the next piece, counts with both pieces, whole and as a changed line; with the tokenizer it counts too", () => {
  // The text after a later "?>" counts whatever it holds, not only where it might end a password word ("xyz" holds no
  // part of one). With the tokenizer PHP's tokens show where each piece ends, so fewer lines count.
  const lit = literal();
  const probe = printed(`var <?php $x = '?>'; echo 'p'; ?>as<?= 'sword' ?> = ${lit};`);
  const overLines = printed(`var <?php $x = '?>';\necho 'p';\n?>as<?= 'sword' ?> = ${lit};`);
  const nextOverLines = printed(`var <?php $x = '?>'; echo 'p'; ?>as<?php\necho 'sword';\n?> = ${lit};`);
  const after = printed(`var <?php $x = '?>'; echo '${HEAD}'; ?>xyz = ${lit};`);
  withoutTokenizer(() => {
    countsOn(probe, [1, 2], "a part between a later ?> and the next piece");
    countsOn(overLines, [1, 2, 3, 4], "the first piece over lines");
    countsOn(nextOverLines, [1, 2, 3, 4], "the next piece over lines");
    countsOn(after, [1], "a name's text after a later ?>, no piece after it");
  });
  countsOn(probe, [1], "a part between a later ?> and the next piece, with the tokenizer");
  countsOn(overLines, [1, 2, 3], "the first piece over lines, with the tokenizer");
  countsOn(nextOverLines, [1, 2, 3], "the next piece over lines, with the tokenizer");
  countsOn(after, [1], "a name's text after the piece, with the tokenizer");
});

test("below a file's first line (a partial patch's excerpt): the PHP code the text starts in is its first piece, so a name's text after it, or between it and the next piece, counts", () => {
  // A name is read across the piece the text starts in whatever follows it: a password word's end, part of one, or a
  // word that holds none (Value).
  const lit = literal();
  assert.deepEqual(routed(PAGE, [{ line: 5, text: `?>${NAME.slice(1, 3)}<?= '${NAME.slice(3)}' ?> = ${lit};` }]), hitAt(5), "a name's part between the first piece and the next");
  assert.deepEqual(routed(PAGE, [{ line: 5, text: `?>${TAIL}() { return ${lit}; } }` }]), hitAt(5), "a name's end after the first piece");
  assert.deepEqual(routed(PAGE, [{ line: 5, text: `?>Value() { return ${lit}; } }` }]), hitAt(5), "a word after the first piece");
  withoutTokenizer(() => {
    assert.deepEqual(routed(PAGE, [{ line: 5, text: `?>${NAME.slice(1, 3)}<?= '${NAME.slice(3)}' ?> = ${lit};` }]), hitAt(5), "a name's part between, no tokenizer");
    assert.deepEqual(routed(PAGE, [{ line: 5, text: `?>Value() { return ${lit}; } }` }]), hitAt(5), "a word after the first piece, no tokenizer");
  });
});

test("controls: a piece no name's text touches (a space, a quote, markup or an operator beside it, a symbol past U+FFFF) is text, with and without the tokenizer, and a plain reference below it is no hit (a plain password reference needs no person)", () => {
  const plain = [
    ["an operand", printed(`var a = <?= $x ?>; var b = 1;`)],
    ["a method, spaces beside the piece", printed(method(`getTotal <?= '' ?> `))],
    ["a string key a piece prints whole (what a piece prints is never read)", printed(`var cfg = { '<?= $key ?>': ${literal()} };`)],
    ["a string a piece prints whole", printed(`var s = '<?= $x ?>' + "<?= $y ?>";`)],
    ["a call on what a piece prints", printed(`var n = <?= $x ?>.toFixed(2);`)],
    ["pieces between tags", "<td><?= $x ?></td>\n<td><?= $y ?> kr</td>"],
    ["a word, a space, then a piece", "<p>Total <?= $x ?> kr</p>"],
    ["an emoji beside a piece", "<p>\u{1F600}<?= $x ?>\u{1F600}</p>"],
  ];
  for (const [label, text] of plain) clearAll(text, label);
  withoutTokenizer(() => {
    for (const [label, text] of plain) clearAll(text, `${label}, no tokenizer`);
  });
  for (const html of ["<p>Total <?= $x ?></p>", "<p><?= $x ?> kr</p>", "<p>\u{1F600}<?= $x ?></p>"]) {
    const text = beforeReference(html);
    assert.deepEqual(routed(PAGE, asNew(text)), NONE, `${JSON.stringify(html)}: whole`);
    assert.deepEqual(routed(PAGE, onlyLine(text, 3)), NONE, `${JSON.stringify(html)}: only line 3`);
  }
});

test("the rule fails closed: from a name a piece leaves unresolved the state is not known, so the literal of a getter whose name a piece completes counts where it alone changes, and so does a plain reference below such a name (there default deny outweighs the rule that a plain reference needs no person)", () => {
  // "get" and "Total" hold no part of a password word; the piece beside them may print one.
  const getter = printed(`class Login {\n  get<?= '${UPPER.slice(0, 4)}' . '${TAIL}' ?>()\n  { return ${literal()}; }\n}`);
  assert.deepEqual(routed(PAGE, asNew(getter)), hitAt(2), "the getter, whole");
  assert.deepEqual(routed(PAGE, onlyLine(getter, 2)), hitAt(2), "the getter's name changes alone");
  assert.deepEqual(routed(PAGE, onlyLine(getter, 3)), hitAt(3), "the getter's literal changes alone");
  const text = beforeReference("<p>Total<?= $x ?></p>");
  assert.deepEqual(routed(PAGE, asNew(text)), hitAt(1, 3), "a reference below: whole");
  assert.deepEqual(routed(PAGE, onlyLine(text, 1)), hitAt(1), "a reference below: only line 1");
  assert.deepEqual(routed(PAGE, onlyLine(text, 3)), hitAt(3), "a reference below: only line 3");
});

/**
 * The census of Saldi's 1,411 tracked PHP files at 5419eee2: the files that a name's text touching a piece of PHP
 * alone routes human-only, with PHP's tokenizer, and the lines that count in each as a new file, [path, lines, what
 * touches a piece there]. admin/admin_panel.php, human-only by other lines as well, counts 1342, 1346, 1439 and 1725
 * this way too (doors-plain-subset.test.mjs NAMING_AT_BASE).
 */
const NEWLY_HUMAN = [
  ["admin/license_manager.php", [164, 169], 'name="feature_<?php ...?>", name="expires_<?php ...?>"'],
  ["bank_integration/includes/auth_check_icon.php", [98, 105, 127, 128], "id=\"<?= $icon_id ?>_link\", '<?= $icon_id ?>_link', <?php endif; ?> before })();"],
  ["debitor/api.php", [983], "a piece before \\n\\n in an alert's string"],
  ["debitor/pos_ordre.php", [3718, 3719], "<?php ...; ?> before if ( on the next line"],
  ["finans/regnskab.php", [686, 688], "'mixedChart<?php print $x; ?>'"],
  ["finans/simuler.php", [31], "a piece before \\\" in a meta tag's content"],
  ["includes/tutorial.php", [191, 208, 224], "fetch(`<?php print get_relative(); ?>includes/..."],
  ["kreditor/ordreliste.php", [899, 900], "<?php endif; ?> before } on the next line"],
  ["projectManager/project_view.php", [162], "<?= round(...) ?>h</h3>"],
  ["projectManager/projects.php", [180], "Budget: $<?= number_format(...) ?>"],
  ["rental/settings.php", [161], "fetch('<?php echo get_relative(); ?>includes/..."],
];

test("the cost on Saldi at 5419eee2: of the 1,411 tracked PHP files, exactly eleven route human-only by a name's text touching a piece (a word, a \"$\", a \"}\" or an escape's backslash), whole and as each such line changed alone", () => {
  // The census (every tracked PHP file, whole, every second line added, and every line that names a password changed
  // alone) found no other file that this rule alone routes human-only, and no line that names a password whose verdict
  // as a changed line alone it changes.
  for (const [path, lines, what] of NEWLY_HUMAN) {
    const shown = spawnSync("git", ["show", `5419eee2:${path}`], { cwd: ROOT, encoding: "utf8", maxBuffer: 1 << 26 });
    assert.equal(shown.status, 0, shown.stderr);
    const text = shown.stdout.replace(/\n$/, "");
    assert.deepEqual(routed(path, asNew(text)), hitAt(...lines), `${path} whole (${what})`);
    for (const line of lines) {
      const changed = addedWithContext(new Map([[line, text.split("\n")[line - 1]]]), shown.stdout);
      assert.equal(routed(path, changed).humanOnly, true, `${path}:${line} as a changed line (${what})`);
    }
  }
});

test("edgeNames reads in linear time: a page of many pieces with a name's text beside them, and of pieces whose end the text does not tell, grows no faster than linearly (linear-time.mjs), with and without the tokenizer", () => {
  const page = (n) => {
    const rows = [];
    for (let i = 0; i < n; i++) rows.push(`<td>top<?= $a ?></td><td><?= $b ?>dag</td><td><?php echo 'x'; ?>as<?= $c ?></td><td>Total <?= $i ?> kr</td>`);
    return rows.join("\n");
  };
  // At 16,000 rows a side the reading spends more than FLOOR_MS of CPU, so its growth is judged.
  assert.equal(linear("edge names", (n) => routed(PAGE, asNew(page(n))).hits.length, 16000), 16000, "every row counts");
  withoutTokenizer(() => {
    assert.equal(linear("edge names, no tokenizer", (n) => routed(PAGE, asNew(page(n))).hits.length, 16000), 16000, "every row counts without the tokenizer");
  });
});

/** "Pass", the capitalised start of a password word, which a piece of PHP prints beside an identifier's character. */
const UPPER_HEAD = UPPER.slice(0, 4);
const hex = (ch) => `U+${ch.codePointAt(0).toString(16).toUpperCase().padStart(4, "0")}`;

/** Whether the JavaScript engine running this file reads `ch` as a character inside an identifier: function a<ch>b() {} parses (compiled, never run). */
function identifierPart(ch) {
  try {
    new vm.Script(`function a${ch}b() {}`);
    return true;
  } catch {
    return false;
  }
}
/**
 * Every code point the engine reads inside an identifier that is no letter, mark or digit, nor "_" or "$": the ones a
 * name's text beside a piece of PHP holds because NAME_TEXT is built from IDENTIFIER_CHAR. Unassigned and private-use
 * code points are no identifier's, so they are not asked.
 */
function engineIdentifierExtras() {
  const own = /[\p{L}\p{M}\p{N}_$]/u;
  const none = /[\p{Cn}\p{Co}]/u;
  const out = [];
  for (let cp = 0; cp <= 0x10ffff; cp++) {
    if (cp >= 0xd800 && cp <= 0xdfff) continue;
    const ch = String.fromCodePoint(cp);
    if (!own.test(ch) && !none.test(ch) && identifierPart(ch)) out.push(ch);
  }
  return out;
}

/**
 * A name a piece of PHP completes, `ch` touching the piece, given a literal: [label, code]. The piece prints its part of
 * the name split ('Pass' . 'word'), so no line spells it.
 */
const forms = (ch) => [
  ["a method, the piece after the character (a suffix piece)", method(`get${ch}<?= '${UPPER_HEAD}' . '${TAIL}' ?>`)],
  ["a method, the piece before the character (a prefix piece)", method(`<?= 'get${UPPER_HEAD}' . '${TAIL}' ?>${ch}Value`)],
  ["a property name, a suffix piece", `login.field${ch}<?= '${HEAD}' . '${TAIL}' ?> = ${literal()};`],
  ["a string key, a suffix piece", `var cfg = { 'k${ch}<?= '${HEAD}' . '${TAIL}' ?>': ${literal()} };`],
  ["an object's property name, a prefix piece", `const login = { <?= '${HEAD}' . '${TAIL}' ?>${ch}x: ${literal()} };`],
  ["a name's text after a later ?> of a piece whose end the text does not tell", `var <?php $x = '?>'; echo 'get' . '${UPPER_HEAD}' . '${TAIL}'; ?>${ch}x = ${literal()};`],
];

/**
 * How `ch` in each form (forms) routes in a script PHP prints and in a page's <script> element: whole, and where the
 * line that holds the name changes alone. Each form is given its own literal, so the readings are compared without it.
 */
function routesOf(ch) {
  const out = [];
  for (const [label, code] of forms(ch)) {
    for (const [where, text, line] of [["printed", printed(code), 1], ["a <script> element", element(code), 2]]) {
      out.push({ label: `${label}, ${where}`, whole: routed(PAGE, asNew(text)), alone: routed(PAGE, onlyLine(text, line)), line });
    }
  }
  return out;
}

/** Each form with `ch` routes as with "_", whose name counts: whole as "_" does, and where the name's line alone changes, there. */
function routesAsUnderscore(ch, underscore, what) {
  const routes = routesOf(ch);
  routes.forEach((route, k) => {
    const twin = underscore[k];
    assert.ok(twin.whole.humanOnly && twin.whole.hits.includes(twin.line), `"_" counts: ${twin.label}${what}`);
    assert.deepEqual(route.whole, twin.whole, `${hex(ch)} as "_": ${route.label}, whole${what}`);
    assert.deepEqual(route.alone, hitAt(route.line), `${hex(ch)}: ${route.label}, only line ${route.line}${what}`);
  });
}

test("every character the JavaScript engine reads inside an identifier, no letter, mark or digit, is a name's text beside a piece of PHP, so the name the piece completes counts as with \"_\", whole and as a changed line, with PHP's tokenizer and without", () => {
  // A name's text read by a list of its own (a letter, a mark, a digit, "_", "$" and an escape's characters) would
  // leave out 19 such characters, U+203F, U+200C, U+200D and U+2118 among them: the reading back from the piece would
  // stop there and no line count, where "_" counts ({"hits":[1],"humanOnly":true} in a printed script).
  assert.ok(tokenize(["<?php $x;"])[0], "PHP's tokenizer runs (lib/php.mjs: PHP 8.2 or Docker)");
  const extras = engineIdentifierExtras();
  for (const cp of [0x203f, 0x200c, 0x200d, 0x2118, 0x00b7]) assert.ok(extras.includes(String.fromCodePoint(cp)), `the engine reads U+${cp.toString(16).toUpperCase()} inside an identifier`);
  assert.ok(!extras.includes(" ") && !extras.includes("\u{A0}") && !extras.includes("\u{1F600}"), "the engine's answer: a space, NBSP and an emoji are no identifier's");
  const underscore = routesOf("_");
  for (const ch of extras) routesAsUnderscore(ch, underscore, ", with PHP's tokenizer");
  withoutTokenizer(() => {
    const bare = routesOf("_");
    for (const ch of extras) routesAsUnderscore(ch, bare, ", no tokenizer");
  });
});

test("U+203F, U+200C, U+200D and U+2118, each form's lines: get<U+203F><?= 'Pass' . 'word' ?>() and its neighbours count on the name's line, whole and as that line changed alone, and where PHP's tokenizer does not run on the lines a piece whose end the text does not tell may run over", () => {
  // Without the tokenizer the pieces' quotes leave their ends untold, so a name's text before one counts to the text's
  // end (the printed script's line 2).
  for (const cp of [0x203f, 0x200c, 0x200d, 0x2118]) {
    const ch = String.fromCodePoint(cp);
    const [suffix, prefix, property, key, objectKey, later] = forms(ch).map(([, code]) => printed(code));
    const label = `U+${cp.toString(16).toUpperCase()}`;
    for (const [what, text] of [["suffix piece", suffix], ["prefix piece", prefix], ["property", property], ["string key", key], ["object key", objectKey], ["later ?>", later]]) {
      assert.deepEqual(routed(PAGE, asNew(text)), hitAt(1), `${label}, ${what}: whole`);
      assert.deepEqual(routed(PAGE, onlyLine(text, 1)), hitAt(1), `${label}, ${what}: only line 1`);
    }
    // Below a file's first line (a partial patch's excerpt) the PHP code the text starts in is its first piece.
    const below = [{ line: 5, text: `?>${ch}x() { return ${literal()}; } }` }];
    assert.deepEqual(routed(PAGE, below), hitAt(5), `${label}, after the first piece below the first line`);
    withoutTokenizer(() => {
      for (const [what, text, lines] of [["suffix piece", suffix, [1, 2]], ["prefix piece", prefix, [1]], ["property", property, [1, 2]], ["string key", key, [1, 2]], ["object key", objectKey, [1]], ["later ?>", later, [1]]]) {
        assert.deepEqual(routed(PAGE, asNew(text)), hitAt(...lines), `${label}, ${what}, no tokenizer: whole`);
        for (const n of lines) assert.deepEqual(routed(PAGE, onlyLine(text, n)), hitAt(n), `${label}, ${what}, no tokenizer: only line ${n}`);
      }
      assert.deepEqual(routed(PAGE, below), hitAt(5), `${label}, after the first piece below the first line, no tokenizer`);
    });
  }
});

test("code points stay whole: a letter past U+FFFF and an identifier's character beside it count, an escape of U+203F counts, and a symbol past U+FFFF, a space, NBSP and a symbol NFKC reads as letters (U+2122) beside a piece are still no name's text", () => {
  // JavaScript reads no name through NFKC, so U+2122 is no identifier's and no name's text.
  const astral = "\u{1D40F}";
  const counting = [
    ["a letter past U+FFFF before the piece", method(`get${astral}<?= '${HEAD.slice(1)}' . '${TAIL}' ?>`)],
    ["U+203F, then a letter past U+FFFF, before the piece", method(`get\u{203F}${astral}<?= '${HEAD.slice(1)}' . '${TAIL}' ?>`)],
    ["a letter past U+FFFF after the piece", method(`<?= 'get${UPPER_HEAD}' . '${TAIL}' ?>${astral}`)],
    ["U+203F as \\u203F before the piece", method(`get\\u203F<?= '${UPPER_HEAD}' . '${TAIL}' ?>`)],
    ["U+203F as \\u{203F} after the piece", method(`<?= 'get${UPPER_HEAD}' . '${TAIL}' ?>\\u{203F}x`)],
  ];
  const clear = [
    ["an emoji beside a piece", "<p>\u{1F600}<?= $x ?>\u{1F600}</p>"],
    ["a space beside a piece", printed(`var t = 'Total <?= $x ?> kr';`)],
    ["NBSP beside a piece", "<p>Total\u{A0}<?= $x ?>\u{A0}kr</p>"],
    ["U+2122 beside a piece", "<p>Saldi\u{2122}<?= $x ?>\u{2122}</p>"],
  ];
  for (const [label, code] of counting) {
    const text = printed(code);
    assert.deepEqual(routed(PAGE, asNew(text)), hitAt(1), `${label}: whole`);
    assert.deepEqual(routed(PAGE, onlyLine(text, 1)), hitAt(1), `${label}: only line 1`);
  }
  for (const [label, text] of clear) {
    assert.deepEqual(routed(PAGE, asNew(text)), NONE, `${label}: whole`);
    text.split("\n").forEach((_, i) => assert.deepEqual(routed(PAGE, onlyLine(text, i + 1)), NONE, `${label}: only line ${i + 1}`));
  }
  withoutTokenizer(() => {
    for (const [label, code] of counting) {
      const text = printed(code);
      assert.ok(routed(PAGE, asNew(text)).hits.includes(1), `${label}, no tokenizer: whole`);
      assert.deepEqual(routed(PAGE, onlyLine(text, 1)), hitAt(1), `${label}, no tokenizer: only line 1`);
    }
    for (const [label, text] of clear) assert.deepEqual(routed(PAGE, asNew(text)), NONE, `${label}, no tokenizer: whole`);
  });
});
