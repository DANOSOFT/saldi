// tools/guardrails/tests/doors-quotes-by-context.test.mjs - which quote rules, PHP's or JavaScript's, hold by
// context, and a CR before a CR LF (lib/doors.mjs, lib/station.mjs, lib/changeset.mjs).
//
// PHP keeps a backslash and the line terminator after it in every string, so a PHP file reads a password's named
// argument, a constant and a var property with PHP's quotes: "\<CR>", "\<U+2028>" or "\<U+2029>" beside getenv('X')
// holds a literal and counts, while "$pw" and "{$cfg['pw']}" stay references. Inside a page's explicit <script> element
// JavaScript's string rules hold, so '\<LF>' + x is a pure reference there. lineText keeps a CR LF's CR after a CR of
// the line's own and is idempotent, so a JavaScript template continued over a CR and broken by a CR LF holds a line
// feed and counts. A line that holds a line terminator other than LF and CR LF is outside the plain subset, where a
// line that names a password counts with a reference too and a code marker reads all of the line, in a comment or not.
// Each case's expectation is what the language itself does (TypeScript, Node's vm.Script, Python, php:8.2-cli,
// PyYAML), but for code before a U+2028 or U+2029 and a comment, which PHP 8.2 does not parse and the marker reads
// whole. The PHP lines of the linear-time test open with <?php, as PHP's own tokens read a file's first line as HTML
// before it.
//
// History:
// 20261106 CL/LH Created.
import test from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { randomBytes } from "node:crypto";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
// PHP files are read by PHP's own tokens (lib/php.mjs gives doors.mjs its tokenizer).
import "../lib/php.mjs";
import { markerHits, stationReport } from "../lib/station.mjs";
import { addedWithContext, splitLines } from "../lib/changeset.mjs";
import { linear } from "./linear-time.mjs";

const CHECK = fileURLToPath(new URL("../check.mjs", import.meta.url));
const DOORS = new URL("../lib/doors.mjs", import.meta.url).href;
const literal = () => `'v${randomBytes(6).toString("hex")}'`;
const NONE = Object.freeze({ hits: [], humanOnly: false });

// ----- cases -------------------------------------------------------------------------------------------------------
// Self-contained: every case below is built from this region alone.

// Built at run time, so this file holds no literal for the marker to find.
const NAME = ["pass", "word"].join("");
const B = "`";
const LS = String.fromCharCode(0x2028);
const PS = String.fromCharCode(0x2029);
const NEL = String.fromCharCode(0x85);
/** Every line terminator a backslash may stand before: [label, text]. */
const ALL_TERMINATORS = [
  ["LF", "\n"],
  ["CR", "\r"],
  ["CR LF", "\r\n"],
  ["U+2028", LS],
  ["U+2029", PS],
];

/**
 * A PHP value beside V, a reference, with a backslash before terminator `t` in its string: PHP keeps the backslash and
 * the terminator in each of its strings, so every value holds a literal beside the reference. Named arguments (a call's,
 * a constructor's, a member's line of a call over lines, the value on a line of its own, among other named arguments),
 * a constant and a class's var property. [label, text with V, the reference].
 */
function phpValues(name, t) {
  const c = `\\${t}`;
  const env = "getenv('X')";
  return [
    ["a call's named argument", `<?php connect(${name}: "${c}" . V);`, env],
    ["a call's named argument, single quotes", `<?php connect(${name}: '${c}' . V);`, env],
    ["a constructor's named argument after another", `<?php $db = new Db(host: 'h', ${name}: "${c}" . V);`, env],
    ["a member's line of a call over lines", `<?php\nconnect(\n    host: 'h',\n    ${name}: "${c}" . V,\n);`, env],
    ["the value on a line of its own", `<?php\nconnect(${name}:\n    "${c}" . V);`, env],
    ["among named arguments and a nested call's", `<?php\n$pdo = make(opts: [1], ${name}: '${c}' . V, x: f(a: 1));`, env],
    ["a constant", `<?php const ${name} = "${c}" . V;`, "X"],
    ["a class's var property", `<?php class C {\n    var $${name} = '${c}' . V;\n}`, "X"],
  ];
}

/** PHP named arguments given V, which holds a reference alone as PHP's quotes read it: [label, text with V, the reference]. */
function phpReferences(name) {
  return [
    ["a call's named argument", `<?php connect(${name}: V);`, "getenv('X')"],
    ["an interpolated variable", `<?php connect(${name}: V);`, `"$pw"`],
    ["an interpolated subscript", `<?php connect(${name}: V);`, `"{$cfg['pw']}"`],
    ["an interpolated property, among named arguments", `<?php $db = new Db(host: "$h", ${name}: V);`, `"{$o->pw}"`],
    ["a member's line of a call over lines", `<?php\nconnect(\n    host: 'h',\n    ${name}: V,\n);`, `"$pw"`],
    ["the value on a line of its own", `<?php\nconnect(${name}:\n    V);`, `"$pw"`],
  ];
}

/**
 * A PHP page's explicit <script> element, which JavaScript reads (var, let, const, var $name, a key inside braces): a
 * backslash continues its quote over a line break and adds nothing. [label, text with V].
 */
function pageScripts(name, t) {
  const c = `\\${t}`;
  return [
    ["var", `<script>var ${name} = '${c}' + V;</script>`],
    ["let", `<script>let ${name} = "${c}" + V;</script>`],
    ["const", `<script>const ${name} = '${c}' + V;</script>`],
    ["var $name", `<script>var $${name} = '${c}' + V;</script>`],
    ["const on a line of its own", `<?php echo 1; ?>\n<script type="text/javascript">\n  const ${name} = "${c}" + V;\n</script>`],
    ["an object's key", `<script>f({ ${name}: '${c}' + V });</script>`],
    ["an object's key over lines", `<script>f({\n  a: 1,\n  ${name}: "${c}" + V,\n});</script>`],
  ];
}

/** A PHP page's script whose "$name" is its text, a literal: [label, text]. */
function pageScriptDollars(name) {
  return [
    ["var", `<script>var ${name} = "$pw";</script>`],
    ["an object's key", `<script>f({ ${name}: "$pw" });</script>`],
    ["an object's key over lines", `<script>f({\n  a: 1,\n  ${name}: "$pw",\n});</script>`],
  ];
}

/**
 * Lines whose CRs and CR LFs stand side by side, in files of CR LF lines: [label, path, text with V, the reference,
 * whether the reference counts]. A line that holds a bare CR is outside the plain subset, and counts with
 * the reference too; one continued over a CR LF holds nothing and stays plain. Over class fields and decorators too,
 * where only a member's line may change.
 */
function mixedForms(name) {
  const env = "process.env.X";
  return [
    ["a template continued over a CR, then a CR LF", "api/client.ts", `const ${name} = ${B}\\\r\r\n${B} + V;`, env, true],
    ["a template continued over a CR, then a CR LF (.js)", "api/client.js", `const ${name} = ${B}\\\r\r\n${B} + V;`, env, true],
    ["a template continued over a CR, then a CR and a CR LF", "api/client.js", `const ${name} = ${B}\\\r\r\r\n${B} + V;`, env, true],
    ["a template's two continuations, then a CR LF", "api/client.js", `const ${name} = ${B}\\\r\\\r\r\n${B} + V;`, env, true],
    ["a class field's template", "api/client.ts", `class C {\r\n  ${name}: string = ${B}\\\r\r\n${B} + V;\r\n}`, env, true],
    ["a class field's value after its template", "api/client.ts", `class C {\r\n  ${name}: string =\r\n    ${B}\\\r\r\n${B} + V;\r\n}`, env, true],
    ["a template continued over a CR LF", "api/client.js", `const ${name} = ${B}\\\r\n${B} + V;`, env, false],
    ["a template continued over a lone CR", "api/client.js", `const ${name} = ${B}\\\r${B} + V;`, env, true],
    ["a quote continued over a lone CR", "api/client.js", `const ${name} = '\\\r' + V;\r\nrun();`, env, true],
    ["a class field's template continued over a CR LF", "api/client.ts", `class C {\r\n  ${name}: string = ${B}\\\r\n${B} + V;\r\n}`, env, false],
    ["a member after a decorator's template", "api/client.ts", `class C {\r\n  @Dec(${B}\\\r\r\n${B}) ${name}: string = V;\r\n}`, env, true],
    ["a member below a decorator's template", "api/client.ts", `class C {\r\n  @Dec(${B}x\\\r\r\n${B})\r\n  ${name}: string = V;\r\n}`, env, true],
    ["Python's triple quote continued over a CR, then a CR LF", "jobs/sync.py", `${name} = """\\\r\r\n""" + V`, "os.environ['X']", true],
    ["Python's triple quote continued over a CR LF", "jobs/sync.py", `${name} = """\\\r\n""" + V`, "os.environ['X']", false],
  ];
}

/**
 * Code markers on a line of the diff that holds more than one line of its file's language: [path, text, the rows that
 * count, label]. Such a line is outside the plain subset: the lexer establishes no comment on it, so every
 * marker on it counts, in a comment or not.
 */
function commentRuns() {
  const drop = ["destructive:DROP DATABASE"];
  const sms = ["outbound:send_sms("];
  const deploy = ["deploy:pull_request_target"];
  const cases = [];
  for (const [label, t] of [["CR", "\r"], ["U+2028", LS], ["U+2029", PS]]) {
    cases.push(
      [`finans/report.js`, `// first${t}// db.query("DROP DATABASE x");`, drop, `${label}, JavaScript, two comments`],
      [`finans/report.js`, `// first${t}  // second${t}db.query("DROP DATABASE x");`, drop, `${label}, JavaScript, code after two comments`],
      [`finans/report.js`, `x = 1;${t}// db.query("DROP DATABASE x");`, drop, `${label}, JavaScript, a comment after code`],
      [`finans/report.js`, `// first${t}/* a */ db.query("DROP DATABASE x");`, drop, `${label}, JavaScript, code after a block comment`],
      [`finans/report.js`, `// first${t}/* db.query("DROP DATABASE x"); */`, drop, `${label}, JavaScript, a block comment`],
      [`finans/report.js`, `// first${t}/* a${t} */ db.query("DROP DATABASE x");`, drop, `${label}, JavaScript, code after a block comment over lines`],
      [`.github/workflows/x.yml`, `# first${t}# on: pull_request_target`, deploy, `${label}, YAML, two comments`],
      [`.github/workflows/x.yml`, `# first${t}  # second${t}on: pull_request_target`, deploy, `${label}, YAML, code after two comments`],
      [`.github/workflows/x.yml`, `a: 1${t}# on: pull_request_target`, deploy, `${label}, YAML, a comment after code`],
      [`.github/workflows/x.yml`, `# first${t}/* on: pull_request_target`, deploy, `${label}, YAML, which has no block comment`],
      [`includes/sms.php`, `# first${t}# send_sms($to, $text);`, sms, `${label}, PHP #, two comments`],
      [`includes/sms.php`, `// first${t}// send_sms($to, $text);`, sms, `${label}, PHP //, two comments`],
      [`includes/sms.php`, `# first${t}// second${t}  send_sms($to, $text);`, sms, `${label}, PHP, code after two comments`],
      [`includes/sms.php`, `$a = 1;${t}# send_sms($to, $text);`, sms, `${label}, PHP, a comment after code`],
      [`includes/sms.php`, `# first${t}/* a */ send_sms($to, $text);`, sms, `${label}, PHP, code after a block comment`],
      [`includes/sms.php`, `# first${t}/* send_sms($to, $text); */`, sms, `${label}, PHP, a block comment`],
      [`includes/sms.inc`, `# first${t}# second ?><?php send_sms($to, $text);`, sms, `${label}, PHP, a comment a closing tag ends`],
    );
  }
  cases.push(
    [`includes/sms.php`, `# first\r# second${NEL}send_sms($to, $text);`, sms, "PHP, a comment that holds U+0085"],
    [`.github/workflows/x.yml`, `# first${NEL}# second\ron: pull_request_target`, deploy, "YAML, U+0085 and CR"],
  );
  return cases;
}

// ----- end cases ---------------------------------------------------------------------------------------------------

/** The password marker's lines and humanOnly, as check.mjs routes `entries` of `path` (markerHits and stationReport). */
function routed(path, entries) {
  const hits = markerHits(path, entries).filter((hit) => hit.row === "secrets").map((hit) => hit.line);
  const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, entries]]) });
  return { hits, humanOnly: report.humanOnly };
}
/** The whole of `text` added as a new file, its lines as git's diff hands them: split at LF, each CR LF's CR kept. */
const wholeRaw = (path, text) => routed(path, text.split("\n").map((line, i) => ({ line: i + 1, text: line })));
/** The same, its lines as changeset.mjs splits a file (splitLines). */
const wholeSplit = (path, text) => routed(path, splitLines(text).map((line, i) => ({ line: i + 1, text: line })));
/** The same, every line added through addedWithContext, which hands each line's text as splitLines does. */
function wholeContext(path, text) {
  const added = new Map(text.split("\n").map((line, i) => [i + 1, line]));
  return routed(path, addedWithContext(added, `${text}\n`));
}
/** Only git's line `n` of `text` changes; the lines above it are unchanged context (changeset.mjs addedWithContext). */
function onlyHit(path, text, n) {
  const file = text.split("\n");
  return routed(path, addedWithContext(new Map([[n, file[n - 1]]]), `${text}\n`));
}
/** Git's line of `text` that holds `value`, 1-based. */
const lineOf = (text, value) => 1 + text.split("\n").findIndex((line) => line.includes(value));
const hitAt = (n) => ({ hits: [n], humanOnly: true });
/** Every way a change hands `text` whole: git's lines, changeset.mjs's split, and addedWithContext's. */
const WHOLE = [
  ["git's lines", wholeRaw],
  ["splitLines", wholeSplit],
  ["addedWithContext", wholeContext],
];

test("secrets, through markerHits and stationReport: a PHP named argument, a constant and a var property keep PHP's quotes, which keep a backslash and the line terminator after it, LF, CR, CR LF, U+2028 or U+2029, as the string's text, so the value counts beside a reference as with a literal, whole and where only the value's line changes", () => {
  // Read with JavaScript's quotes, each value beside a reference would be continued, a pure reference: no hit and
  // humanOnly false.
  for (const [tl, t] of ALL_TERMINATORS) {
    for (const [label, text, ref] of phpValues(NAME, t)) {
      const path = "includes/db.php";
      const lit = literal();
      const name = lineOf(text, NAME);
      for (const [v, kind] of [
        [lit, "a literal"],
        [ref, "a reference"],
      ]) {
        const form = text.replace("V", v);
        const value = lineOf(form, v);
        const what = `${tl}, ${label}, ${kind}: ${JSON.stringify(form)}`;
        for (const [how, whole] of WHOLE) assert.deepEqual(whole(path, form), hitAt(name), `${what}, ${how}`);
        assert.deepEqual(onlyHit(path, form, value), hitAt(value), `${what}, only the value's line changes`);
        assert.deepEqual(onlyHit(path, form, name), hitAt(name), `${what}, only the name's line changes`);
      }
    }
  }
});

test("secrets, the reference-only controls: a PHP named argument given a reference alone (a call, an interpolated variable, subscript or property, on a line of its own or a member's line of a call over lines) is no hit, whole and where only the value's line changes, and humanOnly stays false; with a literal it counts", () => {
  // Read with JavaScript's quotes, "$pw" and "{$cfg['pw']}" are literal text, so each reference would be a hit and
  // humanOnly true.
  for (const [label, text, ref] of phpReferences(NAME)) {
    const path = "includes/db.php";
    const reference = text.replace("V", ref);
    const value = lineOf(reference, ref);
    for (const [how, whole] of WHOLE) assert.deepEqual(whole(path, reference), NONE, `${label}, ${how}: ${JSON.stringify(reference)}`);
    assert.deepEqual(onlyHit(path, reference, value), NONE, `${label}, only the value's line changes: ${JSON.stringify(reference)}`);
    const lit = literal();
    const hit = text.replace("V", lit);
    assert.deepEqual(wholeRaw(path, hit), hitAt(lineOf(hit, NAME)), `${label}, a literal: ${JSON.stringify(hit)}`);
    assert.deepEqual(onlyHit(path, hit, lineOf(hit, lit)), hitAt(lineOf(hit, lit)), `${label}, a literal, only the value's line changes`);
  }
});

test("secrets, where JavaScript's quotes stay: inside an explicit <script> element of a PHP page JavaScript's string rules hold (let, var, var $name, const, a key inside braces), so a quote a backslash continues over LF or CR LF beside a reference is no hit, whole and where only the value's line changes, and \"$pw\" is text, which counts; over a CR, U+2028 or U+2029 the line is outside the plain subset and counts", () => {
  // const and var $name are JavaScript's here too: read with PHP's quotes they would count over LF and CR LF,
  // humanOnly true.
  for (const [tl, t] of ALL_TERMINATORS) {
    const plain = t === "\n" || t === "\r\n";
    for (const [label, text] of pageScripts(NAME, t)) {
      const path = "includes/page.php";
      const lit = literal();
      const hit = text.replace("V", lit);
      const reference = text.replace("V", "x");
      const value = lineOf(reference, "+ x");
      assert.deepEqual(wholeRaw(path, hit), hitAt(lineOf(hit, NAME)), `${tl}, ${label}, a literal: ${JSON.stringify(hit)}`);
      for (const [how, whole] of WHOLE) assert.deepEqual(whole(path, reference), plain ? NONE : hitAt(lineOf(reference, NAME)), `${tl}, ${label}, a reference, ${how}: ${JSON.stringify(reference)}`);
      assert.deepEqual(onlyHit("includes/page.php", reference, value), plain ? NONE : hitAt(value), `${tl}, ${label}, a reference, only its line changes`);
    }
  }
  for (const [label, text] of pageScriptDollars(NAME)) {
    const n = lineOf(text, NAME);
    assert.deepEqual(wholeRaw("includes/page.php", text), hitAt(n), `${label}: ${JSON.stringify(text)}`);
    assert.deepEqual(onlyHit("includes/page.php", text, n), hitAt(n), `${label}, only its line changes: ${JSON.stringify(text)}`);
  }
});

test("secrets, through markerHits and stationReport: a CR that stands before a CR LF stays a terminator of its own, so a JavaScript template or a Python triple quote continued over the CR and broken by the CR LF holds a line feed and counts beside a reference, and one continued over the CR LF, or over a lone CR, holds nothing and does not; through git's lines, splitLines and addedWithContext, whole, where only the value's line changes and over class fields and decorators where only a member's line changes", () => {
  // Dropping the template's or the triple quote's CR once or twice would read the CR LF as continued, so each counting
  // reference would be no hit and humanOnly false; a continuation over a CR LF holds nothing.
  for (const [label, path, text, ref, counts] of mixedForms(NAME)) {
    const lit = literal();
    for (const [v, kind, hits] of [
      [lit, "a literal", true],
      [ref, "a reference", counts],
    ]) {
      const form = text.replace("V", v);
      const name = lineOf(form, NAME);
      const value = lineOf(form, v);
      const what = `${path}, ${label}, ${kind}: ${JSON.stringify(form)}`;
      for (const [how, whole] of WHOLE) assert.deepEqual(whole(path, form), hits ? hitAt(name) : NONE, `${what}, ${how}`);
      assert.deepEqual(onlyHit(path, form, value), hits ? hitAt(value) : NONE, `${what}, only the value's line changes`);
    }
  }
});

test("the one definition, lineText: a line keeps the CR of its CR LF where its own text ends in a CR, so lines joined at LF are the file's, and a line given lineText twice is the line given it once; fileLines and splitLines split so", async () => {
  // Dropping the last CR of a\r\r (and again of a\r) would lose a CR of the line's own and make lineText not idempotent.
  const doors = await import(DOORS);
  for (const [line, text] of [
    ["a", "a"],
    ["a\r", "a"],
    ["a\r\r", "a\r\r"],
    ["a\r\r\r", "a\r\r\r"],
    ["\\\r\r", "\\\r\r"],
    ["\\\r", "\\"],
    ["\r", ""],
    ["\r\r", "\r\r"],
    [`a${LS}\r`, `a${LS}`],
    ["", ""],
  ]) {
    assert.equal(doors.lineText(line), text, JSON.stringify(line));
    assert.equal(doors.lineText(doors.lineText(line)), text, `${JSON.stringify(line)}, twice`);
  }
  for (const file of ["a\r\r\nb\r\n", "x = `\\\r\r\n` + y;\r\n", "\r\r\n\r\n\n\r\r\r\n", "a\rb\r\nc"]) {
    const lines = doors.fileLines(file);
    // Joined at LF, the lines are the file with each CR LF that ends a line read as the LF it is to every language here.
    assert.equal(lines.join("\n"), file.replace(/(?<!\r)\r\n/g, "\n"), JSON.stringify(file));
    assert.deepEqual(splitLines(file), lines, JSON.stringify(file));
    assert.deepEqual(lines.map(doors.lineText), lines, `${JSON.stringify(file)}, given lineText again`);
  }
  assert.deepEqual(doors.fileLines("a\r\r\nb\r\n"), ["a\r\r", "b", ""]);
  const added = new Map([[2, "two\r\r"]]);
  // The unchanged lines below a run are handed too, split so.
  assert.deepEqual(addedWithContext(added, "one\r\r\ntwo\r\r\nthree\r\n"), [
    { line: 1, text: "one\r\r", context: true },
    { line: 2, text: "two\r\r" },
    { line: 3, text: "three", context: true },
  ]);
});

test("code markers: a line of the diff that holds a CR, U+2028 or U+2029 is outside the plain subset, where the lexer establishes no comment, so every DROP DATABASE, send_sms( and pull_request_target on it counts (the last one human-only), after a comment, in one, or after code", () => {
  // Ending each comment where its language ends one and passing over the rest would hide the marker in a comment after
  // a comment, a comment after code, a block comment and a PHP comment holding U+0085.
  const rowsOf = (path, text) => [...new Set(markerHits(path, [{ line: 1, text }]).map((hit) => `${hit.row}:${hit.marker}`))].sort();
  for (const [path, text, rows, label] of commentRuns()) {
    assert.deepEqual(rowsOf(path, text), rows, `${path}, ${label}: ${JSON.stringify(text)}`);
    const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, [{ line: 1, text }]]]) });
    assert.equal(report.humanOnly, rows.includes("deploy:pull_request_target"), `${path}, ${label}: humanOnly`);
  }
});

test("code markers stay linear: a line of the diff that holds 20,000 comments one after another, or 20,000 comments a PHP closing tag ends, or 20,000 block comments, each with a CR or U+2028 between them, grows no faster than linearly (linear-time.mjs) and counts the marker at its end (outside the plain subset)", () => {
  // It holds the reading of every line linear.
  const cases = [
    ["includes/sms.php", (n) => "<?php # a\r" + "# a\r".repeat(n) + "send_sms($to, $text);", ["outbound:send_sms("]],
    ["includes/sms.php", (n) => "<?php # a ?>\r" + "# a ?>\r".repeat(n) + "# send_sms($to, $text);", ["outbound:send_sms("]],
    ["includes/sms.php", (n) => "<?php # a\r" + "/* a */ ".repeat(n) + "send_sms($to, $text);", ["outbound:send_sms("]],
    ["finans/report.js", (n) => `// a${LS}`.repeat(n) + 'db.query("DROP DATABASE x");', ["destructive:DROP DATABASE"]],
    [".github/workflows/x.yml", (n) => "# a\r".repeat(n) + "# on: pull_request_target", ["deploy:pull_request_target"]],
  ];
  for (const [path, text, rows] of cases) {
    const read = (n) => [...new Set(markerHits(path, [{ line: 1, text: text(n) }]).map((hit) => `${hit.row}:${hit.marker}`))];
    assert.deepEqual(linear(path, read, 20000), rows, path);
  }
});

/**
 * A git repository in a scratch directory with `files` ([path, base content or null, head content]) committed as base
 * and written as head, byte for byte: what `check.mjs --station --json --base HEAD` routes as the password marker's
 * hits (path:line), every other marker row it hits (path:row), and humanOnly.
 */
function stationOnChange(files) {
  const dir = mkdtempSync(join(tmpdir(), "guardrails-quotes-by-context-"));
  const git = (...args) => spawnSync("git", ["-c", "user.name=guardrails-test", "-c", "user.email=guardrails-test@example.invalid", "-c", "commit.gpgsign=false", "-c", "core.autocrlf=false", ...args], { cwd: dir, encoding: "utf8" });
  const write = (path, content) => {
    mkdirSync(dirname(join(dir, path)), { recursive: true });
    writeFileSync(join(dir, path), content);
  };
  try {
    assert.equal(git("init", "-q").status, 0);
    for (const [path, base] of files) if (base !== null) write(path, base);
    assert.equal(git("add", "-A").status, 0);
    assert.equal(git("commit", "-q", "--allow-empty", "--no-verify", "-m", "base").status, 0);
    for (const [path, , head] of files) write(path, head);
    const result = spawnSync(process.execPath, [CHECK, "--station", "--json", "--base", "HEAD"], { cwd: dir, encoding: "utf8", timeout: 60000 });
    assert.equal(result.status, 0, result.stderr);
    const report = JSON.parse(result.stdout);
    return {
      hits: report.markersHit.filter((hit) => hit.row === "secrets").map((hit) => `${hit.path}:${hit.line}`).sort(),
      rows: [...new Set(report.markersHit.filter((hit) => hit.row !== "secrets").map((hit) => `${hit.path}:${hit.row}`))].sort(),
      humanOnly: report.humanOnly,
    };
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

test("production wiring, check.mjs --station: a PHP named argument given \"\\<CR>\" beside getenv('X') counts in a new file and where only its member's line changes, \"$pw\" there does not; a CR LF file's template continued over a CR and broken by its CR LF counts beside process.env.X, in a new file and where only a class field's value line changes, and one continued over the CR LF does not; a PHP page's const in its <script> given '\\<LF>' + x does not; a comment line that holds a CR keeps its markers, and a plain one does not", () => {
  const call = (v) => `<?php\nconnect(\n    host: 'h',\n    ${NAME}: ${v},\n);\n`;
  const field = (v) => `class C {\r\n  ${NAME}: string =\r\n    ${v};\r\n}\r\n`;
  const counted = stationOnChange([
    ["includes/db.php", null, `<?php\nconnect(${NAME}: "\\\r" . getenv('X'));\n`],
    ["includes/member.php", call("getenv('X')"), call(`"\\\r" . getenv('X')`)],
    ["api/mixed.js", null, `const ${NAME} = ${B}\\\r\r\n${B} + process.env.X;\r\n`],
    ["api/field.ts", field("process.env.Y"), field(`${B}\\\r\r\n${B} + process.env.X`)],
  ]);
  // The field's value line holds a bare CR: both added lines of the statement its unchanged name line starts count.
  assert.deepEqual(counted, { hits: ["api/field.ts:3", "api/field.ts:4", "api/mixed.js:1", "includes/db.php:2", "includes/member.php:4"], rows: [], humanOnly: true });
  const references = stationOnChange([
    ["includes/ref.php", null, `<?php\nconnect(${NAME}: "$pw");\n`],
    ["includes/member.php", call("getenv('X')"), call(`"{$cfg['pw']}"`)],
    ["api/crlf.js", null, `const ${NAME} = ${B}\\\r\n${B} + process.env.X;\r\n`],
    ["api/field.ts", field("process.env.Y"), field(`${B}\\\r\n${B} + process.env.X`)],
    ["includes/page.php", null, `<?php echo 1; ?>\n<script>\n  const ${NAME} = '\\\n' + x;\n  var $${NAME} = '\\\n' + x;\n</script>\n`],
    ["includes/sms.php", null, `<?php\n# a note send_sms($to, $text);\n// a note send_sms($to, $text);\n`],
    [".github/workflows/ci.yml", null, `on: push\n# a note: pull_request_target\n`],
  ]);
  assert.deepEqual(references, { hits: [], rows: [], humanOnly: false });
  const unplain = stationOnChange([
    ["includes/sms.php", null, `<?php\n# a note\r# send_sms($to, $text);\n// a note\r// send_sms($to, $text);\n`],
    [".github/workflows/ci.yml", null, `on: push\n# a note\r# on: pull_request_target\n`],
  ]);
  assert.deepEqual(unplain, { hits: [], rows: [".github/workflows/ci.yml:deploy", "includes/sms.php:outbound"], humanOnly: true });
});
