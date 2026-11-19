// tools/guardrails/tests/doors-php-output.test.mjs - what a piece of PHP prints may end a comment, a raw text element or
// a tag and open a script, and a switch's case or default label decides which HTML is printed (lib/doors.mjs phpHtml,
// htmlCodes, controlsHtml, htmlReading).
//
// PHP's output may hold a "<": <!-- <?= '--><script>' ?> --> prints a closed comment and a script. So no place after a
// piece outside HTML's data state is certain (a comment's end the text shows, the place after a piece in a raw text
// element's text or a script's code, the places a tag's readings leave it), and the block leaves the plain subset at
// the piece. A case, a default or a goto's label is a place a jump lands on: in
// <?php switch ($x): case 1: ?><style><?php case 2: ?><script>, case 2 passes over the style's start tag and the script
// runs, so such a label counts as a piece that decides which HTML is printed (a doubt in data where a <select> or
// foreign content is open). Inline HTML in data that may run as a script's code all the same (after echo '<script>',
// printed again by a loop, as a function's body, as output PHP buffers) is read as JavaScript too.
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

/** JavaScript's password methods and getters that return a literal, that no "=" gives a value: no PHP reads them so. */
const getters = () => [
  ["a method", `class Login {\n  ${GETTER}() { return ${literal()}; }\n}`],
  ["an object's getter", `const login = { get ${NAME}() { return ${literal()}; } };`],
];
/** An ordinary reference: no hit in JavaScript's plain subset, a hit outside it. */
const REF = `const ${NAME} = document.getElementById('pw').value;`;
/** A page script that holds `code`, on lines of its own. */
const script = (code) => `<script>\n${code}\n</script>`;

test("a password method or getter that returns a literal after <!-- <?= '--><script>' ?> --> and before </script> counts, whole and where only its line changes: PHP's output may hold a \"<\", so the comment's end the text shows is no certain place", () => {
  // PHP's own tokens: inline HTML, <?= and a string, and inline HTML.
  const probe = "<!-- <?= '--><script>' ?> -->";
  assert.deepEqual(tokenize([probe])[0].map(([name]) => name), ["T_INLINE_HTML", "T_OPEN_TAG_WITH_ECHO", "T_WHITESPACE", "T_CONSTANT_ENCAPSED_STRING", "T_WHITESPACE", "T_CLOSE_TAG", "T_INLINE_HTML"]);
  for (const [what, code] of getters()) counts(`${probe}\n${code}\n</script>`, `the probe: ${what}`);
});

test("what a piece of PHP prints in a style's, a textarea's or a title's text, a comment, an attribute's value, a tag's attributes, a script's code or a script's start tag may end it and open a script, so the getter the reading sees as that text counts, and so does a reference in the page's next script, whole and where only its line changes", () => {
  // Read as PHP's code alone, the text gives the getter no literal; and no place after a piece is certain, not the end
  // tag, the comment's end or the place every reading of the tag leaves.
  for (const [label, open, close] of [
    ["a style's text", "<style><?= $css ?>", "</style>"],
    ["a textarea's text", "<textarea name=\"note\"><?= $v ?>", "</textarea>"],
    ["a title's text", "<title><?= $t ?>", "</title>"],
    ["a comment", "<!-- <?= $note ?>", "-->"],
    ["an attribute's value", "<div title=\"<?= $t ?>", "\"></div>"],
    ["a tag's attributes", "<option value=\"1\"<?php echo $sel ?>", ">One</option>"],
  ]) {
    // The piece's closing tag takes the line break, so the getter's first word touches it.
    for (const [what, code] of getters()) counts(`${open}\n${code}\n${close}`, `${label}: ${what}`, [1, 2]);
    counts(`${open}\n${close}\n${script(REF)}`, `${label}: a reference in the next script`);
  }
  for (const [label, before] of [
    ["a script's code", "<script>var a = <?= json_encode($a) ?>;</script>"],
    ["a script's start tag", "<script src=\"a.js?v=<?= $v ?>\"></script>"],
  ]) {
    counts(`${before}\n${script(REF)}`, `${label}: a reference in the next script`);
  }
});

test("a switch's case or default label in a style's text passes over the style's start tag (<?php switch ($x): case 1: ?><style><?php case 2: ?><script>), in the alternative syntax and with braces, so the getter in the script counts, whole and where only its line changes; so does one after a goto's label there", () => {
  for (const [label, open, close] of [
    ["the alternative syntax's case", "<?php switch ($x): case 1: ?><style><?php case 2: ?><script>", "</script><?php endswitch; ?>"],
    ["a switch's case with braces", "<?php switch ($x) { case 1: ?><style><?php case 2: ?><script>", "</script><?php } ?>"],
    ["a default label", "<?php switch ($x) { case 1: ?><style><?php default: ?><script>", "</script><?php } ?>"],
    ["a case ended by \";\"", "<?php switch ($x): case 1; ?><style><?php case 2; ?><script>", "</script><?php endswitch; ?>"],
    ["a goto's label", "<?php if ($a) goto shown; ?><style><?php shown: ?><script>", "</script>"],
  ]) {
    for (const [what, code] of getters()) counts(`${open}\n${code}\n${close}`, `${label}: ${what}`);
  }
});

test("labels in data: a case, a default or a goto's label in data while a <select> or foreign content is open may join HTML read there to HTML read otherwise, so a reference in the next script counts; with neither open, a switch over pieces of HTML keeps the reading known, and a switch, a match's default or an enum's case a piece holds whole decides no HTML", () => {
  // A label is a place a jump lands on.
  for (const [label, before] of [
    ["a case in a <select>", "<?php switch ($x): ?><?php case 1: ?><select><?php case 2: ?><option>2</option></select><?php endswitch; ?>"],
    ["a default in an <svg>", "<?php switch ($x) { case 1: ?><svg><?php default: ?><circle r=\"1\"/></svg><?php } ?>"],
    ["a goto's label in a <select>", "<?php goto shown; ?><select><?php shown: ?><option>1</option></select>"],
  ]) {
    counts(`${before}\n${script(REF)}`, `${label}: a reference`);
  }
  for (const [label, before] of [
    ["a switch over HTML in data", "<?php switch ($x): case 1: ?><p class=\"a\">a</p><?php break; ?><?php default: ?><p>b</p><?php endswitch; ?>"],
    ["a switch a piece holds whole, in a <select>", "<select><?php switch ($x) { case 1: echo '<option>1</option>'; break; default: echo ''; } ?></select>"],
    ["a match's default in a <select>", "<select><?= match ($x) { 1 => '<option>1</option>', default => '' } ?></select>"],
    ["an enum's case", "<?php enum Size { case Small; case Large; } ?><p>x</p>"],
  ]) {
    clear(`${before}\n${script(REF)}`, `${label}: a reference`);
    const given = script(`const ${NAME} = ${literal()};`);
    counts(`${before}\n${given}`, `${label}: a literal`);
  }
});

test("in HTML's data state, inline HTML that may run as a script's code all the same is read as JavaScript too, so a password method or getter that returns a literal counts after echo '<script>', in a loop's body a piece in a script's code jumps back to, in a function's body a script calls, and in output PHP buffers and prints into a script, whole and where only its line changes; ordinary markup that names a password stays no hit", () => {
  // Read as PHP's code alone, neither form gives a password a literal.
  for (const [label, open, close] of [
    ["after echo '<script>'", "<?php echo '<script>'; ?>", "</script>"],
    ["a loop's body", "<?php foreach ($rows as $r): ?>", "<script><?php endforeach; ?></script>"],
    ["a function's body", "<?php function widget() { ?>", "<?php } ?><script><?php widget(); ?></script>"],
    ["output PHP buffers", "<?php ob_start(); ?>", "<?php $s = ob_get_clean(); ?><script><?= $s ?></script>"],
  ]) {
    // The piece's closing tag takes the line break, so the getter's first word touches it.
    for (const [what, code] of getters()) counts(`${open}\n${code}\n${close}`, `${label}: ${what}`, [1, 2]);
  }
  for (const [label, html] of [
    ["a label and an input", `<label for="pw">Password</label>\n<input type="${NAME}" name="${NAME}" value="">`],
    ["a link with a path", `<a href="/index/glemt_${NAME}.php">Glemt ${NAME}?</a>`],
    ["prose with a slash", `<p>Your ${NAME} must be 8 characters and/or hold a digit.</p>`],
    ["a comment with a slash", `<!-- the ${NAME} field / old form -->`],
  ]) {
    clear(html, label);
  }
});

/**
 * A git repository in a scratch directory with `files` ([path, base content or null, head content]) committed as base
 * and written as head: what `check.mjs --station --json --base HEAD` routes as the password marker's hits (path:line)
 * and humanOnly, with PHP 8.2's tokenizer as check.mjs finds it.
 */
function stationOnChange(files) {
  const dir = mkdtempSync(join(tmpdir(), "guardrails-php-output-"));
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

test("production wiring, check.mjs --station: the comment's and the switch's probes in new files and as the changed line of an existing page are human-only with the method's line as the hit; a page whose pieces stand in data is not", () => {
  const method = `class Login {\n  ${GETTER}() { return ${literal()}; }\n}`;
  const comment = "<!-- <?= '--><script>' ?> -->";
  const sw = "<?php switch ($x): case 1: ?><style><?php case 2: ?><script>";
  const base = `${comment}\nclass Login {\n  run() { return 1; }\n}\n</script>\n`;
  assert.deepEqual(
    stationOnChange([
      ["includes/comment.php", null, `${comment}\n${method}\n</script>\n`],
      ["includes/switch.php", null, `${sw}\n${method}\n</script><?php endswitch; ?>\n`],
      ["includes/edited.php", base, base.replace("run() { return 1; }", `${GETTER}() { return ${literal()}; }`)],
    ]),
    { hits: ["includes/comment.php:3", "includes/edited.php:3", "includes/switch.php:3"], humanOnly: true },
  );
  const controls = `<?php if ($a): ?><p class="note"><?= $note ?></p><?php endif; ?>\n<td><?php echo $x ?></td>\n${script(REF)}\n`;
  assert.deepEqual(stationOnChange([["includes/controls.php", null, controls]]), NONE);
});
