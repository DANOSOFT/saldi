// tools/guardrails/tests/doors-php-interpolation.test.mjs - a PHP interpolation read as PHP reads it, and a page
// script's template (lib/doors.mjs).
//
// A PHP file is read by PHP's own tokens (doors.mjs phpBlock, lib/php.mjs), so a comment, a quote or a backtick string
// inside a PHP interpolation ("{$a[/* } " */ 'k']}") is PHP's: it does not end the string early, a later line of the
// string that opens like a comment is the string's text, and a password literal after it on its line counts. An
// interpolation that holds a comment is valid PHP that PHP's tokens read whole, so a reference after it is no hit. A
// page's explicit <script> element is read by HTML's rules and JavaScript's alone, so a regular expression that holds a
// quote (/"/) or a comment's mark reads in the page as in the .js file and hides no template after it: a comment, a
// regular expression or a template in that template's interpolation leaves the plain subset, and a password reference
// below it counts. A piece of PHP inside a script's code leaves HTML's state after it unknown (what it prints may end
// the script and open anything), so a reference below <?= ?> or <?php ?> there counts, and a name whose text touches a
// piece of PHP stays unresolved (doors.mjs edgeNames).
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
import { addedWithContext } from "../lib/changeset.mjs";

const CHECK = fileURLToPath(new URL("../check.mjs", import.meta.url));
// Built at run time, so this file holds no literal for the marker to find.
const NAME = ["pass", "word"].join("");
const B = "`";
const literal = () => `'v${randomBytes(6).toString("hex")}'`;
const NONE = Object.freeze({ hits: [], humanOnly: false });
const hitAt = (...n) => ({ hits: n, humanOnly: true });
const DROP = 'db.query("DROP DATABASE scratch")';
const destructive = ["destructive:DROP DATABASE"];

/** The password marker's lines and humanOnly, as check.mjs routes `entries` of `path` (markerHits and stationReport). */
function routed(path, entries) {
  const hits = markerHits(path, entries).filter((hit) => hit.row === "secrets").map((hit) => hit.line);
  const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, entries]]) });
  return { hits, humanOnly: report.humanOnly };
}
/** The whole of `text` added as a new file, its lines as git's diff hands them: split at LF. */
const asNew = (text) => text.split("\n").map((line, i) => ({ line: i + 1, text: line }));
const wholeHit = (path, text) => routed(path, asNew(text));
/** Only git's line `n` of `text` changes; the lines above it are unchanged context (changeset.mjs addedWithContext). */
const onlyLine = (text, n) => addedWithContext(new Map([[n, text.split("\n")[n - 1]]]), `${text}\n`);
const onlyHit = (path, text, n) => routed(path, onlyLine(text, n));
/** Git's line of `text` that holds `value`, 1-based. */
const lineOf = (text, value) => 1 + text.split("\n").findIndex((line) => line.includes(value));
/** The one-way rows (row:marker) markerHits finds on `entries` of `path`, but the password marker. */
const codeRows = (path, entries) => [...new Set(markerHits(path, entries).filter((hit) => hit.row !== "secrets").map((hit) => `${hit.row}:${hit.marker}`))].sort();

/**
 * PHP interpolations: [label, interpolation]. Each holds, in PHP's code, what a scan of braces and quotes misreads: a
 * "}" and a '"' in a block comment, or in a backtick string (PHP's shell command).
 */
const UNREAD = [
  ["a block comment holding } and \"", `{$a[/* } " */ 'k']}`],
  ["a block comment holding \" and }", `{$a[/* " } */ 'k']}`],
  ["a block comment holding }", `{$a[/* } */ 'k']}`],
  ["a backtick string holding } and \"", `{$a[${B} } " ${B}]}`],
];
/** An echoed PHP string over lines: `interpolation` on its first line, a page script's template below whose interpolation runs DROP. */
const echoed = (interpolation) => `<?php\necho "const t = ${B}x ${interpolation}\n/* \${${DROP}} */\n${B};";\n`;

test("a comment or a backtick string inside a PHP interpolation does not end the string early, so the destructive marker on a later line of the string, whose text opens like a comment, counts, whole and where only that line changes", () => {
  // Closing the interpolation at the "}" in the comment and the string at its '"' would read the template's line as a
  // PHP comment; the other three forms are its neighbours. Passing over every line that opens with a comment's mark
  // would miss all four.
  for (const [label, interpolation] of UNREAD) {
    const text = echoed(interpolation);
    const n = lineOf(text, "DROP");
    assert.deepEqual(codeRows("finans/report.php", asNew(text)), destructive, `${label}: whole: ${JSON.stringify(text)}`);
    assert.deepEqual(codeRows("finans/report.php", onlyLine(text, n)), destructive, `${label}: only line ${n}`);
  }
});

test("after PHP strings with ordinary interpolations (quoted keys, a simple $name in a quoted key, a method call), a PHP comment line is still passed over", () => {
  // The last two hold a "{" in a '...' and a "..." holding a $name inside an interpolation; PHP reads the line after
  // them as a comment, and so does the reader.
  for (const strings of [
    `$s = "{$a['k']} and {$b["j"]}";`,
    `$s = "{$o->f('a')} {$a[0]}";`,
    `$s = "a {$a['{']} b";`,
    `$s = "<b>{$data["x_$i"]}</b>";`,
  ]) {
    const text = `<?php\n${strings}\n// ${DROP}\n`;
    assert.deepEqual(codeRows("finans/report.php", asNew(text)), [], JSON.stringify(text));
    assert.deepEqual(codeRows("finans/report.php", onlyLine(text, 3)), [], `${JSON.stringify(text)}, only the comment's line`);
  }
});

test("a PHP \"...\" string's interpolation is read as PHP reads it (PHP's own tokens), so a quote or a comment in it is its own. A password given a literal after either on its line counts; a reference after either is no hit, on its line, below it and where only its line changes", () => {
  // Reading each string only to the first quote inside its interpolation would hide the literal after
  // {$a[/* " */ 'k']} and after {$a['"']} in a string opened at the last quote (no hit), and count the reference after
  // {$a['"']}. A reader that cannot read the interpolation would count the references after the comment outside its
  // plain subset.
  const commented = `$s = "{$a[/* " */ 'k']}";`;
  const read = `$s = "{$a['"']}";`;
  for (const [label, s] of [["commented", commented], ["read", read]]) {
    assert.deepEqual(wholeHit("includes/db.php", `<?php\n${s} $${NAME} = ${literal()};\n`), hitAt(2), `${label}: a literal on its line`);
    assert.deepEqual(wholeHit("includes/db.php", `<?php\n${s} $${NAME} = getenv('X');\n`), NONE, `${label}: a reference on its line`);
    assert.deepEqual(wholeHit("includes/db.php", `<?php\n${s}\n$${NAME} = getenv('X');\n`), NONE, `${label}: a reference below it`);
    assert.deepEqual(onlyHit("includes/db.php", `<?php\n${s}\n$${NAME} = getenv('X');`, 3), NONE, `${label}: a reference below it, only its line changes`);
  }
  // Ordinary interpolations stay in the plain subset: a reference after them is no hit, a literal counts.
  for (const s of [`$s = "{$a["k"]}";`, `$s = "<b>{$data["x_$i"]}</b>";`, `$s = "x {$o->f("a")} y";`]) {
    assert.deepEqual(wholeHit("includes/db.php", `<?php\n${s}\n$${NAME} = getenv('X');\n`), NONE, `${s}: a reference`);
    assert.deepEqual(wholeHit("includes/db.php", `<?php\n${s}\n$${NAME} = ${literal()};\n`), hitAt(3), `${s}: a literal`);
  }
});

/** Interpolations, each outside the plain subset in JavaScript: [label, code]. */
const UNPLAIN = [
  ["a block comment", "/* c */ x"],
  ["a regular expression", "/x/.test(s)"],
  ["a nested template", `${B}in${B}`],
];
/** A regular expression that holds a quote, which PHP's lexer reads as a string's opening. */
const QUOTED = ['/"/', "/'/"];
/** A PHP page whose script holds `regex`, then a one-line template with `code` in its interpolation, then the name given `v`. */
const pageScript = (regex, code, v) => `<?php echo 1; ?>\n<script>\nconst r = ${regex}; const t = ${B}\${${code}}${B};\nconst ${NAME} = ${v};\n</script>\n`;
/** The same script as a .js file, line for line: the PHP and the tags taken out, their lines kept. */
const asJs = (text) => text.replace("<?php echo 1; ?>", "").replace(/<\/?script>/g, "");

test("inside an explicit <script> element of a PHP page a template is read as JavaScript reads it, also after a regular expression that holds a quote, so a comment, a regular expression or a template in its interpolation leaves the plain subset and a password given a reference below it counts, whole and where only its line changes, as in a .js file", () => {
  // Reading the regular expression's quote as a PHP string's opening would hide the template: no hit, humanOnly false.
  for (const regex of QUOTED) {
    for (const [label, code] of UNPLAIN) {
      const page = pageScript(regex, code, "x");
      const n = lineOf(page, `const ${NAME}`);
      const what = `${regex}, ${label}: ${JSON.stringify(page)}`;
      assert.deepEqual(wholeHit("includes/page.php", page), hitAt(n), `${what}: whole`);
      assert.deepEqual(onlyHit("includes/page.php", page, n), hitAt(n), `${what}: only line ${n}`);
      assert.deepEqual(wholeHit("api/client.js", asJs(page)), hitAt(n), `${what}: in a .js file`);
      assert.deepEqual(onlyHit("api/client.js", asJs(page), n), hitAt(n), `${what}: in a .js file, only line ${n}`);
      assert.deepEqual(wholeHit("includes/page.php", pageScript(regex, code, literal())), hitAt(n), `${what}: a literal`);
    }
  }
});

test("a page's script element is read by HTML's rules and JavaScript's alone, so a quote or a comment's mark in a regular expression (PHP's # too) reads in the PHP page as in the .js file: a reference below it is no hit, a literal counts", () => {
  // The PHP outside the element does not decide how the script's code reads: a reader that read the page's other lines
  // as PHP and could not establish the script's reading would count the reference in the PHP page, not in the .js file.
  for (const regex of [...QUOTED, "/[/*]/", "/#/", "/\\/\\//", "/[\"']/g"]) {
    const page = `<?php echo 1; ?>\n<script>\nconst r = ${regex};\nconst ${NAME} = x;\n</script>\n`;
    assert.deepEqual(wholeHit("includes/page.php", page), NONE, `${regex}: the page`);
    assert.deepEqual(onlyHit("includes/page.php", page, 4), NONE, `${regex}: the page, only line 4`);
    assert.deepEqual(wholeHit("api/client.js", asJs(page)), NONE, `${regex}: a .js file`);
    assert.deepEqual(wholeHit("includes/page.php", page.replace(`${NAME} = x`, `${NAME} = ${literal()}`)), hitAt(4), `${regex}: the page, a literal`);
  }
});

test("a page script's ordinary code (regular expressions without quotes or comment marks, divisions, one-line plain templates, strings and comments JavaScript reads alike) leaves a reference below it no hit, whole and where only its line changes; a literal counts; a piece of PHP inside the code leaves the plain subset, so a reference below <?= ?> or <?php ?> there counts", () => {
  // The plain subset keeps ordinary page scripts. A piece of PHP inside the code leaves HTML's state after it unknown,
  // so the forms with pieces count; "run" touches the piece, so the condition's own line counts too (edgeNames).
  for (const [code, also = []] of [
    ['var a = <?= json_encode($x) ?> / 2; var s = "a/b";'],
    ["var s = '<?= $x ?>' + \"<?= $y['k'] ?>\";"],
    ["<?php if ($a) { ?>run();<?php } ?>", [3]],
  ]) {
    const page = `<?php echo 1; ?>\n<script>\n${code}\nconst ${NAME} = x;\n</script>\n`;
    assert.deepEqual(wholeHit("includes/page.php", page), hitAt(...also, 4), `${code}: a reference`);
    assert.deepEqual(onlyHit("includes/page.php", page, 4), hitAt(4), `${code}: a reference, only line 4`);
  }
  for (const code of [
    "const r = /,/g; const n = a / b / c;",
    `const t = ${B}a \${x} b${B}; const u = ${B}\${o.f('x')}${B};`,
    "const r = /\\d+\\.\\d+/; if (r.test(s)) run();",
    "el.innerHTML = '<a href=\"#\">x</a>'; // it's fine",
    "/* a comment */ const o = { a: 1 };",
  ]) {
    const page = `<?php echo 1; ?>\n<script>\n${code}\nconst ${NAME} = x;\n</script>\n`;
    assert.deepEqual(wholeHit("includes/page.php", page), NONE, `${code}: a reference`);
    assert.deepEqual(onlyHit("includes/page.php", page, 4), NONE, `${code}: a reference, only line 4`);
    assert.deepEqual(wholeHit("includes/page.php", page.replace(`${NAME} = x`, `${NAME} = ${literal()}`)), hitAt(4), `${code}: a literal`);
  }
});

/**
 * A git repository in a scratch directory with `files` ([path, base content or null, head content]) committed as base
 * and written as head, byte for byte: what `check.mjs --station --json --base HEAD` routes as the password marker's
 * hits (path:line), every other marker row it hits (path:row), and humanOnly.
 */
function stationOnChange(files) {
  const dir = mkdtempSync(join(tmpdir(), "guardrails-php-interpolation-"));
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

test("production wiring, check.mjs --station: the echoed string with a comment in its interpolation is one-way, new and where only its template's line changes; the page script after a quoted regular expression is human-only, new and where only the password's line changes; the controls are neither", () => {
  const echo = echoed(UNREAD[0][1]);
  const page = pageScript("/'/", "/x/.test(s)", "x");
  const drop = lineOf(echo, "DROP");
  const named = lineOf(page, `const ${NAME}`);
  const without = (text, n) => `${text.split("\n").filter((_, i) => i !== n - 1).join("\n")}`;
  const counted = stationOnChange([
    ["finans/echo.php", null, echo],
    ["finans/edited.php", without(echo, drop), echo],
    ["includes/page.php", null, page],
    ["includes/edited.php", without(page, named), page],
  ]);
  assert.deepEqual(counted, { hits: [`includes/edited.php:${named}`, `includes/page.php:${named}`], rows: ["finans/echo.php:destructive", "finans/edited.php:destructive"], humanOnly: true });
  const plain = stationOnChange([
    ["finans/sql.php", null, `<?php\n$s = "<b>{$data["x_$i"]}</b>";\n// ${DROP}\n`],
    ["includes/page.php", null, `<?php echo 1; ?>\n<script>\nconst r = /,/g; const t = ${B}a \${x}${B};\nconst ${NAME} = x;\n</script>\n`],
    ["includes/db.php", null, `<?php\n$s = "{$a['"']}"; $${NAME} = getenv('X');\n`],
  ]);
  assert.deepEqual(plain, { hits: [], rows: [], humanOnly: false });
});
