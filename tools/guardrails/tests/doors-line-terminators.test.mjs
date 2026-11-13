// tools/guardrails/tests/doors-line-terminators.test.mjs - the password literal marker (lib/doors.mjs) and the code
// markers (lib/station.mjs) on every line terminator.
//
// doors.mjs defines where a line ends once (its "Line terminators" section: ECMAScript's LF, CR, U+2028 and U+2029 for
// JavaScript and TypeScript, CR LF one; LF, CR and CR LF for PHP and Python; a text format's), and every reader asks
// it; a change's line stays git's, so a hit is a line of the diff. A backslash continues a quote by its language's rule
// (doors.mjs CONTINUES): JavaScript's quotes over ECMAScript's line terminators, Python's over LF, CR and CR LF, a
// shell's "..." over LF, PHP's strings over none. A line comment ends where its file's language ends it. Where the
// reader is unsure the line counts (default deny), so a miss is harmless: a line terminator other than LF and CR LF (a
// bare CR, U+0085, U+2028, U+2029) puts its line outside the plain subset, and so does a regular expression, a comment
// or a template inside a template's interpolation; there a line that names a password counts with a reference too
// (assertCounts) and a code marker reads the line of the diff whole, while each LF and CR LF case of plain code still
// decides (assertDecides). Every JavaScript and TypeScript case parses in TypeScript and compiles in Node's vm.Script,
// every Python case compiles in Python, and PyYAML reads every YAML case's password key as the test's value. A PHP file
// starts in HTML, so the plain PHP comment case is read after its file's "<?php" line.
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
const REF = "process.env.X";
const literal = () => `'v${randomBytes(6).toString("hex")}'`;
const NONE = Object.freeze({ hits: [], humanOnly: false });

// ----- cases -------------------------------------------------------------------------------------------------------
// Self-contained: every case below is built from this region alone.

// Built at run time, so this file holds no literal for the marker to find.
const NAME = ["pass", "word"].join("");
const B = "`";
const q = "'";
const LS = String.fromCharCode(0x2028);
const PS = String.fromCharCode(0x2029);
/** ECMAScript's line terminators other than LF: [label, text]. LF is each form's own. */
const TERMINATORS = [
  ["CR", "\r"],
  ["CR LF", "\r\n"],
  ["U+2028", LS],
  ["U+2029", PS],
];

/**
 * `text` with its LF line breaks (where JavaScript reads a line terminator) given terminator `t`: each one alone, then
 * all of them: [label, text]. In these forms a line break stands in a quote only after a backslash that continues the
 * quote over it, which every terminator does.
 */
function variants(text, t, label) {
  const at = [];
  for (let k = text.indexOf("\n"); k >= 0; k = text.indexOf("\n", k + 1)) at.push(k);
  const out = at.map((k, n) => [`${label} for line break ${n + 1}`, text.slice(0, k) + t + text.slice(k + 1)]);
  if (at.length > 1) out.push([`${label} for every line break`, text.split("\n").join(t)]);
  return out;
}

/**
 * Plain code where a line terminator decides how JavaScript reads a "/" or a "{" before a password (automatic semicolon
 * insertion, a comment's end, a regular expression's line, a string continued over a line), V for the value: [text,
 * TypeScript only, plain]. The first is the probe the first test reads. The last three hold a regular expression in a
 * template's interpolation, outside the plain subset whatever the terminators.
 */
function plainForms(name) {
  const tail = `/'/.test(s); const ${name} = V;`;
  return [
    [`const n = async /*\n*/ function f() {} ${tail}`, false],
    [`const n = async\nfunction f() {} ${tail}`, false],
    [`const n = async /* a\n b */ function f() {} ${tail}`, false],
    [`const n = async // c\nfunction* g() {} ${tail}`, false],
    [`function g() {\n  return\n  function f() {} ${tail}\n}`, false],
    [`function g() {\n  return /*\n  */ class C {} ${tail}\n}`, false],
    [`function* g() {\n  yield\n  function f() {} ${tail}\n}`, false],
    [`x = y\nfunction f() {} ${tail}`, false],
    [`const n = obj.return\nfunction f() {} ${tail}`, false],
    [`const n = obj?.of\nasync function f() {} ${tail}`, false],
    [`x = y!\nfunction f() {} ${tail}`, true],
    [`function g() {\n  return\n  {} ${tail}\n}`, false],
    [`x = 'a'\n{} ${tail}`, false],
    [`for (;;) {\n  break\n  ${tail}\n}`, false],
    [`l: for (;;) {\n  continue l\n  ${tail}\n}`, false],
    [`const n = 1 /*\n*/ ++x / a; const ${name} = V; // /`, false],
    [`if (a) {\n  x();\n}\n/'/.test(s) && (${name} = V);`, false],
    [`const avg = sum\n  / total; const ${name} = V; // ok`, false],
    [`x = 1; // a note ' ${B} /* {\nconst ${name} = V;`, false],
    [`const of = 4; x = of / 2\n; const ${name} = V; /x/;`, false],
    [`const s = 'a\\\nb'; const ${name} = V;`, false],
    [`const t = ${B}x\n\${s.replace(/'/g, '')}${B}; const ${name} = V;`, false, false],
    [`const t = ${B}\${(() => { return\nfunction f() {} /'/.test(s); })()}${B}; const ${name} = V;`, false, false],
    [`f(${B}\${(() => { const n = async\nfunction f() {} /'/.test(s); return n; })()}${B}, { ${name}: V });`, false, false],
  ];
}

/** A quote that holds U+2028 or U+2029, which ECMAScript lets stand in one: it goes on to its close. [text, TypeScript only]. */
function quoteForms(name) {
  return [LS, PS].flatMap((t) => [
    [`const s = 'a${t}b'; const ${name} = V;`, false],
    [`const s = "a${t}/*"; const ${name} = V; // */`, false],
    [`const s = 'a${t}' + ${B}\${/'/.source}${B}; const ${name} = V;`, false],
  ]);
}

/** Every line terminator a backslash may stand before: [label, text]. */
const ALL_TERMINATORS = [["LF", "\n"], ...TERMINATORS];

/**
 * A JavaScript quote that a backslash continues over line terminator `t` (ECMAScript's LineContinuation: the two add
 * nothing, so '\<t>' is the empty string for every one of them), beside the value V, which ends its statement: in a
 * declaration, a template's interpolation, a fallback, an argument's key, a setter's argument, an assignment, a key, a name the continuation
 * splits, and TypeScript's typed declarations, class fields, a parameter property and a decorated field, where only the
 * member's line may change. [text, TypeScript only].
 */
function continuationForms(name, t) {
  const c = `\\${t}`;
  const split = `${name.slice(0, 4)}${c}${name.slice(4)}`;
  const setter = `set${name[0].toUpperCase()}${name.slice(1)}`;
  return [
    [`const ${name} = '${c}' + V;`, false],
    [`const ${name} = ${B}\${'${c}'}\${V}${B};`, false],
    [`const ${name} = '${c}' || V;`, false],
    [`connect({ ${name}: "${c}" + V });`, false],
    [`${setter}('${c}' + V);`, false],
    [`const ${name} = "${c}" + V;`, false],
    [`let ${name} = "${c}" + '${c}${c}' + V;`, false],
    [`cfg.${name} = "${c}" + V;`, false],
    [`const cfg = { ${name}: '${c}' + V };`, false],
    [`cfg['${split}'] = V;`, false],
    [`const ${name}: string = '${c}' + V;`, true],
    [`class C {\n  ${name}: string = '${c}' + V;\n}`, true],
    [`class C {\n  private ${name} = "${c}" + V;\n}`, true],
    [`class C {\n  constructor(private ${name}: string = '${c}' + V) {}\n}`, true],
    [`class C {\n  @Dec('a${c}b') ${name}: string = "${c}" + V;\n}`, true],
  ];
}

/**
 * The same backslash in Python and PHP, which read it otherwise: Python continues a quote over LF, CR and CR LF only
 * (before U+2028 or U+2029 the backslash and the character stay the string's text), and PHP over none (its strings
 * keep the backslash and the terminator, '...', "..." and a heredoc alike). [path, text with V, a reference, whether
 * the backslash leaves text in the string].
 */
function continuationOthers(name, t) {
  const c = `\\${t}`;
  const python = t === LS || t === PS;
  return [
    ["jobs/sync.py", `${name} = '${c}' + V`, "os.environ['X']", python],
    ["jobs/sync.py", `${name} = "${c}" + V`, "os.environ['X']", python],
    ["jobs/sync.py", `cfg = {'${name}': '${c}' + V}`, "os.environ['X']", python],
    ["includes/job.php", `<?php $${name} = "${c}" . V;`, "getenv('X')", true],
    ["includes/job.php", `<?php $${name} = '${c}' . V;`, "getenv('X')", true],
    ["includes/job.php", `<?php $cfg = ['${name}' => "${c}" . V];`, "getenv('X')", true],
    ["includes/job.php", `<?php $${name} = <<<EOT\n${c}\nEOT . V;`, "getenv('X')", true],
  ];
}

/**
 * Code markers on a line of the diff that opens with a comment's mark: [path, text, the rows that count]. A line that
 * holds a CR, U+2028, U+2029 or U+0085 is outside the plain subset, where the lexer establishes no comment:
 * the code marker reads all of it, the comment's text included. A plain comment line is passed over; a JavaScript # is a
 * private name, a PHP #[ an attribute, and a PHP comment that "?>" ends leaves the code after it.
 */
function commentLines() {
  const NEL = String.fromCharCode(0x85);
  const drop = ["destructive:DROP DATABASE"];
  const sms = ["outbound:send_sms("];
  const deploy = ["deploy:pull_request_target"];
  const cases = [];
  for (const [label, t] of [["CR", "\r"], ["U+2028", LS], ["U+2029", PS], ["U+0085", NEL]]) {
    cases.push(
      [`finans/report.js`, `// a note${t}db.query("DROP DATABASE x");`, drop, `${label}, JavaScript`],
      [`finans/report.mjs`, `  // a note ' " ${B}${t}await db.query("DROP DATABASE x");`, drop, `${label}, JavaScript, a quote in the comment`],
      [`includes/sms.php`, `# a note${t}send_sms($to, $text);`, sms, `${label}, PHP #`],
      [`includes/sms.php`, `    // a note${t}send_sms($to, $text);`, sms, `${label}, PHP //`],
      [`includes/sms.inc`, `# a note${t}send_sms($to, $text);`, sms, `${label}, PHP .inc`],
      [`.github/workflows/x.yml`, `# a note${t}on: pull_request_target`, deploy, `${label}, YAML`],
      [`finans/report.js`, `// DROP DATABASE x${t}x = 1;`, drop, `${label}, JavaScript, the marker in the comment's text`],
      [`includes/sms.php`, `# send_sms($to, $text)${t}$x = 1;`, sms, `${label}, PHP, the marker in the comment's text`],
      [`includes/sms.php`, `/* a note${t}send_sms($to, $text);`, sms, `${label}, a block comment`],
      [`finans/report.js`, ` * a note${t}db.query("DROP DATABASE x");`, drop, `${label}, a block comment's line`],
    );
  }
  cases.push(
    [`finans/report.js`, `// a note db.query("DROP DATABASE x");`, [], "a JavaScript comment"],
    [`finans/report.js`, `// a note db.query("DROP DATABASE x");\r`, [], "a JavaScript comment, its CR LF's CR"],
    [`includes/sms.php`, `<?php\n# a note send_sms($to, $text);\r`, [], "a PHP comment, its CR LF's CR"],
    [`.github/workflows/x.yml`, `# a note: pull_request_target`, [], "a YAML comment"],
    [`finans/report.js`, `#drop = () => db.query("DROP DATABASE x");`, drop, "a JavaScript private name"],
    [`includes/sms.php`, `#[Attr] function f() { send_sms($to, $text); }`, sms, "a PHP attribute"],
    [`includes/sms.php`, `// a note ?><?php send_sms($to, $text);`, sms, "a PHP comment a closing tag ends"],
  );
  return cases;
}

/** Interpolations whose line breaks end a statement (automatic semicolon insertion) or a comment before a "/". */
const INTERPOLATIONS = [
  `(() => { const n = async\nfunction f() {} /'/.test(s); return n; })()`,
  `(() => { return /*\n*/ function f() {} /'/.test(s); })()`,
  `(() => { return\nclass C {} /'/.test(s); })()`,
  `(() => { for (;;) { break\n/'/.test(s); } })()`,
  `a // ' ${B} }\n`,
];
/** Where the interpolation stands in a decorator's template: on the member's line, over lines, above it, tagged. */
const PLACEMENTS = [
  ["after the template's line break", (i) => `@Dec(${B}x\n\${${i}}${B})`],
  ["on one line", (i) => `@Dec(${B}\${${i}}${B})`],
  ["on the line above", (i) => `@Dec(${B}\${${i}}\n${B})`],
  ["over lines of its own", (i) => `@Dec(${B}x\n\${\n${i}\n}${B})`],
  ["in a tagged template", (i) => `@tag${B}x\n\${${i}}${B}`],
];
/** Members after the decorator, on their own line of the class: d the decorator, v the value. */
function members(name) {
  return [
    ["a field", (d, v) => `class C {\n  ${d} ${name}: string = ${v};\n}`],
    ["a quoted field", (d, v) => `class C {\n  ${d} ${q}${name}${q}: string = ${v};\n}`],
    ["a computed field", (d, v) => `class C {\n  ${d} [${q}${name}${q}]: string = ${v};\n}`],
    ["a method", (d, v) => `class C {\n  ${d} ${name}(): string { return ${v}; }\n}`],
    ["a parameter", (d, v) => `class C {\n  constructor(${d} ${name}: string = ${v}) {}\n}`],
  ];
}

/** Every decorator case: the decorator's own line breaks given each terminator, the member on its own line. [label, form]. */
function decoratorCases(name) {
  const cases = [];
  for (const inner of INTERPOLATIONS) {
    for (const [where, place] of PLACEMENTS) {
      const d = place(inner);
      for (const [tl, t] of TERMINATORS) {
        for (const [vl, dt] of variants(d, t, tl)) {
          for (const [label, member] of members(name)) cases.push([`${JSON.stringify(inner)} ${where}, ${vl}, ${label}`, (v) => member(dt, v)]);
        }
      }
    }
  }
  return cases;
}

/** Python and PHP, which end a line at CR and CR LF too: [path, text with V, a reference]. */
function otherLanguages(name) {
  return [
    ["jobs/sync.py", `x = 1  # a note '\r${name} = V`, "os.environ['X']"],
    ["jobs/sync.py", `x = 1\r${name} = V`, "os.environ['X']"],
    ["jobs/sync.py", `x = 1\r\n${name} = V`, "os.environ['X']"],
    ["jobs/sync.py", `def get_${name}():\r    return V\r`, "os.environ['X']"],
    ["jobs/sync.py", `cfg = {\r    'a': 1,\r    '${name}': V,\r}`, "os.environ['X']"],
    ["includes/job.php", `<?php $a = 1; // a note '\r$${name} = V;`, "getenv('X')"],
    ["includes/job.php", `<?php $a = 1; # a note "\r$${name} = V;`, "getenv('X')"],
    ["includes/job.php", `<?php $s = <<<EOT\rx\rEOT;\r$${name} = V;`, "getenv('X')"],
    ["includes/job.php", `<?php $s = <<<'EOT'\r\nx\r\nEOT;\r\n$${name} = V;`, "getenv('X')"],
  ];
}

/**
 * Text and YAML files, whose formats end a line at CR (YAML 1.2, INI, Markdown, .properties, dotenv) and in YAML 1.1 at
 * U+0085, U+2028 and U+2029 too, while a shell reads none of them: [path, text with V, a reference, YAML, whether the
 * reference counts]. YAML's literal is a plain scalar, so V is unquoted there. A reference counts where a reading
 * holds a literal beside it: YAML 1.2 ends no line at U+0085, which no reader takes for a space, so ${DB_X}<U+0085>b
 * is one scalar with literal characters.
 */
function textForms(name) {
  const NEL = String.fromCharCode(0x85);
  const upper = name.toUpperCase();
  const forms = [];
  for (const t of ["\r", LS, PS, NEL]) {
    forms.push(
      ["config/app.yml", `a: 1${t}${name}: V`, "${DB_X}", true, false],
      ["config/app.yml", `a: 1 # a note${t}${name}: V`, "${DB_X}", true, false],
      ["config/app.yml", `db:${t}  ${name}: V`, "${DB_X}", true, false],
      ["config/app.yml", `"${name}": V${t}b: 2`, "${DB_X}", true, t === NEL],
      ["config/app.yml", `- a${t}- ${name}: V`, "${DB_X}", true, false],
    );
  }
  forms.push(
    ["config/app.env", `A=1\r${upper}=V`, "${X}", false, false],
    ["config/app.ini", `a = 1\r${name} = V`, "${X}", false, false],
    ["doc_notes/setup.md", `Setup\r${name}: V`, "${X}", false, false],
    ["deploy/run.sh", `A=1\rexport PG${upper}=V`, "$X", false, false],
    ["deploy/run.sh", `PG${upper}=V\rpsql`, "$X", false, false],
  );
  return forms;
}

// ----- end cases ---------------------------------------------------------------------------------------------------

/** The password marker's lines and humanOnly, as check.mjs routes `entries` of `path` (markerHits and stationReport). */
function routed(path, entries) {
  const hits = markerHits(path, entries).filter((hit) => hit.row === "secrets").map((hit) => hit.line);
  const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, entries]]) });
  return { hits, humanOnly: report.humanOnly };
}
/** The whole of `text` added, as a new file, its lines as git's diff hands them: split at LF, each CR LF's CR kept. */
const wholeHit = (path, text) => routed(path, text.split("\n").map((line, i) => ({ line: i + 1, text: line })));
/** Only git's line `n` of `text` changes; the lines above it are unchanged context (changeset.mjs addedWithContext). */
function onlyHit(path, text, n) {
  const file = text.split("\n");
  return routed(path, addedWithContext(new Map([[n, file[n - 1]]]), `${text}\n`));
}
/** Git's line of `text` that holds `value`, 1-based. */
const lineOf = (text, value) => 1 + text.split("\n").findIndex((line) => line.includes(value));
const hitAt = (n) => ({ hits: [n], humanOnly: true });

/** Assert that `form` counts on its value's line with a literal, whole and where only that line changes, and not with a reference. */
function assertDecides(path, label, form, ref = REF) {
  const lit = literal();
  const hit = form(lit);
  const reference = form(ref);
  const n = lineOf(hit, lit);
  assert.deepEqual(wholeHit(path, hit), hitAt(n), `${path}, ${label}: a literal`);
  assert.deepEqual(wholeHit(path, reference), NONE, `${path}, ${label}: a reference`);
  assert.deepEqual(onlyHit(path, hit, n), hitAt(n), `${path}, ${label}: a literal, only its line changes`);
  assert.deepEqual(onlyHit(path, reference, n), NONE, `${path}, ${label}: a reference, only its line changes`);
}
const pathsOf = (tsOnly) => (tsOnly ? ["api/client.ts"] : ["api/client.ts", "api/client.js"]);

/**
 * Assert that `form`, outside the plain subset, counts on its value's line with a literal and with `ref`
 * alike, whole and where only that line changes: no reader clears a line that names a password there.
 */
function assertCounts(path, label, form, ref = REF) {
  const lit = literal();
  const n = lineOf(form(lit), lit);
  for (const [v, kind] of [
    [lit, "a literal"],
    [ref, "a reference, outside the plain subset"],
  ]) {
    assert.deepEqual(wholeHit(path, form(v)), hitAt(n), `${path}, ${label}: ${kind}`);
    assert.deepEqual(onlyHit(path, form(v), n), hitAt(n), `${path}, ${label}: ${kind}, only its line changes`);
  }
}

/** Whether terminator `t` keeps a text in the plain subset: LF, or a CR LF (whose CR is the line's terminator's). */
const plainTerminator = (t) => t === "\n" || t === "\r\n";

test("secrets, through markerHits and stationReport: the probe const n = async /*<terminator>*/ function f() {} /'/.test(s); with a password after it counts with a literal and not with a reference for LF and CR LF, whole and where only its line changes; with a CR, U+2028 or U+2029 it is outside the plain subset and counts with a reference too", () => {
  // Automatic semicolon insertion reads every terminator inside the block comment: knowing only LF there misses the
  // literal after a CR, U+2028 or U+2029.
  const probe = (t) => `const n = async /*${t}*/ function f() {} /'/.test(s); const ${NAME} = V;`;
  for (const [label, t] of [["LF", "\n"], ...TERMINATORS]) {
    for (const path of ["api/client.ts", "api/client.js"]) (plainTerminator(t) ? assertDecides : assertCounts)(path, `the probe with ${label}`, (v) => probe(t).replace("V", v));
  }
});

test("secrets, plain code, through markerHits and stationReport: every line break that automatic semicolon insertion, a comment's end, a regular expression's line or a string's continuation reads, given a CR LF (each alone, then all), decides as with LF: a literal counts on its line and a reference does not, whole and where only its line changes; given a CR, U+2028 or U+2029, or with a regular expression in a template's interpolation, the line is outside the plain subset and counts with a reference too", () => {
  // The terminator decides where async, return, break or a block comment stands before it, where it ends a line
  // comment, where a "/" would close a regular expression only past it, after break (break<U+2028> is no single name),
  // and, with the diff's CR kept, where a backslash continues a quote over CR LF.
  for (const [text, tsOnly, plain = true] of plainForms(NAME)) {
    for (const [tl, t] of TERMINATORS) {
      for (const [label, form] of variants(text, t, tl)) {
        for (const path of pathsOf(tsOnly)) (plain && plainTerminator(t) ? assertDecides : assertCounts)(path, `${JSON.stringify(text)}, ${label}`, (v) => form.replace("V", v));
      }
    }
  }
});

test("secrets, decorator interpolations, through markerHits and stationReport: a decorator template's interpolation that holds a regular expression or a comment, its line breaks given a CR, CR LF, U+2028 or U+2029 (each alone, then all) wherever the interpolation stands, is outside the plain subset, so a field, a quoted or computed field, a method and a parameter after it count with a literal and with a reference alike, also where only the member's line changes", () => {
  for (const [label, form] of decoratorCases(NAME)) assertCounts("api/client.ts", label, form);
});

test("secrets: a quote that holds U+2028 or U+2029 (ECMAScript lets one stand there) puts its line outside the plain subset, so a password after its close counts with a literal and with a reference alike", () => {
  for (const [text, tsOnly] of quoteForms(NAME)) {
    for (const path of pathsOf(tsOnly)) assertCounts(path, JSON.stringify(text), (v) => text.replace("V", v));
  }
});

test("secrets, JavaScript's continuation, through markerHits and stationReport: a backslash continues a JavaScript quote over LF and CR LF and adds nothing, so a password given '\\<line break>' or \"\\<line break>\" beside a reference is a pure reference and no hit, in plain code, keys, a name the continuation splits, TypeScript declarations, class fields, a parameter property and a decorated field, also where only the member's line changes; with a literal it counts; before a CR, U+2028 or U+2029 the line is outside the plain subset and counts with a reference too", () => {
  // A hit is the name's line of the diff; where only the value's line changes, that line. Over LF and CR LF the
  // continuation is a line of the diff of its own, so the name and the value may stand on different ones.
  for (const [tl, t] of ALL_TERMINATORS) {
    for (const [text, tsOnly] of continuationForms(NAME, t)) {
      for (const path of pathsOf(tsOnly)) {
        const label = `${path}, ${tl}: ${JSON.stringify(text)}`;
        const lit = literal();
        const hit = text.replace("V", lit);
        const reference = text.replace("V", REF);
        const name = 1 + hit.split("\n").findIndex((line) => line.toLowerCase().includes(NAME.slice(0, 4)));
        const value = lineOf(hit, lit);
        const outside = plainTerminator(t) ? NONE : null;
        assert.deepEqual(wholeHit(path, hit), hitAt(name), `${label}: a literal`);
        assert.deepEqual(wholeHit(path, reference), outside ?? hitAt(name), `${label}: a reference`);
        assert.deepEqual(onlyHit(path, hit, value), hitAt(value), `${label}: a literal, only its line changes`);
        assert.deepEqual(onlyHit(path, reference, value), outside ?? hitAt(value), `${label}: a reference, only its line changes`);
      }
    }
    // What the continuation leaves of the quote counts beside a reference.
    for (const path of pathsOf(false)) assert.deepEqual(wholeHit(path, `const ${NAME} = '\\${t}x' + ${REF};`), hitAt(1), `${path}, ${tl}: a character after the continuation`);
    // A PHP page's script reads its quotes as JavaScript does.
    const page = (v) => `<script>var ${NAME} = '\\${t}' + ${v};</script>`;
    assert.deepEqual(wholeHit("includes/page.php", page(literal())), hitAt(1), `a PHP page's script, ${tl}: a literal`);
    assert.deepEqual(wholeHit("includes/page.php", page(REF)), plainTerminator(t) ? NONE : hitAt(1), `a PHP page's script, ${tl}: a reference`);
  }
});

test("secrets, the other languages' continuations: Python continues a quote over LF and CR LF, and PHP none of its strings, so a backslash before a line break leaves a PHP string's text, and a password given it beside a reference counts; where the language continues, the reference is no hit; before a CR, U+2028 or U+2029 the line is outside the plain subset and counts", () => {
  // Read as continued, PHP's "...", its key and its heredoc over LF would be no hit beside a reference.
  for (const [tl, t] of ALL_TERMINATORS) {
    for (const [path, text, ref, leaves] of continuationOthers(NAME, t)) {
      const counts = leaves || !plainTerminator(t);
      const lit = literal();
      const hit = text.replace("V", lit);
      const reference = text.replace("V", ref);
      const n = lineOf(hit, NAME);
      if (plainTerminator(t)) {
        assert.deepEqual(wholeHit(path, hit), hitAt(n), `${path}, ${tl}, ${JSON.stringify(text)}: a literal`);
        assert.deepEqual(wholeHit(path, reference), counts ? hitAt(n) : NONE, `${path}, ${tl}, ${JSON.stringify(text)}: a reference`);
        continue;
      }
      // Outside the plain subset the name's line counts, and so does each later line its statement may run on to.
      for (const [form, kind] of [
        [hit, "a literal"],
        [reference, "a reference"],
      ]) {
        const routed = wholeHit(path, form);
        assert.ok(routed.humanOnly && routed.hits[0] === n, `${path}, ${tl}, ${JSON.stringify(text)}: ${kind}: ${JSON.stringify(routed)}`);
      }
    }
  }
});

test("secrets: PHP and Python read a CR LF as a line break: a line comment ends there, a Python statement and a dict over lines and a PHP heredoc's lines too, so a literal after one counts on its line and a reference does not; a bare CR puts the line outside the plain subset, where a reference counts too", () => {
  // A Python or PHP line comment holding a quote ends at the line break: run on past it, the literal after it would be
  // no hit.
  for (const [path, text, ref] of otherLanguages(NAME)) {
    const lit = literal();
    const hit = text.replace("V", lit);
    const reference = text.replace("V", ref);
    const n = lineOf(hit, lit);
    assert.deepEqual(wholeHit(path, hit), hitAt(n), `${path}, ${JSON.stringify(text)}: a literal`);
    assert.deepEqual(wholeHit(path, reference), /\r(?!\n)/.test(text) ? hitAt(n) : NONE, `${path}, ${JSON.stringify(text)}: a reference`);
  }
});

test("secrets: text files: a line of the diff that holds a CR (YAML 1.2, INI, Markdown, dotenv) or a U+0085, U+2028 or U+2029 (YAML 1.1) is outside the plain subset, so a key, a list item's key, a quoted key and an assignment after one count with a literal and with a reference alike; so does an environment name alone before one in a script, which with LF decides", () => {
  // Reading each such line as the lines it holds (textLines) would leave the references no hit.
  for (const [path, text, ref] of textForms(NAME)) {
    const lit = literal().slice(1, -1);
    const hit = text.replace("V", lit);
    const reference = text.replace("V", ref);
    const n = lineOf(hit, lit);
    assert.deepEqual(wholeHit(path, hit), hitAt(n), `${path}, ${JSON.stringify(text)}: a literal`);
    assert.deepEqual(wholeHit(path, reference), hitAt(n), `${path}, ${JSON.stringify(text)}: a reference`);
  }
  // An environment name given a word alone on its line counts in any file; a reference does with LF only.
  const upper = NAME.toUpperCase();
  for (const t of ["\n", "\r", LS, PS]) {
    const lit = literal().slice(1, -1);
    assert.deepEqual(wholeHit("api/job.js", `PG${upper}=${lit}${t}run();`), hitAt(1), JSON.stringify(t));
    assert.deepEqual(wholeHit("api/job.js", `PG${upper}=$X${t}run();`), t === "\n" ? NONE : hitAt(1), `${JSON.stringify(t)}: a reference`);
  }
});

test("code markers: a line of the diff that opens with a comment's mark and holds a CR, U+2028, U+2029 or U+0085 is outside the plain subset, so the lexer establishes no comment there and DROP DATABASE, send_sms( and pull_request_target anywhere on it count, in JavaScript, PHP and YAML; a plain comment line is passed over; a JavaScript private name, a PHP attribute and the code a PHP closing tag leaves after a comment count", () => {
  // Passing over every line that opens with "//" or "#" misses the CR cases, JavaScript's and YAML's other
  // terminators, a JavaScript private name, a PHP attribute and a PHP comment "?>" ends; ending a comment where its
  // language ends one and passing over the rest misses a marker in the comment's own text, a PHP comment holding
  // U+2028, U+2029 or U+0085, a JavaScript one holding U+0085, a block comment and its lines.
  const rowsOf = (path, text) => [...new Set(markerHits(path, text.split("\n").map((line, i) => ({ line: i + 1, text: line }))).map((hit) => `${hit.row}:${hit.marker}`))].sort();
  for (const [path, text, rows, label] of commentLines()) assert.deepEqual(rowsOf(path, text), rows, `${path}, ${label}: ${JSON.stringify(text)}`);
});

test("the one definition: LINE_END is LF; lineText takes a CR LF's CR off a line of the diff and keeps a line's own; fileLines splits at LF; holdsUnusualTerminator finds a bare CR, U+0085, U+2028 and U+2029, never LF or a CR LF; terminatorAt, nextTerminator and holdsTerminator, which would read the unusual terminators per language, are not exported", async () => {
  const doors = await import(DOORS);
  assert.equal(doors.LINE_END, "\n");
  const NEL = String.fromCharCode(0x85);
  for (const [text, unusual] of [
    ["a\nb", false],
    ["a\r\nb", false],
    ["a\r\n", false],
    ["a", false],
    ["", false],
    ["a\rb", true],
    ["a\r", true],
    ["a\r\r\n", true],
    [`a${LS}b`, true],
    [`a${PS}b`, true],
    [`a${NEL}b`, true],
  ]) {
    assert.equal(doors.holdsUnusualTerminator(text), unusual, JSON.stringify(text));
  }
  for (const gone of ["terminatorAt", "nextTerminator", "holdsTerminator"]) assert.equal(doors[gone], undefined, gone);
  assert.equal(doors.lineText("a\r"), "a");
  assert.equal(doors.lineText("a\r\r"), "a\r\r", "a line whose own text ends in a CR keeps its CR LF's CR (doors-quotes-by-context.test.mjs)");
  assert.equal(doors.lineText(`a${LS}`), `a${LS}`);
  assert.deepEqual(doors.fileLines(`a\r\nb\rc${LS}d\ne\n`), ["a", `b\rc${LS}d`, "e", ""]);
});

test("the changeset's line splitting: splitLines splits a file at git's LF and keeps no CR LF's CR, and addedWithContext hands added and unchanged lines alike without it; a bare CR, U+2028 or U+2029 stays inside its line of the diff", () => {
  // addedWithContext and splitLines agree: neither hands a CR LF's CR in a line's text.
  const file = `one\r\ntwo\rhalf${LS}x\r\nthree${PS}y\r\nfour\r\n`;
  assert.deepEqual(splitLines(file), ["one", `two\rhalf${LS}x`, `three${PS}y`, "four", ""]);
  assert.deepEqual(splitLines(null), []);
  const added = new Map([
    [2, `two\rhalf${LS}x\r`],
    [4, "four\r"],
  ]);
  assert.deepEqual(addedWithContext(added, file), [
    { line: 1, text: "one", context: true },
    { line: 2, text: `two\rhalf${LS}x` },
    { line: 3, text: `three${PS}y`, context: true },
    { line: 4, text: "four" },
  ]);
});

/**
 * A git repository in a scratch directory with `files` ([path, base content or null, head content]) committed as base
 * and written as head, byte for byte: what `check.mjs --station --json --base HEAD` routes as the password marker's
 * hits (path:line) and humanOnly, and every marker row it hits.
 */
function stationOnChange(files) {
  const dir = mkdtempSync(join(tmpdir(), "guardrails-line-terminators-"));
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

test("production wiring, check.mjs --station: the probe with CR, U+2028 and U+2029 in new files, a decorator over CR LF lines whose member's line alone changes, an untracked CR LF file and a comment a CR ends before DROP DATABASE count; the same with references do not in plain code, and do outside the plain subset (the probes with CR, U+2028 and U+2029, and the decorator whose interpolation holds a regular expression)", () => {
  const lit = () => literal();
  const probe = (t, v) => `const n = async /*${t}*/ function f() {} /'/.test(s); const ${NAME} = ${v};\n`;
  const decorated = (v) => `class C {\r\n  @Dec(${B}x\r\n\${(() => { const n = async\rfunction f() {} /'/.test(s); return n; })()}${B})\r\n  ${NAME}: string = ${v};\r\n}\r\n`;
  const files = (v) => [
    ["api/cr.ts", null, probe("\r", v)],
    ["api/ls.js", null, probe(LS, v)],
    ["api/ps.ts", null, probe(PS, v)],
    ["api/member.ts", decorated(REF), decorated(v)],
    ["api/crlf.js", null, `const n = async /*\r\n*/ function f() {} /'/.test(s); const ${NAME} = ${v};\r\n`],
    ["finans/drop.js", null, `// a note\rdb.query("DROP DATABASE x");\n`],
  ];
  const withLiterals = stationOnChange(files(lit()));
  assert.deepEqual(withLiterals.hits, ["api/cr.ts:1", "api/crlf.js:2", "api/ls.js:1", "api/member.ts:4", "api/ps.ts:1"]);
  assert.equal(withLiterals.humanOnly, true);
  assert.deepEqual(withLiterals.rows, ["finans/drop.js:destructive"]);
  // With the reference, api/member.ts is its base: no line of it changes.
  const withReferences = stationOnChange(files(REF).filter(([path]) => path !== "finans/drop.js"));
  assert.deepEqual(withReferences, { hits: ["api/cr.ts:1", "api/ls.js:1", "api/ps.ts:1"], rows: [], humanOnly: true });
  assert.deepEqual(stationOnChange([["api/crlf.js", null, `const n = async /*\r\n*/ function f() {} /'/.test(s); const ${NAME} = ${REF};\r\n`]]), { hits: [], rows: [], humanOnly: false });
});

test("production wiring, check.mjs --station, continuations and comment lines: a JavaScript quote a backslash continues over U+2028 or U+2029 is outside the plain subset, so it counts with a literal and with a reference alike, in a new file and in a TypeScript member whose line alone changes; PHP's \"\\<LF>\" beside a reference counts; a PHP comment line that holds a CR, U+2028, U+2029 or U+0085 keeps send_sms( in it", () => {
  const NEL = String.fromCharCode(0x85);
  const member = (v) => `class C {\n  ${NAME}: string = '\\${LS}' + ${v};\n}\n`;
  const files = (v) => [
    ["api/ls.ts", member("process.env.Y"), member(v)],
    ["api/ps.js", null, `const ${NAME} = "\\${PS}" + ${v};\n`],
    ["api/both.mjs", null, `export const cfg = { ${NAME}: '\\${LS}\\${PS}' + ${v} };\n`],
  ];
  const lit = literal();
  const withLiterals = stationOnChange(files(lit));
  assert.deepEqual(withLiterals.hits, ["api/both.mjs:1", "api/ls.ts:2", "api/ps.js:1"]);
  assert.equal(withLiterals.humanOnly, true);
  assert.deepEqual(stationOnChange(files(REF)), { hits: ["api/both.mjs:1", "api/ls.ts:2", "api/ps.js:1"], rows: [], humanOnly: true });
  const php = stationOnChange([
    ["includes/job.php", null, `<?php\n$${NAME} = "\\\n" . getenv('X');\n`],
    ...[["ls", LS], ["ps", PS], ["nel", NEL]].map(([label, t]) => [`includes/sms_${label}.php`, null, `<?php\n# a note${t}send_sms($to, $text);\n// a note${t}send_sms($to, $text);\n`]),
    ["includes/sms_cr.php", null, `<?php\n# a note\rsend_sms($to, $text);\n`],
  ]);
  assert.deepEqual(php, { hits: ["includes/job.php:2"], rows: ["includes/sms_cr.php:outbound", "includes/sms_ls.php:outbound", "includes/sms_nel.php:outbound", "includes/sms_ps.php:outbound"], humanOnly: true });
});

test("markers stay linear: 20,000 statements a CR, U+2028 or U+2029 ends on one line of the diff, 20,000 block comments that hold one, 20,000 line comments one ends and a 20,000-line CR LF file grow no faster than linearly (linear-time.mjs), and count only the literal at their end", () => {
  // It holds the reading the terminators add to linear time.
  const lit = literal();
  const bodies = [
    // Joined with "+", so that no template of this file's own holds a template in an interpolation.
    ...[["CR", "\r"], ["U+2028", LS], ["U+2029", PS]].flatMap(([label, t]) => [
      [`statements over ${label}`, (n) => `x = y${t}`.repeat(n) + `const ${NAME} = ${lit};`, () => 1],
      [`block comments holding ${label}`, (n) => `x = async /*${t}*/ function f() {} /'/.test(s);`.repeat(n) + ` const ${NAME} = ${lit};`, () => 1],
      [`line comments ended by ${label}`, (n) => `x = 1; // ' ${B} /*${t}`.repeat(n) + `const ${NAME} = ${lit};`, () => 1],
    ]),
    ["a CR LF file", (n) => "x = y;\r\n".repeat(n) + `const ${NAME} = ${lit};\r\n`, (n) => n + 1],
  ];
  for (const [label, text, line] of bodies) {
    const read = (n) => {
      const body = text(n);
      return wholeHit("api/client.js", body.endsWith("\n") ? body.slice(0, -1) : body);
    };
    assert.deepEqual(linear(label, read, 20000), hitAt(line(20000)), label);
  }
});
