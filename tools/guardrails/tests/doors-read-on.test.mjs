// tools/guardrails/tests/doors-read-on.test.mjs - a changed line is read on through the end of what it opens
// (lib/changeset.mjs addedWithContext, lib/doors.mjs readOnFrom).
//
// A change read only up to its own line reads a value or a declaration left open there as ended: renaming only a
// method's header to a password getter, its literal-returning body on the unchanged line below, would be no hit. So
// changeset.mjs hands markerHits the unchanged lines below each run too, to the file's end, and a changed line is read
// on over them through the end of the declaration or the statement it opens; a hit is the changed line's, never an
// unchanged one's. The same entries reach the PR's door cross-check (changeset markerLines). A line that goes on with a
// statement an unchanged line above starts (readsBack) still reads only the run: that statement's own unchanged lines,
// above or below, are not the change's. In a script PHP prints, the code's first word touches the echo's piece, so
// line 1 counts too in the whole file (doors.mjs edgeNames).
//
// History:
// 20261107 CL/LH Created.
import test from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { randomBytes } from "node:crypto";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { tokenize } from "../lib/php.mjs";
import { markerHits, stationReport } from "../lib/station.mjs";
import { addedWithContext } from "../lib/changeset.mjs";

const CHECK = fileURLToPath(new URL("../check.mjs", import.meta.url));
const ROOT = fileURLToPath(new URL("../../../", import.meta.url));
// Built at run time, so this file holds no literal for the marker to find.
const NAME = ["pass", "word"].join("");
const CAP = `${NAME[0].toUpperCase()}${NAME.slice(1)}`;
const GETTER = `get${CAP}`;
const literal = () => `'v${randomBytes(6).toString("hex")}'`;
const NONE = Object.freeze({ hits: [], humanOnly: false });
const hitAt = (...n) => ({ hits: n, humanOnly: true });
const PAGE = "includes/page.php";
const CODE = "includes/login.php";
const SCRIPT = "javascript/login.js";

/** The password marker's lines and humanOnly, as check.mjs routes `entries` of `path` (markerHits and stationReport). */
function routed(path, entries) {
  const hits = markerHits(path, entries).filter((hit) => hit.row === "secrets").map((hit) => hit.line);
  const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, entries]]) });
  return { hits, humanOnly: report.humanOnly };
}
/** The whole of `text` added as a new file, its lines as git's diff hands them: split at LF. */
const asNew = (text) => text.split("\n").map((line, i) => ({ line: i + 1, text: line }));
/** Only git's line `n` of `text` changes, as check.mjs hands it (changeset.mjs addedWithContext): every other line is unchanged context, above and below. */
const onlyLine = (text, n, after = Infinity) => addedWithContext(new Map([[n, text.split("\n")[n - 1]]]), `${text}\n`, Infinity, after);
/** Git's line of `text` that `needle` stands on, 1-based. */
const lineOf = (text, needle) => text.split("\n").findIndex((line) => line.includes(needle)) + 1;
/**
 * `text`'s line `n` counts, whole and where only that line changes; whole, so do the lines of `also` (the lines a
 * piece of PHP and a name's text that touches it stand on, doors.mjs edgeNames).
 */
function counts(path, text, n, what, also = []) {
  assert.deepEqual(routed(path, asNew(text)), hitAt(...new Set([...also, n].sort((a, b) => a - b))), `${what}: whole: ${JSON.stringify(text)}`);
  assert.deepEqual(routed(path, onlyLine(text, n)), hitAt(n), `${what}: only line ${n}: ${JSON.stringify(text)}`);
}
/** `text` is no hit, whole and where only its line `n` changes. */
function clear(path, text, n, what) {
  assert.deepEqual(routed(path, asNew(text)), NONE, `${what}: whole: ${JSON.stringify(text)}`);
  assert.deepEqual(routed(path, onlyLine(text, n)), NONE, `${what}: only line ${n}: ${JSON.stringify(text)}`);
}

/** A page whose PHP prints a <script> tag, so its inline HTML may run as the script's code: `code` between, on lines of its own. */
const printed = (code) => `<?php echo '<script>'; ?>${code}\n<?php echo '</script>'; ?>`;

/**
 * A renamed header and its neighbours inside a script PHP prints: only the line that names the password changes, and
 * what gives it its literal (a body, a value, a default) stands after a piece of PHP on the unchanged line below.
 * [label, code, the text the changed line holds].
 */
const printedForms = () => [
  ["a method's header", `class Login {\n  ${GETTER}()\n<?php ?> { return ${literal()}; } }`, `${GETTER}()`],
  ["a function's header", `function ${GETTER}()\n<?php ?> { return ${literal()}; }`, `function ${GETTER}()`],
  ["an object's getter", `const login = { get ${NAME}()\n<?php ?> { return ${literal()}; } };`, `get ${NAME}()`],
  ["a class property", `class Login {\n  ${NAME}\n<?php ?> = ${literal()}; }`, `  ${NAME}`],
  ["a parameter's default", `function connect(${NAME}\n<?php ?> = ${literal()}) { return 1; }`, `connect(${NAME}`],
];

test("inside a script PHP prints, only the header, a property's or a parameter's name changes and the piece of PHP and the literal stand on the unchanged line below: the changed line counts and is human-only, as the whole file is", () => {
  // The method, the property and the parameter's default have their literal on the unchanged line below; the
  // function's and the getter's headers stand on the line PHP's echo opens.
  assert.ok(tokenize(["<?php $x;"])[0], "PHP's tokenizer runs (lib/php.mjs: PHP 8.2 or Docker)");
  // The code's first word touches the echo's piece, so line 1 counts too in the whole file.
  for (const [label, code, changed] of printedForms()) {
    const text = printed(code);
    counts(PAGE, text, lineOf(text, changed), label, [1]);
  }
});

test("the same forms in PHP's own code and in a JavaScript file: a header, a getter, a property, a parameter default and a value-only edit whose literal stands on the unchanged lines below count as the changed line", () => {
  // The value-only edits (an operand renamed above an unchanged literal fallback) and the JavaScript method whose body
  // opens on the next line need the lines below; the other forms count where the lines end at the change too, as a
  // declaration left open there does.
  const php = [
    ["a function's header", `<?php\nfunction ${GETTER}()\n{\n  return ${literal()};\n}`, `function ${GETTER}`],
    ["a method's header", `<?php\nclass Login\n{\n  public function ${GETTER}()\n  {\n    return ${literal()};\n  }\n}`, `function ${GETTER}`],
    ["a property", `<?php\nclass Login\n{\n  public $${NAME} =\n    ${literal()};\n}`, `$${NAME} =`],
    ["a parameter's default", `<?php\nfunction connect($${NAME} =\n  ${literal()}) {\n  return 1;\n}`, `connect($${NAME}`],
    ["a value-only edit", `<?php\n$${NAME} = $input\n  ?? ${literal()};`, `$${NAME} = $input`],
  ];
  for (const [label, text, changed] of php) counts(CODE, text, lineOf(text, changed), `PHP, ${label}`);
  const js = [
    ["a method's header", `class Login {\n  ${GETTER}()\n  { return ${literal()}; }\n}`, `${GETTER}()`],
    ["an object's getter", `const login = {\n  get ${NAME}()\n  { return ${literal()}; },\n};`, `get ${NAME}()`],
    ["a property", `const login = {\n  ${NAME}:\n    ${literal()},\n};`, `${NAME}:`],
    ["a parameter's default", `function connect(${NAME} =\n  ${literal()}) {\n  return 1;\n}`, `connect(${NAME}`],
    ["a value-only edit", `const ${NAME} = input\n  || ${literal()};`, `const ${NAME} = input`],
  ];
  for (const [label, text, changed] of js) counts(SCRIPT, text, lineOf(text, changed), `JavaScript, ${label}`);
});

test("plain controls: a header, a getter, a property or a value whose unchanged lines below hold only references is no hit as a changed line, as the whole file is (a plain password reference needs no person)", () => {
  // Renaming a PHP function or method whose body returns a reference ($this->x) goes to no person: the declaration is
  // read to its end, not taken as left open where the change ends.
  const php = [
    ["a function returning a reference", `<?php\nfunction ${GETTER}()\n{\n  return $this->${NAME};\n}`, `function ${GETTER}`],
    ["a method returning a reference", `<?php\nclass Login\n{\n  public function ${GETTER}(): string\n  {\n    return $this->${NAME};\n  }\n}`, `function ${GETTER}`],
    ["a value-only edit, a reference fallback", `<?php\n$${NAME} = $input\n  ?? $fallback;`, `$${NAME} = $input`],
    ["a request's field", `<?php\n$${NAME} = $_POST['pw'];\n$user = 'v1';`, `$${NAME} = $_POST`],
  ];
  for (const [label, text, changed] of php) clear(CODE, text, lineOf(text, changed), `PHP, ${label}`);
  const js = [
    ["a method returning a reference", `class Login {\n  ${GETTER}()\n  { return this.${NAME}; }\n}`, `${GETTER}()`],
    ["a value-only edit, a reference fallback", `const ${NAME} = input\n  || other;`, `const ${NAME} = input`],
    ["a statement that ends on its line", `const ${NAME} = input\nconst user = ${literal()};`, `const ${NAME} = input`],
  ];
  for (const [label, text, changed] of js) clear(SCRIPT, text, lineOf(text, changed), `JavaScript, ${label}`);
  // A script PHP prints that returns a reference after the piece below.
  const text = printed(`class Login {\n  ${GETTER}()\n<?php ?> { return this.${NAME}; } }`);
  // The piece of PHP after the header leaves what it prints unread, so the header counts there, whole and as the
  // changed line alike; the control is that both read line 2 the same. "class" touches the echo's piece, so the state
  // is not known from line 1, and the whole file counts lines 1 and 3 too.
  assert.deepEqual(routed(PAGE, onlyLine(text, 2)), hitAt(2), "a printed script: the changed line");
  assert.deepEqual(routed(PAGE, asNew(text)), hitAt(1, 2, 3), "a printed script: the whole file reads line 2 so too");
});

test("what the change does not open is not read on: a line that goes on with a statement an unchanged line above starts reads only the run, so that statement's unchanged literal below is not the change's; an unchanged line is never a hit", () => {
  // The read back over the lines above (readsBack) reads no line below the run.
  const lit = literal();
  const text = `<?php\n$${NAME} = $first\n  ?? $second\n  ?? ${lit};`;
  assert.deepEqual(routed(CODE, onlyLine(text, 3)), NONE, "the middle operand edited");
  assert.deepEqual(routed(CODE, asNew(text)), hitAt(2), "the whole file: the statement's first line");
  // The literal fallback itself, edited, counts (read back to the name).
  assert.deepEqual(routed(CODE, onlyLine(text, 4)), hitAt(4), "the literal's own line edited");
  // Two changed lines: the header and a line far below it; every hit is a changed line's.
  const page = `<?php\nfunction ${GETTER}()\n{\n  return ${literal()};\n}\n${"$x = 1;\n".repeat(5)}$${NAME} = $_POST['pw'];`;
  const lines = page.split("\n");
  const entries = addedWithContext(new Map([[2, lines[1]], [11, lines[10]]]), `${page}\n`);
  assert.deepEqual(routed(CODE, entries), hitAt(2), "header and a reference below");
  assert.ok(entries.some((entry) => entry.context && entry.line > 2 && entry.line < 11) && entries.every((entry) => entry.line <= lines.length), "the lines between and below are handed, none past the file's end");
});

test("where the reading stops: a declaration still open past WINDOW_LINES of the lines below counts, and where a caller hands no line below, a declaration open where the lines end counts", () => {
  // The 60-line body and the lines that end at the header count (fail closed); the 20-line body is read to its end,
  // where it returns a reference.
  const body = (n) => Array.from({ length: n }, (_, i) => `  log_it($a${i});`).join("\n");
  const long = `<?php\nfunction ${GETTER}()\n{\n${body(60)}\n  return $this->${NAME};\n}`;
  counts(CODE, long, 2, "a 60-line body below the header");
  const short = `<?php\nfunction ${GETTER}()\n{\n${body(20)}\n  return $this->${NAME};\n}`;
  clear(CODE, short, 2, "a 20-line body below the header, read to its end");
  assert.deepEqual(routed(CODE, onlyLine(short, 2, 0)), hitAt(2), "no line below handed: the body is open where the lines end");
});

test("addedWithContext hands the unchanged lines below each run, up to `after` of them and never past the next run or the file's last line; a last line no LF ends keeps its bare CR, marked eof", () => {
  const lines = (entries) => entries.map((entry) => [entry.line, entry.context === true]);
  assert.deepEqual(lines(addedWithContext(new Map([[2, "b"]]), "a\nb\nc\nd\n")), [[1, true], [2, false], [3, true], [4, true]]);
  assert.deepEqual(lines(addedWithContext(new Map([[2, "b"], [5, "e"]]), "a\nb\nc\nd\ne\nf\n", 1, 1)), [[1, true], [2, false], [3, true], [4, true], [5, false], [6, true]]);
  assert.deepEqual(lines(addedWithContext(new Map([[2, "b"]]), "a\nb\nc\nd\n", Infinity, 0)), [[1, true], [2, false]]);
  assert.deepEqual(addedWithContext(new Map([[1, "a"]]), "a\nb\nc\r"), [
    { line: 1, text: "a" },
    { line: 2, text: "b", context: true },
    { line: 3, text: "c\r", context: true, eof: true },
  ]);
  assert.deepEqual(addedWithContext(new Map([[1, "a"]]), "a\r\nb\r\n"), [
    { line: 1, text: "a" },
    { line: 2, text: "b", context: true },
  ]);
});

/**
 * A git repository in a scratch directory with `files` ([path, base content or null, head content]) committed as base
 * and written as head: what `check.mjs --station --json --base HEAD` routes as the password marker's hits (path:line)
 * and humanOnly, and the PR's door cross-check's findings for "Door: two-way" (check.mjs --pr-body).
 */
function onChange(files) {
  const dir = mkdtempSync(join(tmpdir(), "guardrails-read-on-"));
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
    const result = spawnSync(process.execPath, [CHECK, "--station", "--json", "--base", "HEAD"], { cwd: dir, encoding: "utf8", timeout: 600000 });
    assert.equal(result.status, 0, result.stderr);
    const report = JSON.parse(result.stdout);
    return { hits: report.markersHit.filter((hit) => hit.row === "secrets").map((hit) => `${hit.path}:${hit.line}`).sort(), humanOnly: report.humanOnly };
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

test("production wiring, check.mjs --station: a printed script's method header renamed in a real edit of an existing page, a value-only edit and a control, routed through changeset.mjs's markerLines", () => {
  const body = literal();
  const page = (method) => printed(`class Login {\n  ${method}()\n<?php ?> { return ${body}; } }`);
  assert.deepEqual(onChange([[PAGE, page("run"), page(GETTER)]]), { hits: [`${PAGE}:2`], humanOnly: true }, "a printed script: the header renamed");
  const value = (operand) => `<?php\n$${NAME} = ${operand}\n  ?? ${body};\n`;
  assert.deepEqual(onChange([[CODE, value("$a"), value("$b")]]), { hits: [`${CODE}:2`], humanOnly: true }, "a value-only edit above a literal fallback");
  // A control: the same rename where the unchanged body returns a reference, read to the body's end.
  const reference = (method) => `<?php\nfunction ${method}()\n{\n  return $this->${NAME};\n}\n`;
  assert.deepEqual(onChange([[CODE, reference("fetch"), reference(GETTER)]]), NONE, "a control: the header renamed above a reference body");
});

test("the base 5419eee2: the three lines that count where the lines end at the change are no hit read on through the end of what they open, as the whole file reads them; reading on makes no line a hit", () => {
  // A census of Saldi's tracked text files (whole files, every second and third line added, and every line that names
  // a password changed alone; 2,274 text files, 725,104 lines) found exactly these whose verdict as a changed line
  // reading on changes: each a hit where the lines end at the change (a declaration open there) and no hit with the
  // declaration read to its end, as the whole file is no hit. Reading on makes no line a hit.
  // - restapi/swagger.yaml:3488, an OpenAPI property given a nested schema (type, format, description, example) on the
  //   lines below: the edited key's value is that mapping.
  // - tests/TestCredentials.php:30 and tests/restapi/support/RestApiEnv.php:103, a password() method's header whose
  //   unchanged body returns a generated value or a reader's call.
  for (const [path, line] of [["restapi/swagger.yaml", 3488], ["tests/TestCredentials.php", 30], ["tests/restapi/support/RestApiEnv.php", 103]]) {
    const shown = spawnSync("git", ["show", `5419eee2:${path}`], { cwd: ROOT, encoding: "utf8", maxBuffer: 1 << 26 });
    assert.equal(shown.status, 0, shown.stderr);
    const text = shown.stdout.replace(/\n$/, "");
    const changed = addedWithContext(new Map([[line, text.split("\n")[line - 1]]]), shown.stdout);
    assert.deepEqual(routed(path, changed), NONE, `${path}:${line} as a changed line`);
    assert.deepEqual(routed(path, asNew(text)), NONE, `${path} whole`);
    const above = addedWithContext(new Map([[line, text.split("\n")[line - 1]]]), shown.stdout, Infinity, 0);
    assert.deepEqual(routed(path, above), hitAt(line), `${path}:${line} handed no line below: open where the lines end`);
  }
});
