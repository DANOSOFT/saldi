// tools/guardrails/tests/doors-php-inline-html.test.mjs - what a piece of PHP prints may finish what the inline HTML
// before it leaves open (a method's head before its body), and the readings stay linear on large pages (lib/doors.mjs
// htmlReading, htmlCodes, scriptLines).
//
// A piece of PHP prints what the text does not show, so the end of a piece of inline HTML is no end of a value: what
// either reading of the inline HTML leaves open where a piece follows counts. After PHP prints '<script>', a method
// whose head and body a piece splits still returns a literal. A piece in a <plaintext>'s text that decides which HTML
// is printed (controlsHtml) leaves HTML's state unknown, as one in a tag, a comment or another raw text element does:
// <?php if ($x): ?><plaintext><?php endif; ?><script> may print the script with no <plaintext> before it. Each search
// for the next piece, "--!>" or "<!--" reads the page once (nextOf), and scriptLines finds a line's element by halves.
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
import { linear } from "./linear-time.mjs";

const CHECK = fileURLToPath(new URL("../check.mjs", import.meta.url));
// Built at run time, so this file holds no literal for the marker to find.
const NAME = ["pass", "word"].join("");
const GETTER = `get${NAME[0].toUpperCase()}${NAME.slice(1)}`;
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
/** Only git's line `n` of `text` changes; the lines above it are unchanged context (changeset.mjs addedWithContext). */
const onlyLine = (text, n) => addedWithContext(new Map([[n, text.split("\n")[n - 1]]]), `${text}\n`);
/** Git's last line of `text` that names a password, in any case, 1-based: the line a case is about. */
const passwordLine = (text) => text.split("\n").findLastIndex((line) => line.toLowerCase().includes(NAME)) + 1;
/**
 * `text`'s last line that names a password counts, whole and where only that line changes; whole, so do the lines of
 * `also` (the lines a piece of PHP and a name's text that touches it stand on: doors.mjs edgeNames).
 */
function counts(text, what, also = []) {
  const n = passwordLine(text);
  assert.deepEqual(routed(PAGE, asNew(text)), hitAt(...new Set([...also, n].sort((a, b) => a - b))), `${what}: whole: ${JSON.stringify(text)}`);
  assert.deepEqual(routed(PAGE, onlyLine(text, n)), hitAt(n), `${what}: only line ${n}`);
}
/** `text` is no hit, whole and where only its last line that names a password changes. */
function clear(text, what) {
  const n = passwordLine(text);
  assert.deepEqual(routed(PAGE, asNew(text)), NONE, `${what}: whole: ${JSON.stringify(text)}`);
  assert.deepEqual(routed(PAGE, onlyLine(text, n)), NONE, `${what}: only line ${n}`);
}

/** Where PHP prints a script's start tag in HTML's data state, which HTML's reading of the text sees as text. */
const ECHO_SCRIPT = "<?php echo '<script>'; ?>";
/** A method that returns a reference: no hit in JavaScript's plain subset, a hit outside it. */
const REFERENCE = `class Login {\n  ${GETTER}() { return form.pw.value; }\n}`;

test("a piece of PHP between a password method's head and its body after echo '<script>' (class Login { getPassword() <?php ?> { return '...'; } }) counts, whole and where only its line changes, and so does the same method whose literal PHP prints", () => {
  // PHP's own tokens: inline HTML ends where "<?php" opens, so the method's head and its body are two pieces of inline
  // HTML.
  const method = `class Login {\n  ${GETTER}() <?php ?> { return ${literal()}; }\n}`;
  const names = tokenize([`${ECHO_SCRIPT}\n${method}`])[0].map(([name]) => name);
  assert.deepEqual(names.slice(-4), ["T_INLINE_HTML", "T_OPEN_TAG", "T_CLOSE_TAG", "T_INLINE_HTML"]);
  // The echo's closing tag takes the line break, so "class" touches its piece: lines 1 and 2 count too.
  counts(`${ECHO_SCRIPT}\n${method}\n</script>`, "the probe", [1, 2]);
  counts(`${ECHO_SCRIPT}\nclass Login {\n  ${GETTER}() <?php ?> { return <?= json_encode($v) ?>; }\n}\n</script>`, "the literal PHP prints", [1, 2]);
  // On one line with the echo, and with a piece that prints.
  counts(`${ECHO_SCRIPT}class Login { ${GETTER}() <?php ?> { return ${literal()}; } }</script>`, "on the echo's line");
  counts(`${ECHO_SCRIPT}\nclass Login {\n  ${GETTER}() <?= $nl ?> { return ${literal()}; }\n}\n</script>`, "a piece that prints", [1, 2]);
});

test("a piece of PHP in a password method's or getter's head before its body shows (before its parameters, in them, before a static or an async method's body, before a getter's body) or after a declared name before its \"=\", after echo '<script>', in a loop's body or in a function's body, counts, whole and where only its line changes", () => {
  // The inline HTML's end is no end of the head.
  const heads = [
    ["before the parameters", `class Login {\n  ${GETTER}<?php ?>() { return ${literal()}; }\n}`],
    ["in the parameters", `class Login {\n  ${GETTER}(<?php ?>) { return ${literal()}; }\n}`],
    ["a static method", `class Login {\n  static ${GETTER}() <?php ?> { return ${literal()}; }\n}`],
    ["an async method", `class Login {\n  async ${GETTER}() <?php ?> { return ${literal()}; }\n}`],
    ["an object's method", `const login = {\n  ${NAME}() <?php ?> { return ${literal()}; }\n};`],
    ["an object's getter", `const login = { get ${NAME}() <?php ?> { return ${literal()}; } };`],
    ["a class's getter", `class Login {\n  get ${NAME}() <?php ?> { return ${literal()}; }\n}`],
    ["a declared name before its \"=\"", `const ${NAME} <?php ?> = ${literal()};`],
  ];
  for (const [label, open, close] of [
    ["after echo '<script>'", ECHO_SCRIPT, "</script>"],
    ["a loop's body", "<?php foreach ($rows as $r): ?>", "<script><?php endforeach; ?></script>"],
    ["a function's body", "<?php function widget() { ?>", "<?php } ?><script><?php widget(); ?></script>"],
  ]) {
    // The piece's closing tag takes the line break, so the head's first word touches it.
    for (const [what, code] of heads) counts(`${open}\n${code}\n${close}`, `${label}: ${what}`, [1, 2]);
  }
});

test("a condition, a loop, a function's body, a switch's case or a goto's label that may print the HTML after it without a <plaintext> start tag before it leaves HTML's state unknown: the split method counts, and so does a method that returns a reference in the script after it, whole and where only its line changes", () => {
  // Read as the plaintext's text, the script's method would be inline HTML that gives a password no "=" and no literal.
  const split = `class Login {\n  ${GETTER}() <?php ?> { return ${literal()}; }\n}`;
  for (const [label, open, close] of [
    ["a condition", "<?php if ($x): ?><plaintext><?php endif; ?><script>", "</script>"],
    ["a condition's block", "<?php if ($x) { ?><plaintext><?php } ?><script>", "</script>"],
    ["an else", "<?php if ($x): ?><plaintext><?php else: ?><script>", "</script><?php endif; ?>"],
    ["a loop", "<?php foreach ($rows as $r): ?><plaintext><?php endforeach; ?><script>", "</script>"],
    ["a function's body", "<?php function shown() { ?><plaintext><?php } ?><script>", "</script>"],
    ["a switch's case", "<?php switch ($x): case 1: ?><plaintext><?php case 2: ?><script>", "</script><?php endswitch; ?>"],
    ["a goto's label", "<?php goto shown; ?><plaintext><?php shown: ?><script>", "</script>"],
  ]) {
    counts(`${open}\n${split}\n${close}`, `${label}: the split method`);
    counts(`${open}\n${REFERENCE}\n${close}`, `${label}: a reference`);
  }
});

test("controls: a piece of PHP that follows complete markup, or a complete statement, leaves what the inline HTML says as it was: markup that names a password and a reference in the page's script stay no hit, a piece in a <plaintext>'s text that decides nothing changes nothing, and a literal still counts, whole and where only its line changes", () => {
  for (const [label, html] of [
    ["a label before a piece", `<label for="pw">Password</label> <?= $required ?>`],
    ["an input before a piece", `<input type="${NAME}" name="${NAME}" value=""> <?= $hint ?>`],
    ["a link before a piece", `<a href="/index/glemt_${NAME}.php">Glemt ${NAME}?</a> <?php echo $sep ?>`],
    // A space after each piece, so no name's text touches it (as written, below).
    ["a method that returns a reference before a piece, after echo '<script>'", `${ECHO_SCRIPT} \nclass Login {\n  ${GETTER}() { return form.pw.value; } <?= $more ?> \n}\n</script>`],
  ]) {
    clear(html, label);
  }
  clear(`<plaintext><?= $x ?> \n${REFERENCE}`, "a piece in a <plaintext>'s text");
  // As written without the space, each closing tag takes its line break and the next line's "class" or "}" touches the
  // piece, so the name there is not resolved and the state is not known from it: the reference counts. Where a name's
  // text touches a piece, even a plain reference needs a person.
  const method = `${ECHO_SCRIPT}\nclass Login {\n  ${GETTER}() { return form.pw.value; } <?= $more ?>\n}\n</script>`;
  assert.deepEqual(routed(PAGE, asNew(method)), hitAt(1, 2, 3, 4), "a method that returns a reference before a piece, as written: whole");
  assert.deepEqual(routed(PAGE, onlyLine(method, 3)), hitAt(3), "a method that returns a reference before a piece, as written: only line 3");
  const plain = `<plaintext><?= $x ?>\n${REFERENCE}`;
  assert.deepEqual(routed(PAGE, asNew(plain)), hitAt(1, 2, 3), "a piece in a <plaintext>'s text, as written: whole");
  assert.deepEqual(routed(PAGE, onlyLine(plain, 3)), hitAt(3), "a piece in a <plaintext>'s text, as written: only line 3");
  counts(`<?php if ($x): ?><p>a</p><?php endif; ?>\n<script>\nconst ${NAME} = ${literal()};\n</script>`, "a literal after a condition in data");
});

/**
 * A git repository in a scratch directory with `files` ([path, base content or null, head content]) committed as base
 * and written as head: what `check.mjs --station --json --base HEAD` routes as the password marker's hits (path:line)
 * and humanOnly, with PHP 8.2's tokenizer as check.mjs finds it.
 */
function stationOnChange(files) {
  const dir = mkdtempSync(join(tmpdir(), "guardrails-php-inline-html-"));
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

test("production wiring, check.mjs --station: the split method's two forms in new files and as the changed line of an existing page are human-only with the method's line as the hit; a page whose pieces follow complete markup is not", () => {
  const split = `class Login {\n  ${GETTER}() <?php ?> { return ${literal()}; }\n}\n</script>\n`;
  const plaintext = "<?php if ($x): ?><plaintext><?php endif; ?><script>";
  const base = `${ECHO_SCRIPT}\nclass Login {\n  run() <?php ?> { return 1; }\n}\n</script>\n`;
  assert.deepEqual(
    stationOnChange([
      ["includes/echo.php", null, `${ECHO_SCRIPT}\n${split}`],
      ["includes/plain.php", null, `${plaintext}\n${split}`],
      ["includes/edited.php", base, base.replace("run() <?php ?> { return 1; }", `${GETTER}() <?php ?> { return ${literal()}; }`)],
    ]),
    // The echo's closing tag takes the line break, so "class" touches its piece: echo.php's lines 1 and 2 count.
    { hits: ["includes/echo.php:1", "includes/echo.php:2", "includes/echo.php:3", "includes/edited.php:3", "includes/plain.php:3"], humanOnly: true },
  );
  const controls = `<label for="pw">Password</label> <?= $required ?>\n<td><?php echo $x ?></td>\n<script>\nconst ${NAME} = document.getElementById('pw').value;\n</script>\n`;
  assert.deepEqual(stationOnChange([["includes/controls.php", null, controls]]), NONE);
});

test("pages with no piece of PHP, of 40,000 tags, scripts, comments or raw text elements, each read a text of its own (so PHP's reading of an earlier one is not reused), grow no faster than linearly (linear-time.mjs), and the reference in the script after them is no hit and a literal there counts", () => {
  // A search that reads the rest of the page again at every tag, comment or script, or a scriptLines that looks through
  // every element at every line, spends 12 to 16 times the CPU at four times the input; the linear readings 2 to 5
  // times. An em dash on the page's first line makes the page's text two bytes a character, as Saldi's pages with one or
  // a euro sign are, where a search reads slower.
  let serial = 0;
  /** A page of `n` repetitions of `element`, then a script whose line names a password: a reference or a literal. */
  const page = (element, n, given) => `<p>Saldi \u2014 ${serial++}</p>\n${element.repeat(n)}<script>\nconst ${NAME} = ${given};\n</script>`;
  const n = 40000;
  for (const [shape, element] of [
    ["tags", "<a href=x>y</a>\n"],
    ["scripts", "<script>var s = 1;</script>\n"],
    ["comments", "<!-- c -->\n"],
    ["raw text elements", "<style>a{}</style>\n"],
  ]) {
    const result = linear(`${shape} with no piece of PHP`, (size) => routed(PAGE, asNew(page(element, size, "document.getElementById('pw').value"))), n);
    assert.deepEqual(result, NONE, `${shape}: a reference`);
    assert.deepEqual(routed(PAGE, asNew(page(element, n, literal()))), hitAt(n + 3), `${shape}: a literal`);
  }
});
