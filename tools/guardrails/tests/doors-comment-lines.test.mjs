// tools/guardrails/tests/doors-comment-lines.test.mjs - when a line that opens with a comment's mark is an established
// comment, and the page-script and file-end edges beside it (lib/doors.mjs, lib/changeset.mjs).
//
// A code marker passes over a line only where it is an established comment. After a quote a line break left open, no
// later line of the block is one, however many lines stand between (a PHP string over lines whose text is a page
// script's template). A PHP file is read by PHP's own tokens (doors.mjs phpBlock, lib/php.mjs): an interpolation over
// lines, a quote in an interpolation that interpolates and a "<<<" that opens no heredoc (PHP reads "<<" and "<") are
// valid PHP that PHP's tokens read whole, so the comment line after each is PHP's comment and is passed over, while a
// "<?" opens code where short_open_tag is on, so from there the reading is not known. A page's script element is read
// by HTML's rules and JavaScript's: its template over lines is JavaScript's, as in a .js file, and a function's body (a
// declaration, a function expression, a method, a parameter's default) reads with JavaScript's quotes, not PHP's. A
// bare CR at a file's end, with no LF after it, is no CR LF's.
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
import { addedWithContext, parseDiff } from "../lib/changeset.mjs";

const CHECK = fileURLToPath(new URL("../check.mjs", import.meta.url));
// Built at run time, so this file holds no literal for the marker to find.
const NAME = ["pass", "word"].join("");
const B = "`";
const REF = "process.env.X";
const literal = () => `'v${randomBytes(6).toString("hex")}'`;
const NONE = Object.freeze({ hits: [], humanOnly: false });
const hitAt = (n) => ({ hits: [n], humanOnly: true });
const DROP = 'db.query("DROP DATABASE scratch")';
const destructive = ["destructive:DROP DATABASE"];

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
/** The rows where only git's line `n` of `text` changes, the lines above it unchanged. */
function onlyRows(path, text, n) {
  const file = text.split("\n");
  return codeRows(path, addedWithContext(new Map([[n, file[n - 1]]]), `${text}\n`));
}

/**
 * PHP strings over lines: [label, text]. Each opens a string a line break leaves open on its first line, and
 * a later line, after `between` other lines of the string, opens with a comment's mark; that line is the string's text
 * (here a page script's template, whose interpolation runs in the browser), never a PHP comment.
 */
function openStrings(between) {
  const gap = "hello\n".repeat(between);
  return [
    ["echo, chr(96), /*", `<?php\necho "const t = " . chr(96) . "\n${gap}/* \${${DROP}} */\n" . chr(96) . ";";\n`],
    ["echo, chr(96), //", `<?php\necho "const t = " . chr(96) . "\n${gap}// \${${DROP}}\n" . chr(96) . ";";\n`],
    ["single quotes, #", `<?php\n$js = 'const t = ' . chr(96) . '\n${gap}# \${${DROP}}\n' . chr(96) . ';';\necho $js;\n`],
    ["an assignment's SQL", `<?php\n$sql = "select 1;\n${gap}-- a\n/* ${DROP} */";\ndb_modify($sql);\n`],
  ];
}

test("after a quote a line break leaves open, no later line of the block is an established comment (doors.mjs commentAt), however many lines stand between them, so the destructive marker on a PHP string's /*, // or # line counts, whole and where only that line changes", () => {
  // Looking at the token right above the line only would miss every case with a line between.
  for (const between of [0, 1, 2]) {
    for (const [label, text] of openStrings(between)) {
      const n = text.split("\n").findIndex((line) => /^(?:\/\*|\/\/|#)/.test(line)) + 1;
      const what = `${label}, ${between} lines between: ${JSON.stringify(text)}`;
      assert.deepEqual(wholeRows("finans/report.php", text), destructive, `${what}: whole`);
      assert.deepEqual(onlyRows("finans/report.php", text, n), destructive, `${what}: only line ${n}`);
    }
  }
});

test("a PHP comment above a quote left open, or in a file whose quotes all close on their line, is passed over; a JavaScript comment after a template over lines that closes is passed over", () => {
  // The established comments stay established.
  for (const text of [
    `<?php\n// ${DROP}\n$sql = "select 1\n  from t";\n`,
    `<?php\n$sql = "select 1";\n// ${DROP}\n# ${DROP}\n/* ${DROP} */\n`,
    `<?php\n$a = ['x' => "y"];\n\n  // ${DROP}\n`,
  ]) {
    assert.deepEqual(wholeRows("finans/report.php", text), [], JSON.stringify(text));
  }
  assert.deepEqual(wholeRows("finans/report.js", `const t = ${B}a\nb${B};\n// ${DROP}\n`), [], "a closed template over lines");
});

test("PHP's comment scan (doors.mjs phpComments): a comment line is PHP's comment only where PHP's own tokens read it so; a heredoc's, a nowdoc's, a backtick string's and an escaped quote's text over lines, HTML (before \"<?php\", after \"?>\", a page's script) and anything after a \"<?\" that short_open_tag decides keep their text; comments after a string or an interpolation that closes, after a block comment, after an attribute and after \"<?=\" are passed over", () => {
  // Passing over every line that opens with a comment's mark would miss the kept cases. Reading a PHP file's comments
  // with the code lexer would pass over the HTML cases (a file without <?php, after ?>, after a line comment's ?>) and
  // another <?, and count the comment right after a string over lines that closes.
  const kept = [
    ["a heredoc's text", `<?php\n$sql = <<<SQL\nselect 1;\n-- x\n// ${DROP}\nSQL;\n`],
    ["an indented heredoc's text", `<?php\n$js = <<<"JS"\n    // ${DROP}\n    JS;\n`],
    ["a nowdoc's text", `<?php\n$js = <<<'JS'\n// ${DROP}\nJS;\n`],
    ["a backtick string over lines", `<?php\n$out = \`psql -c "select 1"\n# ${DROP}\n\`;\n`],
    ["an escaped double quote", `<?php\n$s = "a \\" b\n// ${DROP}\n";\n`],
    ["an escaped single quote", `<?php\n$s = 'it\\'s\n# ${DROP}\n';\n`],
    ["a file without <?php", `// ${DROP}\n`],
    ["HTML after ?>", `<?php echo 1; ?>\n<p>x</p>\n// ${DROP}\n`],
    ["HTML a line comment's ?> leaves", `<?php // a ?>\n// ${DROP}\n`],
    ["a page's script", `<?php\n?>\n<script>\nconst t = ${B}a\n// \${${DROP}}${B};\n</script>\n`],
    // A "<?" in HTML opens PHP's code where short_open_tag is on, so from there the reading is not known.
    ["another <?", `<?xml version="1.0"?>\n<?php\n// ${DROP}\n`],
  ];
  for (const [label, text] of kept) {
    const n = lineOf(text, DROP);
    assert.deepEqual(wholeRows("finans/report.php", text), destructive, `${label}: ${JSON.stringify(text)}`);
    assert.deepEqual(onlyRows("finans/report.php", text, n), destructive, `${label}, only line ${n}`);
  }
  const passed = [
    ["after a heredoc closes", `<?php\n$sql = <<<SQL\nselect 1;\nSQL;\n// ${DROP}\n`],
    ["after an indented heredoc closes", `<?php\n$sql = <<<SQL\n  select 1;\n  SQL;\n// ${DROP}\n`],
    ["after a string over lines closes", `<?php\n$sql = "select a\n  from t";\n// ${DROP}\n`],
    ["after an interpolation that closes", `<?php\n$s = "{$a["k"]} and \${b}";\n// ${DROP}\n`],
    ["inside a block comment", `<?php\n/*\n * ${DROP}\n */\n`],
    ["after a block comment over lines", `<?php\n/* a\n b */\n# ${DROP}\n`],
    ["after an attribute", `<?php\n#[Attr]\n# ${DROP}\nfunction f() {}\n`],
    ["after <?=", `<p><?= $x ?></p>\n<?php\n// ${DROP}\n`],
    ["after HTML and <?php", `<p>don't</p>\n<?php\n  // ${DROP}\n`],
    // Valid PHP that a scan of braces and quotes cannot read and PHP's own tokens read whole.
    ["after an interpolation over lines that closes", `<?php\n$s = "{$a[\n'k']}";\n// ${DROP}\n`],
    ["after a quote in an interpolation that interpolates", `<?php\n$s = "{$a["{$k}"]}";\n// ${DROP}\n`],
    ["after a <<< that opens no heredoc (PHP's << and <)", `<?php\n$x = 1 <<<\n// ${DROP}\n`],
  ];
  for (const [label, text] of passed) {
    const n = lineOf(text, DROP);
    assert.deepEqual(wholeRows("finans/report.php", text), [], `${label}: ${JSON.stringify(text)}`);
    assert.deepEqual(onlyRows("finans/report.php", text, n), [], `${label}, only line ${n}`);
  }
});

/** Interpolations after a line break, each outside the plain subset in JavaScript: [label, code]. */
const UNPLAIN = [
  ["a block comment", "/* c */ x"],
  ["a line comment", "x // c\n"],
  ["a regular expression", "/x/.test(s)"],
  ["a nested template", `${B}in${B}`],
];

/** A PHP page whose script holds a template over lines with `code` in an interpolation after a line break, then the name given `v`. */
const pageTemplate = (code, v) => `<?php echo 1; ?>\n<script>\nconst t = ${B}hello\n\${${code}}${B};\nconst ${NAME} = ${v};\n</script>`;

test("a PHP page's script template over lines whose later interpolation holds a comment, a regular expression or a template leaves JavaScript's plain subset, so a password named after it counts with a reference, whole and where only its line changes; a literal counts too", () => {
  // The script is read by HTML's rules and JavaScript's (doors.mjs phpHtml, scriptLines), as the .js file's controls
  // below. Read as a PHP backtick string to its first line's end, the template holds no "${" and the reference after it
  // reads as plain: no hit, humanOnly false.
  for (const [label, code] of UNPLAIN) {
    const ref = pageTemplate(code, "x");
    const n = lineOf(ref, `const ${NAME}`);
    assert.deepEqual(wholeHit("includes/page.php", ref), hitAt(n), `${label}: a reference`);
    assert.deepEqual(onlyHit("includes/page.php", ref, n), hitAt(n), `${label}: a reference, only its line changes`);
    assert.deepEqual(wholeHit("includes/page.php", pageTemplate(code, literal())), hitAt(n), `${label}: a literal`);
  }
});

test("in a .js file a plain template over lines leaves a reference after it no hit and an unplain one counts; in a PHP page a plain script template, on its line or over lines, leaves a reference no hit", () => {
  for (const [label, code] of UNPLAIN) {
    const js = `const t = ${B}hello\n\${${code}}${B};\nconst ${NAME} = x;`;
    assert.deepEqual(wholeHit("api/client.js", js), hitAt(lineOf(js, `const ${NAME}`)), `.js, ${label}`);
  }
  assert.deepEqual(wholeHit("api/client.js", `const t = ${B}hello\n\${x}${B};\nconst ${NAME} = x;`), NONE, ".js, a plain template over lines");
  assert.deepEqual(wholeHit("includes/page.php", `<?php echo 1; ?>\n<script>\nconst t = ${B}hello \${x}${B};\nconst ${NAME} = x;\n</script>`), NONE, "a page's one-line plain template");
  // A page's plain template over lines is JavaScript's, as in the .js file: a reference after it is no hit (the
  // template is no PHP backtick string that a line break breaks).
  const page = pageTemplate("x", "x");
  const n = lineOf(page, `const ${NAME}`);
  assert.deepEqual(wholeHit("includes/page.php", page), NONE, "a page's plain template over lines");
  assert.deepEqual(onlyHit("includes/page.php", page, n), NONE, "a page's plain template over lines, only its line changes");
  assert.deepEqual(wholeHit("includes/page.php", pageTemplate("x", literal())), hitAt(n), "a page's plain template over lines: a literal");
});

/** A file whose last line names a password given a reference and ends in `end`, with no LF after it: [path, text]. */
const lastLines = (end) => [
  ["api/client.js", `run();\nconst ${NAME} = ${REF};${end}`],
  ["api/client.ts", `const ${NAME}: string = ${REF};${end}`],
  ["includes/db.php", `<?php\n$${NAME} = getenv('X');${end}`],
  ["jobs/sync.py", `import os\n${NAME} = os.environ['X']${end}`],
];

test("a bare CR at the file's end, with no LF after it, is no CR LF's (changeset.mjs addedWithContext): the line keeps it and is outside the plain subset, so a reference there counts; the same line ended by CR LF or LF, or by nothing, is plain and no hit", () => {
  // Stripping the CR as a CR LF's (lineText) would leave no hit.
  for (const [path, text] of lastLines("\r")) {
    const n = text.split("\n").length;
    const entries = addedWithContext(new Map([[n, text.split("\n")[n - 1]]]), text);
    assert.equal(entries[entries.length - 1].text.endsWith("\r"), true, `${path}: the CR stays`);
    assert.deepEqual(routed(path, entries), hitAt(n), `${path}: ${JSON.stringify(text)}`);
  }
  for (const end of ["\r\n", "\n", ""]) {
    for (const [path, text] of lastLines(end)) {
      // The line as git's diff hands it: a CR LF's CR still in its text.
      const n = text.split("\n").length - (end.endsWith("\n") ? 1 : 0);
      assert.deepEqual(routed(path, addedWithContext(new Map([[n, text.split("\n")[n - 1]]]), text)), NONE, `${path}: ${JSON.stringify(text)}`);
    }
  }
});

test("a bare CR at the file's end through git's diff: parseDiff marks the added line that git says has no newline at the end of the file (eof), and keeps the CR of each added line's CR LF in its text as git prints it", () => {
  // Without the mark the CR before git's "\ No newline" is indistinguishable from a CR LF's.
  const diff = [
    "diff --git a/api/client.js b/api/client.js",
    "index 1111111..2222222 100644",
    "--- a/api/client.js",
    "+++ b/api/client.js",
    "@@ -1 +1,2 @@",
    "-run();",
    "\\ No newline at end of file",
    "+run();\r",
    `+const ${NAME} = ${REF};\r`,
    "\\ No newline at end of file",
    "",
  ].join("\n");
  const file = parseDiff(diff).get("api/client.js");
  assert.deepEqual(file.hunks[0].added, [
    { line: 1, text: "run();\r" },
    { line: 2, text: `const ${NAME} = ${REF};\r`, eof: true },
  ]);
  assert.deepEqual(file.hunks[0].removed, ["run();"]);
  assert.deepEqual([...file.added], [
    [1, "run();\r"],
    [2, `const ${NAME} = ${REF};\r`],
  ]);
});

/**
 * Page scripts: [label, text with V where the value goes]. Each is a function whose body or default gives
 * the name what JavaScript reads as a pure reference when V is one: '\<LF>' is the empty string there.
 */
const FUNCTIONS = [
  ["a declaration", `<script>function ${NAME}() { return '\\\n' + V; }</script>`],
  ["a declaration, its tag on a line of its own", `<script>\nfunction ${NAME}() { return '\\\n' + V; }\n</script>`],
  ["a declaration over lines", `<script>\nfunction ${NAME}() {\n  return '\\\n' + V;\n}\n</script>`],
  ["a function expression", `<script>const ${NAME} = function () { return '\\\n' + V; };</script>`],
  ["a function expression over lines", `<?php echo 1; ?>\n<script>\nvar ${NAME} = function () {\n  return "\\\n" + V;\n};\n</script>`],
  ["an assignment's function", `<script>\n${NAME} = function () {\n  return '\\\n' + V;\n};\n</script>`],
  ["an arrow's block", `<script>\nconst ${NAME} = () => {\n  return '\\\n' + V;\n};\n</script>`],
  ["a class's method", `<script>\nclass C {\n  ${NAME}() {\n    return '\\\n' + V;\n  }\n}\n</script>`],
  ["a class's getter", `<script>\nclass C {\n  get ${NAME}() {\n    return '\\\n' + V;\n  }\n}\n</script>`],
  ["an object's method", `<script>\nconst o = { ${NAME}() { return '\\\n' + V; } };\n</script>`],
  ["a parameter's default", `<script>\nfunction ${NAME}(a = '\\\n' + V) {\n  return a;\n}\n</script>`],
];
/** The same script as a .js file, line for line: the PHP and the tags taken out, their lines kept. */
const asJs = (text) => text.replace("<?php echo 1; ?>", "").replace(/<\/?script>/g, "");

test("inside an explicit <script> element JavaScript's string rules reach a function's body and defaults (doors.mjs accessorValue, bodyValue, paramsHoldLiteral), so a declaration, a function expression, an arrow's block, a method, a getter and a default given '\\<LF>' and a reference are no hit as a new file; where one line changes, the page reads as the same script in a .js file does (a body whose close is below the change is open there and counts in both, one that closes on the changed line does not); a literal counts", () => {
  // Read with PHP's quotes, which keep '\<LF>' as two characters, the declarations, the function expressions, the
  // arrow's block and the default would be a hit, humanOnly true.
  for (const [label, text] of FUNCTIONS) {
    const reference = text.replace("V", "x");
    const hit = text.replace("V", literal());
    const named = lineOf(hit, NAME);
    assert.deepEqual(wholeHit("includes/page.php", reference), NONE, `${label}: a reference`);
    assert.deepEqual(wholeHit("api/client.js", asJs(reference)), NONE, `${label}: a reference, in a .js file`);
    for (let n = 1; n <= reference.split("\n").length; n++) {
      assert.deepEqual(onlyHit("includes/page.php", reference, n), onlyHit("api/client.js", asJs(reference), n), `${label}: a reference, only line ${n} changes`);
    }
    const page = wholeHit("includes/page.php", hit);
    assert.equal(page.humanOnly, true, `${label}: a literal`);
    assert.ok(page.hits.includes(named), `${label}: a literal, on the name's line ${named}`);
  }
  // A body that closes on the changed line holds the whole value there: no hit.
  for (const [label, text] of FUNCTIONS.slice(0, 2)) {
    const reference = text.replace("V", "x");
    assert.deepEqual(onlyHit("includes/page.php", reference, lineOf(reference, "+ x")), NONE, `${label}: only the return's line changes`);
  }
});

test("inside an explicit <script> element a method and a getter are JavaScript's (accessorAt), so a class's or an object's method or getter that returns a literal counts, as it does in a .js file", () => {
  // A reader that takes no method without PHP's function keyword in a PHP file leaves the literal no hit, humanOnly
  // false.
  const body = `return ${literal()};`;
  for (const text of [
    `<script>\nclass C {\n  ${NAME}() {\n    ${body}\n  }\n}\n</script>`,
    `<script>\nclass C {\n  get ${NAME}() {\n    ${body}\n  }\n}\n</script>`,
    `<script>\nclass C {\n  static ${NAME}() { ${body} }\n}\n</script>`,
    `<script>\nconst o = {\n  ${NAME}() {\n    ${body}\n  },\n};\n</script>`,
    `<?php echo 1; ?>\n<script>\nconst o = { get ${NAME}() { ${body} } };\n</script>`,
  ]) {
    assert.deepEqual(wholeHit("includes/page.php", text), hitAt(lineOf(text, NAME)), JSON.stringify(text));
    assert.deepEqual(wholeHit("api/client.js", asJs(text)), hitAt(lineOf(text, NAME)), `${JSON.stringify(text)}, in a .js file`);
  }
  // PHP's own code keeps PHP's: a call given a block is no method there.
  assert.deepEqual(wholeHit("includes/db.php", `<?php
if (${NAME}($x)) {
  run();
}
`), NONE);
});

test("PHP's own functions keep PHP's quotes, so '\\<LF>' beside a reference in a PHP function's body or default counts", () => {
  for (const text of [
    `<?php\nfunction ${NAME}() {\n  return '\\\n' . getenv('X');\n}\n`,
    `<?php\nclass C {\n  public function ${NAME}($a = '\\\n' . X) {\n    return $a;\n  }\n}\n`,
    `<?php\n$${NAME} = function () {\n  return "\\\n" . getenv('X');\n};\n`,
  ]) {
    assert.equal(wholeHit("includes/db.php", text).humanOnly, true, JSON.stringify(text));
  }
});

/**
 * A git repository in a scratch directory with `files` ([path, base content or null, head content]) committed as base
 * and written as head, byte for byte: what `check.mjs --station --json --base HEAD` routes as the password marker's
 * hits (path:line), every other marker row it hits (path:row), and humanOnly.
 */
function stationOnChange(files) {
  const dir = mkdtempSync(join(tmpdir(), "guardrails-comment-lines-"));
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

test("production wiring, check.mjs --station: a PHP string over lines, a page's unplain script template over lines and a bare CR at the end of a changed and of a new file are routed as one-way and human-only; page functions given '\\<LF>' and a reference, and the controls, are not", () => {
  const counted = stationOnChange([
    ["finans/echo.php", null, openStrings(1)[0][1]],
    ["includes/page.php", null, `${pageTemplate("/* c */ x", "x")}\n`],
    ["api/edited.js", "run();\n", `run();\nconst ${NAME} = ${REF};\r`],
    ["api/new.js", null, `const ${NAME} = ${REF};\r`],
  ]);
  assert.deepEqual(counted, { hits: ["api/edited.js:2", "api/new.js:1", "includes/page.php:5"], rows: ["finans/echo.php:destructive"], humanOnly: true });
  const plain = stationOnChange([
    ["includes/fns.php", null, `${FUNCTIONS.map(([, text]) => text.replace("V", "x")).join("\n")}\n`],
    ["api/crlf.js", "run();\n", `run();\r\nconst ${NAME} = ${REF};\r\n`],
    ["api/none.js", null, `const ${NAME} = ${REF};`],
    ["finans/sql.php", null, `<?php\n$sql = "select 1";\n// ${DROP}\n`],
  ]);
  assert.deepEqual(plain, { hits: [], rows: [], humanOnly: false });
});
