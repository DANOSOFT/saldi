// tools/guardrails/tests/doors-php-tokens.test.mjs - the markers read a PHP file by PHP's own tokens (lib/php.mjs).
//
// doors.mjs does not lex PHP: PHP 8.2's token_get_all (lib/php.mjs, through php-syntax's phpCommand) decides where every
// string, comment, heredoc, nowdoc and piece of inline HTML starts and ends, so an HTML comment holding "//" before a
// <script> or a PHP string over lines reads as PHP reads it. HTML's own rules find each <script> element in the inline
// HTML (doors.mjs phpHtml), and JavaScript reads each element's code as a program of its own, in its plain subset
// (scriptLines). Where PHP's tokenizer cannot run, every line that names a password in a PHP file counts, no PHP comment
// is established, and check.mjs says why. A binary interpolated string (b"..." or B"...", one token b" that '"' closes)
// is read as the string it is (doors.mjs phpStringCloser), so it routes as the same file with a plain "..." string.
//
// History:
// 20261106 CL/LH Created.
import test from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { mkdirSync, mkdtempSync, rmSync, symlinkSync, writeFileSync } from "node:fs";
import { randomBytes } from "node:crypto";
import { tmpdir } from "node:os";
import { delimiter, dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { tokenize } from "../lib/php.mjs";
import { markerHits, stationReport } from "../lib/station.mjs";
import { addedWithContext } from "../lib/changeset.mjs";
import { phpFailure, prefetchPhp, usePhpTokenizer } from "../lib/doors.mjs";

const CHECK = fileURLToPath(new URL("../check.mjs", import.meta.url));
// Built at run time, so this file holds no literal for the marker to find.
const NAME = ["pass", "word"].join("");
const B = "`";
const literal = () => `'v${randomBytes(6).toString("hex")}'`;
const NONE = Object.freeze({ hits: [], humanOnly: false });
const hitAt = (n) => ({ hits: [n], humanOnly: true });
const DROP = 'db.query("DROP DATABASE scratch")';

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
/** The one-way rows (row:marker) markerHits finds on `entries` of `path`, but the password marker. */
const codeRows = (path, entries) => [...new Set(markerHits(path, entries).filter((hit) => hit.row !== "secrets").map((hit) => `${hit.row}:${hit.marker}`))].sort();

/** Git's last line of `text` that names a password, in any case, 1-based: the line a case is about. */
const passwordLine = (text) => text.split("\n").findLastIndex((line) => line.toLowerCase().includes(NAME)) + 1;
/** `text`'s last line that names a password counts, whole and where only that line changes. */
function counts(path, text, what) {
  const n = passwordLine(text);
  assert.deepEqual(wholeHit(path, text), hitAt(n), `${what}: whole: ${JSON.stringify(text)}`);
  assert.deepEqual(onlyHit(path, text, n), hitAt(n), `${what}: only line ${n}`);
}
/** `text` is no hit, whole and where only its last line that names a password changes. */
function clear(path, text, what) {
  const n = passwordLine(text);
  assert.deepEqual(wholeHit(path, text), NONE, `${what}: whole: ${JSON.stringify(text)}`);
  assert.deepEqual(onlyHit(path, text, n), NONE, `${what}: only line ${n}`);
}

/** A template whose interpolation holds a regular expression: JavaScript's plain subset ends there. */
const UNPLAIN = `const t = ${B}\${/x/.test("x")}${B};`;

/**
 * HTML before a page's <script>: [label, html]. Each holds a PHP comment's mark, or PHP's "?>", where HTML reads it as
 * text, an attribute's value or a raw text element's text, never as a comment's mark.
 */
const HTML_BEFORE = [
  ["an HTML comment holding //", "<!-- // --> "],
  ["an HTML comment holding /*", "<!-- /* --> "],
  ["an HTML comment holding #", "<!-- # --> "],
  ["a quoted attribute holding //", `<a href="//example.invalid/x">x</a> `],
  ["an unquoted attribute holding //", "<a href=//example.invalid/x>x</a> "],
  ["a style element holding //", "<style>a { background: url(//example.invalid/x) }</style> "],
  ["text holding /*", "<p>see /* this</p> "],
  ["an attribute holding ?>", `<a title="?>">x</a> `],
  ["HTML a PHP line comment's ?> leaves, holding //", "<?php // a ?> // b "],
];

test("HTML before a page's <script> that holds a PHP comment's mark (an HTML comment, an attribute, a style element, text) hides no script: a template whose interpolation holds a regular expression leaves JavaScript's plain subset, so a password given a reference after it counts, on the script tag's line and below it, whole and where only its line changes", () => {
  // Read as PHP's code, the HTML's comment mark would hide the <script> tag, and the reference would be no hit,
  // humanOnly false.
  for (const [label, html] of HTML_BEFORE) {
    counts("includes/page.php", `${html}<script>\n${UNPLAIN}\nconst ${NAME} = t;\n</script>`, `${label}, the tag's line`);
    counts("includes/page.php", `${html}\n<script>\n${UNPLAIN}\nconst ${NAME} = t;\n</script>`, `${label}, a line below`);
  }
});

test("controls: after the same HTML, a page's plain script leaves a reference no hit and a literal counts, whole and where only its line changes", () => {
  for (const [label, html] of HTML_BEFORE) {
    clear("includes/page.php", `${html}<script>\nconst t = ${B}a \${x} b${B};\nconst ${NAME} = t;\n</script>`, `${label}: a reference`);
    counts("includes/page.php", `${html}<script>\nconst t = 1;\nconst ${NAME} = ${literal()};\n</script>`, `${label}: a literal`);
  }
});

test("each <script> element's code is a program of its own: a comment or a string an element above leaves open does not read on into the next, so a regular expression in the next one's template leaves the plain subset and a reference after it counts; two elements on one line, and a script whose code holds <!-- and <script (HTML may run it past its </script>), leave the reading unknown, so a password line there counts; a plain element is plain", () => {
  // Read as one text, the page's scripts would let a comment the first element leaves open hide the second, and no
  // element would be found after "</script><script>" on one line.
  for (const [label, first] of [
    ["a comment left open", "<script>/*</script>"],
    ["a string left open", "<script>var s = '</script>"],
    ["a template left open", `<script>var s = ${B}</script>`],
    ["a group left open", "<script>f(</script>"],
  ]) {
    counts("includes/page.php", `${first}\n<script>\n${UNPLAIN}\nconst ${NAME} = t;\n</script>`, label);
    clear("includes/page.php", `${first}\n<script>\nconst t = 1;\nconst ${NAME} = t;\n</script>`, `${label}, then a plain element`);
  }
  counts("includes/page.php", `<script>var a = 1;</script><script>\nconst ${NAME} = t;\n</script>`, "two elements on one line");
  counts("includes/page.php", `<script>\nvar a = "<!--<script>";\n</script>\nconst ${NAME} = t;\n</script>`, "<!-- and <script in a script's code");
  clear("includes/page.php", `<script>var a = 1;</script>\n<script>\nconst ${NAME} = t;\n</script>`, "two plain elements");
});

/** PHP strings over lines, heredocs and comments: [label, code]. Each ends on the line the password's follows on. */
const OVER_LINES = [
  ["a double-quoted string over lines", `$x = "a\nb";`],
  ["a single-quoted string over lines", `$x = 'a\nb';`],
  ["a heredoc", `$x = <<<EOT\na\nEOT;`],
  ["a nowdoc", `$x = <<<'EOT'\na\nEOT;`],
  ["a backtick string over lines", "$x = `ls\n-l`;"],
  ["an interpolation on the string's later line", `$x = "a\n{$cfg['k']} b";`],
  ["nested quotes in an interpolation, then a line break", `$x = "a {$cfg["k"]}\nb";`],
  ["?> in a string over lines", `$x = "a ?>\nb";`],
  ["?> in a string", `$x = '?>';`],
  ["an escaped quote, then a line break", `$x = "a \\"\nb";`],
  ["a comment between two strings", `$x = "a"; /* " */ $y = 'b\nc';`],
  ["a block comment over lines holding a quote", `/* a\n' b */`],
  ["a doc comment's prose apostrophe", `/**\n * it's\n */`],
];

test("after an ordinary PHP string over lines (and a heredoc, a nowdoc, a backtick string, an interpolation, nested quotes, ?> in a string, an escaped quote, a comment), a password given a literal counts, on the string's last line and on the next, whole and where only its line changes", () => {
  // A lexer that split PHP's strings at each line end would read the literal on the string's last line as the
  // string's text.
  for (const [label, code] of OVER_LINES) {
    counts("includes/db.php", `<?php\n${code} $${NAME} = ${literal()};`, `${label}, the same line`);
    counts("includes/db.php", `<?php\n${code}\n$${NAME} = ${literal()};`, `${label}, the next line`);
  }
});

test("controls: after the same strings, heredocs and comments, a password given a reference is no hit, on the string's last line and on the next, whole and where only its line changes", () => {
  for (const [label, code] of OVER_LINES) {
    for (const ref of ["getenv('X')", "$_POST['pw']", `"{$cfg['pw']}"`, "$pw"]) {
      clear("includes/db.php", `<?php\n${code} $${NAME} = ${ref};`, `${label}, the same line, ${ref}`);
      clear("includes/db.php", `<?php\n${code}\n$${NAME} = ${ref};`, `${label}, the next line, ${ref}`);
    }
  }
});

test("a string's text over lines is text: a password it gives a value counts (a connection string, a page script printed by PHP), as on one line; a heredoc's or a nowdoc's PHP-shaped text too; PHP's own simple interpolations in it stay references", () => {
  // A string's later lines are the string's text, not code.
  for (const [label, text] of [
    ["a connection string over lines", `<?php\n$dsn = "host=db\n  ${NAME}=secret\n";`],
    ["a script printed over lines", `<?php\nprint "<script>\n  var ${NAME} = document.getElementById('pw').value;\n</script>";`],
    ["a nowdoc's PHP-shaped text", `<?php\n$php = <<<'PHP'\n$${NAME} = getenv('X');\nPHP;`],
    ["a connection string on one line", `<?php\n$dsn = "host=db ${NAME}=secret";`],
  ]) {
    counts("includes/db.php", text, label);
  }
  // A reference: no hit as a new file, nor where only the password's line changes: the change's line is read on over
  // the unchanged lines below it to the string's close, as the whole file is (changeset.mjs addedWithContext). Where the
  // lines a caller hands end inside the string (none below the change), a value open there still counts.
  for (const [label, text] of [
    ["a variable in a string over lines", `<?php\n$dsn = "host=db\n  ${NAME}=$pw\n";`],
    ["a heredoc's interpolation", `<?php\n$dsn = <<<EOT\n  ${NAME}=$pw\nEOT;`],
  ]) {
    const n = passwordLine(text);
    assert.deepEqual(wholeHit("includes/db.php", text), NONE, label);
    assert.deepEqual(onlyHit("includes/db.php", text, n), NONE, `${label}, only its line, read on to the string's close`);
    const handed = addedWithContext(new Map([[n, text.split("\n")[n - 1]]]), `${text}\n`, Infinity, 0);
    assert.deepEqual(routed("includes/db.php", handed), hitAt(n), `${label}, only its line, no line below handed: open where the lines end`);
  }
});

test("short open tags: PHP's tokens read with short_open_tag off, as php.ini-production sets it, so <? opens no code; where it is on it would, so from a <? in inline HTML the reading is not known: a password line counts, a reference too, and no comment after it is passed over", () => {
  counts("includes/page.php", `<? $${NAME} = getenv('X'); ?>`, "a short tag's reference");
  counts("includes/page.php", `<?xml version="1.0"?>\n<?php\n$${NAME} = getenv('X');`, "a reference after <?xml");
  assert.deepEqual(codeRows("finans/report.php", asNew(`<?xml version="1.0"?>\n<?php\n// ${DROP}\n`)), ["destructive:DROP DATABASE"], "a comment after <?xml");
  // <?php and <?= are code wherever short_open_tag stands.
  clear("includes/page.php", `<p><?= $x ?></p>\n<?php\n$${NAME} = getenv('X');`, "after <?=");
  assert.deepEqual(codeRows("finans/report.php", asNew(`<p><?= $x ?></p>\n<?php\n// ${DROP}\n`)), [], "a comment after <?=");
});

test("PHP's comments are PHP's own tokens': a line comment ends at ?>, so what follows is HTML and keeps its text; a comment's prose apostrophe hides no literal; commented-out code with a reference is no hit", () => {
  // The HTML after a comment's ?> is HTML, not PHP's code.
  counts("includes/page.php", `<?php // a ?> $${NAME} = getenv('X');`, "after a comment's ?>, HTML");
  assert.deepEqual(codeRows("finans/report.php", asNew(`<?php // a ?> ${DROP}\n`)), ["destructive:DROP DATABASE"], "HTML after a comment's ?>");
  counts("includes/db.php", `<?php\n// don't keep $${NAME} = ${literal()} in the file`, "a prose apostrophe");
  counts("includes/db.php", `<?php\n# it's $${NAME} = ${literal()};`, "a prose apostrophe after #");
  clear("includes/db.php", `<?php\n// $${NAME} = getenv('PGPASSWORD');`, "commented-out code with a reference");
  clear("includes/db.php", `<?php\n#\t$qtxt.= "${NAME} = '".db_escape_string(trim($_POST['pw1']))."' ";`, "commented-out SQL with a reference");
});

/** A realistic PHP page: ordinary code, a heredoc, HTML with attributes and comments, a page script, and PHP in it. */
const PAGE = [
  "<?php",
  "// The login form.",
  "$sql = \"select * from brugere\n  where brugernavn = '$user'\";",
  "$msg = <<<EOT\nHello {$row['navn']},\nEOT;",
  "?>",
  "<!-- login // form -->",
  `<form action="//example.invalid/login" method="post">`,
  `  <input type="${NAME}" name="${NAME}" value="">`,
  "  <p class=\"msg\"><?= htmlspecialchars($msg) ?></p>",
  "</form>",
  "<script>",
  `  var t = ${B}<b>\${user}</b>${B};`,
  "  var pw = document.getElementById('pw').value;",
  "</script>",
  "<?php",
].join("\n");

test("plain code after every form PHP's tokens read (a string over lines, a heredoc, HTML with comments and attributes, a page script, <?= in an element's text) needs no person: each ordinary reference is no hit, in PHP's code and in the page's script, whole and where only its line changes; the same names given a literal count; with <?= in an attribute the references after it count", () => {
  const refs = [
    `$${NAME} = getenv('DB_PASSWORD');`,
    `$${NAME} = $_POST['${NAME}'];`,
    `$${NAME} = $cfg['db']['${NAME}'] ?? '';`,
    `$${NAME} = "$pw";`,
    `$cfg = ['user' => $u, '${NAME}' => $pw];`,
    `define('DB_${NAME.toUpperCase()}', getenv('X'));`,
    `function login($user, $${NAME}) { return check($user, $${NAME}); }`,
  ];
  for (const ref of refs) {
    clear("includes/login.php", `${PAGE}\n${ref}`, `PHP's code: ${ref}`);
    counts("includes/login.php", `${PAGE}\n$${NAME} = ${literal()};`, "PHP's code: a literal");
  }
  // The page without its last "<?php", so that it ends in HTML (no regular expression in a template's interpolation
  // here: this file stays in JavaScript's plain subset).
  const html = PAGE.slice(0, PAGE.lastIndexOf("<?php"));
  for (const ref of [`const ${NAME} = document.getElementById('pw').value;`, `const ${NAME} = process.env.X;`, `let ${NAME} = form.elements.pw.value;`]) {
    clear("includes/login.php", `${html}<script>\n${ref}\n</script>`, `a page script: ${ref}`);
  }
  counts("includes/login.php", `${html}<script>\nconst ${NAME} = ${literal()};\n</script>`, "a page script: a literal");
  // A piece of PHP in an attribute's value: what it prints may end the value and the tag, and open anything.
  // From the piece on the page is outside the plain subset: the input's own line names a password and counts in the
  // whole page, and so does each reference after it, whole and where only its line changes.
  const attribute = PAGE.replace('value=""', 'value="<?= htmlspecialchars($pw) ?>"');
  const input = attribute.split("\n").findIndex((line) => line.includes("<input")) + 1;
  const attributeHtml = attribute.slice(0, attribute.lastIndexOf("<?php"));
  for (const [what, text] of [
    ["PHP's code after <?= in an attribute", `${attribute}\n${refs[0]}`],
    ["a page script after <?= in an attribute", `${attributeHtml}<script>\nconst ${NAME} = document.getElementById('pw').value;\n</script>`],
  ]) {
    const n = passwordLine(text);
    assert.deepEqual(wholeHit("includes/login.php", text), { hits: [input, n], humanOnly: true }, `${what}: whole`);
    assert.deepEqual(onlyHit("includes/login.php", text, n), hitAt(n), `${what}: only line ${n}`);
  }
});

test("where PHP's tokenizer cannot run, every line that names a password in a PHP file counts, references too, no PHP comment is passed over, phpFailure() says why, and other files read as before", () => {
  const real = usePhpTokenizer(() => {
    throw new Error("no PHP 8.2 here (the test's tokenizer)");
  });
  try {
    assert.deepEqual(wholeHit("includes/db.php", `<?php\n$${NAME} = getenv('X');`), hitAt(2), "a reference");
    assert.deepEqual(onlyHit("includes/db.php", `<?php\n$x = 1;\n$${NAME} = $_POST['pw'];`, 3), hitAt(3), "a reference, only its line");
    assert.deepEqual(wholeHit("includes/db.php", `<?php\n// the ${NAME} is read from the environment`), hitAt(2), "a comment naming it");
    assert.deepEqual(codeRows("finans/report.php", asNew(`<?php\n// ${DROP}\n`)), ["destructive:DROP DATABASE"], "a comment line keeps its text");
    assert.match(phpFailure() ?? "", /could not run: no PHP 8\.2 here/);
    assert.deepEqual(wholeHit("api/client.js", `const ${NAME} = process.env.X;`), NONE, "a .js file reads as before");
  } finally {
    usePhpTokenizer(real);
  }
  assert.equal(phpFailure(), null);
  assert.deepEqual(wholeHit("includes/db.php", `<?php\n$${NAME} = getenv('X');`), NONE, "with PHP's tokenizer again");
});

test("production wiring, check.mjs --station: without PHP 8.2 on PATH and without Docker the change still routes, every line that names a password in a PHP file counts, and check.mjs says why on stderr (--json) or after the report", () => {
  const dir = mkdtempSync(join(tmpdir(), "guardrails-no-php-"));
  const bin = join(dir, "bin");
  const repo = join(dir, "repo");
  try {
    mkdirSync(bin);
    mkdirSync(repo);
    // Only git on PATH: no php, no docker.
    const gitPath = spawnSync("sh", ["-c", "command -v git"], { encoding: "utf8" }).stdout.trim();
    symlinkSync(gitPath, join(bin, "git"));
    const env = { ...process.env, PATH: bin };
    const git = (...args) => spawnSync("git", ["-c", "user.name=guardrails-test", "-c", "user.email=guardrails-test@example.invalid", "-c", "commit.gpgsign=false", ...args], { cwd: repo, encoding: "utf8", env });
    assert.equal(git("init", "-q").status, 0);
    assert.equal(git("commit", "-q", "--allow-empty", "--no-verify", "-m", "base").status, 0);
    mkdirSync(join(repo, "includes"));
    writeFileSync(join(repo, "includes/db.php"), `<?php\n$${NAME} = getenv('X');\n`);
    const json = spawnSync(process.execPath, [CHECK, "--station", "--json", "--base", "HEAD"], { cwd: repo, encoding: "utf8", env, timeout: 600000 });
    assert.equal(json.status, 0, json.stderr);
    const report = JSON.parse(json.stdout);
    assert.deepEqual(report.markersHit.filter((hit) => hit.row === "secrets").map((hit) => `${hit.path}:${hit.line}`), ["includes/db.php:2"]);
    assert.equal(report.humanOnly, true);
    assert.match(json.stderr, /PHP's tokenizer could not read the PHP files \(.*php-syntax needs PHP 8\.2.*\), so every line that names a password in a PHP file counts/);
    const text = spawnSync(process.execPath, [CHECK, "--station", "--base", "HEAD"], { cwd: repo, encoding: "utf8", env, timeout: 600000 });
    assert.equal(text.status, 0, text.stderr);
    assert.match(text.stdout, /PHP's tokenizer could not read the PHP files/);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});

test("one request to PHP reads a change's PHP files (doors.mjs prefetchPhp), and markerHits asks PHP again only for a comment's or a string's text it reads as code", () => {
  const asked = [];
  const real = usePhpTokenizer((texts) => {
    asked.push(texts.length);
    return tokenize(texts);
  });
  try {
    const files = [
      ["includes/a.php", asNew(`<?php\n$${NAME} = getenv('X');\n// plain\n`)],
      ["includes/b.php", asNew(`<p>x</p>\n<script>\nconst ${NAME} = process.env.X;\n</script>\n`)],
      ["includes/c.inc", asNew(`<?php\n$sql = "select 1\n  from t";\n`)],
      ["api/client.js", asNew(`const ${NAME} = process.env.X;`)],
    ];
    prefetchPhp(files);
    assert.deepEqual(asked, [3], "the three PHP files' blocks, in one request");
    for (const [path, entries] of files) assert.deepEqual(markerHits(path, entries).filter((hit) => hit.row === "secrets"), [], path);
    assert.deepEqual(asked, [3], "no request more: no comment or string names a password");
    assert.deepEqual(markerHits("includes/d.php", asNew(`<?php\n// $${NAME} = getenv('X');`)), []);
    assert.deepEqual(asked, [3, 1, 1], "a file not prefetched, then its comment's text read as code");
  } finally {
    usePhpTokenizer(real);
  }
});

/** A binary interpolated string (b"..." or B"...") is read as the string it is (doors.mjs phpStringCloser). */
const GETTER = `get${NAME[0].toUpperCase()}${NAME.slice(1)}`;
const BINARY_PATH = "includes/page.php";
const QUOTES = ['b"', 'B"'];

/**
 * The forms, each built with the opener `q` ('"', b" or B"; every string closes at '"'), and the line the password
 * names. The closing tag before the script's code is followed by a space, so no name's text touches a piece of PHP
 * and only the string's reading decides.
 */
const binaryForms = (q, method = GETTER) => ({
  "a binary string before a script PHP prints": [`<?php\n$a = ${q}x $v";\necho "<script>";\n?>\n class X { ${method}() { return ${literal()}; } }\n<?php echo "</script>"; ?>`, 5],
  "a binary string with a braced interpolation": [`<?php\n$a = ${q}x {$v}";\necho "<script>";\n?>\n class X { ${method}() { return ${literal()}; } }\n<?php echo "</script>"; ?>`, 5],
  "a binary string nested in an interpolation's code": [`<?php\n$a = "x {$rows[${q}k$i"]}";\necho "<script>";\n?>\n class X { ${method}() { return ${literal()}; } }\n<?php echo "</script>"; ?>`, 5],
  "a binary string that prints the script tag": [`<?php\necho ${q}<script>{$nl}";\n?>\n class X { ${method}() { return ${literal()}; } }\n<?php echo "</script>"; ?>`, 4],
});

test("each binary-string form routes as its plain-quote control does, a hit on the password's line, whole and as a changed line", () => {
  for (const [form, [plain, line]] of Object.entries(binaryForms('"'))) {
    const control = { whole: routed(BINARY_PATH, asNew(plain)), changed: routed(BINARY_PATH, onlyLine(plain, line)) };
    assert.deepEqual(control.whole, { hits: [line], humanOnly: true }, `control, ${form}, whole`);
    assert.deepEqual(control.changed, { hits: [line], humanOnly: true }, `control, ${form}, line ${line} changed alone`);
    for (const q of QUOTES) {
      const [text] = binaryForms(q)[form];
      // Read as plain text, b" would leave its closing '"' to open a string that hides the script: no hit.
      assert.deepEqual(routed(BINARY_PATH, asNew(text)), control.whole, `${q}, ${form}, whole`);
      assert.deepEqual(routed(BINARY_PATH, onlyLine(text, line)), control.changed, `${q}, ${form}, line ${line} changed alone`);
    }
  }
});

test("a password variable after a binary string, and one given a binary string, count with every quote", () => {
  for (const q of ['"', ...QUOTES]) {
    const after = `<?php\n$a = ${q}x $v";\n$${NAME} = ${literal()};`;
    assert.deepEqual(routed(BINARY_PATH, asNew(after)), { hits: [3], humanOnly: true }, `${q}, after`);
    assert.deepEqual(routed(BINARY_PATH, onlyLine(after, 3)), { hits: [3], humanOnly: true }, `${q}, after, line 3 alone`);
    const given = `<?php\n$${NAME} = ${q}v{$v}";`;
    assert.deepEqual(routed(BINARY_PATH, asNew(given)), { hits: [2], humanOnly: true }, `${q}, given`);
  }
});

test("controls: the same files with a getter that names no password are no hit with every quote", () => {
  for (const q of ['"', ...QUOTES]) {
    for (const [form, [text, line]] of Object.entries(binaryForms(q, "getTotal"))) {
      assert.deepEqual(routed(BINARY_PATH, asNew(text)), { hits: [], humanOnly: false }, `${q}, ${form}, whole`);
      assert.deepEqual(routed(BINARY_PATH, onlyLine(text, line)), { hits: [], humanOnly: false }, `${q}, ${form}, line ${line} alone`);
    }
  }
});
