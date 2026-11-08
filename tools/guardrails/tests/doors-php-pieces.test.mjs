// tools/guardrails/tests/doors-php-pieces.test.mjs - a piece of PHP anywhere but in HTML's data state leaves HTML's
// state after it unknown (lib/doors.mjs phpHtml, htmlCodes).
//
// PHP's output can finish an HTML delimiter with the text around it: <style>x<<?php echo '/'; ?>style><script> prints a
// closed style element and a script, and <!-- -<?php echo '-'; ?>><script> a closed comment and a script. doors.mjs
// never reads what a piece prints. In HTML's data state a piece is text; anywhere else (a tag, an attribute, a comment,
// a raw text element's text, a script's code) it may print anything, so no later place is certain: the block leaves the
// plain subset at the piece and every later line that names a password counts. A <plaintext>'s text, which HTML never
// leaves, is the one place outside data where a piece changes nothing. A piece that decides which HTML is printed (a
// condition, a loop, a switch, a function's body over pieces of HTML) is known in data, except where a <select> or
// foreign content is open. A line where a name's text touches a piece counts too (doors.mjs edgeNames).
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
 * `also` (a line where a name's text touches a piece of PHP, or a piece over lines does: doors.mjs edgeNames).
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

/**
 * Password getters that return a literal and that no "=" gives a value. A function is left out: inline HTML read as the
 * file's code counts it anyway.
 */
const getters = () => [
  ["a method", `class Login {\n  ${GETTER}() { return ${literal()}; }\n}`],
  ["an object's getter", `const login = { get ${NAME}() { return ${literal()}; } };`],
];
/** A page script that holds `code`, on lines of its own. */
const script = (code) => `<script>\n${code}\n</script>`;
/** An ordinary reference: no hit in JavaScript's plain subset, a hit outside it. */
const REF = `const ${NAME} = document.getElementById('pw').value;`;

test("<style>x<<?php echo '/'; ?>style><script> and <!-- -<?php echo '-'; ?>><script>: PHP's output may finish the style's end tag or the comment's end, so HTML's state after the piece is unknown and a password method or getter that returns a literal in the script counts, whole and where only its line changes", () => {
  // Read as the style's text or the comment's, the getters would be no hit, humanOnly false. PHP's own tokens: the
  // probe is inline HTML, an echo and inline HTML. "style" touches the style probe's piece, so its line counts too.
  for (const [probe, also] of [["<style>x<<?php echo '/'; ?>style><script>", [1]], ["<!-- -<?php echo '-'; ?>><script>", []]]) {
    assert.ok(tokenize([probe])[0].some(([name]) => name === "T_ECHO") && tokenize([probe])[0][0][0] === "T_INLINE_HTML", `PHP's tokens of ${probe}`);
    for (const [what, code] of getters()) counts(`${probe}\n${code}\n</script>`, `${probe}: ${what}`, also);
    // The same on one line with the probe, and a reference after it: outside the plain subset it counts too.
    const oneLine = getters()[0][1].replaceAll("\n", " ");
    counts(`${probe}${oneLine}</script>`, `${probe}: on its line`);
    counts(`${probe}\n${REF}\n</script>`, `${probe}: a reference`, also);
  }
});

test("in a raw text element's text: a piece of PHP anywhere in a <style>'s, a <textarea>'s, a <title>'s, an <xmp>'s or a <noscript>'s text, or in its start tag's attribute, leaves HTML's state unknown: the getter in the script after it counts, and so does a reference in a later script", () => {
  // The place after a piece with no "<" before it is not certain either: a reference in a later script counts.
  for (const tag of ["style", "textarea", "title", "xmp", "noscript"]) {
    // Where the tag's name touches the piece (all but "</"), line 1 counts too.
    for (const [label, open, also] of [
      ["<", `<${tag}>x<<?= '/' ?>${tag}><script>`, [1]],
      ["</", `<${tag}>x</<?= $tag ?>><script>`, []],
      ["</ and part of the name", `<${tag}>x</${tag.slice(0, 2)}<?= $rest ?>><script>`, [1]],
    ]) {
      for (const [what, code] of getters()) counts(`${open}\n${code}\n</script>`, `${tag}, ${label}: ${what}`, also);
    }
    counts(`<${tag}><?= $text ?></${tag}>\n${script(REF)}`, `${tag}, a piece in its text: a reference`);
    counts(`<${tag}>x<<?php echo '/'; ?>${tag}>a</${tag}>\n${script(REF)}`, `${tag}, a "<" piece with no "<" before its end tag: a reference`, [1]);
  }
  // A quote PHP prints in the start tag's attribute may end the tag later, outside the element's text the reading sees.
  for (const [what, code] of getters()) counts(`<textarea name="<?= $n ?>">'><script>\n${code}\n</script></textarea>`, `a textarea's attribute: ${what}`);
});

test("in a comment: a piece in a comment, before the end the text shows or not, and a piece in a bogus comment (<!DOCTYPE ...>, <!...>, </...>, <?...>) leave HTML's state unknown: the getter, or a reference, after them counts", () => {
  // Neither a comment's end with no "<" before it nor a bogus comment's ">" is a certain place after a piece.
  for (const open of ["<!-- note: <?= $note ?>><script>", "<!-- a <?= $x ?>!><script>"]) {
    for (const [what, code] of getters()) counts(`${open}\n${code}\n</script>`, `${open}: ${what}`);
  }
  for (const [what, code] of getters()) counts(`<!-- -<?php echo '-'; ?>><script>\n${code}\n</script> -->`, `the comment's end after the script: ${what}`);
  // "CD" touches the CDATA's piece, so its line counts too.
  for (const [before, also = []] of [
    ["<!-- <?php echo $note ?> -->"],
    ["<!-- a -<?php echo '-'; ?>> -->"],
    ["<!DOCTYPE html <?= $dt ?>>"],
    ["<!<?= '--' ?> x>"],
    ["<!-<?= '-' ?> x>"],
    ["<![CD<?= 'ATA[' ?> x>", [1]],
    ["</ <?= $x ?>>"],
  ]) {
    counts(`${before}\n${script(REF)}`, `${before}: a reference`, also);
  }
});

test("in a tag: a piece in an attribute's value, among a tag's attributes, after its name, in an end tag or in a script's, a style's or an <svg>'s start tag may end the value or the tag and open anything: the getter or the reference after it counts", () => {
  // A quote of each kind after the piece (QUOTES) ends every reading of the tag, yet the place is not certain: what the
  // piece prints may be anything, so the reference after them counts.
  const QUOTES = `<p class="note" title='x'>y</p>\n`;
  for (const [what, code] of getters()) {
    counts(`<div title="<?= $t ?>"><!-- '><script>\n${code}\n</script> -->`, `a quote into a comment: ${what}`);
    counts(`<input value="<?= $v ?>"><textarea>'><script>\n${code}\n</script></textarea>`, `a quote into a textarea: ${what}`);
  }
  // Where a tag's name touches the piece ("t", "tr", "b"), line 1 counts too.
  for (const [label, before, also = []] of [
    ["an attribute's value", `<div class="<?php echo $c ?>">x</div>\n${QUOTES}`],
    ["an input's value", `<input type="hidden" name="pw" value="<?= htmlspecialchars($v) ?>">\n${QUOTES}`],
    ["an option's attributes", `<option value="1"<?php echo $sel ?>>One</option>\n${QUOTES}`],
    ["a tag's name an element of raw text may start with", `<t<?= $rest ?>>x</textarea>\n${QUOTES}`, [1]],
    ["after a tag's name", `<tr<?php echo $hidden; ?>><td>x</td></tr>\n${QUOTES}`, [1]],
    ["an end tag", `<b>x</b<?php ?>>\n${QUOTES}`, [1]],
    ["a script's start tag", `<script src="a.js?<?= $v ?>"></script>\n${QUOTES}`],
    ["a style's start tag", `<style media="<?= $m ?>">a { color: red }</style>\n${QUOTES}`],
    ["an svg's start tag", `<svg class="<?= $c ?>"></svg>\n${QUOTES}`],
    ["a <select>'s options", `<select><option value="<?= $v ?>">One</option></select>\n${QUOTES}`],
    ["a condition's own echo in a tag", `<p class="<?php if ($a) echo 'x'; ?>">y</p>\n${QUOTES}`],
    ["a block that closes in its own piece", `<p class="<?php if ($a) { echo 'x'; } else { echo 'y'; } ?>">z</p>\n${QUOTES}`],
  ]) {
    counts(`${before}${script(REF)}`, `${label}: a reference`, also);
  }
});

test("pieces that decide which HTML is printed, and nested PHP blocks: a condition, a loop, a jump or a return in a tag, a comment, a style's text or a script's code, whether its other end stands there or outside, leaves HTML's state unknown: the getter or the reference after it counts", () => {
  // Reading a piece as text, or leaving it out, is not enough: the HTML between two pieces may be left out too. "b"
  // between two pieces touches both, so its line counts too.
  for (const [label, open, also = []] of [
    ["a comment's end over nested blocks", "<!-- -<?php if ($a) { ?><?php echo '-'; ?><?php } ?>><script>"],
    ["an alternative syntax's condition over a style's start tag", "<?php if ($a): ?><style><?php endif; ?><script>"],
    ["a condition's block over a style's start tag", "<?php if ($a) { ?><style><?php } ?><script>"],
    ["an else over a comment's start", "<?php if ($a): ?><p>a</p><?php else: ?><!--<?php endif; ?><script>"],
    ["a condition that joins \"<\" to \"/style>\"", "<style>a<<?php if ($x): ?>b<?php endif; ?>/style><script>", [1]],
  ]) {
    for (const [what, code] of getters()) counts(`${open}\n${code}\n</script>`, `${label}: ${what}`, also);
  }
  // Where a word or a script's code touches a piece ("x", "y", "selected", "run", "f"), line 1 counts too.
  for (const [label, before, also = []] of [
    ["a condition that opens in a comment and ends after it", "<!-- <?php if ($a): ?> --><?php endif; ?>\n"],
    ["a condition that opens and ends in a comment", "<!-- <?php if ($a): ?>x<?php else: ?>y<?php endif; ?> -->\n", [1]],
    ["a function's body over a tag", `<a title="<?php function note() { ?>">x</a><?php } ?>\n`],
    ["a condition in a tag", `<option value="1"<?php if ($s): ?> selected<?php endif; ?>>One</option>\n`, [1]],
    ["a condition that opens in a tag and closes after it", `<option value="1"<?php if ($s): ?> selected>One</option><?php endif; ?>\n`],
    ["a condition and a loop in a script's code", "<script><?php if ($a) { ?>run();<?php } ?><?php foreach ($l as $x): ?>f();<?php endforeach; ?></script>\n", [1]],
    ["a break in a tag", `<a title="<?php if ($x) break; ?>">x</a>\n`],
    ["a return in a tag", `<a title="<?php if (!$ok) return; ?>">x</a>\n`],
    ["a condition in data while a <select> is open", `<select><?php if ($a): ?><option>1</option><?php endif; ?></select>\n`],
  ]) {
    counts(`${before}${script(REF)}`, `${label}: a reference`, also);
  }
});

test("in a script's code: a piece anywhere in it, an operand, after a \"<\" and a space, the whole code, or after a \"<\", \"<!\", \"<!-\" or \"<s\" its output may finish into \"</script\", \"<!--\" or \"<script\", leaves HTML's state unknown: the getter after the first </script>, or a reference in the next script, counts", () => {
  // The place after a script's piece is not certain even where the piece follows no start of a delimiter. Where a
  // tag's name touches the piece ("s", "scr"), its line counts too.
  for (const [label, code, also = []] of [
    ["<!", "<!<?= '-' ?>-<script>"],
    ["<!-", "<!-<?= '-' ?><script>"],
    ["<s in an escaped script", "<!--\n<s<?= $rest ?>>", [3]],
    ["</scr", "</scr<?= 'ipt' ?>>", [2]],
  ]) {
    for (const [what, getter] of getters()) counts(`<script>\n${code}\n</script>\n${getter}\n</script>`, `${label}: ${what}`, also);
  }
  for (const [label, before] of [
    ["a script's operand", "<script>var a = <?php echo json_encode($a) ?>;</script>\n"],
    ["a \"<\" and a space before a piece", "<script>if (a < <?= $n ?>) { b(); }</script>\n"],
    ["a script whose code PHP prints", "<script><?php echo $js ?></script>\n"],
  ]) {
    counts(`${before}${script(REF)}`, `${label}: a reference`);
  }
});

test("controls: pieces of PHP in HTML's data state, and in a <plaintext>'s text, leave the reading known, so a reference in the script after them is no hit and a literal counts, whole and where only its line changes (an element's text, a \"<\" and a space before a piece, conditions, loops and switches in data, a block over pieces of HTML in data)", () => {
  for (const [label, before] of [
    ["a cell PHP prints", "<td><?php echo $x ?></td>\n"],
    ["a \"<\" and a space before a piece", "<p>a < <?php echo $b ?></p>\n"],
    ["a condition in data", "<?php if ($a): ?><p class=\"x\">y</p><?php else: ?><p>z</p><?php endif; ?>\n"],
    ["a loop in data", "<?php foreach ($rows as $r): ?><td><?= $r ?></td><?php endforeach; ?>\n"],
    ["a condition's block in data", "<?php if ($a) { ?><p>a</p><?php } ?>\n"],
    ["a switch in data", "<?php switch ($k): case 1: ?><p>a</p><?php break; default: ?><p>b</p><?php endswitch; ?>\n"],
    ["a function's body in data", "<?php function row($r) { ?><tr><td><?= $r ?></td></tr><?php } ?>\n"],
    ["PHP's code between", "<?php\n$x = 1;\n?>\n<p>x</p>\n"],
  ]) {
    clear(`${before}${script(REF)}`, `${label}: a reference`);
    const given = script(`const ${NAME} = ${literal()};`);
    counts(`${before}${given}`, `${label}: a literal`);
  }
  // PHP's own code after an unknown place keeps PHP's tokens' reading: a reference there is no hit only above the place.
  // "style" touches the probe's piece, so the probe's line counts; the reference above it stays no hit.
  const above = `<?php\n$${NAME} = getenv('X');\n?>\n<style>x<<?php echo '/'; ?>style><script>\n</script>`;
  assert.deepEqual(routed(PAGE, asNew(above)), hitAt(4), "PHP's code above the place: whole");
  assert.deepEqual(routed(PAGE, onlyLine(above, 2)), NONE, "PHP's code above the place: only line 2");
  counts(`<style>x<<?php echo '/'; ?>style><script>\n</script>\n<?php\n$${NAME} = getenv('X');`, "PHP's code below the place", [1]);
  // HTML never leaves a <plaintext>'s text, so a piece there changes nothing; in a <style>'s text the same piece leaves
  // the reading unknown. A method that returns a reference: no hit in the plain subset, a hit outside it.
  const reference = `class Login {\n  ${GETTER}() { return form.pw.value; }\n}`;
  // A space after the <plaintext>'s piece, as the closing tag would take a line break and the class's name
  // would touch the piece (doors.mjs edgeNames); as written that page counts, as the <style>'s does on lines 1 and 2.
  clear(`<plaintext><?= $x ?> \n${reference}`, "a piece in a <plaintext>'s text");
  counts(`<style><?= $x ?>\n${reference}\n</style>`, "the same piece in a <style>'s text", [1, 2]);
});

/**
 * A git repository in a scratch directory with `files` ([path, base content or null, head content]) committed as base
 * and written as head: what `check.mjs --station --json --base HEAD` routes as the password marker's hits (path:line)
 * and humanOnly, with PHP 8.2's tokenizer as check.mjs finds it.
 */
function stationOnChange(files) {
  const dir = mkdtempSync(join(tmpdir(), "guardrails-php-pieces-"));
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

test("production wiring, check.mjs --station: the style and comment probes in new files and as the changed line of an existing page are human-only with the getter's line as the hit; a reference after a comment's, a style's or an attribute's piece is human-only; the page of pieces in data is not", () => {
  const method = (probe) => `${probe}\nclass Login {\n  ${GETTER}() { return ${literal()}; }\n}\n</script>\n`;
  const style = "<style>x<<?php echo '/'; ?>style><script>";
  const comment = "<!-- -<?php echo '-'; ?>><script>";
  const base = `${comment}\nclass Login {\n  run() { return 1; }\n}\n</script>\n`;
  assert.deepEqual(
    stationOnChange([
      ["includes/style.php", null, method(style)],
      ["includes/comment.php", null, method(comment)],
      ["includes/edited.php", base, base.replace("run() { return 1; }", `${GETTER}() { return ${literal()}; }`)],
    ]),
    // "style" touches the style probe's piece, so its line counts too.
    { hits: ["includes/comment.php:3", "includes/edited.php:3", "includes/style.php:1", "includes/style.php:3"], humanOnly: true },
  );
  const pieces = `<!-- <?php echo $note ?> -->\n<style><?php echo $css ?></style>\n<input name="pw" value="<?= $v ?>">\n${script(REF)}\n`;
  assert.deepEqual(stationOnChange([["includes/pieces.php", null, pieces]]), { hits: ["includes/pieces.php:5"], humanOnly: true });
  const controls = `<td><?php echo $x ?></td>\n<?php if ($a): ?><p class="x"><?= $y ?></p><?php endif; ?>\n${script(REF)}\n`;
  assert.deepEqual(stationOnChange([["includes/controls.php", null, controls]]), NONE);
});

test("the readers stay linear: a page of tags with a piece each, of such tags between scripts, of comments with a piece each, and of conditions and switches in data grows no faster than linearly (linear-time.mjs), and the reference after it counts where a piece stands outside data and not where all stand in it", () => {
  // Each read is a page of its own (a comment that counts the reads ends it), so what is timed is the reading, not
  // PHP's reading of the page before, which doors.mjs keeps.
  let serial = 0;
  const read = (row, n) => routed(PAGE, asNew(`${row.repeat(n)}${script(REF)}\n<!-- ${serial++} -->`));
  const tags = linear("tags with a piece each", (n) => read("<a href=x?<?= $i ?>>y</a>\n", n), 4000);
  assert.deepEqual(tags, hitAt(4002));
  const scripts = linear("tags with a piece each between scripts", (n) => read("<a title=\"<?= $t ?>\">x</a><script>var s = 1;</script>\n", n), 4000);
  assert.deepEqual(scripts, hitAt(4002));
  const comments = linear("comments with a piece each", (n) => read("<!-- <?= $i ?> -->\n", n), 4000);
  assert.deepEqual(comments, hitAt(4002));
  const conditions = linear("conditions in data", (n) => read("<?php if ($a): ?><p class=\"x\">y</p><?php endif; ?>\n", n), 4000);
  assert.deepEqual(conditions, NONE);
  const switches = linear("switches in data", (n) => read("<?php switch ($k): case 1: ?><p>a</p><?php default: ?><p>b</p><?php endswitch; ?>\n", n), 4000);
  assert.deepEqual(switches, NONE);
});
