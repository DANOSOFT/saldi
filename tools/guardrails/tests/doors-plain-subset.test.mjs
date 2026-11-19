// tools/guardrails/tests/doors-plain-subset.test.mjs - the password literal marker's plain subset (lib/doors.mjs) and
// the comments a code marker passes over (lib/station.mjs).
//
// A plain password reference in ordinary code needs no person; where the reader is unsure, the line counts (default
// deny), so a miss is harmless. doors.mjs defines the plain subset once: a block is plain up to its first line that
// holds a line terminator other than LF and CR LF (a bare CR, U+0085, U+2028, U+2029), or where the lexer's state stops
// being known (a comment, a regular expression or a template inside a template's interpolation, a "/" whose readings
// differ past its line, past LEAD_FRAMES). Outside it a line that names a password counts with a reference too. A code
// marker passes over a line only where the lexer has established that all of it is comment, so a destructive marker in
// an executable template interpolation after a CR, U+2028 or U+2029 counts. Inside an explicit <script> element of a
// PHP page JavaScript's string rules hold. A piece of PHP anywhere but in HTML's data state leaves HTML's state after
// it unknown, and a name whose text touches a piece of PHP stays unresolved (doors.mjs edgeNames). The census tests read
// the base 5419eee2 and pin what these rules cost there; every .js case compiles in Node's vm.Script, and the
// destructive probes' interpolations run there and call db.query.
//
// History:
// 20261106 CL/LH Created.
import test from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { randomBytes } from "node:crypto";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
// PHP files are read by PHP's own tokens (lib/php.mjs gives doors.mjs its tokenizer).
import "../lib/php.mjs";
import { markerHits, stationReport } from "../lib/station.mjs";
import { addedWithContext, VENDORED } from "../lib/changeset.mjs";
import { holdsUnusualTerminator } from "../lib/doors.mjs";

const CHECK = fileURLToPath(new URL("../check.mjs", import.meta.url));
const ROOT = fileURLToPath(new URL("../../../", import.meta.url));
// Built at run time, so this file holds no literal for the marker to find.
const NAME = ["pass", "word"].join("");
const UPPER = NAME.toUpperCase();
const B = "`";
const LS = String.fromCharCode(0x2028);
const PS = String.fromCharCode(0x2029);
const NEL = String.fromCharCode(0x85);
const REF = "process.env.X";
const literal = () => `'v${randomBytes(6).toString("hex")}'`;
const NONE = Object.freeze({ hits: [], humanOnly: false });
const hitAt = (n) => ({ hits: [n], humanOnly: true });
/** The line terminators that keep a line in the plain subset, and the ones that put it outside: [label, text]. */
const PLAIN = [
  ["LF", "\n"],
  ["CR LF", "\r\n"],
];
const UNUSUAL = [
  ["CR", "\r"],
  ["U+0085", NEL],
  ["U+2028", LS],
  ["U+2029", PS],
];

/** The password marker's lines and humanOnly, as check.mjs routes `entries` of `path` (markerHits and stationReport). */
function routed(path, entries) {
  const hits = markerHits(path, entries).filter((hit) => hit.row === "secrets").map((hit) => hit.line);
  const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, entries]]) });
  return { hits, humanOnly: report.humanOnly };
}
/** The whole of `text` added as a new file, its lines as git's diff hands them: split at LF. */
const wholeHit = (path, text) => routed(path, text.split("\n").map((line, i) => ({ line: i + 1, text: line })));
/** Only git's line `n` of `text` changes; the lines above it are unchanged context (changeset.mjs addedWithContext). */
function onlyHit(path, text, n) {
  const file = text.split("\n");
  return routed(path, addedWithContext(new Map([[n, file[n - 1]]]), `${text}\n`));
}
/** Git's line of `text` that holds `value`, 1-based. */
const lineOf = (text, value) => 1 + text.split("\n").findIndex((line) => line.includes(value));
/** The one-way rows (row:marker) markerHits finds on `entries` of `path`, but the password marker. */
const codeRows = (path, entries) => [...new Set(markerHits(path, entries).filter((hit) => hit.row !== "secrets").map((hit) => `${hit.row}:${hit.marker}`))].sort();
const wholeRows = (path, text) => codeRows(path, text.split("\n").map((line, i) => ({ line: i + 1, text: line })));

/**
 * A line that names a password, a reference given to it, with terminator `t` where a line may end: [path, form(t, v),
 * the reference]. With LF or CR LF in its place the reference is plain code, which needs no person.
 */
function terminatorForms() {
  return [
    ["api/client.js", (t, v) => `const ${NAME} = ${v}; // a note${t}run();`, REF],
    ["api/client.ts", (t, v) => `let ${NAME}: string = ${v};${t}run();`, REF],
    ["api/client.js", (t, v) => `connect({ ${NAME}: ${v} });${t}run();`, REF],
    ["includes/db.php", (t, v) => `<?php $${NAME} = ${v};${t}run();`, "getenv('X')"],
    ["includes/db.php", (t, v) => `<?php $cfg = ['${NAME}' => ${v}];${t}run();`, "getenv('X')"],
    ["jobs/sync.py", (t, v) => `${NAME} = ${v}${t}run()`, "os.environ['X']"],
    ["config/app.yml", (t, v) => `${NAME}: ${v}${t}b: 1`, "${DB_X}"],
    ["config/app.env", (t, v) => `DB_${UPPER}=${v}${t}A=1`, "${X}"],
    ["config/app.ini", (t, v) => `${NAME} = ${v}${t}a = 1`, "${X}"],
    ["deploy/run.sh", (t, v) => `export PG${UPPER}="${v}"${t}psql`, "$X"],
    ["config/app.json", (t, v) => `{"${NAME}": "${v}"}${t}{}`, "${X}"],
  ];
}

test("the plain subset's terminators: a line that holds a CR, U+0085, U+2028 or U+2029 and names a password counts with a pure reference, in JavaScript, TypeScript, PHP, Python, YAML, environment, INI, shell and an unknown format; the same reference with LF or CR LF in its place is plain code and no hit, and a literal counts with every terminator", () => {
  // Reading each such line as the lines its language makes of it would leave the reference no hit and humanOnly false
  // (and in PHP and Python U+2028, U+2029 and U+0085 are a name's or a string's characters).
  for (const [path, form, ref] of terminatorForms()) {
    for (const [tl, t] of [...PLAIN, ...UNUSUAL]) {
      const plain = !holdsUnusualTerminator(t);
      const lit = path.match(/\.(yml|env|ini|sh|json)$/) ? `v${randomBytes(6).toString("hex")}` : literal();
      const label = `${path}, ${tl}: ${JSON.stringify(form(t, "V"))}`;
      assert.deepEqual(wholeHit(path, form(t, lit)), hitAt(1), `${label}: a literal`);
      assert.deepEqual(wholeHit(path, form(t, ref)), plain ? NONE : hitAt(1), `${label}: a reference`);
    }
  }
});

test("holdsUnusualTerminator is the one test of a line terminator other than LF and CR LF", () => {
  for (const [text, unusual] of [
    ["const a = 1;", false],
    ["a\nb", false],
    ["a\r\nb", false],
    ["a\rb", true],
    ["a\r\r\n", true],
    [`a${NEL}`, true],
    [`a${LS}`, true],
    [`a${PS}`, true],
  ]) {
    assert.equal(holdsUnusualTerminator(text), unusual, JSON.stringify(text));
  }
});

test("outside the plain subset a name counts as it is spelled once its backslashes and unusual terminators are taken out: a JavaScript key 'pa\\<U+2028>ssword' (the string password) given a reference counts; the same name over LF stays plain", () => {
  // Read as JavaScript's continuation, the name is password and the value a reference: no hit and humanOnly false.
  for (const t of [LS, PS, "\r"]) {
    const text = `cfg['pa\\${t}ssword'] = ${REF};`;
    assert.deepEqual(wholeHit("api/client.js", text), hitAt(1), JSON.stringify(text));
  }
  assert.deepEqual(wholeHit("api/client.js", `cfg['pa\\\nssword'] = ${REF};`), NONE, "over LF the continuation is plain");
  assert.deepEqual(wholeHit("api/client.js", `cfg['pa\\\nssword'] = ${literal()};`), hitAt(1), "over LF a literal counts");
});

/** Interpolations: [code, plain]. A comment, a regular expression or a template inside one is outside the plain subset. */
const INTERPOLATIONS = [
  ["a /* c */", false],
  ["a // c\n", false],
  ["/x/.test(s)", false],
  ['s.replace(/a/g, "")', false],
  [B + "inner" + B, false],
  ["a ? " + B + "x" + B + ' : ""', false],
  ["a", true],
  ['"}"', true],
  ["f(a, b)", true],
  ["a / b", true],
  ["(a)", true],
  ['o["k"]', true],
  ["a ?\n  b : c", true],
];

test("the plain subset's interpolations: a comment, a regular expression or a template inside a template's interpolation leaves the plain subset, so a password named on any later line of the block counts with a reference too, whole and where only its line changes; strings, groups, divisions and names there are plain, and a reference after them is no hit", () => {
  // Read as JavaScript reads them, the comments, regular expressions and templates would leave the reference after
  // them no hit.
  for (const [code, plain] of INTERPOLATIONS) {
    for (const path of ["api/client.js", "api/client.ts"]) {
      const form = (v) => "const t = " + B + "x${" + code + "}y" + B + `;\nrun();\nconst ${NAME} = ${v};`;
      const n = lineOf(form(REF), NAME);
      const label = `${path}: ${JSON.stringify(code)}`;
      assert.deepEqual(wholeHit(path, form(literal())), hitAt(n), `${label}: a literal`);
      assert.deepEqual(wholeHit(path, form(REF)), plain ? NONE : hitAt(n), `${label}: a reference`);
      assert.deepEqual(onlyHit(path, form(REF), n), plain ? NONE : hitAt(n), `${label}: a reference, only its line changes`);
    }
  }
});

test("the plain subset in a PHP page: a script's template whose interpolation holds a regular expression, a comment or a template, which PHP's lexer reads as a backtick string, leaves the plain subset, so a password named after it counts with a reference; a plain interpolation leaves a reference after it no hit", () => {
  for (const [code, plain] of INTERPOLATIONS.filter(([c]) => !c.includes("\n"))) {
    const page = (v) => "<?php echo 1; ?>\n<script>\nconst t = " + B + "${" + code + "}" + B + `;\nvar ${NAME} = ${v};\n</script>`;
    assert.deepEqual(wholeHit("includes/page.php", page(literal())), hitAt(4), `${JSON.stringify(code)}: a literal`);
    assert.deepEqual(wholeHit("includes/page.php", page("x")), plain ? NONE : hitAt(4), `${JSON.stringify(code)}: a reference`);
  }
});

test("the plain subset's lexer state: where it cannot be established (a quote a line break ends inside an interpolation, a close that opens nothing there, more than LEAD_FRAMES groups open in one, a \"/\" whose two readings may leave its line differently) every later line that names a password counts with a reference; LEAD_FRAMES groups less decide", () => {
  // Kept so the plain subset keeps counting these.
  const after = `;\nconst ${NAME} = ${REF};`;
  for (const [label, text] of [
    ["a quote a line break ends", "const t = " + B + "${'a\n}" + B + after],
    ["a close that opens nothing", "const t = " + B + "${a)}" + B + after],
    ["70 groups", "const t = " + B + "${f(" + "(\n".repeat(70) + ")".repeat(70) + ")}" + B + after],
    ["a \"/\" after a line break", `x = y\n/'/.test(s) && f(${B}\n${B})` + after],
  ]) {
    assert.deepEqual(wholeHit("api/client.js", text), hitAt(lineOf(text, NAME)), label);
  }
  const fewer = "const t = " + B + "${f(" + "(\n".repeat(60) + ")".repeat(60) + ")}" + B + after;
  assert.deepEqual(wholeHit("api/client.js", fewer), NONE, "60 groups");
});

test("the readers' limits: a pure reference read past READ_DEPTH, a statement past WINDOW_LINES lines or WINDOW_CHARS characters, and one that starts past STATEMENT_CHARS above the change all count; below each limit the same reference is no hit", () => {
  // Nesting and length past the readers' limits need no line of their own.
  const call = (d) => "f(".repeat(d) + "x" + ")".repeat(d);
  assert.deepEqual(wholeHit("api/client.js", `const ${NAME} = ${call(9)};`), hitAt(1), "9 deep");
  assert.deepEqual(wholeHit("api/client.js", `const ${NAME} = ${call(8)};`), NONE, "8 deep");
  const lines = (n) => `const ${NAME} = [\n` + "  x,\n".repeat(n) + "];";
  assert.deepEqual(wholeHit("api/client.js", lines(45)), hitAt(1), "45 lines");
  assert.deepEqual(wholeHit("api/client.js", lines(39)), NONE, "39 lines");
  const wide = (n) => `const ${NAME} = [\n` + ("  " + "x".repeat(1000) + ",\n").repeat(n) + "];";
  assert.deepEqual(wholeHit("api/client.js", wide(25)), hitAt(1), "25,000 characters");
  assert.deepEqual(wholeHit("api/client.js", wide(10)), NONE, "10,000 characters");
  const above = (n) => `const ${NAME} = [\n` + ("  " + "x".repeat(1000) + ",\n").repeat(n) + "  y];";
  assert.deepEqual(onlyHit("api/client.js", above(210), 212), hitAt(212), "210,000 characters above");
  assert.deepEqual(onlyHit("api/client.js", above(150), 152), NONE, "150,000 characters above");
});

test("the plain part of a block stays exempt: a pure reference above the first unusual line whose statement ends there is no hit; one whose statement runs on into the unusual lines counts; an added line below an unchanged line that names a password in the unusual part counts, a reference too, and an inert line does not", () => {
  // Reading U+2028 as JavaScript's line terminator would leave the two references that count no hit.
  assert.deepEqual(wholeHit("api/client.js", `const ${NAME} = ${REF};\nconst s = 'a${LS}b';`), NONE, "a statement that ends above");
  const runsOn = (v) => `const ${NAME} = ${REF} // c${LS}|| ${v};\nrun();`;
  assert.deepEqual(wholeHit("api/client.js", runsOn(literal())), hitAt(1), "runs on into it: a literal");
  assert.deepEqual(wholeHit("api/client.js", runsOn("process.env.Y")), hitAt(1), "runs on into it: a reference");
  const below = (v) => `const ${NAME} = ${REF} // c${LS}||\n  ${v};\n}`;
  assert.deepEqual(onlyHit("api/client.js", below(literal()), 2), hitAt(2), "below an unchanged line: a literal");
  assert.deepEqual(onlyHit("api/client.js", below("process.env.Y"), 2), hitAt(2), "below an unchanged line: a reference");
  assert.deepEqual(onlyHit("api/client.js", below("process.env.Y"), 3), NONE, "an inert line");
  const crosses = (v) => `const ${NAME} = ${REF} ||\n  process.env.Z // c${LS}||\n  ${v};`;
  assert.deepEqual(onlyHit("api/client.js", crosses("process.env.Y"), 3), hitAt(3), "below a statement that runs on into the unusual line");
  assert.deepEqual(onlyHit("api/client.js", `const ${NAME} = ${REF};\nconst s = 'a${LS}b';\nrun(${REF});`, 3), NONE, "below a statement that ended above it");
});

/** The destructive probe and its kin: a destructive marker in a template's interpolation that runs, after terminator `t` and a comment's mark. */
const DROP = 'db.query("DROP DATABASE scratch")';
const destructive = ["destructive:DROP DATABASE"];

test("code markers, through markerHits: a template's text that a CR, U+2028 or U+2029 breaks, then /* or //, holds an interpolation that runs; the line is outside the plain subset, the lexer establishes no comment on it, and the destructive marker counts; plain comment lines are passed over", () => {
  // Read as a comment, the line after the terminator would be passed over: no hit.
  for (const [tl, t] of [["CR", "\r"], ["U+2028", LS], ["U+2029", PS]]) {
    for (const mark of ["/*", "//"]) {
      const close = mark === "/*" ? " */" : "";
      const text = "const msg = " + B + "notice" + t + mark + " ${" + DROP + "}" + close + B + ";";
      assert.deepEqual(wholeRows("finans/report.js", text), destructive, `${tl}, ${mark}: ${JSON.stringify(text)}`);
      assert.deepEqual(wholeRows("finans/report.mjs", text), destructive, `${tl}, ${mark}, .mjs`);
    }
  }
  // Comments the lexer establishes: a plain line that opens with a comment's mark, in a block from the file's first
  // line. A line inside a block comment that opens with none is read whole, as it always was.
  for (const text of [`// ${DROP}`, `/* ${DROP} */`, `const msg = ${B}notice${B};\n// ${DROP}`, `/*\n * ${DROP}\n */`]) {
    assert.deepEqual(wholeRows("finans/report.js", text), [], JSON.stringify(text));
  }
  assert.deepEqual(wholeRows("finans/report.js", `/*\n${DROP}\n*/`), destructive, "a block comment's line without a mark");
});

test("code markers over LF: a line that opens with /* or // inside a template's text is the template's, so the interpolation on it counts, in a JavaScript file and in a PHP page's script; a # line inside a PHP string over lines and a YAML block scalar's # line are their strings'; a comment line handed without the lines above it keeps its text", () => {
  // Passing over every line that opens with a comment's mark would leave no hit.
  for (const mark of ["/*", "//"]) {
    const close = mark === "/*" ? " */" : "";
    const js = "const msg = " + B + "notice\n" + mark + " ${" + DROP + "}" + close + B + ";";
    assert.deepEqual(wholeRows("finans/report.js", js), destructive, `${mark}: ${JSON.stringify(js)}`);
    const page = "<?php echo 1; ?>\n<script>\nconst msg = " + B + "notice\n" + mark + " ${" + DROP + "}" + close + B + ";\n</script>";
    assert.deepEqual(wholeRows("finans/report.php", page), destructive, `${mark}, a PHP page's script`);
  }
  assert.deepEqual(wholeRows("finans/reset.php", `<?php\n$sql = "select 1;\n# DROP DATABASE scratch";\ndb_modify($sql);`), destructive, "a PHP string over lines");
  assert.deepEqual(wholeRows(".github/workflows/x.yml", "on: push\njobs:\n  a:\n    steps:\n      - run: |\n          # DROP DATABASE scratch\n          psql"), destructive, "a YAML block scalar");
  assert.deepEqual(wholeRows(".github/workflows/x.yml", "on: push\n# DROP DATABASE scratch\njobs: {}"), [], "a YAML comment");
  assert.deepEqual(wholeRows(".github/workflows/x.yml", `on: push\nenv:\n  A: "x\n# DROP DATABASE scratch"\n`), destructive, "a YAML quoted scalar over lines");
  // Below the file's first line, with nothing above, the lexer's state is not established.
  assert.deepEqual(codeRows("finans/report.js", [{ line: 5, text: `// ${DROP}` }]), destructive, "line 5 alone");
  assert.deepEqual(codeRows("finans/report.js", [{ line: 1, text: `// ${DROP}` }]), [], "line 1 alone");
  // PHP code a page's script is not read by PHP's lexer: a script's comment line keeps its text.
  assert.deepEqual(wholeRows("finans/report.php", `<?php echo 1; ?>\n<script>\n// ${DROP}\n</script>`), destructive, "a page script's comment");
  assert.deepEqual(wholeRows("finans/report.php", `<?php\n// ${DROP}\n?>`), [], "PHP's own comment");
});

/** Page scripts: [label, text with V]; a backslash before LF or CR LF continues JavaScript's quote over it. */
function scripts(t) {
  return [
    ["const", `<script>const ${NAME} = '\\${t}' + V;</script>`],
    ["var $name", `<script>var $${NAME} = '\\${t}' + V;</script>`],
    ["const below PHP's code", `<?php echo 1; ?>\n<script type="text/javascript">\n  const ${NAME} = "\\${t}" + V;\n</script>`],
    ["a plain assignment", `<script>\n${NAME} = '\\${t}' + V;\n</script>`],
    ["a setter", `<script>\nset${NAME[0].toUpperCase()}${NAME.slice(1)}('\\${t}' + V);\n</script>`],
  ];
}

test("secrets, through markerHits and stationReport: inside an explicit <script> element of a PHP page JavaScript's string rules hold, so const, var $name, a plain assignment and a setter given '\\<LF>' or '\\<CR LF>' and a reference are a pure reference and no hit, whole and where only the value's line changes; with a literal they count", () => {
  // Read with PHP's quotes, each reference would be a hit, humanOnly true.
  for (const [tl, t] of PLAIN) {
    for (const [label, text] of scripts(t)) {
      const reference = text.replace("V", "x");
      const hit = text.replace("V", literal());
      const what = `${tl}, ${label}`;
      assert.deepEqual(wholeHit("includes/page.php", hit), hitAt(lineOf(hit, NAME.slice(1))), `${what}: a literal`);
      assert.deepEqual(wholeHit("includes/page.php", reference), NONE, `${what}: a reference`);
      assert.deepEqual(onlyHit("includes/page.php", reference, lineOf(reference, "+ x")), NONE, `${what}: a reference, only the value's line changes`);
    }
  }
});

test("secrets, the controls: PHP's own code keeps PHP's quotes (a const, a var $name, a script tag inside a PHP string), so '\\<LF>' beside a reference counts there; a PHP page's script reads \"$pw\" as text, which counts", () => {
  for (const text of [
    `<?php const ${NAME} = '\\\n' . getenv('X');`,
    `<?php class C {\n  var $${NAME} = '\\\n' . X;\n}`,
    `<?php echo '<script>';\n$${NAME} = '\\\n' . getenv('X');`,
  ]) {
    assert.equal(wholeHit("includes/page.php", text).humanOnly, true, JSON.stringify(text));
  }
  assert.deepEqual(wholeHit("includes/page.php", `<script>var ${NAME} = "$pw";</script>`), hitAt(1));
});

/**
 * A git repository in a scratch directory with `files` ([path, base content or null, head content]) committed as base
 * and written as head: what `check.mjs --station --json --base HEAD` routes as the password marker's hits (path:line),
 * every other marker row it hits (path:row), and humanOnly.
 */
function stationOnChange(files) {
  const dir = mkdtempSync(join(tmpdir(), "guardrails-plain-subset-"));
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

test("production wiring, check.mjs --station: the destructive probe in a new file and where only its line changes is the destructive row; a reference after a U+2028 or after a regular expression in an interpolation is human-only; a page script's continued quotes and plain code with references are not", () => {
  const probe = "const msg = " + B + "notice\r/* ${" + DROP + "} */" + B + ";\n";
  const counted = stationOnChange([
    ["finans/probe.js", null, probe],
    ["finans/edited.js", "const msg = 1;\n", "const msg = 1;\n" + probe],
    ["api/ls.js", null, `const ${NAME} = ${REF}; // a${LS}run();\n`],
    ["api/regex.ts", null, "const t = " + B + "${s.replace(/a/g, '')}" + B + `;\nconst ${NAME} = ${REF};\n`],
  ]);
  assert.deepEqual(counted, { hits: ["api/ls.js:1", "api/regex.ts:2"], rows: ["finans/edited.js:destructive", "finans/probe.js:destructive"], humanOnly: true });
  const plain = stationOnChange([
    ["includes/page.php", null, `<?php echo 1; ?>\n<script>\n  const ${NAME} = '\\\n' + x;\n  var $${NAME} = '\\\r\n' + x;\n</script>\n`],
    ["api/plain.js", null, "const t = " + B + "${a / b}" + B + `;\nconst ${NAME} = ${REF};\n// db.query("DROP DATABASE x")\n`],
  ]);
  assert.deepEqual(plain, { hits: [], rows: [], humanOnly: false });
});

/**
 * The census at the base 5419eee2 (master, where the routing change starts): its tracked JavaScript, TypeScript, PHP and
 * Python files hold no line terminator other than LF and CR LF, and these alone are outside the plain subset by the
 * lexer's reading: the nine JavaScript files with a regular expression or a template in a template's interpolation. A
 * password named in one below where it leaves the plain subset counts. A PHP file is read by PHP's own tokens
 * (doors.mjs phpBlock), so no PHP file is outside it by that reading: a page script's template over lines is no PHP
 * backtick string a line break breaks, and HTML's rules find each script element's end (phpHtml), so
 * sager/view_bilag_sager.php's script does not run on over a <style> element. A page's script is JavaScript, read in
 * JavaScript's plain subset per element (scriptLines), which this census does not probe: the probe is PHP's code, a
 * "?>" line and then a "<?php" line that gives the name getenv('X'), so that it is code at the end of a page in HTML
 * and of a file in PHP's code alike.
 */
const OUTSIDE_AT_BASE = [
  "javascript/popupManager.js",
  "javascript/pulje_review.js",
  "mysale/tailwind.js",
  "remoteBooking/tailwind.js",
  "tools/advisory-review/advisory-review.mjs",
  "tools/guardrails/check.mjs",
  "tools/guardrails/checks/tekster.mjs",
  "tools/guardrails/checks/test-discovery.mjs",
  "tools/review-notify/review-notify.mjs",
];

/**
 * The base 5419eee2's PHP pages where a piece of PHP stands anywhere but in HTML's data state
 * (doors.mjs htmlCodes): what it prints may end the tag, comment or element it stands in and open any other, so from
 * that piece on the page is outside the plain subset, PHP's code too. The first such piece stands in a tag's
 * attributes in 44 of them, in a script's start tag in 11 (<script src="x.js?<?php echo time(); ?>">, a nonce), in a
 * script's code in 45, in a <title>'s text in 10, in a <style>'s text in 8, and in a comment in rental/header.php.
 */
const PIECES_AT_BASE = [
  "admin/admin_panel.php",
  "admin/license_manager.php",
  "admin/slet_regnskab.php",
  "api/test_mismatch.php",
  "bank_integration/aiia_import.php",
  "bank_integration/get_accounts.php",
  "bank_integration/includes/auth_check_icon.php",
  "bank_integration/includes/page_header.php",
  "bank_integration/login.php",
  "bank_integration/test_expire.php",
  "debitor/api.php",
  "debitor/bogfor.php",
  "debitor/cashCountHistory.php",
  "debitor/crmkalender.php",
  "debitor/crmopret.php",
  "debitor/crmvisning.php",
  "debitor/debitorkort.php",
  "debitor/historikvisning.php",
  "debitor/kds/index.php",
  "debitor/koekkenprint.php",
  "debitor/ordre.php",
  "debitor/ordreliste.php",
  "debitor/payments/lane3000-sim.php",
  "debitor/payments/lane3000.php",
  "debitor/payments/lane3000_afstemning.php",
  "debitor/payments/vibrant.php",
  "debitor/peppol.php",
  "debitor/pos_ordre.php",
  "debitor/saetpris.php",
  "debitor/saftCashRegister.php",
  "debitor/salg_postnr.php",
  "finans/autoudlign.php",
  "finans/kassekladde.php",
  "finans/kassekladde_includes/bilagsmatch.php",
  "finans/kassekladde_includes/currencyLookup.php",
  "finans/kassekladde_includes/departmentLookup.php",
  "finans/kassekladde_includes/drag-handle-ajax-call.php",
  "finans/kassekladde_includes/employeeLookup.php",
  "finans/kassekladde_includes/financeLookup.php",
  "finans/kassekladde_includes/projectLookup.php",
  "finans/kassekladde_includes/topLineKassekladde.php",
  "finans/kladdeliste.php",
  "finans/ordre.php",
  "finans/pulje_review.php",
  "finans/regnskab.php",
  "finans/regnskabbasis.php",
  "finans/saft.php",
  "finans/simuler.php",
  "finans/topLineFinans.php",
  "includes/docsIncludes/docPool.php",
  "includes/online.php",
  "includes/ordrefunc.php",
  "includes/top_header.php",
  "includes/top_header_sager_small.php",
  "includes/topmenu/header.php",
  "includes/tutorial.php",
  "index/askNemhandel.php",
  "index/backup.php",
  "index/dashboardIncludes/varegrp_doughnut.php",
  "index/index.php",
  "index/login.php",
  "index/main.php",
  "index/tofaktor.php",
  "kreditor/mail_kontoudtog.php",
  "kreditor/modtag.php",
  "kreditor/orderIncludes/dropshipping.php",
  "kreditor/ordre.php",
  "kreditor/ordreliste.php",
  "kreditor/productLookup.php",
  "lager/lister/indkøb.php",
  "lager/modtagelse.php",
  "lager/productCardIncludes/fieldVisibility.php",
  "lager/productCardIncludes/notesEtc.php",
  "lager/productCardIncludes/showLocations.php",
  "lager/productCardIncludes/stockLog.php",
  "lager/salg_rapport.php",
  "lager/shopStockIncludes/page.php",
  "lager/varekort_includes/notesEtc.php",
  "mysale/mybooking.php",
  "mysale/remoteBooking.php",
  "mysale/shopLookup.php",
  "mysale/sidemenu.php",
  "projectManager/index.php",
  "projectManager/project_view.php",
  "projectManager/projects.php",
  "remoteBooking/index.php",
  "rental/booking.php",
  "rental/daysoff.php",
  "rental/edit.php",
  "rental/header.php",
  "rental/index.php",
  "rental/items.php",
  "rental/lookupcust.php",
  "rental/remote.php",
  "rental/settings.php",
  "rotary/kortbetaling2.php",
  "rotary/pbs_tilmelding2.php",
  "sager/planlaeg_opgaver.php",
  "sager/planlaeg_sag.php",
  "sager/planlaeg_sager.php",
  "sager/template_form.php",
  "sager/template_list.php",
  "sager/tilbud.php",
  "sager/view_bilag_sager.php",
  "systemdata/barcodescan.php",
  "systemdata/bruger2.php",
  "systemdata/bruger3.php",
  "systemdata/brugerNew.php",
  "systemdata/brugerNewest.php",
  "systemdata/brugerNewest1.php",
  "systemdata/brugere.php",
  "systemdata/diverseIncludes/bank_integration.php",
  "systemdata/exporter_debitor.php",
  "systemdata/formeditor.php",
  "systemdata/importer_saet_hms.php",
  "systemdata/posmenuer.php",
  "systemdata/settings/departments.php",
  "systemdata/valuta.php",
  "topmenu/header.php",
];

/**
 * The files of OUTSIDE_AT_BASE and PIECES_AT_BASE that name a password, and the lines of each that count as a new file.
 * None of OUTSIDE_AT_BASE's. Of the pages of PIECES_AT_BASE that name a password, 12 have a line that names one below
 * the first piece, where every such line counts, references too (the cost of that rule: station.test.mjs
 * PIECES_UNKNOWN_AT_BASE). debitorkort.php's 743, above its first piece (3066, in a style's text), is a literal. The
 * other 7 name one only above theirs. A line where a name's text touches a piece of PHP counts too, and so does each
 * line the piece stands on (admin_panel.php 1342, 1346, 1439, 1725; pos_ordre.php 3718, 3719; settings.php 161).
 */
const NAMING_AT_BASE = {
  "admin/admin_panel.php": [1342, 1346, 1439, 1725, 1939, 2001, 2021],
  "admin/slet_regnskab.php": [103, 104, 105],
  "debitor/debitorkort.php": [743],
  "debitor/payments/lane3000_afstemning.php": [],
  "debitor/payments/lane3000-sim.php": [],
  "debitor/payments/lane3000.php": [],
  "debitor/pos_ordre.php": [3718, 3719],
  "includes/ordrefunc.php": [5987, 6024, 6057],
  "index/index.php": [175, 177, 256, 258, 259, 294, 295, 298, 299, 302],
  "index/login.php": [636, 698, 950, 976, 977, 984, 1001, 1085, 1087, 1145],
  "index/tofaktor.php": [43],
  "mysale/remoteBooking.php": [],
  "rental/settings.php": [161],
  "rotary/pbs_tilmelding2.php": [369, 370, 383],
  "systemdata/bruger2.php": [870, 878],
  "systemdata/bruger3.php": [],
  "systemdata/brugere.php": [350, 351, 421, 422],
  "systemdata/brugerNew.php": [867, 872, 1035, 1040],
  "systemdata/brugerNewest.php": [723, 728, 862, 867],
  "systemdata/brugerNewest1.php": [714, 719, 853, 858],
};

test("the census at the base 5419eee2: no tracked source file holds a bare CR, U+0085, U+2028 or U+2029; a pure reference added at the end of each tracked JavaScript, TypeScript, PHP and Python file counts exactly in the 9 JavaScript files outside the plain subset, none of which names a password, and in the 119 PHP pages where a piece of PHP leaves HTML's state unknown, 12 of which name a password below it", () => {
  const sha = "5419eee2";
  const listed = spawnSync("git", ["-c", "core.quotePath=false", "ls-tree", "-r", "--name-only", sha], { cwd: ROOT, encoding: "utf8", maxBuffer: 1 << 26 });
  assert.equal(listed.status, 0, listed.stderr);
  const probes = { js: [`const ${NAME} = ${REF};`], php: ["?>", `<?php $${NAME} = getenv('X');`], py: [`${NAME} = os.environ['X']`] };
  const kind = (p) => (/\.(js|mjs|cjs|jsx|ts|tsx|mts|cts)$/.test(p) ? "js" : /\.(php|inc)$/.test(p) ? "php" : /\.py$/.test(p) ? "py" : null);
  const paths = listed.stdout.split("\n").filter((p) => kind(p) && !VENDORED.test(p));
  assert.ok(paths.length > 1000, `${paths.length} files`);
  const batch = spawnSync("git", ["cat-file", "--batch"], { cwd: ROOT, input: paths.map((p) => `${sha}:${p}\n`).join(""), maxBuffer: 1 << 28 });
  assert.equal(batch.status, 0);
  const out = batch.stdout;
  const outside = [];
  const naming = {};
  let at = 0;
  for (const path of paths) {
    const nl = out.indexOf(10, at);
    const [, type, size] = out.toString("latin1", at, nl).split(" ");
    const body = out.subarray(nl + 1, nl + 1 + Number(size));
    at = nl + 1 + Number(size) + 1;
    if (type !== "blob" || body.includes(0)) continue;
    let text = body.toString("utf8");
    assert.equal(holdsUnusualTerminator(text.replaceAll("\r\n", "\n")), false, path);
    if (!text.endsWith("\n")) text += "\n";
    const probe = probes[kind(path)];
    const n = text.split("\n").length;
    const last = n + probe.length - 1;
    const added = new Map(probe.map((line, i) => [n + i, line]));
    if (!markerHits(path, addedWithContext(added, `${text}${probe.join("\n")}\n`)).some((hit) => hit.row === "secrets" && hit.line === last)) continue;
    outside.push(path);
    if (!/pass(?:word|wd)/i.test(text)) continue;
    const file = text.split("\n").slice(0, -1);
    const all = new Map(file.map((line, i) => [i + 1, line]));
    naming[path] = markerHits(path, addedWithContext(all, text)).filter((hit) => hit.row === "secrets").map((hit) => hit.line);
  }
  assert.deepEqual(outside.sort(), [...OUTSIDE_AT_BASE, ...PIECES_AT_BASE].sort());
  assert.deepEqual(naming, NAMING_AT_BASE);
});

test("the door map's code, check.mjs, checks/pr-body.mjs and the door tests stay in the plain subset: a pure reference added at the end of each counts in none of them", () => {
  // A guard for this change's own files: a regular expression or a template in a template's interpolation would put
  // the rest of a file outside the plain subset.
  const dir = fileURLToPath(new URL("../", import.meta.url));
  // PHP's tokenizer (lib/php.mjs, lib/php-worker.mjs), checks/pr-body.mjs and the tests too.
  const files = ["check.mjs", "lib/doors.mjs", "lib/station.mjs", "lib/changeset.mjs", "lib/php.mjs", "lib/php-worker.mjs", "checks/pr-body.mjs", "tests/station.test.mjs", "tests/doors-js-syntax.test.mjs", "tests/doors-line-terminators.test.mjs", "tests/doors-quotes-by-context.test.mjs", "tests/doors-plain-subset.test.mjs", "tests/doors-comment-lines.test.mjs", "tests/doors-php-interpolation.test.mjs", "tests/doors-php-tokens.test.mjs", "tests/pr-body.test.mjs", "tests/doors-php-html.test.mjs", "tests/linear-time.mjs", "tests/doors-php-pieces.test.mjs", "tests/doors-php-output.test.mjs", "tests/doors-php-inline-html.test.mjs", "tests/doors-read-on.test.mjs", "tests/doors-php-split-names.test.mjs", "tests/doors-php-split-over-lines.test.mjs", "tests/doors-php-split-escapes.test.mjs", "tests/doors-php-edge-names.test.mjs", "tests/tekster.test.mjs"];
  for (const file of files) {
    let text = readFileSync(join(dir, file), "utf8");
    if (!text.endsWith("\n")) text += "\n";
    const probe = `const ${NAME} = ${REF};`;
    const n = text.split("\n").length;
    const hits = markerHits(`tools/guardrails/${file}`, addedWithContext(new Map([[n, probe]]), `${text}${probe}\n`)).filter((hit) => hit.row === "secrets");
    assert.deepEqual(hits, [], file);
  }
});
