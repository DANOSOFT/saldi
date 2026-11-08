// tools/guardrails/tests/station.test.mjs - the door map (lib/doors.mjs) and `check.mjs --station` on real PRs.
//
// The routing is pinned on the past PRs its rules were written from: #980 527579cc, #886 bad3e197, #913 40fff95c,
// #912 eaaca4b7, #832 8e09393b, #917 ff80f1b5, #935 d8a2bc70 and #910 e1a3e534, and the schema data step (#755
// 2fdbb9a7) and structure (#611 4602fb01) alone. The password and destructive markers are read per language (PHP by
// PHP 8.2's own tokens) on spellings built at run time, through markerHits, stationReport and check.mjs --station in
// a scratch repository, and on every line of the base 5419eee2. A plain password reference needs no person; where
// the reader is unsure the line counts (default deny), and every reading stays linear (linear-time.mjs).
//
// History:
// 20261103 CL/LH Created.
// 20261104 CL/LH Password values read by a tokenizer per language, over the change's lines.
// 20261106 CL/LH PHP files read by PHP's own tokens; lines outside the plain subset count.
// 20261107 CL/LH Unchanged lines below a run are read too; a value open where the lines stop counts.
import test from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { chmodSync, cpSync, mkdirSync, mkdtempSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from "node:fs";
import { randomBytes } from "node:crypto";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { createHash } from "node:crypto";
// PHP files are read by PHP's own tokens (lib/php.mjs gives doors.mjs its tokenizer).
import "../lib/php.mjs";
import { canonicalPath, expand, firstMatch, markerHits, rowPattern, stationReport, validateDoorMap } from "../lib/station.mjs";
import { VENDORED, addedWithContext } from "../lib/changeset.mjs";
import { CARE_ROWS, ONE_WAY_ROWS, TIERS } from "../lib/doors.mjs";
import { linear } from "./linear-time.mjs";

/**
 * A one-line PHP fixture as PHP's code. A PHP file is read by PHP's own tokens, and PHP reads a file as HTML up
 * to its first "<?php": a fixture on line 1 that is meant as PHP's code opens it, on the same line. Other files' lines
 * are as written.
 */
const asCode = (path, text) => (/\.(?:php|inc)$/.test(path) && !/<\?(?:php|=)/i.test(text) ? `<?php ${text}` : text);
const ROOT = fileURLToPath(new URL("../../../", import.meta.url));
const CHECK = fileURLToPath(new URL("../check.mjs", import.meta.url));

function station(args, { input, script = CHECK } = {}) {
  const result = spawnSync(process.execPath, [script, "--station", "--json", ...args], { cwd: ROOT, input, encoding: "utf8" });
  return { status: result.status, stderr: result.stderr, stdout: result.stdout, report: result.stdout ? JSON.parse(result.stdout) : null };
}
const commit = (sha) => station(["--base", `${sha}^`, "--head", sha]);
const paths = (...list) => station(["--base", "HEAD", "--paths", "-"], { input: `${list.join("\n")}\n` }).report;
const row = (id) => [...ONE_WAY_ROWS, ...CARE_ROWS].find((r) => r.id === id);
/** A quoted TRUNCATE in each case, alone or joined from pieces, in PHP and JS. */
const QUOTED_TRUNCATE = ["truncate", "Truncate", "tRuNcAtE", "TRUNCATE"].flatMap((keyword) => [
  `db_modify('${keyword}' . ' ' . $table);`,
  `db_modify("${keyword}" . " " . $table);`,
  `$sql = "${keyword}";`,
  `$sql = '${keyword} table' . $table;`,
  `$sql = $prefix . '${keyword}';`,
  `const sql = '${keyword}' + ' ' + table;`,
  `$sql = $all ? '${keyword}' : 'delete from';`,
]);
const sources = (report) => report.minimumProof.map((proof) => proof.source.row ?? proof.source.station + (proof.source.party ? ` ${proof.source.party}` : ""));

test("the shipped door map is valid, with a reviewer entry for each approver", () => {
  assert.deepEqual(validateDoorMap(), []);
});

test("brace expansion and fnmatch globs, as in the reference classifier", () => {
  assert.deepEqual(expand("{includes/,}docsIncludes/{deleteDoc,poolArchive}.php"), [
    "includes/docsIncludes/deleteDoc.php",
    "includes/docsIncludes/poolArchive.php",
    "docsIncludes/deleteDoc.php",
    "docsIncludes/poolArchive.php",
  ]);
  assert.equal(firstMatch(["systemdata/*/createAccountPrimo.php"], "systemdata/a/b/createAccountPrimo.php"), "systemdata/*/createAccountPrimo.php");
  assert.equal(firstMatch(["debitor/payments/"], "debitor/payments/x/y.php"), "debitor/payments/");
  assert.equal(firstMatch(["debitor/payments/"], "debitor/payments.php"), null);
  assert.equal(firstMatch(["finans/bogfor.php"], "finans/bogfor.phpx"), null);
  assert.equal(rowPattern(row("kassekladde"), "finans/kassekladde_includes/autoSettlement.php"), null, "autoSettlement.php is a posting path");
  assert.equal(rowPattern(row("posting"), "finans/kassekladde_includes/autoSettlement.php"), "finans/kassekladde_includes/autoSettlement.php");
  assert.equal(rowPattern(row("ci"), ".github/workflows/deploy-example.yml"), null, "deploy workflows are the deploy row");
});

test("markers: added code lines only, comments, tests and tools skipped, deploy only under .github/, schema only on its paths", () => {
  const lines = (...texts) => texts.map((text, i) => ({ line: i + 1, text }));
  assert.deepEqual(markerHits("finans/x.php", lines('$q = "insert into transaktioner (a) values (1)";')), [
    { row: "posting", marker: "insert into transaktioner", line: 1 },
  ]);
  assert.deepEqual(markerHits("finans/x.php", lines("<?php", "// TRUNCATE is never used here", "# delete from ordrer")), []);
  assert.deepEqual(markerHits("tests/x.php", lines('db_modify("delete from ordrer")')), []);
  assert.deepEqual(markerHits("tools/guardrails/lib/doors.mjs", lines('{ marker: "TRUNCATE" }')), []);
  assert.deepEqual(markerHits("doc/x.md", lines("delete from ordrer")), []);
  assert.deepEqual(markerHits("vendor/x.php", lines("delete from ordrer"), (path) => path.startsWith("vendor/")), []);
  assert.deepEqual(markerHits("includes/x.php", lines("on: pull_request_target")), []);
  assert.deepEqual(markerHits(".github/workflows/x.yml", lines("on: pull_request_target", "      token: ${{ secrets.TOKEN }}", "# public, so no secrets.")), [
    { row: "deploy", marker: "pull_request_target", line: 1 },
    { row: "deploy", marker: "secrets.", line: 2 },
  ]);
  assert.deepEqual(markerHits("includes/betweenUpdates.php", lines("db_modify(\"update settings set var_value='1'\");")), [
    { row: "schema", marker: "update <table> set", line: 1 },
  ]);
  assert.deepEqual(markerHits("debitor/ordre.php", lines("db_modify(\"update settings set var_value='1'\");")), []);
  assert.deepEqual(markerHits("finans/x.js", lines("const short = truncate(text, 20);")), [], "a JS truncate() is not SQL");
});

test("TRUNCATE in any case, with or without TABLE, quoted or joined, makes an unlisted file destructive; truncate() and its kin do not", () => {
  const sql = [
    "TRUNCATE ordrer",
    "truncate ordrer, ordrelinjer RESTART IDENTITY",
    "Truncate Table varer",
    "truncate table `$table`",
    'db_modify("TrUnCaTe " . $table);',
    "$statements[] = 'truncate ' . implode(',', $tables);",
    "truncate only openpost",
    '$sql = "TRUNCATE";',
    // A quoted keyword is SQL in any case, also when the statement is joined from pieces.
    ...QUOTED_TRUNCATE,
  ];
  for (const text of sql) {
    assert.deepEqual(markerHits("finans/cleanup.php", [{ line: 7, text }]), [{ row: "destructive", marker: "TRUNCATE", line: 7 }], text);
    const report = stationReport({ input: { mode: "diff" }, paths: ["finans/cleanup.php"], lines: new Map([["finans/cleanup.php", [{ line: 7, text }]]]) });
    assert.deepEqual([report.rule, report.party, report.doorRows.map((match) => match.row)], [1, "1d", ["destructive"]], text);
    assert.deepEqual(report.markersHit, [{ kind: "marker", row: "destructive", path: "finans/cleanup.php", line: 7, marker: "TRUNCATE" }], text);
  }
  const notSql = [
    "const short = truncate(text, 20);",
    "$truncate = true;",
    "$vat = truncate_number($price);",
    'import { truncate } from "./text.js";',
    "TRUNCATE($text, 20);",
    'i({".truncate":{overflow:"hidden"}})',
    "// TRUNCATE ordrer when the year is closed",
    // A class name, a lone argument, list element or key, and a compared value are not SQL.
    '<div class="truncate">',
    '<span class="truncate text-sm">',
    '<td class="cell truncate table-cell">',
    "el.classList.add('truncate');",
    "$('#total').addClass('truncate');",
    '{ className: "truncate" }',
    "$width = $options['truncate'];",
    "$defaults = ['truncate' => true];",
    "if (in_array($mode, ['truncate', 'wrap'])) {",
    "case 'truncate':",
    "if ($mode == 'Truncate') {",
  ];
  for (const text of notSql) assert.deepEqual(markerHits("javascript/cleanup.js", [{ line: 1, text }]), [], text);
});

test("secrets: a password given a quoted literal counts in any file, comment or fixture and is human-only; a non-literal does not", () => {
  // Built at run time, so this file holds no literal for the marker to find.
  const name = ["pass", "word"].join("");
  const NAME = name.toUpperCase();
  const value = randomBytes(6).toString("hex");
  const literal = [
    `$${name} = '${value}';`,
    `$db_${name}="${value}";`,
    `DB_${NAME}='${value}'`,
    `$mail->P${name.slice(1)} = "${value}";`,
    `#\t$${name} = '${value}'; // the old SMTP login`,
    `const pass${name.slice(4)} = "${value}";`,
    // A passphrase with spaces, in either quote.
    `$${name} = 'correct ${value} horse';`,
    `$${name} = "correct ${value} horse";`,
    // Single quotes do not interpolate: a leading $, % or { is part of the password.
    `$${name} = '$${value}';`,
    `$${name} = '%${value}';`,
    `$${name} = '{${value}}';`,
    `$${name} = '$${value} ${value}';`,
    // Double quotes: a leading % or a { that opens no {$...} is literal, and so is a character outside $name.
    `$${name} = "%${value}";`,
    `$${name} = "{${value}}";`,
    `$${name} = "x$${value}";`,
    // A literal quoted inside a connection string or a shell command.
    `$dsn = "host=localhost user=saldi ${name}='${value}'";`,
    `$cmd = 'mysql --${name}="${value}" saldi';`,
    // Prose around it: an apostrophe between letters is no quote.
    `// don't keep $${name} = '${value} x' in the file`,
    // A schema type is none only as a field its own declaration names a schema ($cols = [...]); alone it is a literal.
    `'${name}' => 'varchar(255)';`,
    // The space between two references is literal padding.
    `$${name} = "$a $b";`,
  ];
  const places = ["includes/newFeature.php", "debitor/mailer.php", "tests/fixtures/settings.php", "e2e/login.spec.mjs", "doc/setup.md"];
  for (const [i, text] of literal.entries()) {
    for (const [path, line] of [
      [places[i % places.length], text],
      ["includes/newFeature.php", `// ${text}`],
      ["includes/newFeature.php", `#\t${text}`],
      ["tests/fixtures/settings.php", text],
    ]) {
      assert.deepEqual(markerHits(path, [{ line: 4, text: line }]), [{ row: "secrets", marker: "password literal", line: 4 }], `${path}: ${line}`);
      const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, [{ line: 4, text: line }]]]) });
      assert.deepEqual([report.rule, report.party, report.humanOnly], [1, "1d", true], `${path}: ${line}`);
    }
  }
  const notLiteral = [
    `$${name} = '';`,
    `$${name} = '   ';`,
    `$${name} = "";`,
    `$${name} = $_POST['${name}'];`,
    `if ($${name} == '${value}') {`,
    `$cols = ['${name}' => 'varchar(255)'];`,
    `$${name}_label = 'Adgangskode';`,
    `"host='%s' user='%s' ${name}='%s'"`,
    `$${name} = "{$row['${name}']}";`,
    // Double quotes interpolate: nothing outside $name, $name[...], $name->x, {$...}, ${...}, $(...) or $1.
    `$${name} = "$pw";`,
    `$${name} = "$row[${name}]";`,
    `$${name} = "$this->${name}";`,
    `$${name} = "$a$b";`,
    `export DB_${NAME}="$(cat /run/secrets/db)"`,
    `DB_${NAME}="$1"`,
    // Quoted inside a double-quoted string, the inner quotes interpolate too
    // (admin/vis_regnskaber.php, mysale/editProfile.php and rental/rental.php at 5419eee2).
    `fwrite($fp,"export PG${NAME}='$sqpass'\\n");`,
    `$qtxt = "update adresser set ${name} = '$encPw' where id = '$accountId'";`,
    `$query = db_modify("UPDATE rentalmail SET username = '$data[username]', ${name} = '$data[${name}]' WHERE id = 1");`,
    // Inside a single-quoted string, a double-quoted value is the shell's to interpolate.
    `'PG${NAME}="$POSTGRES_${NAME}" exec psql -X'`,
    // The quote after the name closes the string it stands in: the value is joined in.
    `$dsn = "host=$host user=$user ${name}=" . $pw . " port=5432";`,
    `$dsn = 'host=localhost ${name}=' . $pw . ' port=5432';`,
    `#\t$qtxt.= "${name} = '".db_escape_string(trim($_POST['pw1']))   ."' ";`,
    // Placeholders: a printf conversion, a template or CI expression, angle brackets.
    `$${name} = "%s";`,
    `$${name} = '%1$s';`,
    `$db_${name} = '{{ db_${name} }}';`,
    `PG${NAME}='\${{ secrets.PG_${NAME} }}' psql -c 'select 1'`,
    `DB_${NAME}="\${DB_${NAME}:-}"`,
    `$${name} = '<your ${name}>';`,
  ];
  for (const text of notLiteral) assert.deepEqual(markerHits("includes/newFeature.php", [{ line: 1, text: asCode("includes/newFeature.php", text) }]), [], text);
});

test("secrets: in JS, TS and Python both ordinary quotes are literal, so a leading $ is part of the password; PHP, shell and YAML still interpolate", () => {
  // Built at run time, so this file holds no literal for the marker to find.
  const name = ["pass", "word"].join("");
  // It starts with a letter, so "$v..." is a variable where quotes interpolate ("$1abc" would be a literal).
  const value = `v${randomBytes(6).toString("hex")}`;
  const literal = [
    `const ${name} = "$${value}";`,
    `const ${name} = '$${value}';`,
    `let db${name.replace("p", "P")} = "$${value} ${value}";`,
    `config.${name} = "\${${value}";`,
    `const ${name} = \`$${value}\`;`,
    `const ${name} = \`\${prefix}${value}\`;`,
    `const ${name} = "{$${value}}";`,
    `${name} = "$${value}"`,
  ];
  const files = ["javascript/login.js", "tools/seed.mjs", "e2e/login.spec.mjs", "api/client.ts", "tools/ci/seed.py"];
  for (const text of literal) {
    for (const path of files) {
      assert.deepEqual(markerHits(path, [{ line: 2, text }]), [{ row: "secrets", marker: "password literal", line: 2 }], `${path}: ${text}`);
      const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, [{ line: 2, text }]]]) });
      assert.deepEqual([report.rule, report.party, report.humanOnly], [1, "1d", true], `${path}: ${text}`);
    }
  }
  // Where double quotes interpolate, the same value is a variable.
  for (const path of ["includes/newFeature.php", "tools/deploy.sh", ".github/workflows/tests.yml", "doc/setup.md"]) {
    assert.deepEqual(markerHits(path, [{ line: 2, text: `$${name} = "$${value}";` }]), [], path);
  }
  const notLiteral = [
    `const ${name} = "";`,
    `const ${name} = process.env.DB_${name.toUpperCase()};`,
    `const ${name} = "%s";`,
    `if (${name} === "$${value}") {`,
    `const opts = { ${name}: pw };`,
  ];
  for (const text of notLiteral) {
    for (const path of files) assert.deepEqual(markerHits(path, [{ line: 1, text: asCode(path, text) }]), [], `${path}: ${text}`);
  }
  // A template is JavaScript's; Python has no backtick string, so there the reader cannot read it and it counts.
  for (const text of [`const ${name} = \`\${pw}\`;`, `const ${name} = \`\${a}\${b}\`;`]) {
    for (const path of files) {
      const expected = path.endsWith(".py") ? [{ row: "secrets", marker: "password literal", line: 1 }] : [];
      assert.deepEqual(markerHits(path, [{ line: 1, text: asCode(path, text) }]), expected, `${path}: ${text}`);
    }
  }
});

test("secrets: in JS, TS and Python a single-quoted password inside a double-quoted string is literal and human-only; PHP still interpolates it", () => {
  // Built at run time, so this file holds no literal for the marker to find. These are connection
  // strings with a single-quoted $-value inside a JS, TS or Python string.
  const name = ["pass", "word"].join("");
  const NAME = name.toUpperCase();
  // It starts with a letter, so "$v..." is a variable where quotes interpolate.
  const value = `v${randomBytes(6).toString("hex")}`;
  const literal = [
    `const dsn = "${name}='$${value}'";`,
    `const dsn = "host=localhost user=saldi ${name}='$${value}' port=5432";`,
    `let conn = "${name}='{$${value}}' dbname=saldi";`,
    `const dsn = "${name}='$(${value})'";`,
    `const dsn = "${name}='$1${value}'";`,
    `dsn = "host=localhost ${name}='$${value}'"`,
    // A Python f-string interpolates {...} only, so a $ or a letter outside the braces is literal.
    `dsn = f"host=localhost ${name}='$${value}'"`,
    `dsn = f"${name}='x{${value}}'"`,
    `${name} = f"x{${value}}"`,
  ];
  const files = ["javascript/db.js", "tools/seed.mjs", "api/client.ts", "tools/ci/seed.py"];
  for (const text of literal) {
    for (const path of files) {
      assert.deepEqual(markerHits(path, [{ line: 3, text }]), [{ row: "secrets", marker: "password literal", line: 3 }], `${path}: ${text}`);
      const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, [{ line: 3, text }]]]) });
      assert.deepEqual([report.rule, report.party, report.humanOnly], [1, "1d", true], `${path}: ${text}`);
    }
  }
  // In PHP and shell the outer double quotes interpolate the same value: it is a variable there.
  for (const path of ["includes/newFeature.php", "tools/deploy.sh"]) {
    assert.deepEqual(markerHits(path, [{ line: 3, text: `$dsn = "host=localhost ${name}='$${value}'";` }]), [], path);
  }
  const notLiteral = [
    // A shell command in a single-quoted string: the shell interpolates the double-quoted value
    // (tests/assist/run_records_integration.py:68 at 5419eee2).
    `'PG${NAME}="$POSTGRES_${NAME}" exec psql -X -At -d "$1"', "sh"`,
    `const cmd = 'mysql --${name}="$DB_${NAME}" saldi';`,
    // A value joined in, a placeholder, a selector.
    `const dsn = "host=localhost ${name}='" + ${value} + "'";`,
    `const dsn = "${name}='%s'";`,
    `await page.fill("input[name='${name}']", ${value});`,
  ];
  for (const text of notLiteral) {
    for (const path of files) assert.deepEqual(markerHits(path, [{ line: 1, text: asCode(path, text) }]), [], `${path}: ${text}`);
  }
  // A Python f-string that only interpolates, and a JavaScript template that does: each in its own language. In
  // the other, f"..." is a word beside a string and a backtick no string, which the reader cannot read: they count.
  const own = [
    ["tools/ci/seed.py", `dsn = f"host=localhost ${name}='{${value}}'"`],
    ["tools/ci/seed.py", `${name} = f"{${value}}"`],
    ["javascript/db.js", `const dsn = \`${name}='\${${value}}'\`;`],
  ];
  for (const [home, text] of own) {
    for (const path of files) {
      const expected = path.endsWith(".py") === home.endsWith(".py") ? [] : [{ row: "secrets", marker: "password literal", line: 1 }];
      assert.deepEqual(markerHits(path, [{ line: 1, text: asCode(path, text) }]), expected, `${path}: ${text}`);
    }
  }
});

test("secrets: a double-quoted password inside a PHP, JS, TS or Python single-quoted string, or a quoted one in a JS template, is literal and human-only; a variable in capitals is a reference in any string", () => {
  // Built at run time, so this file holds no literal for the marker to find. A lower-case $-value in
  // double quotes inside single quotes or a template is literal: only a shell would interpolate it there,
  // and the classifier must not assume one.
  const name = ["pass", "word"].join("");
  const NAME = name.toUpperCase();
  // It starts with a letter, so "$v..." is a variable where quotes interpolate.
  const value = `v${randomBytes(6).toString("hex")}`;
  const q = "'";
  const everywhere = [
    `const dsn = ${q}${name}="$${value}"${q};`,
    `const dsn = ${q}host=localhost user=saldi ${name}="$${value}" port=5432${q};`,
    `let conn = ${q}${name}="{$${value}}" dbname=saldi${q};`,
    `const dsn = ${q}${name}="$(${value})"${q};`,
    `dsn = ${q}host=localhost ${name}="$${value}"${q}`,
    // SQL is no shell, and "SET password" does not open the string as an environment assignment does.
    `cur.execute(${q}UPDATE users SET ${name}="$${value}" WHERE id = 1${q})`,
  ];
  const templates = [
    `const dsn = \`${name}="$${value}"\`;`,
    `const dsn = \`host=localhost user=saldi ${name}="$${value}" port=5432\`;`,
    `const dsn: string = \`${name}='$${value}'\`;`,
    `const dsn = \`${name}="x\${${value}}"\`;`,
  ];
  const fstrings = [`dsn = f${q}host=localhost ${name}="$${value}"${q}`, `dsn = f${q}${name}="x{${value}}"${q}`];
  const cases = [
    ...["javascript/db.js", "tools/seed.mjs", "api/client.ts", "tools/ci/seed.py", "includes/newFeature.php"].flatMap((path) => everywhere.map((text) => [path, text])),
    ...["javascript/db.js", "tools/seed.mjs", "api/client.ts"].flatMap((path) => templates.map((text) => [path, text])),
    ...fstrings.map((text) => ["tools/ci/seed.py", text]),
  ];
  for (const [path, text] of cases) {
    assert.deepEqual(markerHits(path, [{ line: 5, text }]), [{ row: "secrets", marker: "password literal", line: 5 }], `${path}: ${text}`);
    const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, [{ line: 5, text }]]]) });
    assert.deepEqual([report.rule, report.party, report.humanOnly], [1, "1d", true], `${path}: ${text}`);
  }
  // An environment variable in capitals is a reference in any string, a shell's command or not.
  const shell = [
    `${q}PG${NAME}="$POSTGRES_${NAME}" exec psql -X -At -d "$1"${q}, "sh"`,
    `const cmd = ${q}mysql --${name}="$DB_${NAME}" saldi${q};`,
    `const cmd = ${q}mysqldump --db-${name}="$DB_${NAME}" saldi${q};`,
    `const cmd = ${q}LANG=C PG${NAME}="$PG_PW" pg_dump saldi${q};`,
    `execSync(${q}PG${NAME}="$PG_PW" pg_dump saldi${q});`,
    `subprocess.run(${q}${name}="$PW" ./deploy.sh${q}, shell=True)`,
    `os.system(${q}PG${NAME}="$PG_PW" psql -c "select 1"${q})`,
  ];
  for (const text of shell) {
    for (const path of ["javascript/db.js", "api/client.ts", "tools/ci/seed.py", "includes/newFeature.php"]) {
      assert.deepEqual(markerHits(path, [{ line: 1, text: asCode(path, text) }]), [], `${path}: ${text}`);
    }
  }
  for (const text of [`execSync(\`PG${NAME}="$PG_PW" psql\`);`, `await $\`PG${NAME}="$PG_PW" psql\`;`, `spawn("sh", ["-c", \`PG${NAME}="$PG_PW" psql\`]);`]) {
    assert.deepEqual(markerHits("javascript/db.js", [{ line: 1, text }]), [], text);
  }
  for (const text of [`exec(${q}PG${NAME}="$PW" psql${q});`, `$out = shell_exec(${q}PG${NAME}="$PW" pg_dump saldi${q});`]) {
    assert.deepEqual(markerHits("includes/newFeature.php", [{ line: 1, text: asCode("includes/newFeature.php", text) }]), [], text);
  }
  // A template or a value joined in that only interpolates, and a string that closes at the value.
  for (const text of [`const dsn = \`${name}="\${${value}}"\`;`, `const dsn = \`${name}="\${a}\${b}"\`;`, `const dsn = ${q}${name}="${q} + ${value} + ${q}"${q};`]) {
    for (const path of ["javascript/db.js", "api/client.ts"]) assert.deepEqual(markerHits(path, [{ line: 1, text: asCode(path, text) }]), [], `${path}: ${text}`);
  }
  assert.deepEqual(markerHits("includes/newFeature.php", [{ line: 1, text: asCode("includes/newFeature.php", `$dsn = ${q}${name}="${q} . $pw . ${q}"${q};`) }]), []);
  // In shell and YAML too.
  for (const path of ["tools/deploy.sh", ".github/workflows/tests.yml"]) {
    assert.deepEqual(markerHits(path, [{ line: 1, text: `DSN=${q}host=x ${name}="$PW"${q}` }]), [], path);
  }
});

test("secrets: no shell is read, so an unrelated call, a comment naming one or spawn() without a shell leaves a nested password literal and human-only, and a string handed to a shell is read the same way", () => {
  // Built at run time, so this file holds no literal for the marker to find. No shell-command exemption
  // covers a whole line: a DSN beside an unrelated execSync(), behind a comment naming execSync( or handed
  // to spawn() (no shell by default) is a literal and human-only.
  const name = ["pass", "word"].join("");
  // It starts with a letter, so "$v..." is a variable where quotes interpolate.
  const value = `v${randomBytes(6).toString("hex")}`;
  const q = "'";
  const dsn = `${q}${name}="$${value}"${q}`;
  const hostDsn = `${q}host=localhost ${name}="$${value}"${q}`;
  const js = [
    `const dsn = ${dsn}; execSync("ls");`,
    `const dsn = ${hostDsn}; exec("ls", done);`,
    `const dsn = ${dsn}; // like execSync(`,
    `const dsn = ${dsn}; /* spawn("sh", ["-c", cmd]) */`,
    `const dsn = \`${name}="$${value}"\`; execSync("ls");`,
    `spawn("psql", [${dsn}]);`,
    `const child = spawn(${q}psql${q}, [${hostDsn}]);`,
    `spawnSync(${q}psql${q}, [${dsn}], { stdio: "inherit" });`,
    `execFile("psql", [${dsn}]);`,
    `await execa("psql", [${dsn}]);`,
    // A shell's marks elsewhere on the line: another string's shape, an option, "sh", shell: true.
    `const a = ${q}LANG=C ls${q}; const dsn = ${dsn};`,
    `const dsn = ${dsn}; const opt = "--${name}=";`,
    `const sh = "sh"; const dsn = ${dsn};`,
    `const dsn = ${dsn}; run({ shell: true });`,
    `spawn("psql", [${dsn}]); spawn(cmd, { shell: true });`,
    // An exec that runs SQL or a regular expression is no shell.
    `db.exec(${q}UPDATE users SET ${name}="$${value}" WHERE id = 1${q});`,
  ];
  const py = [
    `dsn = ${dsn}; subprocess.run(["ls"])`,
    `dsn = ${dsn}  # see os.system(`,
    `subprocess.run(["psql", ${dsn}])`,
    `subprocess.Popen(${hostDsn})`,
    `subprocess.check_output(["psql", ${dsn}], text=True)`,
    `dsn = ${dsn}; subprocess.run(cmd, shell=True)`,
    `dsn = f${dsn}; os.system("ls")`,
    `exec(${q}${name}="$${value}"${q})`,
  ];
  const php = [
    `$dsn = ${dsn}; exec("ls");`,
    `$dsn = ${hostDsn}; // shell_exec(`,
    `$pdo->exec(${q}UPDATE users SET ${name}="$${value}" WHERE id = 1${q});`,
    `Db::exec(${dsn});`,
  ];
  const cases = [
    ...["javascript/db.js", "tools/seed.mjs", "api/client.ts"].flatMap((path) => js.map((text) => [path, text])),
    ...py.map((text) => ["tools/ci/seed.py", text]),
    ...php.map((text) => ["includes/newFeature.php", text]),
  ];
  for (const [path, text] of cases) {
    assert.deepEqual(markerHits(path, [{ line: 6, text }]), [{ row: "secrets", marker: "password literal", line: 6 }], `${path}: ${text}`);
    const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, [{ line: 6, text }]]]) });
    assert.deepEqual([report.rule, report.party, report.humanOnly], [1, "1d", true], `${path}: ${text}`);
  }
  // The string a shell is handed, as its first argument, after a shell's -c or as a $`...` template:
  // $PW in capitals is a reference there as in any string, so none is a hit.
  const shell = {
    "javascript/db.js": [
      `execSync(${q}echo ${name}="$PW" >> .env${q});`,
      `exec(${q}echo ${name}="$PW"${q}, done);`,
      `child_process.exec(${q}echo ${name}="$PW"${q});`,
      `spawn(${q}echo ${name}="$PW"${q}, { shell: true });`,
      `spawnSync("bash", ["-c", ${q}echo ${name}="$PW"${q}]);`,
      `execFileSync("/bin/sh", ["-c", \`echo ${name}="$PW"\`]);`,
      `await $\`echo ${name}="$PW"\`;`,
    ],
    "tools/ci/seed.py": [
      `os.system(${q}echo ${name}="$PW"${q})`,
      `subprocess.run(${q}echo ${name}="$PW"${q}, shell=True)`,
      `subprocess.check_output(${q}echo ${name}="$PW"${q}, shell=True, text=True)`,
      `subprocess.run(["bash", "-c", ${q}echo ${name}="$PW"${q}])`,
      `subprocess.getoutput(${q}echo ${name}="$PW"${q})`,
      `os.system(f${q}{tool} ${name}="$PW" > {path}${q})`,
    ],
    "includes/newFeature.php": [
      `exec(${q}echo ${name}="$PW"${q});`,
      `$out = shell_exec(${q}echo ${name}="$PW"${q});`,
      `system(${q}echo ${name}="$PW"${q});`,
      `$p = proc_open(["bash", "-c", ${q}echo ${name}="$PW"${q}], $spec, $pipes);`,
    ],
  };
  for (const [path, lines] of Object.entries(shell)) {
    for (const text of lines) assert.deepEqual(markerHits(path, [{ line: 1, text: asCode(path, text) }]), [], `${path}: ${text}`);
  }
});

/**
 * Call shapes that could mislead a reading of whether a shell runs a string, by file, with `nested` the
 * string the call is handed. A shell option in code, a comment, a string, a nested object, a regular expression,
 * a spread or an ignored argument: none of it may change what the value is.
 */
function callShapes(nested, name) {
  const q = "'";
  const qqq = '"'.repeat(3);
  const js = [
    `spawn(${nested}, { shell: false /* shell: true */ });`,
    `spawn(${nested}, { shell: false, note: "shell: true" });`,
    `spawnSync(${nested}, { note: ${q}shell: true${q}, shell: false });`,
    `execFile(${nested}, { shell: false, why: \`shell: true is off\` });`,
    `spawn(${nested}, { shell: false }); // shell: true`,
    `spawn(${nested}, { shell: process.env.SHELL_TRUE });`,
    `run(${nested}, { shell: true });`,
    `myspawn(${nested}, { shell: true });`,
    `spawn(${nested}, { shell: true });`,
    `spawn(${nested}, { /* the one place */ shell: true });`,
    `child_process.spawnSync(${nested}, { stdio: "inherit", "shell": true });`,
    `execFileSync(${nested}, { note: "shell: false", shell: true });`,
    `await execa(${nested}, { shell: true });`,
    `spawn(${nested}, { shell: false, env: { shell: true } });`,
    `spawn(${nested}, { shell: false, re: /shell: true/ });`,
    `spawn(${nested}, [/shell: true/], { stdio: "pipe" });`,
    `spawn(${nested}, { shell: true, ...opts });`,
    `spawn(${nested}, wrap({ shell: true }));`,
    `spawn(${nested}, { shell: true ? false : true });`,
    `spawn(${nested}, opts || { shell: true });`,
    `spawn(${nested}, { shell: true, ['shell']: false });`,
    `spawn(${nested}, { shell: true, get shell() { return false; } });`,
    `spawn(${nested}, [], {}, { shell: true });`,
    `spawn(${nested}, args, { shell: true });`,
    `spawn(${nested}, { shell: true,`,
    `spawn(${nested}, { shell: true }, { shell: false });`,
    `execFile(${nested}, [], { shell: true }, (error) => {});`,
    `execaCommand(${nested}, { shell: true });`,
    `execSync(${nested});`,
    `exec(${nested}, done);`,
    `child_process.exec(${nested});`,
    `spawnSync("bash", ["-c", ${nested}]);`,
    `// execSync(${nested});`,
    `/* spawn(${nested}, { shell: true }) */`,
    `const cmd = ${nested}; execSync(cmd);`,
  ];
  return {
    "javascript/db.js": js,
    "api/client.ts": [...js, `spawn(${nested}, { shell: true as const });`],
    "tools/ci/seed.py": [
      `subprocess.run(${nested}, shell=False, env={"NOTE": "shell=True"})`,
      `subprocess.Popen(${nested}, shell=False, text=True)  # shell=True`,
      `helper.run(${nested}, shell=True)`,
      `subprocess.run(${nested}, text=True, shell=True)`,
      `subprocess.run(${nested}, shell=False, env=${qqq}x" shell=True "y${qqq})`,
      `subprocess.run(${nested}, env=dict(shell=True))`,
      `subprocess.Popen(${nested}, **{"shell": True})`,
      `subprocess.run(${nested}, *argv, shell=True)`,
      `subprocess.run(${nested}, shell=True`,
      `os.system(${nested})`,
      `subprocess.getoutput(${nested})`,
      `subprocess.run(["bash", "-c", ${nested}])`,
      `# os.system(${nested})`,
      `s = ${qqq}os.system(${nested})${qqq}`,
    ],
    "includes/newFeature.php": [
      `exec(${nested});`,
      `$out = shell_exec(${nested});`,
      `system(${nested});`,
      `$p = proc_open(["bash", "-c", ${nested}], $spec, $pipes);`,
      `// exec(${nested});`,
      `# shell_exec(${nested});`,
      `$pdo->exec(${nested});`,
    ],
    "tools/deploy.sh": [`ssh host ${nested}`, `bash -c ${nested}`, `# ssh host ${nested}`, `${name}_CMD=${nested}`],
  };
}

test("secrets: who runs a string never decides: in every call, option, comment and string around it, a nested value with a literal is a hit and a variable in capitals is not", () => {
  // Built at run time, so this file holds no literal for the marker to find. Whether a shell runs the
  // string (its call, its shell option, a -c) is not read: the value alone decides, so the same shapes
  // give the same answer whatever the call or its options say.
  const name = ["pass", "word"].join("");
  const q = "'";
  // It starts with a letter, so "$v..." would be a variable where quotes interpolate.
  const value = `v${randomBytes(6).toString("hex")}`;
  for (const [nested, hits] of [
    [`${q}echo ${name}="$PW"${q}`, false],
    [`${q}PG${name.toUpperCase()}="$PG_PW" psql${q}`, false],
    [`${q}echo ${name}="$${value}"${q}`, true],
    [`${q}echo ${name}="x$PW"${q}`, true],
    [`${q}PG${name.toUpperCase()}=${value} psql${q}`, true],
  ]) {
    for (const [path, lines] of Object.entries(callShapes(nested, name.toUpperCase()))) {
      for (const text of lines) {
        const expected = hits ? [{ row: "secrets", marker: "password literal", line: 7 }] : [];
        assert.deepEqual(markerHits(path, [{ line: 7, text }]), expected, `${path}: ${text}`);
        const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, [{ line: 7, text }]]]) });
        assert.equal(report.humanOnly, hits, `${path}: ${text}`);
      }
    }
  }
});

test("secrets: a password value with a literal character counts wherever it stands, in JS, TS, Python, PHP and shell: code, comments, strings, calls, a call in a comment and a Python triple-quoted string", () => {
  // Built at run time, so this file holds no literal for the marker to find. The first JS and Python
  // cases trap a regular-expression reading: execSync( in a JS comment runs no shell, and Python's """
  // is one triple quote, not "" and ".
  const name = ["pass", "word"].join("");
  const NAME = name.toUpperCase();
  const q = "'";
  const qqq = '"'.repeat(3);
  const value = `v${randomBytes(6).toString("hex")}`;
  const cases = {
    "javascript/db.js": [
      `// execSync(${q}echo ${name}="$${value}"${q})`,
      `const ${name} = "${value}";`,
      `const ${name} = "$${value}";`,
      `// const ${name} = ${q}${value}${q};`,
      `/* ${name} = "${value}" */`,
      ` * ${name} = "${value}"`,
      `const dsn = ${q}host=localhost ${name}=${value}${q};`,
      `const dsn = "host=localhost ${name}='$${value}'";`,
      `const dsn = "${name}=\\"${value}\\"";`,
      `const url = \`https://x?user=a&${name}=${value}\`;`,
      `const cmd = \`PG${NAME}=\${pw}${value} psql\`;`,
      `execSync(${q}echo ${name}="$${value}"${q});`,
      `/* execSync(${q}echo ${name}="$${value}"${q}) */`,
      `spawn(${q}PG${NAME}="$${value}" psql${q}, { shell: true });`,
      `const ${name} = process.env.DB_${NAME} ?? "${value}";`,
      `const ${name} = process.env.DB_${NAME} || ${q}${value}${q};`,
      `login(page, { ${name} = "${value}" } = {});`,
    ],
    "api/client.ts": [
      `// execSync(${q}echo ${name}="$${value}"${q})`,
      `const ${name}: string = "${value}";`,
      `let ${name}: string | undefined = ${q}${value}${q};`,
      `const ${name}: string = process.env.X ?? "${value}";`,
      `function connect(${name}: string = "${value}") {}`,
      `const dsn: string = \`${name}="$${value}"\`;`,
    ],
    "tools/ci/seed.py": [
      `s = ${qqq}${name}="${value}" ${qqq}`,
      `s = ${q}${q}${q}${name}="${value}" ${q}${q}${q}`,
      `s = ${qqq}os.system(${q}echo ${name}="$${value}"${q})${qqq}`,
      `${qqq}${name}=${value}`,
      `# os.system(${q}echo ${name}="$${value}"${q})`,
      `# ${name} = "${value}"`,
      `${name} = "$${value}"`,
      `${name}: str = ${q}${value}${q}`,
      `def connect(${name}: str = "${value}"):`,
      `conn = connect(host="db", ${name}="${value}")`,
      `dsn = f"host=localhost ${name}='$${value}'"`,
      `dsn = f${q}${name}="x{${value}}"${q}`,
      `subprocess.run(${q}PG${NAME}="$${value}" psql${q}, shell=True)`,
      `${name} = os.getenv("DB_${NAME}", "${value}")`,
      `${name} = os.environ.get(${q}DB_${NAME}${q}, ${q}${value}${q})`,
      `${name} = os.environ.get("DB_${NAME}") or "${value}"`,
    ],
    "includes/newFeature.php": [
      `$${name} = ${q}${value}${q};`,
      `$${name} = "x$${value}";`,
      `// $${name} = ${q}${value}${q};`,
      `# $mail->P${name.slice(1)} = "${value}";`,
      `/* $${name} = "${value}"; */`,
      `$dsn = "host=$host ${name}=${value}";`,
      `$dsn = ${q}host=localhost ${name}=$${value}${q};`,
      `exec(${q}echo ${name}="$${value}"${q});`,
      `// exec(${q}echo ${name}="$${value}"${q});`,
      `exec("PG${NAME}='${value}' psql");`,
      `$${name} = getenv(${q}DB_${NAME}${q}) ?: ${q}${value}${q};`,
      `$${name} = $_ENV[${q}DB_${NAME}${q}] ?? "${value}";`,
    ],
    "tools/deploy.sh": [
      `PG${NAME}=${q}${value}${q} psql -h db`,
      `export DB_${NAME}="x$PW"`,
      `# PG${NAME}=${q}${value}${q} psql`,
      `ssh host ${q}PG${NAME}="$${value}" psql${q}`,
      `docker run -e "PG${NAME}=${value}" postgres`,
      `mysql --${name}="${value}" saldi`,
      `PG${NAME}="\${PG${NAME}:-${value}}" psql`,
    ],
    ".github/workflows/tests.yml": [`        run: PG${NAME}=${q}${value}${q} psql`, `          FIXTURE_PG: "host=db ${name}=${value}"`],
    "doc/setup.md": [`Run \`PG${NAME}=${value} psql\` once.`, `    ${name} = "${value}"`],
  };
  for (const [path, lines] of Object.entries(cases)) {
    for (const text of lines) {
      assert.deepEqual(markerHits(path, [{ line: 8, text }]), [{ row: "secrets", marker: "password literal", line: 8 }], `${path}: ${text}`);
      const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, [{ line: 8, text }]]]) });
      assert.deepEqual([report.rule, report.party, report.humanOnly], [1, "1d", true], `${path}: ${text}`);
    }
  }
});

test("secrets: a value that is wholly a reference is no hit wherever it stands: $PGPASSWORD, ${PGPASSWORD}, \"$PGPASSWORD\", getenv('X'), os.environ['X'], process.env.X and what the file's language interpolates", () => {
  // Built at run time, so this file holds no literal for the marker to find.
  const name = ["pass", "word"].join("");
  const NAME = name.toUpperCase();
  const q = "'";
  const qqq = '"'.repeat(3);
  const env = `PG${NAME}`;
  const cases = {
    "javascript/db.js": [
      `const ${name} = process.env.${env};`,
      `const ${name} = process.env[${q}${env}${q}];`,
      `// const ${name} = process.env.${env};`,
      `const ${name} = process.env.${env} ?? "";`,
      `const ${name} = process.env.${env} || process.env.DB_${NAME};`,
      `const ${name} = "$${env}";`,
      `const ${name} = "\${${env}}";`,
      `execSync(${q}${env}="$${env}" psql${q});`,
      `// execSync(${q}echo ${name}="$${env}"${q})`,
      `const cmd = \`${env}=\${pw} psql\`;`,
      `const cmd = \`${env}="\${pw}" psql\`;`,
      `const s = ${q}${name}=\${${env}}${q};`,
      `const dsn = "host=localhost ${name}=" + pw + " port=5432";`,
      `login(page, { ${name} = ${NAME} } = {});`,
      // A case label is no type: the literal is another name's.
      `case ${NAME}: label = "Adgangskode"; break;`,
    ],
    "api/client.ts": [
      `const ${name}: string = process.env.${env} ?? "";`,
      `const ${name}: string = config.${name};`,
      // A literal default of the next parameter is not the password's.
      `function connect(${name}: string, user = "admin") {}`,
    ],
    "tools/ci/seed.py": [
      `${name} = os.environ[${q}${env}${q}]`,
      `${name} = os.environ.get(${q}${env}${q})`,
      `${name} = os.getenv("${env}", "")`,
      `${name} = os.getenv("${env}", os.environ["DB_${NAME}"])`,
      `${name}: str = os.environ["${env}"]`,
      `# os.system(${q}echo ${name}="$${env}"${q})`,
      `s = ${qqq}${name}="$${env}" ${qqq}`,
      `dsn = f"host=localhost ${name}='{pw}'"`,
      `dsn = f"host=localhost ${name}={pw}"`,
      `subprocess.run(${q}${env}="$${env}" psql${q}, shell=False)`,
      `dsn = "host=localhost ${name}=%s" % pw`,
      `dsn = "host=localhost ${name}={}".format(pw)`,
    ],
    "includes/newFeature.php": [
      `$${name} = getenv(${q}${env}${q});`,
      `// $${name} = getenv(${q}${env}${q});`,
      `$${name} = $_ENV[${q}${env}${q}] ?? ${q}${q};`,
      `$${name} = "$pw";`,
      `$${name} = "{$cfg[${q}${name}${q}]}";`,
      `exec(${q}${env}="$${env}" psql${q});`,
      `$dsn = "host=$host ${name}=$pw";`,
      `$dsn = "host=$host ${name}='$pw'";`,
      `$dsn = ${q}host=localhost ${name}=\${${env}}${q};`,
      `$dsn = "host=$host ${name}=" . $pw . " port=5432";`,
    ],
    "tools/deploy.sh": [
      `${env}=$${env} psql -h db`,
      `${env}="\${${env}}" psql`,
      `${env}="$${env}"`,
      `export DB_${NAME}="\${DB_${NAME}:-}"`,
      `export DB_${NAME}="\${DB_${NAME}:-$${env}}"`,
      `ssh host ${q}${env}="$${env}" psql${q}`,
      `# ${env}="$${env}"`,
      `docker run -e "${env}=$${env}" postgres`,
      `mysql --${name}="$DB_${NAME}" saldi`,
    ],
    ".github/workflows/tests.yml": [`        run: ${env}="\${{ secrets.${env} }}" psql`, `          FIXTURE_PG: host=db ${name}=\${{ env.${env} }}`],
  };
  for (const [path, lines] of Object.entries(cases)) {
    // The deploy row's "secrets." marker may hit a workflow line; the password marker may not.
    for (const text of lines) assert.deepEqual(markerHits(path, [{ line: 1, text: asCode(path, text) }]).filter((hit) => hit.row === "secrets"), [], `${path}: ${text}`);
  }
});

/**
 * Each case through markerHits and stationReport: [path, text, hits]; the password marker, the secrets
 * row and humanOnly agree.
 */
function assertPasswordCases(cases) {
  for (const [path, text, hits] of cases) {
    const expected = hits ? [{ row: "secrets", marker: "password literal", line: 3 }] : [];
    assert.deepEqual(markerHits(path, [{ line: 3, text }]).filter((hit) => hit.row === "secrets"), expected, `${path}: ${text}`);
    const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, [{ line: 3, text }]]]) });
    assert.equal(report.doorRows.some((match) => match.row === "secrets"), hits, `${path}: ${text}`);
    // A workflow's "secrets." is the deploy row's marker, which is human-only too.
    assert.equal(report.humanOnly, hits || report.doorRows.some((match) => match.row === "deploy"), `${path}: ${text}`);
  }
}

test("secrets: an unquoted value is text in shell, environment, YAML and config files, and after an environment name in capitals in any file's comment", () => {
  // Built at run time, so this file holds no literal for the marker to find. An unquoted value counts
  // as a quoted one does: PGPASSWORD=<literal> psql, in a script or a comment, holds a secret.
  const name = ["pass", "word"].join("");
  const NAME = name.toUpperCase();
  const env = `PG${NAME}`;
  const value = `v${randomBytes(6).toString("hex")}`;
  assertPasswordCases([
    ["tools/deploy.sh", `${env}=${value} psql -h db`, true],
    ["tools/deploy.sh", `# ${env}=${value} psql`, true],
    ["tools/deploy.sh", `export DB_${NAME}=${value}`, true],
    ["tools/deploy.sh", `${name}=${value}`, true],
    [".env.example", `DB_${NAME}=${value}`, true],
    ["deploy/app.env", `DB_${NAME}=${value}`, true],
    ["Dockerfile", `ENV ${env}=${value}`, true],
    ["config/app.ini", `${name} = ${value}`, true],
    [".github/workflows/tests.yml", `        run: ${env}=${value} psql`, true],
    ["includes/newFeature.php", `// ${env}=${value} psql -h db`, true],
    ["includes/newFeature.php", `# export ${env}=${value}`, true],
    ["javascript/db.js", `// ${env}=${value} psql -h db`, true],
    ["tools/ci/seed.py", `# ${env}=${value} psql`, true],
    // Wholly a reference, or empty.
    ["tools/deploy.sh", `${env}=$${env} psql -h db`, false],
    ["tools/deploy.sh", `${env}=\${${env}} psql`, false],
    ["tools/deploy.sh", `export DB_${NAME}=$(cat /run/secrets/db)`, false],
    ["tools/deploy.sh", `export DB_${NAME}=`, false],
    ["tools/deploy.sh", `${env}= psql -h db`, false],
    [".env.example", `DB_${NAME}=`, false],
    [".env.example", `DB_${NAME}=\${DB_${NAME}}`, false],
    // In code an unquoted value is code: a name.
    ["javascript/db.js", `const DB_${NAME}=pw;`, false],
    ["tools/ci/seed.py", `conn = connect(${name}=pw)`, false],
  ]);
});

test("secrets: every part of a value counts, a concatenation, a ternary, a group, a fallback and a number; a call's arguments, a subscript's key and a name do not", () => {
  // Built at run time, so this file holds no literal for the marker to find. A literal counts anywhere in
  // the value, not only right after "=" or after ??, ||, ?: and or: $pw . '<literal>' and its kin hold one.
  const name = ["pass", "word"].join("");
  const NAME = name.toUpperCase();
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  assertPasswordCases([
    ["includes/newFeature.php", `$${name} = $prefix . ${q}${value}${q};`, true],
    ["includes/newFeature.php", `$${name} = ${q}${value}${q} . $suffix;`, true],
    ["includes/newFeature.php", `$${name} = $a ? ${q}${value}${q} : $b;`, true],
    ["includes/newFeature.php", `$${name} = (string) ($x ?? ${q}${value}${q});`, true],
    ["includes/newFeature.php", `$${name} = isset($x) ? $x : "${value}";`, true],
    ["includes/newFeature.php", `$${name} .= ${q}${value}${q};`, true],
    ["includes/newFeature.php", `$${name} = 12345;`, true],
    ["includes/newFeature.php", `$${name} = <<<EOT`, true],
    ["includes/newFeature.php", `$cfg[${q}db_${name}${q}] = ${q}${value}${q};`, true],
    ["includes/newFeature.php", `define(${q}DB_${NAME}${q}, ${q}${value}${q});`, true],
    ["javascript/db.js", `const ${name} = process.env.X + ${q}${value}${q};`, true],
    ["javascript/db.js", `const ${name} = cond ? "${value}" : process.env.X;`, true],
    ["javascript/db.js", `const ${name} = (process.env.X || "${value}");`, true],
    ["javascript/db.js", `settings[${q}${name}${q}] = "${value}";`, true],
    ["api/client.ts", `const ${name}: string = process.env.X ?? (cond ? "${value}" : "");`, true],
    ["tools/ci/seed.py", `${name} = os.environ[${q}X${q}] + "${value}"`, true],
    ["tools/ci/seed.py", `${name} = "${value}" if debug else os.environ[${q}X${q}]`, true],
    ["tools/ci/seed.py", `${name} = os.environ[${q}X${q}] if x else ${q}${value}${q}`, true],
    ["tools/ci/seed.py", `${name} = os.environ.get(${q}X${q}) or (${q}${value}${q})`, true],
    ["tools/ci/seed.py", `os.environ["PG${NAME}"] = "${value}"`, true],
    ["tools/ci/seed.py", `${name} = 12345`, true],
    // References: names, calls with their arguments, subscripts, constants and flags.
    ["includes/newFeature.php", `$${name} = $prefix . $suffix;`, false],
    ["includes/newFeature.php", `$${name} = $a ? $b : $c;`, false],
    ["includes/newFeature.php", `$${name} = (string) ($x ?? ${q}${q});`, false],
    ["includes/newFeature.php", `$${name} = hash(${q}sha256${q}, $pw);`, false],
    ["includes/newFeature.php", `$${name} = get_settings_value(${q}${name}${q}, ${q}move3500${q}, ${q}${q}, null, $kasse);`, false],
    ["includes/newFeature.php", `$${name} = check_and_sanitize_input(${q}${name}${q}, ${q}Input for ${name} is too long.${q}, $nonce);`, false],
    ["includes/newFeature.php", `$${name} = null;`, false],
    ["includes/newFeature.php", `$use_${name} = 0;`, false],
    ["includes/newFeature.php", `$cfg[${q}db_${name}${q}] = $_POST[${q}pw${q}];`, false],
    ["includes/newFeature.php", `define(${q}DB_${NAME}${q}, getenv(${q}X${q}));`, false],
    ["includes/newFeature.php", `if ($cfg[${q}${name}${q}] == ${q}${value}${q}) {`, false],
    // A shell command's words after the value are not the value.
    ["includes/newFeature.php", `PG${NAME}=${q}$X${q} psql -c ${q}select 1${q}`, false],
    ["javascript/db.js", `const ${name} = document.querySelector("#${name}").value;`, false],
    ["javascript/db.js", `const ${name} = cond ? a : b;`, false],
    ["tools/ci/seed.py", `${name} = os.environ[${q}X${q}] if x else os.environ[${q}Y${q}]`, false],
    ["tools/ci/seed.py", `os.environ["PG${NAME}"] = os.environ["DB_${NAME}"]`, false],
    ["tools/ci/seed.py", `${name} = None`, false],
  ]);
});

test("secrets: an interpolation is a reference only when it holds one; ${12345}, f'{12345}', a literal inside and a shell alternative are literals", () => {
  // Built at run time, so this file holds no literal for the marker to find. A ${...} or an f-string
  // field is a reference only when what it holds is one.
  const name = ["pass", "word"].join("");
  const env = `PG${name.toUpperCase()}`;
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  assertPasswordCases([
    ["javascript/db.js", `const ${name} = \`\${12345}\`;`, true],
    ["javascript/db.js", `const ${name} = \`\${"${value}"}\`;`, true],
    ["javascript/db.js", `const ${name} = \`\${pw || "${value}"}\`;`, true],
    ["tools/ci/seed.py", `${name} = f${q}{12345}${q}`, true],
    ["tools/ci/seed.py", `${name} = f"{${q}${value}${q}}"`, true],
    ["tools/ci/seed.py", `${name} = f"{pw or ${q}${value}${q}}"`, true],
    ["tools/deploy.sh", `${env}="\${X:+${value}}" psql`, true],
    ["tools/deploy.sh", `${env}="$(echo ${value})" psql`, true],
    ["includes/newFeature.php", `$${name} = "\${X:+${value}}";`, true],
    ["javascript/db.js", `const ${name} = \`\${pw}\`;`, false],
    ["javascript/db.js", `const ${name} = \`\${process.env.X}\`;`, false],
    ["javascript/db.js", `const ${name} = \`\${cfg[${q}pw${q}]}\`;`, false],
    ["tools/ci/seed.py", `${name} = f${q}{pw}${q}`, false],
    ["tools/ci/seed.py", `${name} = f${q}{cfg["pw"]!r}${q}`, false],
    // A conversion takes the value as it is; a width or a fill pads it with literal characters.
    ["tools/ci/seed.py", `${name} = f"{pw!s}"`, false],
    ["tools/ci/seed.py", `${name} = f"{pw:>10}"`, true],
    ["tools/deploy.sh", `${env}="\${X:-}" psql`, false],
    ["tools/deploy.sh", `${env}="\${X:-$Y}" psql`, false],
    ["tools/deploy.sh", `${env}="\${X:?unset}" psql`, false],
    ["tools/deploy.sh", `${env}="$(cat /run/secrets/db)" psql`, false],
  ]);
});

test("secrets: a key given a value with ':' or '=>' counts, quoted or not; schema declarations and labels in their context, prose and a ternary's branch do not", () => {
  // Built at run time, so this file holds no literal for the marker to find. A key counts as a name before
  // "=" does: { password: '<literal>' } and ['password' => '<literal>'] hold a secret.
  const name = ["pass", "word"].join("");
  const NAME = name.toUpperCase();
  const Name = name.replace("p", "P");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  assertPasswordCases([
    ["javascript/db.js", `const opts = { ${name}: ${q}${value}${q} };`, true],
    ["javascript/db.js", `login({ user: "a", ${name}: "${value}" });`, true],
    ["javascript/db.js", `    ${name}: ${q}${value}${q},`, true],
    ["javascript/db.js", `const c = { "${name}": "${value}" };`, true],
    ["javascript/db.js", `// ${name}: ${q}${value}${q}`, true],
    ["includes/newFeature.php", `$cfg = [${q}${name}${q} => ${q}${value}${q}];`, true],
    ["includes/newFeature.php", `$cfg = array(${q}user${q} => ${q}a${q}, ${q}${name}${q} => "${value}");`, true],
    ["includes/newFeature.php", `    ${q}passwd${q} => ${q}${value}${q},`, true],
    ["includes/newFeature.php", `connect(${name}: ${q}${value}${q});`, true],
    ["tools/ci/seed.py", `cfg = {${q}${name}${q}: ${q}${value}${q}}`, true],
    ["tools/ci/seed.py", `    "${name}": "${value}",`, true],
    ["config/app.json", `  "${name}": "${value}",`, true],
    [".github/workflows/tests.yml", `      ${name}: ${value}`, true],
    ["docker-compose.yml", `      - DB_${NAME}=${value}`, true],
    ["docker-compose.yml", `      DB_${NAME}: ${value}`, true],
    // Schema declarations and validation rules.
    ["includes/newFeature.php", `$cols = [${q}${name}${q} => ${q}varchar(255)${q}];`, false],
    ["includes/newFeature.php", `$cols = [${q}${name}${q} => ${q}VARCHAR(255) NOT NULL${q}];`, false],
    ["includes/newFeature.php", `$rules = [${q}${name}${q} => ${q}required|min:8${q}];`, false],
    ["javascript/db.js", `const schema = { ${name}: { type: ${q}string${q} } };`, false],
    // A bare type is a literal unless the line names columns, a schema, fields, types or rules.
    ["config/app.json", `  "${name}": "string",`, true],
    // Labels, and an example that names what goes there.
    ["includes/newFeature.php", `$labels = [${q}${name}${q} => ${q}Adgangskode${q}];`, false],
    ["javascript/db.js", `const labels = { ${name}: ${q}${Name}${q} };`, false],
    ["javascript/db.js", `const labels = { ${name}: "Indtast adgangskode:" };`, false],
    // A password word is a literal unless the line names labels, texts, titles, ... (a database login).
    ["docker-compose.yml", `      POSTGRES_${NAME}: ${name}`, true],
    ["restapi/swagger.yaml", `              ${name}: your-api-user-${name}`, false],
    // Prose, a case label, a ternary's branch, a condition, a type, a reference.
    ["includes/newFeature.php", `echo "<label>MySQL ${Name}:</label>";`, false],
    ["includes/newFeature.php", `$debug .= "${Name}: " . ($kode ? ${q}SET${q} : ${q}NOT SET${q});`, false],
    ["includes/newFeature.php", `case ${q}${name}${q}: $x = ${q}a${q}; break;`, false],
    ["javascript/db.js", `input.type = hidden ? ${q}${name}${q} : ${q}text${q};`, false],
    ["tools/ci/seed.py", `if ${name}: print(${q}x${q})`, false],
    ["api/client.ts", `  ${name}: string;`, false],
    ["javascript/db.js", `  ${name}: pw,`, false],
    ["javascript/db.js", `  ${name}: document.getElementById(${q}pw-${q} + id).value,`, false],
    ["tools/ci/seed.py", `env = {"DB_USER": reader, "DB_${NAME}": ${name},`, false],
    ["config/app.yml", `      ${name}: \${{ secrets.DB }}`, false],
    // YAML's empty key takes its value from the lines after it, which a line alone does not show.
    [".github/workflows/tests.yml", `      ${name}:`, true],
    [".github/workflows/tests.yml", `      ${name}: null`, false],
  ]);
});

/**
 * Consecutive added lines of one file, starting at line 20, through markerHits and stationReport: the
 * lines whose password marker hits, and humanOnly.
 */
function linesHit(path, texts) {
  const lines = texts.map((text, i) => ({ line: 20 + i, text }));
  const hits = markerHits(path, lines).filter((hit) => hit.row === "secrets").map((hit) => hit.line);
  const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, lines]]) });
  return { hits, humanOnly: report.humanOnly };
}

test("secrets: a value that runs on past its line counts on the line that names it, read as markerHits and stationReport read a change", () => {
  // Built at run time, so this file holds no literal for the marker to find. The lexer reads on over the
  // change's lines as markerHits hands them, so a Python value in parentheses on the next line holds a secret.
  // A value the line leaves open (an open group or string, a trailing operator or backslash, or in PHP,
  // JS, TS and Python a name given nothing) is not shown to be a reference, so it counts.
  const name = ["pass", "word"].join("");
  const q = "'";
  const qqq = '"'.repeat(3);
  const value = `v${randomBytes(6).toString("hex")}`;
  for (const [path, texts, hits] of [
    ["tools/ci/seed.py", [`${name} = (`, `    ${qqq}${value}${qqq}`, `)`], [20]],
    ["tools/ci/seed.py", [`${name} = \\`, `    ${qqq}${value}${qqq}`], [20]],
    ["tools/ci/seed.py", [`${name} = ${qqq}`, `${value}${qqq}`], [20]],
    ["tools/ci/seed.py", [`${name} = (`, `    os.environ.get("X")`, `    or "${value}"`, `)`], [20]],
    ["tools/ci/seed.py", [`${name} = os.environ["X"] + \\`, `    "${value}"`], [20]],
    ["tools/ci/seed.py", [`${name} = os.environ["X"] or`, `    "${value}"`], [20]],
    ["includes/newFeature.php", [`$${name} =`, `    ${q}${value}${q};`], [20]],
    ["includes/newFeature.php", [`$${name} = $prefix .`, `    ${q}${value}${q};`], [20]],
    ["includes/newFeature.php", [`$${name} = <<<EOT`, value, `EOT;`], [20]],
    ["includes/newFeature.php", [`$cfg = [`, `    ${q}${name}${q} =>`, `        ${q}${value}${q},`, `];`], [21]],
    ["javascript/db.js", [`const ${name} =`, `  "${value}";`], [20]],
    ["javascript/db.js", [`  ${name}:`, `    "${value}",`], [20]],
    ["javascript/db.js", [`const ${name} = \``, `${value}\`;`], [20]],
    ["api/client.ts", [`const ${name}: string = cond ?`, `  "${value}" : "";`], [20]],
    // The line after a complete value, and a literal that is another name's, are not the value.
    ["tools/ci/seed.py", [`${name} = os.environ["X"]`, `user = "admin"`], []],
    ["tools/ci/seed.py", [`user = "admin"`, `${name} = "${value}"`], [21]],
    // No name is a reader by its prefix: a call handed a literal counts.
    ["javascript/db.js", [`const ${name} = await readSecret("db");`, `const user = "admin";`], [20]],
    // A name given nothing in a comment and an arrow function's open body are no value. (YAML's empty key,
    // whose value is on the next lines, is read on in the production wiring test below.)
    ["includes/newFeature.php", [`// the ${name} =`, `// is set below`], []],
    ["rental/api/api.js", [`export const checkSettings${name.replace("p", "P")} = async (${name}) => {`, `  return x;`], []],
    // YAML's block scalar holds the value on the next lines.
    [".github/workflows/tests.yml", [`      ${name}: |`, `        ${value}`], [20]],
    // A quote the lexer misreads (in a regular expression literal) does not read the next line as a string
    // (javascript/jquery.placeholder.min.js:9 at 5419eee2).
    ["javascript/db.js", [`x.replace(/type=([${q}"])?${name}\\1/gi,`, `"type=$1text$1"));`], []],
  ]) {
    assert.deepEqual(linesHit(path, texts), { hits, humanOnly: hits.length > 0 }, `${path}: ${texts.join(" / ")}`);
  }
  // Every password spelling reads one line (pattern.test) and the change's lines from one on (readsFrom).
  const spellings = row("secrets").markers.filter((m) => m.marker === "password literal");
  assert.ok(spellings.length > 0 && spellings.every((m) => m.pattern instanceof RegExp && typeof m.readsFrom === "function"));
});

test("secrets, through markerHits and stationReport: a group at any depth, an interpolated call or default, schema and label spellings outside their context, a segment joined to a shell reference and an environment word in a comment count; pure references do not", () => {
  // Built at run time, so this file holds no literal for the marker to find. Each hit guards against a
  // reading that looks for a literal and lets what it cannot read go: one that descends one group only,
  // reads a call's arguments as references inside ${...} and {...}, exempts a schema type or a label by
  // its spelling alone, stops a shell word at a quote, or reads an environment name in a comment only
  // after export or before a command.
  const name = ["pass", "word"].join("");
  const NAME = name.toUpperCase();
  const env = `PG${NAME}`;
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  assertPasswordCases([
    // A group at any depth, in each language.
    ["javascript/db.js", `const ${name} = ((process.env.X + ${q}${value}${q}));`, true],
    ["javascript/db.js", `const ${name} = ((((((process.env.X + ${q}${value}${q}))))));`, true],
    ["api/client.ts", `const ${name}: string = (((process.env.X ?? "${value}")));`, true],
    ["tools/ci/seed.py", `${name} = ((os.environ[${q}X${q}] + ${q}${value}${q}))`, true],
    ["includes/newFeature.php", `$${name} = (($x . ${q}${value}${q}));`, true],
    ["includes/newFeature.php", `$${name} = trim((string) (($x ?? ${q}${value}${q})));`, true],
    ["javascript/db.js", `const ${name} = f(g((process.env.X || "${value}")));`, true],
    ["javascript/db.js", `const ${name} = [${q}${value}${q}].join("");`, true],
    // A call or a default inside an interpolation, and the same call in code.
    ["javascript/db.js", `const ${name} = \`\${String(${q}${value}${q})}\`;`, true],
    ["tools/ci/seed.py", `${name} = f${q}{os.getenv("X", "${value}")}${q}`, true],
    ["javascript/db.js", `const ${name} = String(${q}${value}${q});`, true],
    ["includes/newFeature.php", `$${name} = base64_decode(${q}${value}${q});`, true],
    ["includes/newFeature.php", `${q}${name}${q} => Hash::make(${q}${value}${q}),`, true],
    ["tools/ci/seed.py", `${name} = base64.b64decode(${q}${value}${q}).decode()`, true],
    ["tools/ci/seed.py", `${name} = kwargs.get("${name}", "${value}")`, true],
    ["javascript/db.js", `const ${name} = /${value}/.source;`, true],
    // A schema type or a label used as a credential.
    ["javascript/db.js", `const ${name} = "text";`, true],
    ["tools/ci/seed.py", `${name} = "my ${name}"`, true],
    ["javascript/db.js", `login({${name}: "text"});`, true],
    ["includes/newFeature.php", `$${name} = ${q}varchar(255)${q};`, true],
    ["includes/newFeature.php", `$c = [${q}use_${name}${q} => ${q}INTEGER${q}];`, true],
    ["javascript/db.js", `const opts = { ${name}: ${q}${name.replace("p", "P")}${q} };`, true],
    ["javascript/db.js", `const auth = { type: "basic", ${name}: "text" };`, true],
    ["javascript/db.js", `const text = 1; const auth = { ${name}: ${q}${name.replace("p", "P")}${q} };`, true],
    // Every segment of a shell word.
    ["tools/deploy.sh", `${env}=$X${q}${value}${q} psql`, true],
    ["tools/deploy.sh", `${env}="$X"${value} psql`, true],
    ["tools/deploy.sh", `export ${env}="$X"${q}${value}${q}`, true],
    ["javascript/db.js", `// ${env}=$X${q}${value}${q} psql`, true],
    ["tools/ci/seed.py", `cmd = "${env}=$X'${value}' psql"`, true],
    // An environment name given a word in a comment, or alone on its line.
    ["javascript/db.js", `// ${env}=${value}`, true],
    ["tools/ci/seed.py", `# ${env}=${value}`, true],
    ["includes/newFeature.php", `/* ${env}=${value} */`, true],
    ["tools/ci/seed.py", `${env}=${value}`, true],
    // Pure references stay no hit.
    ["javascript/db.js", `const ${name} = ((process.env.X));`, false],
    ["tools/ci/seed.py", `${name} = ((os.environ[${q}X${q}] or os.environ[${q}Y${q}]))`, false],
    ["includes/newFeature.php", `$${name} = trim((string) (isset($_POST[${q}pw${q}]) ? $_POST[${q}pw${q}] : ${q}${q}));`, false],
    ["javascript/db.js", `const ${name} = \`\${encodeURIComponent(pw)}\`;`, false],
    ["tools/ci/seed.py", `${name} = f${q}{os.getenv("X")}${q}`, false],
    ["tools/ci/seed.py", `${name} = kwargs.pop(${q}${name}${q}, None)`, false],
    ["includes/newFeature.php", `${q}${name}${q} => Hash::make(Str::random(16)),`, false],
    ["javascript/db.js", `const ${name} = prompt("Indtast adgangskode for at fortsætte");`, false],
    ["javascript/db.js", `const ${name} = document.querySelector("#${name}").value;`, false],
    ["javascript/db.js", `const ${name} = total / count;`, false],
    ["includes/newFeature.php", `$expectedColumns = [${q}id${q} => ${q}SERIAL PRIMARY KEY${q}, ${q}use_${name}${q} => ${q}INTEGER${q}];`, false],
    ["javascript/db.js", `const labels = { ${name}: ${q}${name.replace("p", "P")}${q} };`, false],
    ["tools/deploy.sh", `${env}="$X" psql`, false],
    ["tools/deploy.sh", `${env}=$X"$Y" psql`, false],
    ["tools/deploy.sh", `tenant_db="$(docker compose exec -T -e "${env}=$PG_PASS" postgres \\`, false],
    ["javascript/db.js", `// ${env}=$${env}`, false],
    ["tools/ci/seed.py", `# ${env}=\${${env}}`, false],
    ["javascript/db.js", `const DB_${NAME}=pw;`, false],
  ]);
});

test("secrets: five escape classes are hits through markerHits and stationReport, and pure references in the same shapes are not", () => {
  // Built at run time, so this file holds no literal for the marker to find. Every hit guards against regular
  // expressions that look for a literal and let what they cannot read go: a value whose next line continues it,
  // a literal beside a reference in a call, a reader by its name's prefix, a schema type or label where the line
  // names a context, a block comment inside the value, padding around a reference, and a digit.
  const name = ["pass", "word"].join("");
  const NAME = name.toUpperCase();
  const Name = name.replace("p", "P");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  // Class 1: a continuation after a complete operand, on the next line or joined in code to a string's value.
  for (const [path, texts] of [
    ["javascript/db.js", [`const ${name} = process.env.X`, `  + ${q}${value}${q};`]],
    ["includes/newFeature.php", [`$${name} = $prefix`, `    . ${q}${value}${q};`]],
    ["javascript/db.js", [`  ${name}: process.env.X`, `    + ${q}${value}${q},`]],
    ["javascript/db.js", [`const ${name} = base`, `  .concat(${q}${value}${q});`]],
    ["javascript/db.js", [`const ${name} = cond`, `  ? ${q}${value}${q} : process.env.X;`]],
    ["includes/newFeature.php", [`$${name} = $x ?? $y`, `    ?? ${q}${value}${q};`]],
    ["javascript/db.js", [`const ${name} = process.env.X // the login`, `  + ${q}${value}${q};`]],
    ["api/client.ts", [`const ${name}: string = process.env.X`, `  ?? "${value}";`]],
  ]) {
    assert.deepEqual(linesHit(path, texts), { hits: [20], humanOnly: true }, `${path}: ${texts.join(" / ")}`);
  }
  assertPasswordCases([
    ["includes/newFeature.php", `$dsn = "host=$h ${name}=" . ${q}${value}${q};`, true],
    ["javascript/db.js", `const dsn = "host=x ${name}=" + "${value}";`, true],
    ["tools/ci/seed.py", `dsn = "host=x ${name}=" "${value}"`, true],
    ["includes/newFeature.php", `$dsn = "host=$h ${name}=" . $pw . " port=5432";`, false],
    // Class 2: a call's arguments are values unless the call reads them (READER_CALLS); defaults are values.
    ["javascript/db.js", `const ${name} = String(${q}${value}${q}, process.env.X);`, true],
    ["includes/newFeature.php", `$${name} = sprintf(${q}${value}%s${q}, $x);`, true],
    ["tools/ci/seed.py", `${name} = os.getenv(key=${q}X${q}, default=${q}${value}${q})`, true],
    ["tools/ci/seed.py", `${name} = os.getenv(${q}X${q}, default=${q}${value}${q})`, true],
    ["javascript/db.js", `const ${name} = getFoo(${q}${value}${q});`, true],
    ["javascript/db.js", `const ${name} = readSecret(${q}${value}${q});`, true],
    ["includes/newFeature.php", `$${name} = fetch_${name}_for(${q}${value}${q});`, true],
    ["includes/newFeature.php", `$${name} = env(${q}X${q}, ${q}${value}${q});`, true],
    ["javascript/db.js", `const ${name} = prompt("Kode", ${q}${value}${q});`, true],
    ["includes/newFeature.php", `$${name} = str_replace(${q}a${q}, ${q}${value}${q}, $x);`, true],
    ["includes/newFeature.php", `$${name} = hash(${q}sha256${q}, ${q}${value}${q});`, true],
    ["javascript/db.js", `const ${name} = ${q}${value}${q}.trim();`, true],
    ["includes/newFeature.php", `$${name} = chr(104) . chr(105);`, true],
    ["tools/ci/seed.py", `${name} = ${q}${q}.join([${q}a${q}, ${q}b${q}])`, true],
    ["includes/newFeature.php", `$${name} = sprintf(${q}%s${q}, $x);`, false],
    ["tools/ci/seed.py", `${name} = os.getenv(key=${q}X${q}, default=None)`, false],
    ["includes/newFeature.php", `$${name} = hash(${q}sha256${q}, $pw);`, false],
    ["includes/newFeature.php", `$${name} = mb_convert_encoding($pw, ${q}UTF-8${q}, ${q}ISO-8859-1${q});`, false],
    ["includes/newFeature.php", `$${name} = if_isset($_SESSION, ${q}${q}, ${q}db_${name}${q});`, false],
    ["tools/ci/seed.py", `${name} = ${q}%s${q} % pw`, false],
    ["tools/ci/seed.py", `${name} = ${q}{}${q}.format(pw)`, false],
    ["javascript/db.js", `const { check${Name} } = await import(${q}./api.js${q});`, false],
    // Class 3: a schema type or a label is none only as a field its own declaration names.
    ["javascript/db.js", `login({${name}: ${q}varchar(255)${q}});`, true],
    ["javascript/db.js", `login({ ${name}: ${q}required|min:8${q} });`, true],
    ["javascript/db.js", `const labels = {}; const ${name} = ${q}${Name}${q};`, true],
    ["javascript/db.js", `const opts = { label: ${q}x${q}, ${name}: ${q}${Name}${q} };`, true],
    ["includes/newFeature.php", `$x = [${q}labels${q} => [], ${q}${name}${q} => ${q}${Name}${q}];`, true],
    ["includes/newFeature.php", `$cols = []; $${name} = ${q}varchar(255)${q};`, true],
    ["includes/newFeature.php", `$protocol = [${q}${name}${q} => ${q}varchar(255)${q}];`, true],
    ["includes/newFeature.php", `$expectedColumns = [${q}${name}${q} => ${q}varchar(255)${q}];`, false],
    ["includes/newFeature.php", `$rules = [${q}${name}${q} => ${q}required|min:8${q}];`, false],
    ["javascript/db.js", `const labels = { ${name}: ${q}${Name}${q} };`, false],
    ["includes/newFeature.php", `$labels[${q}${name}${q}] = ${q}Adgangskode${q};`, false],
    ["javascript/db.js", `const schema = { ${name}: { type: ${q}string${q} } };`, false],
    // Class 4: a block comment inside the value is trivia.
    ["javascript/db.js", `const ${name} = process.env.X /* note */ + ${q}${value}${q};`, true],
    ["includes/newFeature.php", `$${name} = $prefix /* note */ . ${q}${value}${q};`, true],
    ["includes/newFeature.php", `$${name} = /* x */ ${q}${value}${q};`, true],
    ["javascript/db.js", `const ${name} = process.env.X /* still open`, true],
    ["javascript/db.js", `const ${name} = process.env.X; /* + ${q}${value}${q} */`, false],
    ["javascript/db.js", `const ${name} = /* note */ process.env.X;`, false],
    // Class 5: literal padding, the space between two references, and a number.
    ["javascript/db.js", `const ${name} = " $PG${NAME} ";`, true],
    ["javascript/db.js", `const ${name} = ${q}$PG${NAME} ${q};`, true],
    ["tools/ci/seed.py", `${name} = f" {pw} "`, true],
    ["tools/deploy.sh", `PG${NAME}=" $X " psql`, true],
    ["javascript/db.js", `const ${name} = \`\${a} \${b}\`;`, true],
    [".github/workflows/tests.yml", `      ${name}: \${{ secrets.A }} \${{ secrets.B }}`, true],
    ["includes/newFeature.php", `$${name} = 7;`, true],
    ["tools/ci/seed.py", `${name} = 7`, true],
    ["javascript/db.js", `const ${name} = 7;`, true],
    ["includes/newFeature.php", `$${name} = -1;`, true],
    ["javascript/db.js", `const ${name} = 0x1F;`, true],
    ["includes/newFeature.php", `$${name} = 0;`, true],
    ["includes/newFeature.php", `$use_${name} = 0;`, false],
    ["javascript/db.js", `const opts = { use_${name}: 1 };`, false],
    ["javascript/db.js", `const ${name} = "$PG${NAME}";`, false],
    ["tools/ci/seed.py", `${name} = f"{pw}"`, false],
    ["tools/deploy.sh", `PG${NAME}="$X" psql`, false],
    ["javascript/db.js", `const ${name} = \`\${a}\${b}\`;`, false],
    // Other ways to give a name a value: a setter, a list pattern, a Python tuple, a chain, a declaration's type.
    ["includes/newFeature.php", `$mail->set${Name}(${q}${value}${q});`, true],
    ["javascript/db.js", `localStorage.setItem(${q}${name}${q}, ${q}${value}${q});`, true],
    ["tools/ci/seed.py", `setattr(cfg, ${q}${name}${q}, ${q}${value}${q})`, true],
    ["includes/newFeature.php", `[$user, $${name}] = [${q}a${q}, ${q}${value}${q}];`, true],
    ["javascript/db.js", `[user, ${name}] = [${q}a${q}, ${q}${value}${q}];`, true],
    ["tools/ci/seed.py", `user, ${name} = os.environ[${q}U${q}], ${q}${value}${q}`, true],
    ["includes/newFeature.php", `$${name} = $x = ${q}${value}${q};`, true],
    ["tools/ci/seed.py", `${name} = x = ${q}${value}${q}`, true],
    ["api/client.ts", `let ${name}: Record<string, string> = ${q}${value}${q};`, true],
    ["tools/ci/seed.py", `self.${name}: str = ${q}${value}${q}`, true],
    ["javascript/db.js", `const ${name} = { value: ${q}${value}${q} };`, true],
    ["includes/newFeature.php", `$mail->set${Name}($pw);`, false],
    ["includes/newFeature.php", `list($u, $${name}) = explode(${q},${q}, $s);`, false],
    ["api/client.ts", `let ${name}: Record<string, string> = cfg.pw;`, false],
    ["includes/newFeature.php", `return TestCredentials::${name}(${q}restapi${q});`, false],
  ]);
});

/** The password spellings that read `path`, by their `in` globs as markerHits reads them. */
const passwordSpellings = (path) => row("secrets").markers.filter((m) => m.marker === "password literal" && (!m.in || firstMatch(m.in, path)) && !(m.notIn && firstMatch(m.notIn, path)));
/** Whether `text`, read alone (pattern.test: markerHits without readsFrom), gives a password a literal. */
const readAlone = (path, text) => passwordSpellings(path).some((m) => m.pattern.test(text));
/** The lines of `texts` (consecutive, from line 20) whose statement, read on through the next ones (spelling.readsFrom), gives a password a literal. */
function readOn(path, texts) {
  const lines = texts.map((text, i) => ({ line: 20 + i, text }));
  return lines.flatMap((entry, i) => (passwordSpellings(path).some((m) => m.readsFrom(lines, i)) ? [entry.line] : []));
}

test("secrets: read alone, a statement whose end is not on its line counts in PHP and JavaScript; read on through the change's next lines, a complete value ends where the change does and an open one counts", () => {
  // Built at run time, so this file holds no literal for the marker to find. A line alone cannot show that the
  // next one does not continue it: PHP and JavaScript statements run on to a ";" (or JavaScript's next line
  // decides), so an unended one counts. The change's next lines can show it; the lines after the change are not
  // the change's, so a complete value there has ended. Python ends a statement at its line's end, unless a
  // bracket is open.
  const name = ["pass", "word"].join("");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  for (const [path, text, hit] of [
    ["javascript/db.js", `const ${name} = process.env.X`, true],
    ["javascript/db.js", `const ${name} = prompt("Indtast adgangskode for at fortsætte")`, true],
    ["javascript/db.js", `const DB_${name.toUpperCase()}=pw`, true],
    ["includes/newFeature.php", `$${name} = $prefix`, true],
    ["includes/newFeature.php", `    ${q}${name}${q} => $passwd`, true],
    ["tools/ci/seed.py", `${name} = os.environ[${q}X${q}]`, false],
    ["tools/ci/seed.py", `conn = connect(${name}=os.environ[${q}X${q}]`, true],
    ["javascript/db.js", `const ${name} = process.env.X;`, false],
    ["includes/newFeature.php", `$${name} = $prefix;`, false],
  ]) {
    assert.equal(readAlone(path, text), hit, `${path}: ${text}`);
  }
  for (const [path, texts, hits] of [
    // The continuation classes, read on.
    ["javascript/db.js", [`const ${name} = process.env.X`, `  + ${q}${value}${q};`], [20]],
    ["includes/newFeature.php", [`$${name} = $prefix`, `    . ${q}${value}${q};`], [20]],
    ["tools/ci/seed.py", [`conn = connect(`, `    ${name}=os.environ[${q}X${q}]`, `    + ${q}${value}${q})`], [21]],
    ["includes/newFeature.php", [`$${name} = $x // the login`, `    . ${q}${value}${q};`], [20]],
    ["javascript/db.js", [`const ${name} = process.env.X /* the`, `   login */ + ${q}${value}${q};`], [20]],
    ["javascript/db.js", [`// const ${name} = process.env.X`, `//   + ${q}${value}${q};`], [20]],
    ["includes/newFeature.php", [`$${name} = <<<EOT`, value, `EOT;`], [20]],
    [".github/workflows/tests.yml", [`      ${name}: |`, `        ${value}`], [20]],
    // A complete value whose next line does not continue it, or after which the change ends.
    ["javascript/db.js", [`const ${name} = process.env.X`, `  ?? process.env.Y;`], []],
    ["javascript/db.js", [`const ${name} = prompt("Indtast adgangskode")`, `if (!${name}) return`], []],
    ["javascript/db.js", [`const ${name} = document.querySelector("#x").value`], []],
    ["includes/newFeature.php", [`$cfg = [`, `    ${q}${name}${q} => $passwd`, `];`], []],
    ["includes/newFeature.php", [`    ${q}${name}${q} => $passwd`], []],
    ["includes/newFeature.php", [`$${name} = <<<EOT`, `$pw`, `EOT;`], []],
    ["tools/ci/seed.py", [`${name} = (`, `    os.environ["PG${name.toUpperCase()}"]`, `)`], []],
    ["api/client.ts", [`let ${name}: string`, `user = "${value}";`], []],
    // A value still open where the change ends counts.
    ["javascript/db.js", [`const ${name} =`], [20]],
    ["tools/ci/seed.py", [`${name} = (`, `    os.environ["X"]`], [20]],
  ]) {
    assert.deepEqual(readOn(path, texts), hits, `${path}: ${texts.join(" / ")}`);
  }
  // Past WINDOW_LINES lines a statement still open counts.
  assert.deepEqual(readOn("javascript/db.js", [`const ${name} = [`, ...Array(60).fill("  x,"), `];`]), [20]);
  // Each line is read twice at most, alone and with its window: 200 open 2 KB lines read on in linear time
  // (linear-time.mjs; reading the window again for every line added would grow quadratically).
  const open = (n) => Array.from({ length: n }, () => `const ${name} = f(${"a, ".repeat(600)}`);
  assert.equal(linear("200 open lines", (n) => readOn("javascript/db.js", open(n)).length, 200), 200);
});

test("secrets: the same five classes in further shapes: a spread into a reader, a command fed a literal, YAML's next line, a context past a call or body, a list pattern's last line, a SQL parameter's spelling", () => {
  // Built at run time, so this file holds no literal for the marker to find. None of these is a reference:
  // a reader call handed a spread (its arguments' places are
  // unknown, so a default can hide in it), a command substitution other than a file read (echo, a here-string,
  // base64), a YAML key whose plain scalar is on the next line, a schema type or label inside a call or a body
  // that a labels or columns map holds, the last name of a list pattern whose bracket opened a line before,
  // filter_input's options (which hold a default), FormData's append(), and a word spelled as a SQL parameter.
  const name = ["pass", "word"].join("");
  const NAME = name.toUpperCase();
  const Name = name.replace("p", "P");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  assertPasswordCases([
    // Class 2: a spread or an unpacked argument is a value, and so is every argument after it.
    ["tools/ci/seed.py", `${name} = os.getenv(**{${q}key${q}: ${q}X${q}, ${q}default${q}: ${q}${value}${q}})`, true],
    ["tools/ci/seed.py", `${name} = os.getenv(*[${q}X${q}, ${q}${value}${q}])`, true],
    ["javascript/db.js", `const ${name} = config(...[${q}k${q}, ${q}${value}${q}]);`, true],
    ["includes/newFeature.php", `$${name} = getenv(...[${q}X${q}, ${q}${value}${q}]);`, true],
    ["includes/newFeature.php", `$${name} = env(...$args);`, false],
    // Class 2 in shell: a command substitution is a call; only a file read (cat, a redirect) is a reader.
    ["tools/deploy.sh", `DB_${NAME}=$(base64 -d <<< ${value})`, true],
    ["tools/deploy.sh", `DB_${NAME}="$(printf %s ${value} | base64)"`, true],
    ["tools/deploy.sh", `DB_${NAME}=$(openssl enc -d -k ${value} -in x)`, true],
    ["tools/deploy.sh", `export DB_${NAME}="$(cat "$SECRETS_DIR/db")"`, false],
    ["tools/deploy.sh", `export DB_${NAME}=$(< /run/secrets/db)`, false],
    ["includes/newFeature.php", `exec("PG${NAME}=$(cat /run/secrets/db) pg_dump x");`, false],
    ["includes/newFeature.php", `exec("PG${NAME}=$(echo ${value}) pg_dump x");`, true],
    ["includes/newFeature.php", `$${name} = filter_input(INPUT_POST, ${q}pw${q}, FILTER_DEFAULT, [${q}options${q} => [${q}default${q} => ${q}${value}${q}]]);`, true],
    ["includes/newFeature.php", `$${name} = filter_input(INPUT_POST, ${q}pw${q}, FILTER_DEFAULT);`, false],
    ["javascript/db.js", `const ${name} = crypto.randomBytes(16).toString(${q}hex${q});`, false],
    // Class 3: a call's arguments and a body's statements have no declaration, whatever map holds them.
    ["includes/newFeature.php", `$labels = [${q}x${q} => login([${q}${name}${q} => ${q}${Name}${q}])];`, true],
    ["javascript/db.js", `const labels = { x: login({ ${name}: ${q}${Name}${q} }) };`, true],
    ["includes/newFeature.php", `$columns = [${q}x${q} => connect([${q}${name}${q} => ${q}varchar(255)${q}])];`, true],
    ["javascript/db.js", `const labels = { f() { const ${name} = ${q}${Name}${q}; } };`, true],
    ["includes/newFeature.php", `$labels = [${q}f${q} => function () { $${name} = ${q}${Name}${q}; }];`, true],
    ["includes/newFeature.php", `$labels = [${q}a${q} => array(${q}${name}${q} => ${q}Adgangskode${q})];`, false],
    ["javascript/db.js", `const labels = { x: [{ ${name}: ${q}${Name}${q} }] };`, false],
    ["includes/newFeature.php", `$texts = array(${q}${name}${q} => ${q}Kodeord${q});`, false],
    // Another way to give a name a value: the last name of a list pattern, and a setter of the standard library.
    ["javascript/db.js", ` ${name}] = [${q}u${q}, ${q}${value}${q}];`, true],
    ["includes/newFeature.php", ` $${name}) = [${q}u${q}, ${q}${value}${q}];`, true],
    ["javascript/db.js", `  ${name} } = { ${name}: ${q}${value}${q} };`, true],
    ["javascript/db.js", `fd.append(${q}${name}${q}, ${q}${value}${q});`, true],
    ["javascript/db.js", `  ${name} } = cfg;`, false],
    ["javascript/db.js", `fd.append(${q}${name}${q}, ${name});`, false],
    ["javascript/db.js", `cfg[${name}] = ${q}x${q};`, false],
    // Class 5: a word spelled as a SQL parameter is a placeholder only in a string's own text.
    ["javascript/db.js", `const ${name} = ${q}:${value}${q};`, true],
    ["includes/newFeature.php", `$sql = "UPDATE users SET ${name} = :${name} WHERE id = :id";`, false],
    // Class 5: a template's width, fill or flag pads the value; a shell replacement puts its own characters in.
    ["includes/newFeature.php", `$${name} = sprintf(${q}%20s${q}, $pw);`, true],
    ["includes/newFeature.php", `$${name} = sprintf(${q}%-20s${q}, $pw);`, true],
    ["tools/ci/seed.py", `${name} = "%20s" % pw`, true],
    ["tools/ci/seed.py", `${name} = "{:>20}".format(pw)`, true],
    ["tools/ci/seed.py", `${name} = f"{pw:*^30}"`, true],
    ["tools/deploy.sh", `PG${NAME}="\${X/a/${value}}" psql`, true],
    ["tools/deploy.sh", `PG${NAME}=\${X//a/${value}} psql`, true],
    ["includes/newFeature.php", `$${name} = sprintf(${q}%1$s${q}, $pw);`, false],
    ["tools/ci/seed.py", `${name} = "%(pw)s" % cfg`, false],
    ["tools/ci/seed.py", `${name} = f"{pw!r}"`, false],
    ["tools/deploy.sh", `PG${NAME}="\${X#prefix}" psql`, false],
    ["tools/deploy.sh", `PG${NAME}="\${X:0:8}" psql`, false],
    ["tools/deploy.sh", `PG${NAME}="\${X/a/$Y}" psql`, false],
  ]);
  // Class 1 in YAML: a key given nothing takes the more-indented lines after it.
  for (const [texts, hits] of [
    [[`      ${name}:`, `        ${value}`], [20]],
    [[`      ${name}:`, `        - ${value}`], [20]],
    [[`      ${name}:`, `        # the login`, `        ${value}`], [20]],
    [[`      ${name}:`, `        from: secrets`], []],
    [[`      ${name}:`, `        type: string`, `        format: ${name}`], []],
    [[`      ${name}:`, `        \${{ secrets.DB }}`, `      user: x`], []],
    // A plain scalar still open where the change ends counts, as a block scalar does.
    [[`      ${name}:`, `        \${{ secrets.DB }}`], [20]],
    [[`      ${name}:`, `      user: x`], []],
    [[`      ${name}:`], [20]],
  ]) {
    assert.deepEqual(readOn(".github/workflows/tests.yml", texts), hits, texts.join(" / "));
    assert.equal(readAlone(".github/workflows/tests.yml", texts[0]), true, texts[0]);
  }
});

/**
 * The change `added` (line numbers) to `file` (its texts, line 1 first) as check.mjs hands it to markerHits:
 * each run of added lines after the unchanged lines above it (changeset markerLines, addedWithContext).
 */
const changeEntries = (file, added) => addedWithContext(new Map(added.map((n) => [n, file[n - 1]])), `${file.join("\n")}\n`);
/** A PHP fixture file whose line 1 is meant as PHP's code, as PHP reads one (asCode). */
const codeFile = (path, file) => [asCode(path, file[0]), ...file.slice(1)];
/** The added lines whose statement a password spelling's readsFrom() reads as a literal, over those entries (doors.mjs alone). */
function readWithContext(path, file, added) {
  const entries = changeEntries(codeFile(path, file), added);
  return entries.flatMap((entry, i) => (!entry.context && passwordSpellings(path).some((m) => m.readsFrom(entries, i)) ? [entry.line] : []));
}
/**
 * The context cases: [path, file, added, hits]. Each change edits lines of an unchanged file; `hits` are the
 * added lines the password marker hits when the lines above each run are read too.
 */
function contextCases(name, value) {
  const NAME = name.toUpperCase();
  const Name = name.replace("p", "P");
  const q = "'";
  return [
    ["includes/newFeature.php", [`$${name} = $prefix`, `    . ${q}${value}${q};`], [2], [2]],
    ["includes/newFeature.php", [`$cfg = [`, `    ${q}${name}${q} => $prefix`, `        . ${q}${value}${q},`, `];`], [3], [3]],
    ["tools/ci/seed.py", [`${name} = (`, `    os.environ["X"]`, `    + ${q}${value}${q}`, `)`], [3], [3]],
    ["javascript/db.js", [`const ${name} = process.env.X /* the`, `   login */`, `  + ${q}${value}${q};`], [3], [3]],
    [".github/workflows/tests.yml", [`      ${name}:`, `        ${value}`], [2], [2]],
    // A statement that ended before the added line, or whose own unchanged lines hold the literal, is not this change's.
    ["javascript/db.js", [`const ${name} = process.env.X;`, `  + ${q}${value}${q};`], [2], []],
    ["javascript/db.js", [`const ${name} = ${q}${value}${q}`, `run();`], [2], []],
    ["javascript/db.js", [`const ${name} = process.env.X`, `  ?? process.env.Y;`], [2], []],
    ["tools/ci/seed.py", [`${name} = os.environ["X"]`, `user = ${q}${value}${q}`], [2], []],
    // An unchanged line is never a hit. A change's own line reads on over the unchanged lines
    // below it through the end of the statement it opens, and what they give it is its own: a value-only edit (the
    // reference renamed) above an unchanged literal continuation counts, as the whole file does.
    ["javascript/db.js", [`const ${name} = process.env.X`, `  + ${q}${value}${q};`], [1], [1]],
    ["javascript/db.js", [`const ${name} = process.env.X`, `  + process.env.Y;`], [1], []],
    // The added line reads back whatever it holds, a password named in its own literal, its comment or an
    // environment name included, not only a line that names none.
    ["javascript/db.js", [`const ${name} = process.env.X`, `  ?? ${q}${value}-${name}-value${q};`, `run(${name});`], [2], [2]],
    ["javascript/db.js", [`const ${name} = process.env.X`, `  ?? ${q}${value}${q}; // ${name} fallback`], [2], [2]],
    ["javascript/db.js", [`const ${name} = process.env.X`, `  ?? process.env.DB_${NAME}_FALLBACK + ${q}${value}${q};`], [2], [2]],
    ["includes/newFeature.php", [`$${name} = getenv(${q}X${q})`, `    ?: ${q}${value}-${name}${q};`], [2], [2]],
    ["tools/ci/seed.py", [`${name} = (os.environ["X"]`, `    or ${q}${value}${q})  # ${name} fallback`], [2], [2]],
    ["javascript/db.js", [`const ${name} = process.env.X`, `  ?? process.env.DB_${NAME}_FALLBACK;`], [2], []],
    ["javascript/db.js", [`const ${name} = process.env.X;`, `const label = ${q}${Name}${q}; // the ${name} label`], [2], []],
  ];
}
/** The same change through markerHits and stationReport, as check.mjs routes it: the password marker's lines, and humanOnly. */
function contextHit(path, file, added) {
  const entries = changeEntries(codeFile(path, file), added);
  const hits = markerHits(path, entries).filter((hit) => hit.row === "secrets").map((hit) => hit.line);
  const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, entries]]) });
  return { hits, humanOnly: report.humanOnly };
}

test("secrets: an added line that goes on with a statement an unchanged line starts counts when markerHits hands the lines above it", () => {
  // Built at run time, so this file holds no literal for the marker to find. The change edits only the
  // fallback line of an existing reference (`?? ''` becomes `?? '<literal>'`): the line it adds names no
  // password, so a reader of added lines alone never sees whose value it continues. addedWithContext puts the
  // unchanged lines above each run beside it; the run's first line reads back to the statement's start.
  const name = ["pass", "word"].join("");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  const file = [
    `const user = process.env.U;`,
    `const ${name} = process.env.X`,
    `  ?? ${q}${value}${q};`,
    `run(${name});`,
  ];
  assert.deepEqual(readWithContext("javascript/db.js", file, [3]), [3]);
  assert.deepEqual(readWithContext("javascript/db.js", file, [2, 3]), [2]);
  // The entries: context lines 1-2 above the run, the added line, and context line 4 below it.
  assert.deepEqual(addedWithContext(new Map([[3, file[2]]]), file.join("\n")).map((e) => [e.line, Boolean(e.context)]), [[1, true], [2, true], [3, false], [4, true]]);
  for (const [path, lines, added, hits] of contextCases(name, value)) {
    assert.deepEqual(readWithContext(path, lines, added), hits, `${path}: ${lines.join(" / ")} (added ${added})`);
  }
});

test("secrets, through markerHits and stationReport: a function's value counts unless it is all of a name's value, and a map's literal key counts unless the map is the value's own structure", () => {
  // Built at run time, so this file holds no literal for the marker to find. A function body is a value unless
  // it is all of a name's value, so an invoked one cannot hide its return (an immediately invoked function,
  // call_user_func()); a map's keys are values inside a call, so what reads its keys cannot hide them
  // (Object.keys(), array_key_first(), ''.join(), dict()); and in JavaScript and Python fn(...) is a call, not
  // PHP's arrow, so its arguments are read.
  const name = ["pass", "word"].join("");
  const Name = name.replace("p", "P");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  assertPasswordCases([
    // A function: no value only as all of what a name is given, a helper's or a method's declaration.
    ["javascript/db.js", `const ${name} = (function () { return ${q}${value}${q}; })();`, true],
    ["includes/newFeature.php", `$${name} = (function () { return ${q}${value}${q}; })();`, true],
    ["javascript/db.js", `const ${name} = function () { return ${q}${value}${q}; }();`, true],
    ["javascript/db.js", `const ${name} = function () { return ${q}${value}${q}; }.call(this);`, true],
    ["includes/newFeature.php", `$${name} = call_user_func(function () { return ${q}${value}${q}; });`, true],
    ["javascript/db.js", `const ${name} = run(function () { return pw; });`, true],
    ["javascript/db.js", `const ${name} = (() => { return ${q}${value}${q}; })();`, true],
    ["includes/newFeature.php", `$${name} = (fn() => ${q}${value}${q})();`, true],
    ["includes/newFeature.php", `$${name} = static fn() => ${q}${value}${q};`, true],
    ["includes/newFeature.php", `$${name} = (static function () { return ${q}${value}${q}; })();`, true],
    ["javascript/db.js", `const ${name} = fn(${q}${value}${q});`, true],
    ["tools/ci/seed.py", `${name} = fn(${q}${value}${q})`, true],
    ["tools/ci/seed.py", `${name} = function(${q}${value}${q})`, true],
    ["javascript/db.js", `const opts = { validate${Name}: function (v) { return v.length > 8; } };`, false],
    ["includes/newFeature.php", `$check${Name} = function ($pw) { return strlen($pw) > 8; };`, false],
    ["includes/newFeature.php", `$rules = [${q}${name}${q} => function ($v) { return $v !== ${q}${q}; }];`, false],
    ["javascript/db.js", `const check${Name} = async function (pw) { return api.check(pw); };`, false],
    ["includes/newFeature.php", `$${name} = fn($x) => $x;`, false],
    ["includes/newFeature.php", `$check${Name} = static function ($pw) { return strlen($pw) > 8; };`, false],
    // A map's literal key, inside a call, joined or formatted: what the map is handed to may return it.
    ["javascript/db.js", `const ${name} = Object.keys({${q}${value}${q}: process.env.X})[0];`, true],
    ["javascript/db.js", `const ${name} = Object.keys({ ${value}: process.env.X })[0];`, true],
    ["javascript/db.js", `const ${name} = Object.keys({ [${q}${value}${q}]: process.env.X })[0];`, true],
    ["javascript/db.js", `const ${name} = JSON.stringify({ ${value}: process.env.X });`, true],
    ["javascript/db.js", `const ${name} = Object.keys({ ${value} })[0];`, true],
    ["javascript/db.js", `const ${name} = decrypt({ iv, data });`, true],
    ["tools/ci/seed.py", `${name} = ${q}${q}.join({${q}${value}${q}: None})`, true],
    ["tools/ci/seed.py", `${name} = ${q}${q}.join(dict(${value}=None))`, true],
    ["tools/ci/seed.py", `${name} = "%s" % {${q}${value}${q}: x}`, true],
    ["tools/ci/seed.py", `${name} = f"{ {${q}${value}${q}: x} }"`, true],
    ["includes/newFeature.php", `$${name} = array_key_first([${q}${value}${q} => $x]);`, true],
    ["includes/newFeature.php", `$${name} = array_key_first(array(${q}${value}${q} => $x));`, true],
    ["includes/newFeature.php", `$${name} = json_encode([${q}${value}${q} => $x]);`, true],
    ["includes/newFeature.php", `$${name} = build(${value}: $x);`, true],
    ["includes/newFeature.php", `$${name} = uniqid(${q}${value}${q});`, true],
    // No literal key: a structure's keys name its fields, a known reader's keywords its parameters, a
    // variable or a constant is a reference, and an arrow's or a typed parameter is no key.
    ["includes/newFeature.php", `$${name} = [${q}k${q} => $x];`, false],
    ["includes/newFeature.php", `$${name} = array(${q}driver${q} => $x, ${q}user${q} => $u);`, false],
    ["javascript/db.js", `const ${name} = { value: process.env.X, meta: { source: process.env.Y } };`, false],
    ["tools/ci/seed.py", `${name} = {${q}value${q}: os.environ[${q}X${q}]}`, false],
    ["tools/ci/seed.py", `${name} = os.getenv(key=${q}X${q})`, false],
    ["includes/newFeature.php", `$${name} = array_key_first([$k => $x]);`, false],
    ["includes/newFeature.php", `$${name} = array_key_first([KEY => $x]);`, false],
    ["javascript/db.js", `const ${name} = decrypt(iv, data);`, false],
    ["javascript/db.js", `const ${name} = { iv, data };`, false],
    ["javascript/db.js", `const ${name} = parts.map(x => x.trim()).join(sep);`, false],
    ["api/client.ts", `const ${name} = await page.evaluate((sel: string) => document.querySelector(sel).value, sel);`, false],
  ]);
  // YAML's quoted key given nothing takes the lines after it, as a plain key does, and does not throw.
  for (const [texts, hits] of yamlNextCases(name, value)) assert.deepEqual(readOn("config/app.yml", texts), hits, texts.join(" / "));
});

/** YAML keys given nothing, whose value is on the lines after them: [texts from line 20, hits]. */
function yamlNextCases(name, value) {
  const q = "'";
  return [
    [[`  "${name}":`, `    \${{ secrets.DB }}`, `  user: x`], []],
    [[`  ${q}${name}${q}:`, `    type: string`, `  user: x`], []],
    [[`  ${name}:`, `    from: secrets`], []],
    [[`  "${name}":`, `    ${value}`, `  user: x`], [20]],
    [[`  "db_${name}":`, `    - ${value}`, `  user: x`], [20]],
  ];
}

test("production wiring, markerHits (station.mjs): a password spelling reads the change's lines on and back over the unchanged lines above a run, as its readsFrom() does, never one line alone", () => {
  // Built at run time, so this file holds no literal for the marker to find. Production must not read each line
  // alone where the spellings read on (readsFrom): markerHits hands a spelling's readsFrom() all of the entries it
  // is given, and never hits an unchanged line (context: true); check.mjs gives it those (the next test). Each case
  // here is a doors.mjs case above, read the way markerHits reads a change.
  const name = ["pass", "word"].join("");
  const value = `v${randomBytes(6).toString("hex")}`;
  for (const [path, lines, added, hits] of contextCases(name, value)) {
    assert.deepEqual(contextHit(path, lines, added), { hits, humanOnly: hits.length > 0 }, `${path}: ${lines.join(" / ")} (added ${added})`);
  }
  for (const [texts, hits] of yamlNextCases(name, value)) {
    assert.deepEqual(linesHit("config/app.yml", texts), { hits, humanOnly: hits.length > 0 }, texts.join(" / "));
  }
  // A field's declaration on a line above, and a set call opened on one.
  for (const [path, texts, hits] of declarationCases(name, value)) {
    assert.deepEqual(linesHit(path, texts), { hits, humanOnly: hits.length > 0 }, `${path}: ${texts.join(" / ")}`);
  }
  // A reference without a ";" whose statement ends where the change does (rental/items.js:22 and
  // rental/remote.js:95 at 5419eee2): read alone it might go on, read on it ended.
  for (const [path, texts, at] of [
    ["javascript/db.js", [`const ${name} = prompt("Indtast adgangskode for at fortsætte")`, `const result = await api.check(${name} ?? "")`], 0],
    ["javascript/db.js", [`const ${name} = document.querySelector("#${name}").value`], 0],
    ["includes/newFeature.php", [`$data = array(`, `\t'${name}' => $passwd`, `);`], 1],
  ]) {
    assert.ok(passwordSpellings(path).some((m) => m.pattern.test(texts[at])), `${path}: ${texts[at]} read alone counts`);
    assert.deepEqual(linesHit(path, texts), { hits: [], humanOnly: false }, `${path}: ${texts.join(" / ")}`);
  }
  // The base 5419eee2 through markerHits is the base through the spellings' readsFrom(): UNENDED_AT_BASE is no hit,
  // but where its line is read alone with a gap after it and its statement is open there (CUT_AT_BASE: the lines
  // below are not handed).
  const pattern = `${["pass", "(word|wd)"].join("")}['"]?[[:space:]]*:|${["pass", "(word|wd)"].join("")}[^=]*=`;
  const base = rowHitsAt("5419eee2", pattern, "secrets");
  assert.deepEqual(base, spellingHitsAt("5419eee2", pattern));
  for (const where of UNENDED_AT_BASE) assert.equal(base.includes(where), CUT_AT_BASE.includes(where), where);
});

/**
 * A change through `check.mjs --station`, as it routes one: each [path, base, head] file's base lines (null: the
 * change adds the file) are committed in a scratch repository and its head lines left in the working tree. The
 * password marker's hits as "path:line", and humanOnly.
 */
function stationOnChange(files) {
  const dir = mkdtempSync(join(tmpdir(), "guardrails-change-"));
  const git = (...args) => spawnSync("git", ["-c", "user.name=guardrails-test", "-c", "user.email=guardrails-test@example.invalid", "-c", "commit.gpgsign=false", ...args], { cwd: dir, encoding: "utf8" });
  const write = (path, lines) => {
    mkdirSync(dirname(join(dir, path)), { recursive: true });
    writeFileSync(join(dir, path), `${lines.join("\n")}\n`);
  };
  try {
    assert.equal(git("init", "-q").status, 0);
    for (const [path, base] of files) if (base) write(path, base);
    assert.equal(git("add", "-A").status, 0);
    assert.equal(git("commit", "-q", "--allow-empty", "--no-verify", "-m", "base").status, 0);
    for (const [path, , head] of files) write(path, head);
    const result = spawnSync(process.execPath, [CHECK, "--station", "--json", "--base", "HEAD"], { cwd: dir, encoding: "utf8", timeout: 30000 });
    assert.equal(result.status, 0, result.stderr);
    const report = JSON.parse(result.stdout);
    return { hits: report.markersHit.filter((hit) => hit.row === "secrets").map((hit) => `${hit.path}:${hit.line}`).sort(), humanOnly: report.humanOnly };
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

test("production wiring, check.mjs --station: each file's added lines go to markerHits after the unchanged lines above each run, so an edited continuation line counts, also where it names a password itself, and pure references without a ';' and YAML's quoted empty key read on and do not", () => {
  // Built at run time, so this file holds no literal for the marker to find. check.mjs hands markerHits each
  // file's added lines after the unchanged lines above each run (changeset markerLines), and markerHits hands the
  // password spellings all of them (readsFrom). Read alone, an edited continuation line names no password and
  // would never count, a reference left without a ";" would count (rental/*.js, #755), and a quoted YAML key
  // given nothing must not throw.
  const name = ["pass", "word"].join("");
  const NAME = name.toUpperCase();
  const Name = name.replace("p", "P");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  const fallback = (line) => [`const user = process.env.U;`, `const ${name} = process.env.X`, line, `run(${name});`];
  assert.deepEqual(
    stationOnChange([
      ["javascript/fallback.js", fallback(`  ?? ${q}${q};`), fallback(`  ?? ${q}${value}${q};`)],
      ["javascript/named.js", fallback(`  ?? ${q}${q};`), fallback(`  ?? ${q}${value}-${name}-value${q};`)],
      ["javascript/commented.js", fallback(`  ?? ${q}${q};`), fallback(`  ?? ${q}${value}${q}; // ${name} fallback`)],
      ["javascript/envName.js", fallback(`  ?? ${q}${q};`), fallback(`  ?? process.env.DB_${NAME}_FALLBACK + ${q}${value}${q};`)],
      ["includes/prefix.php", [`<?php`, `$${name} = $prefix`, `    . ${q}${q};`], [`<?php`, `$${name} = $prefix`, `    . ${q}${value}${q};`]],
      ["config/app.yml", null, [`db:`, `  "${name}":`, `    ${value}`, `  user: x`]],
      // A set call opened on a line above, and a getter's body over lines.
      ["javascript/setter.js", null, [`api.set(`, `  ${q}${name}${q},`, `  ${q}${value}${q}`, `);`]],
      ["javascript/getter.js", null, [`class Cfg {`, `  get ${name}() {`, `    return ${q}${value}${q};`, `  }`, `}`]],
    ]),
    {
      hits: [
        "config/app.yml:2",
        "includes/prefix.php:3",
        "javascript/commented.js:3",
        "javascript/envName.js:3",
        "javascript/fallback.js:3",
        "javascript/getter.js:2",
        "javascript/named.js:3",
        "javascript/setter.js:2",
      ],
      humanOnly: true,
    },
  );
  assert.deepEqual(
    stationOnChange([
      // rental/daysoff.js:8, rental/items.js:22 and rental/remote.js:95 at 5419eee2, in the same shapes.
      ["javascript/imports.js", null, [`const run = async () => {`, `    const { getSettings, checkSettings${Name} } = await import(\`/\${firstFolder}/rental/api/api.js\`)`, `    const settings = await getSettings()`, `}`]],
      ["javascript/prompt.js", null, [`const ${name} = prompt("Indtast adgangskode for at fortsætte")`, `const result = await api.checkSettings${Name}(${name} ?? "")`]],
      ["javascript/form.js", null, [`const username = document.querySelector("#username").value`, `const ${name} = document.querySelector("#${name}").value`, `const mail = {`, `  user: username,`, `}`]],
      // systemdata/diverseIncludes/create_vibrant_login.php:112 and systemdata/sys_div_func.php:1789.
      ["includes/login.php", null, [`<?php`, `$data = array(`, `\t'user' => $user,`, `\t${q}${name}${q} => $passwd`, `);`]],
      ["javascript/flatpay.js", null, [`fetch(url, { body: JSON.stringify({`, `  ${q}username${q}: document.getElementById(${q}u${q}).value,`, `  ${q}${name}${q}: document.getElementById(${q}flatpay-${name}${q}).value`, `}) });`]],
      ["config/quoted.yml", null, [`db:`, `  "${name}":`, `    \${{ secrets.DB }}`, `  user: x`]],
      // A reference's fallback edited to another reference.
      ["javascript/fallback.js", fallback(`  ?? ${q}${q};`), fallback(`  ?? process.env.DB_${NAME}_FALLBACK;`)],
      // A multiline schema and labels map, and a getter that returns a reference.
      ["javascript/schema.js", null, [`const schema = {`, `  user: ${q}varchar(64)${q},`, `  ${name}: ${q}varchar(255)${q},`, `};`]],
      ["includes/columns.php", null, [`<?php`, `$expectedColumns = [`, `    ${q}id${q} => ${q}SERIAL PRIMARY KEY${q},`, `    ${q}${name}${q} => ${q}varchar(255)${q},`, `];`]],
      ["javascript/labels.js", [`const labels = {`, `  ${name}: ${q}Kode${q},`, `};`], [`const labels = {`, `  ${name}: ${q}${Name}${q},`, `};`]],
      ["javascript/getter.js", null, [`class Cfg {`, `  get ${name}() {`, `    return process.env.X;`, `  }`, `}`]],
    ]),
    { hits: [], humanOnly: false },
  );
});

test("secrets, through markerHits and stationReport: a reader is known by its language and the call's form, so a method read through a type, a namespace or a class, a call through a variable or a namespace hands its arguments as values; a reader's name or option is no map, list or function", () => {
  // Built at run time, so this file holds no literal for the marker to find. A reader known by its callee's last
  // name alone would miss each hit: Python's unbound str.strip(x) would take x for strip's characters,
  // bytes.decode(b) for an encoding and list.pop(l) for a key, and JavaScript's Reflect.get(target, key) its
  // target for get's key; a key may also be a map or a list.
  const name = ["pass", "word"].join("");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  assertPasswordCases([
    // A method read through a Python built-in type is unbound: its first argument is self, a value.
    ["tools/ci/seed.py", `${name} = str.strip(${q}${value}${q})`, true],
    ["tools/ci/seed.py", `${name} = bytes.decode(b${q}${value}${q})`, true],
    ["tools/ci/seed.py", `${name} = list.pop([${q}${value}${q}])`, true],
    ["tools/ci/seed.py", `${name} = dict.pop(d, ${q}k${q}, ${q}${value}${q})`, true],
    ["tools/ci/seed.py", `${name} = str.lower(${q}${value}${q})`, true],
    // A method read through a class or a namespace is a function of all of its arguments.
    ["javascript/db.js", `const ${name} = Reflect.get({value: ${q}${value}${q}}, field);`, true],
    ["javascript/db.js", `const ${name} = _.get({a: ${q}${value}${q}}, ${q}a${q});`, true],
    ["javascript/db.js", `const ${name} = String.fromCharCode(104, 105);`, true],
    ["javascript/db.js", `const ${name} = Buffer.from(${q}${value}${q}, ${q}base64${q}).toString();`, true],
    ["tools/ci/seed.py", `${name} = Config.get(${q}${value}${q})`, true],
    ["includes/newFeature.php", `$${name} = Foo::get(${q}${value}${q});`, true],
    // A call through a variable, a namespaced function, a reader's name position given a map, a list or a function.
    ["includes/newFeature.php", `$${name} = $getenv(${q}${value}${q});`, true],
    ["includes/newFeature.php", `$${name} = Foo\\getenv(${q}${value}${q});`, true],
    ["includes/newFeature.php", `$${name} = getenv([${q}${value}${q}]);`, true],
    ["javascript/db.js", `const ${name} = cfg.get({a: ${q}${value}${q}});`, true],
    ["javascript/db.js", `const ${name} = cfg.get(() => ${q}${value}${q});`, true],
    ["javascript/db.js", `const ${name} = localStorage.getItem(${q}pw${q}) || ${q}${value}${q};`, true],
    ["includes/newFeature.php", `$${name} = $request->input(${q}${name}${q}, ${q}${value}${q});`, true],
    // Readers in their own forms stay no hit.
    ["tools/ci/seed.py", `${name} = os.environ.get(${q}X${q})`, false],
    ["tools/ci/seed.py", `${name} = dict.get(os.environ, ${q}X${q})`, false],
    ["tools/ci/seed.py", `${name} = CONFIG.get(${q}${name}${q})`, false],
    ["tools/ci/seed.py", `${name} = getpass.getpass(${q}Kode: ${q})`, false],
    ["javascript/db.js", `const ${name} = new URLSearchParams(location.search).get(${q}pw${q});`, false],
    ["javascript/db.js", `const ${name} = document.getElementById(\`pw-\${id}\`).value;`, false],
    ["includes/newFeature.php", `$${name} = $request->input(${q}${name}${q});`, false],
    ["includes/newFeature.php", `$${name} = TestCredentials::password(${q}restapi${q});`, false],
    ["includes/newFeature.php", `$${name} = \\getenv(${q}X${q});`, false],
    ["includes/newFeature.php", `$${name} = getenv(strtoupper(${q}db_pw${q}));`, false],
    ["includes/newFeature.php", `$${name} = getenv(${q}X${q}) ?: getenv(${q}Y${q});`, false],
    // PHP's own tokens read a PHP file's code as PHP's, so here document is a constant joined by "." to a call
    // no reader knows, whose argument counts. A page's script is JavaScript only inside a <script> element, where it is
    // read as JavaScript (doors-php-tokens.test.mjs: a page script's document.getElementById(...) is no hit).
    ["includes/newFeature.php", `var ${name} = document.getElementById(${q}pw${q}).value;`, true],
    ["includes/newFeature.php", `$${name} = $x . getenv(${q}X${q});`, false],
  ]);
});

test("secrets, through markerHits and stationReport: a function given to a name, a getter, a method and a declaration named by a password are what their bodies return and assign; a helper's function is no value, and a test of equality is a boolean", () => {
  // Built at run time, so this file holds no literal for the marker to find. A function that is all of a name's
  // value is what its body returns, and getters, methods and declarations are read for what they return.
  const name = ["pass", "word"].join("");
  const Name = name.replace("p", "P");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  assertPasswordCases([
    // A function given to a credential's name is what its body returns.
    ["javascript/db.js", `const ${name} = function () { return ${q}${value}${q}; };`, true],
    ["javascript/db.js", `const ${name} = () => { return ${q}${value}${q}; };`, true],
    ["javascript/db.js", `const ${name} = async () => { return ${q}${value}${q}; };`, true],
    ["javascript/db.js", `const ${name} = function* () { yield ${q}${value}${q}; };`, true],
    ["javascript/db.js", `module.exports.${name} = function () { return ${q}${value}${q}; };`, true],
    ["includes/newFeature.php", `$cfg[${q}${name}${q}] = function () { return ${q}${value}${q}; };`, true],
    // A getter, a method and a declaration: what they return, and what their bodies assign.
    ["javascript/db.js", `const cfg = { get ${name}() { return ${q}${value}${q}; } };`, true],
    ["javascript/db.js", `class A { static get ${name}() { return ${q}${value}${q}; } }`, true],
    ["javascript/db.js", `class A { ${name}() { return ${q}${value}${q}; } }`, true],
    ["javascript/db.js", `class A { async ${name}() { return ${q}${value}${q}; } }`, true],
    ["javascript/db.js", `const cfg = { ${name}() { return ${q}${value}${q}; } };`, true],
    ["javascript/db.js", `function get${Name}() { return ${q}${value}${q}; }`, true],
    ["javascript/db.js", `class A { get ${name}() { const x = ${q}${value}${q}; return x; } }`, true],
    ["api/client.ts", `class A { get ${name}(): string { return ${q}${value}${q}; } }`, true],
    ["api/client.ts", `class A { @Input() get ${name}() { return ${q}${value}${q}; } }`, true],
    ["includes/newFeature.php", `function ${name}() { return ${q}${value}${q}; }`, true],
    ["includes/newFeature.php", `public function get${Name}(): string { return ${q}${value}${q}; }`, true],
    ["tools/ci/seed.py", `def ${name}(self): return ${q}${value}${q}`, true],
    // Pure references, a helper's function, a setter, a call and a declaration without a body stay no hit.
    ["javascript/db.js", `const ${name} = function () { return process.env.X; };`, false],
    ["javascript/db.js", `class A { get ${name}() { return this._pw; } }`, false],
    ["javascript/db.js", `class A { set ${name}(v) { this._pw = v; } }`, false],
    ["javascript/db.js", `const validate${Name} = (v) => { return v.length > 8; };`, false],
    ["javascript/db.js", `if (${name}(x)) { run(); }`, false],
    ["javascript/db.js", `// we get ${name}() from the vault`, false],
    ["api/client.ts", `interface A { ${name}(): string; }`, false],
    ["includes/newFeature.php", `$${name} = function () { return getenv(${q}X${q}); };`, false],
    ["includes/newFeature.php", `function check${Name}($pw) { return $pw === ${q}${value}${q}; }`, false],
    ["includes/newFeature.php", `abstract public function ${name}(): string;`, false],
    // A test of equality is a boolean: jQuery 1.4's :password filter, a type check.
    ["javascript/db.js", `jQuery.filter = { ${name}: function(g){return"${name}"===g.type} };`, false],
    ["javascript/db.js", `const is${Name} = input.type === ${q}${name}${q};`, false],
    ["javascript/db.js", `const ${name} = a === b ? ${q}${value}${q} : c;`, true],
    ["api/client.ts", `const ${name} = useState<string>(${q}${value}${q});`, true],
  ]);
  // Over lines, read on through the change's next lines (spelling.readsFrom).
  for (const [path, texts, hits] of [
    ["javascript/db.js", [`function get${Name}() {`, `  return ${q}${value}${q};`, `}`], [20]],
    ["javascript/db.js", [`class A { get ${name}() {`, `    return process.env.X;`, `  }`, `}`], []],
    ["includes/newFeature.php", [`  public static function ${name}(): string`, `  {`, `    return ${q}${value}${q};`, `  }`], [20]],
    ["includes/newFeature.php", [`  public static function ${name}(): string`, `  {`, `    return TestCredentials::${name}(${q}restapi${q});`, `  }`], []],
    ["tools/ci/seed.py", [`class A:`, `    @property`, `    def ${name}(self):`, `        return ${q}${value}${q}`], [22]],
    ["tools/ci/seed.py", [`class A:`, `    @property`, `    def ${name}(self):`, `        return os.environ[${q}X${q}]`, ``], []],
    ["tools/ci/seed.py", [`    def get_${name}(self) -> str:`, `        x = ${q}${value}${q}`, `        return x`, `    def other(self):`, `        return 1`], [20]],
  ]) {
    assert.deepEqual(readOn(path, texts), hits, `${path}: ${texts.join(" / ")}`);
  }
});

test("secrets: a setter is read through spaces, comments and line breaks between its name and its arguments, and a set call opened on a line above takes the name and value on the lines after it", () => {
  // Built at run time, so this file holds no literal for the marker to find. A setter's name need not touch its
  // "(", and a line that hands set() a password's name need not open the call itself.
  const name = ["pass", "word"].join("");
  const Name = name.replace("p", "P");
  const NAME = name.toUpperCase();
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  assertPasswordCases([
    ["javascript/db.js", `api.set${Name} (${q}${value}${q});`, true],
    ["javascript/db.js", `api.set${Name}/* note */(${q}${value}${q});`, true],
    ["javascript/db.js", `api.set${Name}?.(${q}${value}${q});`, true],
    ["includes/newFeature.php", `$mail->set${Name} (${q}${value}${q});`, true],
    ["tools/ci/seed.py", `cfg.set_${name} (${q}${value}${q})`, true],
    ["javascript/db.js", `Object.defineProperty(cfg, ${q}${name}${q}, { value: ${q}${value}${q} });`, true],
    ["javascript/db.js", `Object.defineProperty(cfg, ${q}${name}${q}, { get() { return ${q}${value}${q}; } });`, true],
    ["javascript/db.js", `api.set${Name} (pw);`, false],
    ["javascript/db.js", `Object.defineProperty(cfg, ${q}${name}${q}, { value: process.env.X });`, false],
    // A password's name as a call's last argument is given nothing.
    ["javascript/db.js", `cache.set(${q}key${q}, ${q}${name}${q},);`, false],
  ]);
  for (const [path, texts, hits] of [
    ["javascript/db.js", [`api.set(`, `  ${q}${name}${q},`, `  ${q}${value}${q}`, `);`], [21]],
    ["javascript/db.js", [`api.set(`, `  ${q}${name}${q}, ${q}${value}${q});`], [21]],
    ["javascript/db.js", [`api.set${Name}`, `  (${q}${value}${q});`], [20]],
    ["includes/newFeature.php", [`$mail->set${Name}(`, `    ${q}${value}${q}`, `);`], [20]],
    ["tools/ci/seed.py", [`setattr(`, `    cfg,`, `    ${q}${name}${q},`, `    ${q}${value}${q},`, `)`], [22]],
    ["tools/ci/seed.py", [`os.environ.setdefault(`, `    "DB_${NAME}",`, `    ${q}${value}${q},`, `)`], [21]],
    ["includes/newFeature.php", [`define(`, `  ${q}DB_${NAME}${q},`, `  ${q}${value}${q}`, `);`], [21]],
    // References stay no hit, and so does a name handed to a call that sets nothing.
    ["javascript/db.js", [`fd.append(`, `  ${q}${name}${q},`, `  pw`, `);`], []],
    ["includes/newFeature.php", [`define(`, `  ${q}DB_${NAME}${q},`, `  getenv(${q}X${q})`, `);`], []],
    ["javascript/db.js", [`log(`, `  ${q}${name}${q},`, `  count`, `);`], []],
    ["javascript/db.js", [`cache.set(`, `  ${q}key${q},`, `  ${q}${name}${q},`, `);`], []],
  ]) {
    assert.deepEqual(readOn(path, texts), hits, `${path}: ${texts.join(" / ")}`);
  }
});

/** Schema and label declarations over lines: [path, texts from line 20, hits]. */
function declarationCases(name, value) {
  const Name = name.replace("p", "P");
  const q = "'";
  return [
    // A field of the declaration a line above opens, or of a map inside it.
    ["javascript/db.js", [`const schema = {`, `  ${name}: ${q}varchar(255)${q},`, `};`], []],
    ["javascript/db.js", [`const labels = {`, `  ${name}: ${q}${Name}${q},`, `};`], []],
    ["javascript/db.js", [`const schema = {`, `  user: { ${name}: ${q}varchar(255)${q} },`, `};`], []],
    ["includes/newFeature.php", [`$expectedColumns = [`, `    ${q}id${q} => ${q}SERIAL PRIMARY KEY${q},`, `    ${q}${name}${q} => ${q}varchar(255)${q},`, `];`], []],
    ["includes/newFeature.php", [`$labels = array(`, `    ${q}${name}${q} => ${q}Adgangskode${q},`, `);`], []],
    ["includes/newFeature.php", [`$rules = [`, `    ${q}user${q} => ${q}required${q},`, `    ${q}${name}${q} => ${q}required|min:8${q},`, `];`], []],
    // Another declaration's, a call's, a body's, a plain assignment's, and a closed declaration's neighbour count.
    ["javascript/db.js", [`const labels = {`, `  user: ${q}User${q},`, `};`, `const ${name} = ${q}${Name}${q};`], [23]],
    ["javascript/db.js", [`const labels = {`, `  user: ${q}User${q},`, `};`, `const cfg = {`, `  ${name}: ${q}${Name}${q},`, `};`], [24]],
    ["javascript/db.js", [`const schema = {`, `  x: login({`, `    ${name}: ${q}varchar(255)${q},`, `  }),`, `};`], [22]],
    ["javascript/db.js", [`function f() {`, `  const ${name} = ${q}${Name}${q};`, `}`], [21]],
    ["javascript/db.js", [`const labels = {`, `  f() {`, `    const ${name} = ${q}${Name}${q};`, `  },`, `};`], [22]],
    ["includes/newFeature.php", [`$columns = [`, `    $${name} = ${q}varchar(255)${q},`, `];`], [21]],
    ["javascript/db.js", [`const auth = {`, `  ${name}: ${q}varchar(255)${q},`, `};`], [21]],
    ["javascript/db.js", [`/*`, `const labels = {`, `*/`, `const cfg = {`, `  ${name}: ${q}${Name}${q},`, `};`], [24]],
    ["javascript/db.js", [`const ${name} = ${q}${value}${q};`], [20]],
  ];
}

test("secrets: a schema type or a label is none as a field of the declaration a line above opens, read over the change's lines and the unchanged lines above them, and counts beside another declaration, inside a call or a body, or as a plain assignment", () => {
  // Built at run time, so this file holds no literal for the marker to find. A field's declaration need not stand
  // on the field's own line: a multiline schema, labels map or $expectedColumns array is no hit on its fields.
  const name = ["pass", "word"].join("");
  const value = `v${randomBytes(6).toString("hex")}`;
  for (const [path, texts, hits] of declarationCases(name, value)) {
    assert.deepEqual(readOn(path, texts), hits, `${path}: ${texts.join(" / ")}`);
  }
  // The same declarations with only the field's line edited: its declaration is an unchanged line above.
  const q = "'";
  for (const [path, file, added, hits] of [
    ["javascript/db.js", [`const schema = {`, `  user: ${q}varchar(64)${q},`, `  ${name}: ${q}varchar(255)${q},`, `};`], [3], []],
    ["includes/newFeature.php", [`$expectedColumns = [`, `    ${q}id${q} => ${q}SERIAL PRIMARY KEY${q},`, `    ${q}${name}${q} => ${q}varchar(255)${q},`, `];`], [3], []],
    ["javascript/db.js", [`const auth = {`, `  user: ${q}u${q},`, `  ${name}: ${q}varchar(255)${q},`, `};`], [3], [3]],
    ["javascript/db.js", [`api.set(`, `  ${q}${name}${q},`, `  ${q}${value}${q}`, `);`], [3], [3]],
  ]) {
    assert.deepEqual(readWithContext(path, file, added), hits, `${path}: ${file.join(" / ")} (added ${added})`);
  }
  // Read alone (pattern.test), a field has no declaration but its own line's: it counts.
  assert.equal(readAlone("javascript/db.js", `  ${name}: ${q}varchar(255)${q},`), true);
});

test("secrets, through markerHits and stationReport: a reader's roles are bound to its receiver, so a module's function that shares a transform's name, and a transform on a receiver no table understands, hand their arguments as values", () => {
  // Built at run time, so this file holds no literal for the marker to find. A transform known by its method's name
  // on any receiver but a type, a class or a namespace would miss each hit: Python's codecs.decode(b'...') would take
  // the bytes for decode's encoding, shlex.split('...') and os.path.split('...') the text for split's separator, and
  // pw.split(/,/) the pattern for an option.
  const name = ["pass", "word"].join("");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  assertPasswordCases([
    // A module's function, or a transform on a receiver that may be a module: its arguments are values.
    ["tools/ci/seed.py", `${name} = codecs.decode(b${q}${value}${q})`, true],
    ["tools/ci/seed.py", `${name} = codecs.decode(${q}${value}${q}, ${q}rot13${q})`, true],
    ["tools/ci/seed.py", `${name} = codecs.encode(${q}${value}${q}, ${q}rot13${q})`, true],
    ["tools/ci/seed.py", `${name} = codecs.lookup(${q}rot13${q}).decode(b${q}${value}${q})[0]`, true],
    ["tools/ci/seed.py", `${name} = shlex.split(${q}${value} x${q})[0]`, true],
    ["tools/ci/seed.py", `${name} = os.path.split(${q}/x/${value}${q})[1]`, true],
    ["tools/ci/seed.py", `${name} = re.split(${q},${q}, ${q}${value},x${q})[0]`, true],
    ["tools/ci/seed.py", `${name} = raw.decode(${q}utf-8${q})`, true],
    ["tools/ci/seed.py", `${name} = os.environment.split(${q}${value}${q})[0]`, true],
    ["javascript/db.js", `const ${name} = pw.split(/,/)[0];`, true],
    ["javascript/db.js", `const ${name} = codec.split(${q}${value}${q})[0];`, true],
    ["javascript/db.js", `const ${name} = this.codec.slice(${q}${value}${q});`, true],
    ["javascript/db.js", `const ${name} = window.prompt(${q}Kode${q}, ${q}${value}${q});`, true],
    ["javascript/db.js", `const ${name} = process.binding(${q}x${q}).split(${q}${value}${q})[0];`, true],
    ["javascript/db.js", `const ${name} = Buffer.from(${q}${value}${q}, ${q}base64${q}).toString();`, true],
    // Readers in the forms their table names stay no hit: a lookup on any instance, a module's function by its
    // whole path, a transform on the runtime's own objects or the environment, on what a known module function
    // returns, or through a built-in type.
    ["tools/ci/seed.py", `${name} = os.environ.get(${q}X${q})`, false],
    ["tools/ci/seed.py", `${name} = environ.get(${q}X${q})`, false],
    ["tools/ci/seed.py", `${name} = os.getenv(${q}X${q})`, false],
    ["tools/ci/seed.py", `${name} = request.args.get(${q}${name}${q})`, false],
    ["tools/ci/seed.py", `${name} = getpass.getpass(${q}Kode: ${q})`, false],
    ["tools/ci/seed.py", `${name} = secrets.token_hex(16)`, false],
    ["tools/ci/seed.py", `${name} = secrets.token_urlsafe(nbytes=16)`, false],
    ["tools/ci/seed.py", `${name} = os.urandom(16)`, false],
    ["tools/ci/seed.py", `${name} = str.strip(pw)`, false],
    ["tools/ci/seed.py", `${name} = bytes.decode(raw, ${q}utf-8${q})`, false],
    ["tools/ci/seed.py", `${name} = raw.decode()`, false],
    ["tools/ci/seed.py", `${name} = os.environ[${q}X${q}].split(${q},${q})[0]`, false],
    ["tools/ci/seed.py", `${name} = os.environ.get(${q}X${q}).strip(${q} ${q})`, false],
    ["tools/ci/seed.py", `${name} = os.getenv(${q}X${q}).split(${q},${q})[0]`, false],
    ["javascript/db.js", `const ${name} = window.location.pathname.split(${q}/${q})[1];`, false],
    ["javascript/db.js", `const ${name} = document.getElementById(${q}pw${q}).value.split(${q},${q})[0];`, false],
    ["javascript/db.js", `const ${name} = document.cookie.split(${q}; ${q})[0];`, false],
    ["javascript/db.js", `const ${name} = crypto.randomBytes(16).toString(${q}hex${q});`, false],
    ["javascript/db.js", `const ${name} = process.env.X.split(${q},${q})[0];`, false],
    ["javascript/db.js", `const ${name} = Buffer.from(process.env.X, ${q}base64${q}).toString();`, false],
    ["javascript/db.js", `const ${name} = params.get(${q}pw${q});`, false],
    ["javascript/db.js", `const ${name} = window.prompt(${q}Kode${q});`, false],
    ["javascript/db.js", `const ${name} = pw.split();`, false],
    ["includes/newFeature.php", `$${name} = $request->input(${q}${name}${q});`, false],
    // PHP's code, as above; the same line in a page's <script> element is JavaScript's and no hit.
    ["includes/newFeature.php", `var ${name} = document.getElementById(${q}pw${q}).value.split(${q},${q})[0];`, true],
  ]);
  // Over lines, read on through the change's next lines (spelling.readsFrom).
  for (const [path, texts, hits] of [
    ["tools/ci/seed.py", [`${name} = codecs.decode(`, `    b${q}${value}${q}`, `)`], [20]],
    ["tools/ci/seed.py", [`${name} = os.environ.get(`, `    ${q}X${q}`, `)`], []],
  ]) {
    assert.deepEqual(readOn(path, texts), hits, `${path}: ${texts.join(" / ")}`);
  }
});

test("secrets, through markerHits and stationReport: a function's parameter defaults are values, in a closure, an arrow, PHP's fn and an accessor; a reference default and a helper's function stay no hit", () => {
  // Built at run time, so this file holds no literal for the marker to find. A function's parameter list is read:
  // a call without the argument returns the default, so const <name> = function (v = '...') { return v; } gives a
  // literal.
  const name = ["pass", "word"].join("");
  const Name = name.replace("p", "P");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  assertPasswordCases([
    ["javascript/db.js", `const ${name} = function (v = ${q}${value}${q}) { return v; };`, true],
    ["javascript/db.js", `const ${name} = function ({ v = ${q}${value}${q} } = {}) { return v; };`, true],
    ["javascript/db.js", `const ${name} = function (a, b = run(${q}${value}${q})) { return b; };`, true],
    ["javascript/db.js", `function get${Name}(v = ${q}${value}${q}) { return v; }`, true],
    ["javascript/db.js", `function ${name}(v = ${q}${value}${q}) { return v; }`, true],
    ["javascript/db.js", `class A { ${name}(v = ${q}${value}${q}) { return v; } }`, true],
    ["javascript/db.js", `const cfg = { ${name}(v = ${q}${value}${q}) { return v; } };`, true],
    ["javascript/db.js", `const cfg = { get${Name}: function (v = ${q}${value}${q}) { return v; } };`, true],
    ["api/client.ts", `function get${Name}(v: string = ${q}${value}${q}): string { return v; }`, true],
    ["includes/newFeature.php", `$${name} = fn($v = ${q}${value}${q}) => $v;`, true],
    ["includes/newFeature.php", `$${name} = static fn(string $v = ${q}${value}${q}) => $v;`, true],
    ["includes/newFeature.php", `$${name} = function ($v = ${q}${value}${q}) { return $v; };`, true],
    ["includes/newFeature.php", `$${name} = function ($v = ${q}${value}${q}) use ($x) { return $v; };`, true],
    ["includes/newFeature.php", `$${name} = run(fn($v = ${q}${value}${q}) => $v);`, true],
    ["includes/newFeature.php", `function get${Name}($v = ${q}${value}${q}) { return $v; }`, true],
    ["includes/newFeature.php", `public static function ${name}(string $v = ${q}${value}${q}): string { return $v; }`, true],
    ["tools/ci/seed.py", `def ${name}(self, v=${q}${value}${q}): return v`, true],
    ["tools/ci/seed.py", `def get_${name}(self, *, v: str = ${q}${value}${q}): return v`, true],
    // A reference or a blank default, no default, and a helper's function stay no hit.
    ["javascript/db.js", `const ${name} = function (v = process.env.X) { return v; };`, false],
    ["javascript/db.js", `function get${Name}({ v = process.env.X } = {}) { return v; }`, false],
    ["javascript/db.js", `class A { ${name}(v = this.fallback) { return v; } }`, false],
    ["includes/newFeature.php", `$${name} = fn($v = null) => $v;`, false],
    ["includes/newFeature.php", `$${name} = fn($v = ${q}${q}) => $v;`, false],
    ["includes/newFeature.php", `function get${Name}($v = self::FALLBACK) { return $v; }`, false],
    ["includes/newFeature.php", `$check${Name} = fn($pw, $min = 8) => strlen($pw) >= $min;`, false],
    ["javascript/db.js", `const validate${Name} = function (v, min = 8) { return v.length >= min; };`, false],
    ["tools/ci/seed.py", `def ${name}(self, v=None): return v`, false],
  ]);
  // Over lines, read on through the change's next lines (spelling.readsFrom).
  for (const [path, texts, hits] of [
    ["tools/ci/seed.py", [`def ${name}(`, `    self,`, `    v=${q}${value}${q},`, `):`, `    return v`], [20]],
    ["javascript/db.js", [`function get${Name}(`, `  v = ${q}${value}${q},`, `) {`, `  return v;`, `}`], [20]],
    ["javascript/db.js", [`function get${Name}(`, `  v = process.env.X,`, `) {`, `  return v;`, `}`], []],
  ]) {
    assert.deepEqual(readOn(path, texts), hits, `${path}: ${texts.join(" / ")}`);
  }
});

test("secrets, through markerHits and stationReport: a quoted or computed name is a JavaScript method, getter, class field or key, read as the word is; a reference stays no hit", () => {
  // Built at run time, so this file holds no literal for the marker to find. An accessor's name may be quoted or
  // computed, and a computed key ({ ['...']: ... }) is read as a word is.
  const name = ["pass", "word"].join("");
  const Name = name.replace("p", "P");
  const q = "'";
  const bq = "\x60";
  const value = `v${randomBytes(6).toString("hex")}`;
  assertPasswordCases([
    ["javascript/db.js", `const cfg = { get ${q}${name}${q}() { return ${q}${value}${q}; } };`, true],
    ["javascript/db.js", `const cfg = { get "${name}"() { return ${q}${value}${q}; } };`, true],
    ["javascript/db.js", `const cfg = { ${q}${name}${q}() { return ${q}${value}${q}; } };`, true],
    ["javascript/db.js", `const cfg = { async ${q}${name}${q}() { return ${q}${value}${q}; } };`, true],
    ["javascript/db.js", `const cfg = { get [${q}${name}${q}]() { return ${q}${value}${q}; } };`, true],
    ["javascript/db.js", `class A { [${q}${name}${q}]() { return ${q}${value}${q}; } }`, true],
    ["javascript/db.js", `class A { [${bq}${name}${bq}]() { return ${q}${value}${q}; } }`, true],
    ["javascript/db.js", `class A { static get [${q}${name}${q}]() { return ${q}${value}${q}; } }`, true],
    ["javascript/db.js", `class A { *[${q}${name}${q}]() { yield ${q}${value}${q}; } }`, true],
    ["javascript/db.js", `class A { [${q}${name}${q}](v = ${q}${value}${q}) { return v; } }`, true],
    ["javascript/db.js", `const cfg = { [${q}${name}${q}]: ${q}${value}${q} };`, true],
    ["javascript/db.js", `const cfg = { [${q}db_${name}${q}]: ${q}${value}${q} };`, true],
    ["javascript/db.js", `const cfg = { [${q}db_${q} + ${q}${name}${q}]: ${q}${value}${q} };`, true],
    ["javascript/db.js", `cfg[${q}db_${q} + ${q}${name}${q}] = ${q}${value}${q};`, true],
    ["javascript/db.js", `class A { [${q}${name}${q}] = ${q}${value}${q}; }`, true],
    ["javascript/db.js", `class A { ${q}${name}${q} = ${q}${value}${q}; }`, true],
    ["javascript/db.js", `class A { static ${q}${name}${q} = ${q}${value}${q}; }`, true],
    ["includes/newFeature.php", `$cfg[${q}db_${q} . ${q}${name}${q}] = ${q}${value}${q};`, true],
    ["tools/ci/seed.py", `cfg[${q}db_${q} + ${q}${name}${q}] = ${q}${value}${q}`, true],
    // The next member on the same line, after a block's "}" or a statement's ";", word or quoted.
    ["javascript/db.js", `class A { x() {} ${name}() { return ${q}${value}${q}; } }`, true],
    ["javascript/db.js", `class A { x() {} ${q}${name}${q}() { return ${q}${value}${q}; } }`, true],
    ["javascript/db.js", `class A { x() {} [${q}${name}${q}]() { return ${q}${value}${q}; } }`, true],
    ["api/client.ts", `class A { x = 1; ${name}: string = ${q}${value}${q}; }`, true],
    ["tools/ci/seed.py", `x = 1; ${name}: str = ${q}${value}${q}`, true],
    // A reference, a helper's name, a list of names and a label its declaration names stay no hit.
    ["javascript/db.js", `class A { x() {} ${name}() { return process.env.X; } }`, false],
    ["api/client.ts", `class A { x = 1; ${name}: string = process.env.X; }`, false],
    ["javascript/db.js", `if (a) { run(); } ${name}(x);`, false],
    ["javascript/db.js", `const cfg = { get ${q}${name}${q}() { return process.env.X; } };`, false],
    ["javascript/db.js", `class A { [${q}${name}${q}]() { return this._pw; } }`, false],
    ["javascript/db.js", `const cfg = { [${q}${name}${q}]: process.env.X };`, false],
    ["javascript/db.js", `class A { [${q}${name}${q}] = process.env.X; }`, false],
    ["javascript/db.js", `const cfg = { ${q}validate${Name}${q}() { return true; } };`, false],
    ["javascript/db.js", `const keys = [${q}${name}${q}, ${q}user${q}];`, false],
    ["includes/newFeature.php", `$labels[${q}db_${q} . ${q}${name}${q}] = ${q}Adgangskode${q};`, false],
  ]);
  // Over lines, read on through the change's next lines (spelling.readsFrom).
  for (const [path, texts, hits] of [
    ["javascript/db.js", [`const cfg = {`, `  get ${q}${name}${q}() {`, `    return ${q}${value}${q};`, `  },`, `};`], [21]],
    ["javascript/db.js", [`class A {`, `  [${q}${name}${q}]() {`, `    return process.env.X;`, `  }`, `}`], []],
  ]) {
    assert.deepEqual(readOn(path, texts), hits, `${path}: ${texts.join(" / ")}`);
  }
});

test("production wiring, check.mjs --station: over lines, a module's function, a parameter default and a quoted getter count; the same shapes with references do not", () => {
  // Built at run time, so this file holds no literal for the marker to find. Each file's statement runs over lines,
  // so only markerHits reading on through spelling.readsFrom (check.mjs handing it changeset markerLines) sees it.
  const name = ["pass", "word"].join("");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  assert.deepEqual(
    stationOnChange([
      ["tools/ci/codec.py", null, [`${name} = codecs.decode(`, `    b${q}${value}${q},`, `)`]],
      ["includes/defaults.php", null, [`<?php`, `$${name} = fn(`, `    $v = ${q}${value}${q},`, `) => $v;`]],
      ["javascript/getter.js", null, [`const cfg = {`, `  get ${q}${name}${q}() {`, `    return ${q}${value}${q};`, `  },`, `};`]],
      ["javascript/computed.js", null, [`const cfg = {`, `  [${q}${name}${q}]:`, `    ${q}${value}${q},`, `};`]],
    ]),
    { hits: ["includes/defaults.php:2", "javascript/computed.js:2", "javascript/getter.js:2", "tools/ci/codec.py:1"], humanOnly: true },
  );
  assert.deepEqual(
    stationOnChange([
      ["tools/ci/codec.py", null, [`${name} = os.environ.get(`, `    ${q}X${q},`, `)`]],
      ["includes/defaults.php", null, [`<?php`, `$${name} = fn(`, `    $v = null,`, `) => $v ?? getenv(${q}X${q});`]],
      ["javascript/getter.js", null, [`const cfg = {`, `  get ${q}${name}${q}() {`, `    return process.env.X;`, `  },`, `};`]],
      ["javascript/computed.js", null, [`const cfg = {`, `  [${q}${name}${q}]:`, `    process.env.X,`, `};`]],
      ["javascript/split.js", null, [`const parts = {`, `  ${name}: window.location.pathname`, `    .split(${q}/${q})[1],`, `};`]],
    ]),
    { hits: [], humanOnly: false },
  );
});

/**
 * The change `added` (line numbers) to `file` as check.mjs hands it to markerHits, with at most `before` unchanged
 * lines above each run (changeset addedWithContext hands all of them by default); the password marker's lines through
 * markerHits and stationReport, and the same lines through the spellings' readsFrom() alone.
 */
function farHit(path, file, added, before) {
  file = codeFile(path, file);
  const entries = addedWithContext(new Map(added.map((n) => [n, file[n - 1]])), `${file.join("\n")}\n`, before);
  const hits = markerHits(path, entries).filter((hit) => hit.row === "secrets").map((hit) => hit.line);
  const read = entries.flatMap((entry, i) => (!entry.context && passwordSpellings(path).some((m) => m.readsFrom(entries, i)) ? [entry.line] : []));
  assert.deepEqual(read, hits, `${path}: readsFrom() and markerHits agree`);
  const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, entries]]) });
  return { hits, humanOnly: report.humanOnly };
}

/**
 * An unchanged statement that names a password, a distance of `n` lines, and the change's line that goes on with it.
 * [label, path, file, added, hits]; a reader that read back a fixed 40 lines would miss each hit for n >= 40.
 */
function farCases(name, value, n) {
  const q = "'";
  const lit = `${q}${value}${q}`;
  const blank = Array(n).fill("");
  const last = (file) => [file.length];
  const cases = [];
  const add = (label, path, file, added, hits) => cases.push([`${label} (${n})`, path, file, added, hits]);
  // An unchanged const password = process.env.X, n blank lines, an added literal fallback.
  const fallback = [`const ${name} = process.env.X`, ...blank, `  ?? ${lit};`];
  add("blank lines, a fallback", "javascript/db.js", fallback, last(fallback), last(fallback));
  const php = [`<?php`, `$${name} = getenv(${q}X${q})`, ...blank, `    ?: ${lit};`];
  add("PHP's ?:", "includes/newFeature.php", php, last(php), last(php));
  const py = [`${name} = (`, `    os.environ["X"]`, ...blank, `    or ${lit})`];
  add("Python in brackets", "tools/ci/seed.py", py, last(py), last(py));
  const chain = [`const ${name} = process.env.X`, ...Array.from({ length: n }, (_, i) => `  || process.env.Y${i}`), `  ?? ${lit};`];
  add("a chain of references", "javascript/db.js", chain, last(chain), last(chain));
  // The change adds an item, or a return, and the bracket that closes it.
  const items = [`<?php`, `$${name} = implode(${q}${q}, [`, ...Array(n).fill(`    $a,`), `    ${lit},`, `]);`];
  add("an array's items", "includes/newFeature.php", items, [items.length - 1, items.length], [items.length - 1]);
  const body = [`class Cfg {`, `  get ${name}() {`, ...Array(n).fill(`    log(this);`), `    return ${lit};`, `  }`, `}`];
  add("a getter's body", "javascript/db.js", body, [body.length - 2, body.length - 1], [body.length - 2]);
  const key = [`<?php`, `$cfg = [`, `    ${q}db_${name}${q} => getenv(${q}X${q})`, ...blank, `        . ${lit},`, `];`];
  add("a key's value", "includes/newFeature.php", key, [key.length - 1], [key.length - 1]);
  // The same shapes with a reference, a statement that ended, and another statement between, are not.
  const ref = [`const ${name} = process.env.X`, ...blank, `  ?? process.env.Y;`];
  add("a reference fallback", "javascript/db.js", ref, last(ref), []);
  const ended = [`const ${name} = process.env.X;`, ...blank, `const label = ${lit};`];
  add("a statement that ended", "javascript/db.js", ended, last(ended), []);
  const between = [`const ${name} = process.env.X`, `const user = process.env.U`, ...blank, `  ?? ${lit};`];
  add("another statement between", "javascript/db.js", between, last(between), []);
  const itemsRef = [`<?php`, `$${name} = implode(${q}${q}, [`, ...Array(n).fill(`    $a,`), `    $b,`, `]);`];
  add("an array's reference item", "includes/newFeature.php", itemsRef, [itemsRef.length - 1, itemsRef.length], []);
  const bodyRef = [`class Cfg {`, `  get ${name}() {`, ...Array(n).fill(`    log(this);`), `    return process.env.X;`, `  }`, `}`];
  add("a getter's reference", "javascript/db.js", bodyRef, [bodyRef.length - 2, bodyRef.length - 1], []);
  return cases;
}

test("secrets, through markerHits and stationReport: an added line that goes on with a statement an unchanged line starts counts however far up that line is, and the same shapes with references do not", () => {
  // Built at run time, so this file holds no literal for the marker to find. A run's first line that read back at
  // most 40 lines (a fixed window) and counted nothing past it would miss an unchanged const password =
  // process.env.X, 40 blank lines and an added ?? '...', while 39 would be read. The distances straddle such a window:
  // every hit at 40 lines and more, and Python's at 39 (its name is a line further up), needs the whole read back;
  // the other hits at 39 and the references are the controls.
  const name = ["pass", "word"].join("");
  const value = `v${randomBytes(6).toString("hex")}`;
  for (const n of [39, 40, 41, 250]) {
    for (const [label, path, file, added, hits] of farCases(name, value, n)) {
      assert.deepEqual(farHit(path, file, added), { hits, humanOnly: hits.length > 0 }, label);
    }
  }
});

test("secrets: where a limit stops the reader from finding a statement's start, the run counts: a statement open past STATEMENT_CHARS, and an added literal that goes on with a statement above the lines markerHits was handed", () => {
  // Built at run time, so this file holds no literal for the marker to find. What the reader cannot read counts:
  // a caller that hands 200 lines above a run, a patch's few lines or a gap in them, and a statement whose
  // unchanged lines run past what the reader reads. A statement that starts in the lines handed, a pure reference
  // and a fresh statement stay no hit.
  const name = ["pass", "word"].join("");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  const lit = `${q}${value}${q}`;
  const blank = Array(250).fill("");
  const cases = [
    // 200 lines handed above the run, the statement's start 251 lines up.
    ["a fallback", "javascript/db.js", [`const ${name} = process.env.X`, ...blank, `  ?? ${lit};`], 200, true],
    ["a chain", "javascript/db.js", [`const ${name} = process.env.X`, ...Array(250).fill(`  || process.env.Y`), `  ?? ${lit};`], 200, true],
    ["an array's items", "includes/newFeature.php", [`<?php`, `$${name} = implode(${q}${q}, [`, ...Array(250).fill(`    $a,`), `    ${lit},`], 200, true],
    ["a bracket that closes above", "includes/newFeature.php", [`<?php`, `$${name} = f(`, ...Array(250).fill(`    $a,`), `    $b) . ${lit};`], 200, true],
    ["Python in brackets", "tools/ci/seed.py", [`${name} = (`, `    os.environ["X"]`, ...Array(250).fill(`    or os.environ["Y"]`), `    or ${lit})`], 200, true],
    ["a reference fallback", "javascript/db.js", [`const ${name} = process.env.X`, ...blank, `  ?? process.env.Y;`], 200, false],
    ["a fresh statement", "javascript/db.js", [`const ${name} = process.env.X;`, ...blank, `const label = ${lit};`], 200, false],
    ["a statement in the lines handed", "includes/newFeature.php", [`<?php`, ...blank, `$sql = "select * from x"`, `    . " where a = ${q}b${q}";`], 200, false],
    // No line handed above the run: a line that goes on with one counts, one that may start a statement does not.
    ["a fallback alone", "includes/newFeature.php", [`<?php`, `$${name} = getenv(${q}X${q})`, `    ?: ${lit};`], 0, true],
    ["a reference alone", "includes/newFeature.php", [`<?php`, `$${name} = getenv(${q}X${q})`, `    ?: getenv(${q}Y${q});`], 0, false],
    ["a statement alone", "includes/newFeature.php", [`<?php`, `$x = 1;`, `$y = ${lit};`], 0, false],
    ["HTML alone", "includes/newFeature.php", [`<?php`, `?>`, `<input type="${name}" placeholder="Kode">`], 0, false],
    ["a block's end alone", "includes/newFeature.php", [`<?php`, `if ($a) {`, `} elseif ($b) $msg = "Kode udløbet";`], 0, false],
  ];
  for (const [label, path, file, before, hit] of cases) {
    const hits = hit ? [file.length] : [];
    assert.deepEqual(farHit(path, file, [file.length], before), { hits, humanOnly: hit }, `${label}: ${before} lines handed`);
  }
  // A gap in the lines handed: the line above the gap is not read on into.
  const gap = [{ line: 1, text: `$${name} = getenv(${q}X${q})`, context: true }, { line: 3, text: `    ?: getenv(${q}Y${q})`, context: true }];
  for (const [text, hit] of [[`    ?: ${lit};`, true], [`    ?: getenv(${q}Z${q});`, false]]) {
    const entries = [...gap, { line: 4, text }];
    assert.deepEqual(markerHits("includes/newFeature.php", entries).filter((h) => h.row === "secrets").map((h) => h.line), hit ? [4] : [], text);
  }
  // A statement still open past STATEMENT_CHARS of unchanged lines counts whatever the change adds to it; within it,
  // the same reference and close are read: the statement 300 lines long.
  const open = (n) => [`const ${name} = [`, ...Array(n).fill(`  process.env.${"X".repeat(80)},`), `  process.env.Y,`, `];`];
  assert.deepEqual(farHit("javascript/db.js", open(3000), [3002, 3003]), { hits: [3002], humanOnly: true });
  assert.deepEqual(farHit("javascript/db.js", open(300), [302, 303]), { hits: [], humanOnly: false });
  // Long minified lines: a statement that ends within STATEMENT_CHARS is read to its end, not counted for the lines after it.
  const minified = [`var ${name}=f(a,${"b,".repeat(10000)}`, `c);${"x=1;".repeat(5000)}`, ...Array(28).fill("y=2;".repeat(5000)), "z=3;"];
  assert.deepEqual(farHit("javascript/app.js", minified, [31]), { hits: [], humanOnly: false });
});

test("production wiring, changeset.mjs and check.mjs --station: every unchanged line above a run is handed to markerHits, so an edited continuation 250 lines below its statement counts, and a reference there does not", () => {
  // Built at run time, so this file holds no literal for the marker to find. With 200 lines handed or 40 read back,
  // an edit 40 or 250 blank lines below its statement would be no hit.
  const name = ["pass", "word"].join("");
  const NAME = name.toUpperCase();
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  const file = Array.from({ length: 300 }, (_, i) => `line ${i + 1}`);
  const entries = addedWithContext(new Map([[300, file[299]]]), `${file.join("\n")}\n`);
  assert.equal(entries.length, 300);
  assert.deepEqual([entries[0].line, entries[0].context, entries[299].context], [1, true, undefined]);
  assert.equal(addedWithContext(new Map([[300, file[299]]]), `${file.join("\n")}\n`, 200).length, 201, "a caller may still hand fewer");
  const far = (n, line) => [`const user = process.env.U;`, `const ${name} = process.env.X`, ...Array(n).fill(""), line, `run(${name});`];
  assert.deepEqual(
    stationOnChange([
      ["javascript/probe.js", far(40, `  ?? ${q}${q};`), far(40, `  ?? ${q}${value}${q};`)],
      ["javascript/far.js", far(250, `  ?? ${q}${q};`), far(250, `  ?? ${q}${value}${q};`)],
      ["includes/far.php", [`<?php`, `$${name} = getenv(${q}X${q})`, ...Array(250).fill(""), `    ?: ${q}${q};`], [`<?php`, `$${name} = getenv(${q}X${q})`, ...Array(250).fill(""), `    ?: ${q}${value}${q};`]],
    ]),
    { hits: ["includes/far.php:253", "javascript/far.js:253", "javascript/probe.js:43"], humanOnly: true },
  );
  assert.deepEqual(
    stationOnChange([
      ["javascript/probe.js", far(40, `  ?? ${q}${q};`), far(40, `  ?? process.env.DB_${NAME}_FALLBACK;`)],
      ["javascript/far.js", far(250, `  ?? ${q}${q};`), far(250, `  ?? process.env.Y;`)],
      ["javascript/ended.js", [`const ${name} = process.env.X;`, ...Array(250).fill(""), `const a = 1;`], [`const ${name} = process.env.X;`, ...Array(250).fill(""), `const a = ${q}${value}${q};`]],
    ]),
    { hits: [], humanOnly: false },
  );
});

test("markers stay linear: reading back over every unchanged line, 1,000 runs below 2,000 statements that name a password, or below one long open statement, grow no faster than linearly (linear-time.mjs), and count as the reader decides: distant continuations and the fail-closed limits", () => {
  // Built at run time, so this file holds no literal for the marker to find. Each run's first line reads back to every
  // mention above it in its block; one whose statement ended is passed at once (endedLinks), and one still open is read
  // in windows from what is known open (openThrough). The hits are asserted, so a reader that finds none fails: the
  // literal fallback 1,010 lines below its statement, the runs past READ_BACK_CHARS and the ninth open statement.
  // 1,000 runs below one statement 4,000 lines long are read within READ_BACK_CHARS, not each again from its start.
  const name = ["pass", "word"].join("");
  const q = "'";
  const lit = `${q}v${randomBytes(6).toString("hex")}${q}`;
  const hitsOf = (path, file, added) => markerHits(path, addedWithContext(added, `${file.join("\n")}\n`)).filter((hit) => hit.row === "secrets").map((hit) => hit.line);
  // 2,000 statements, each run below one of them: a call left open, so each run counts where the change leaves it
  // open; a call that ended; a reference whose next line does not go on with it.
  for (const [line, counts] of [
    [`const ${name} = f(${"a, ".repeat(600)}`, true],
    [`const ${name} = f(${"a, ".repeat(600)});`, false],
    [`const ${name} = process.env.X`, false],
  ]) {
    const statements = (n) => {
      const added = new Map();
      for (let k = 2; k <= n; k += 2) added.set(k, `  x,`);
      return [Array(n).fill(line), added];
    };
    const hits = linear(line.slice(0, 40), (n) => hitsOf("javascript/db.js", ...statements(n)), 2000);
    assert.deepEqual(hits, counts ? [...statements(2000)[1].keys()] : [], line.slice(0, 40));
  }
  // One statement, 100 reference fallbacks 10 lines apart below it, and a literal fallback 1,010 lines below it: only
  // the literal counts.
  const fallbacks = (n) => {
    const near = [`const ${name} = process.env.X`];
    const added = new Map();
    for (let i = 0; i < n; i++) {
      near.push(...Array(9).fill(""), `  ?? process.env.Y${i}`);
      added.set(near.length, near.at(-1));
    }
    near.push(...Array(9).fill(""), `  ?? ${lit};`);
    added.set(near.length, near.at(-1));
    return [near, added];
  };
  assert.deepEqual(linear("100 fallbacks", (n) => hitsOf("javascript/db.js", ...fallbacks(n)), 100), [fallbacks(100)[0].length]);
  // 1,000 reference fallbacks 4 lines apart, below a statement 4,000 lines long: once the file's runs have read back
  // READ_BACK_CHARS in all, each later run counts, the last one with them; the first 200 are read and do not.
  const longStatement = (n) => {
    const far = [`const ${name} = process.env.X`];
    const runs = [];
    for (let i = 0; i < n; i++) {
      far.push("", "", "", `  ?? process.env.Y${i}`);
      runs.push(far.length);
    }
    return [far, runs];
  };
  const runs = longStatement(1000)[1];
  const farHits = linear("1,000 runs below one statement", (n) => {
    const [far, at] = longStatement(n);
    return hitsOf("javascript/db.js", far, new Map(at.map((k) => [k, far[k - 1]])));
  }, 1000);
  assert.ok(farHits.length > 0 && farHits.length < 800, `${farHits.length} runs counted`);
  assert.deepEqual(farHits, runs.slice(runs.length - farHits.length), "the runs past the limit, each");
  // OPEN_STATEMENTS: eight statements open around a run are each read on, and a ninth makes the run count.
  for (const [n, hit] of [
    [8, false],
    [9, true],
    [30, true],
  ]) {
    const js = [...Array.from({ length: n }, (_, i) => `${name} = process.env.X${i} ||`), `  process.env.Y;`];
    assert.deepEqual(hitsOf("javascript/db.js", js, new Map([[js.length, js.at(-1)]])), hit ? [js.length] : [], `${n} open`);
    const php = ["<?php", ...Array.from({ length: n }, (_, i) => `$${name} = getenv(${q}X${i}${q}) ?:`), `  getenv(${q}Y${q});`];
    assert.deepEqual(hitsOf("includes/newFeature.php", php, new Map([[php.length, php.at(-1)]])), hit ? [php.length] : [], `PHP, ${n} open`);
  }
});

/** TypeScript declarations whose value is a literal, [text, ...] on one line each. */
function typeScriptHits(name, lit) {
  const q = "'";
  return [
    // Optional and definite markers, on a word, a quoted and a computed name, with modifiers.
    `  ${name}?: string = ${lit};`,
    `  ${name}!: string = ${lit};`,
    `  ${q}${name}${q}?: string = ${lit};`,
    `  ["${name}"]?: string = ${lit};`,
    `  [\x60${name}\x60]?: string = ${lit};`,
    `  private ${name}?: string = ${lit};`,
    `  static readonly ${name}?: string = ${lit};`,
    // A whole field type before its initializer: commas in type arguments, an object type, a union over lines.
    `  ${name}: Map<string, string> = ${lit};`,
    `  ${name}?: Record<string, string> = ${lit};`,
    `  ${name}?: Array<Map<string, number>> = ${lit};`,
    `let ${name}: Map<string, string> = ${lit};`,
    `let ${name}: { a: string, b: number } = ${lit};`,
    // Generic parameter lists before a function's, a method's or an optional method's parameters.
    `function ${name}<T>() { return ${lit}; }`,
    `function ${name}<T extends string = string>(): T { return ${lit} as T; }`,
    `export function ${name}<K, V>(m: Map<K, V>): string { return ${lit}; }`,
    `async function ${name}<T>(): Promise<string> { return ${lit}; }`,
    `function* ${name}<T>() { yield ${lit}; }`,
    `  ${name}<T>(): T { return ${lit} as T; }`,
    `  async ${name}<T>(): Promise<T> { return ${lit} as T; }`,
    `  ${q}${name}${q}<T>() { return ${lit}; }`,
    `  [${q}${name}${q}]<T>() { return ${lit}; }`,
    `  ${name}?(): string { return ${lit}; }`,
    `  ${name}?<T>(): string { return ${lit}; }`,
    // Complete return types before a body.
    `  get ${name}(): string & { length: number } { return ${lit}; }`,
    `  get ${name}(): { a: string } { return ${lit}; }`,
    `  get ${name}(): Map<string, string> { return ${lit}; }`,
    `  get ${q}${name}${q}(): string | { x: 1 } { return ${lit}; }`,
    `  get [${q}${name}${q}](): (() => string) | null { return ${lit}; }`,
    `function ${name}(): Map<string, string> { return ${lit}; }`,
    `function ${name}(): [string, number] { return [${lit}, 1]; }`,
    `function ${name}(): typeof cfg[${q}k${q}] { return ${lit}; }`,
    `function ${name}(): keyof T { return ${lit}; }`,
    // Parameter defaults behind a marker or a whole type, and parameter properties.
    `function f(${name}?: string = ${lit}) {}`,
    `const f = (${name}?: string = ${lit}) => 1;`,
    `function f(${name}: Map<string, string> = ${lit}) {}`,
    `  constructor(private ${name}?: string = ${lit}) {}`,
    `  constructor(private readonly ${name}: Map<string, string> = ${lit}) {}`,
    // An arrow's return type and generic parameters, and a function expression's.
    `const ${name} = (): Map<string, string> => ${lit};`,
    `const ${name} = async <T,>(x: T): Promise<string> => ${lit};`,
    `const ${name} = <T>(x: T): string => ${lit};`,
    `const ${name} = function (): Map<string, string> { return ${lit}; };`,
    `const ${name} = function <T>(): string { return ${lit}; };`,
    // A decorator or a member modifier before a typed field.
    `  @Input() ${name}: string = ${lit};`,
    `  @Input() ${name}?: string = ${lit};`,
    `  @Input ${name}: Map<string, string> = ${lit};`,
    `  override ${name}: Map<string, string> = ${lit};`,
    `  accessor ${name}: Map<string, string> = ${lit};`,
    // Syntax the reader cannot read counts: an unclosed generic list or type, a close glued to "=", a stray word.
    `function ${name}<T() { return x; }`,
    `  get ${name}(): Map<string { return x; }`,
    `function ${name}(): Map<string { return x; }`,
    `  ${name}?: Foo<T>= x;`,
    `let ${name}: Foo<T>= x;`,
    `  ${name}?: string extends x = y;`,
  ];
}

/** The same declarations with references, and the shapes a declaration's reading must not take for one. */
function typeScriptReferences(name) {
  const q = "'";
  return [
    `  ${name}?: string;`,
    `  ${name}!: string;`,
    `  ${name}?: string = process.env.X;`,
    `  ${q}${name}${q}?: string;`,
    `  [${q}${name}${q}]?: Map<string, string>;`,
    `  ${name}?: Map<string, string> = new Map();`,
    `  ${name}: string | undefined = undefined;`,
    `  ${name}?: () => string = () => cfg.p;`,
    `function ${name}<T>(x: T): T { return x; }`,
    `  ${name}<T>(): T { return this.p as T; }`,
    `  get ${name}(): string & { length: number } { return this.p; }`,
    `  get ${name}(): Map<string, string> { return this.m; }`,
    `function ${name}(): Map<string, string>;`,
    `  abstract ${name}(): string;`,
    `function f(${name}?: string) { return 1; }`,
    `const f = (${name}?: string) => ${name} ?? fallback;`,
    `function f(${name}: Map<string, string> = defaults) {}`,
    `  constructor(private ${name}?: string) {}`,
    `const ${name} = (): Map<string, string> => cfg.p;`,
    `const ${name} = <T>(k: T): string => cfg[k];`,
    `let ${name}: Map<string, string> = new Map();`,
    `const ${name}: string = process.env.X!;`,
    `const ${name} = process.env.X as string;`,
    `  @Input() ${name}: string;`,
    `  @Input() ${name}?: string = cfg.pw;`,
    `type Creds = { ${name}?: string; user: string };`,
    `function f({ ${name} }: { ${name}?: string }) { return ${name}; }`,
    // A ternary, a comparison and a generic-looking comparison are no declaration.
    `const v = ok ? ${name} : other;`,
    `x = ${name} < limit;`,
    `if (${name} < min || ${name} > max) { run(); }`,
    `  ${name} < max && run();`,
  ];
}

test("secrets, through markerHits and stationReport: TypeScript's optional and definite markers, generic parameter lists, whole types, arrow return types, modifiers and decorators are read before a value, and a declaration the reader cannot read counts; the same shapes with references do not", () => {
  // Built at run time, so this file holds no literal for the marker to find. A name followed by "?", "!" or "<"
  // still has a target, a return type does not end at its first comma or "{", nor a field's type at its first
  // comma: password?: string = '...', function password<T>() {...} and get password(): string & { length: number }
  // {...} are hits, and a generic arrow that returns cfg[k] is a reference.
  const name = ["pass", "word"].join("");
  const q = "'";
  const lit = `${q}v${randomBytes(6).toString("hex")}${q}`;
  for (const path of ["api/client.ts", "api/client.tsx", "api/client.mts"]) {
    assertPasswordCases([...typeScriptHits(name, lit).map((text) => [path, text, true]), ...typeScriptReferences(name).map((text) => [path, text, false])]);
  }
  // Python's generic parameters (def password[T](): ...) are read the same way.
  assertPasswordCases([
    ["tools/ci/seed.py", `def ${name}[T](self) -> str: return ${lit}`, true],
    ["tools/ci/seed.py", `def ${name}[T](self) -> str: return self._pw`, false],
  ]);
  // Over lines: a type and its initializer on the next line, a union that goes on, an interface's members.
  for (const [texts, hits] of [
    [[`  ${name}?: string`, `    = ${lit};`], [20]],
    [[`  ${name}?: string |`, `    undefined = ${lit};`], [20]],
    [[`let ${name}: string`, `  = ${lit};`], [20]],
    [[`  get ${name}(): Map<string, string>`, `  {`, `    return ${lit};`, `  }`], [20]],
    [[`interface Creds {`, `  ${name}?: string`, `  user?: string`, `}`], []],
    [[`class Creds {`, `  ${name}?: Map<string, string>`, `    = new Map();`, `}`], []],
  ]) {
    assert.deepEqual(linesHit("api/client.ts", texts), { hits, humanOnly: hits.length > 0 }, texts.join(" / "));
  }
});

test("production wiring, check.mjs --station: TypeScript declarations with markers, generic lists and whole types count in a .ts file, and the same declarations with references do not", () => {
  // Built at run time, so this file holds no literal for the marker to find.
  const name = ["pass", "word"].join("");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  assert.deepEqual(
    stationOnChange([
      ["api/optional.ts", null, [`export class Creds {`, `  ${name}?: string = ${q}${value}${q};`, `}`]],
      ["api/generic.ts", null, [`export function ${name}<T>(): string {`, `  return ${q}${value}${q};`, `}`]],
      ["api/getter.ts", null, [`export class Creds {`, `  get ${name}(): string & { length: number } {`, `    return ${q}${value}${q};`, `  }`, `}`]],
      ["api/computed.ts", null, [`export class Creds {`, `  [${q}${name}${q}]?: Map<string, string> = ${q}${value}${q};`, `}`]],
    ]),
    { hits: ["api/computed.ts:2", "api/generic.ts:1", "api/getter.ts:2", "api/optional.ts:2"], humanOnly: true },
  );
  assert.deepEqual(
    stationOnChange([
      ["api/optional.ts", null, [`export class Creds {`, `  ${name}?: string = process.env.X;`, `}`]],
      ["api/generic.ts", null, [`export function ${name}<T>(x: T): T {`, `  return x;`, `}`]],
      ["api/getter.ts", null, [`export class Creds {`, `  get ${name}(): string & { length: number } {`, `    return this.p;`, `  }`, `}`]],
      ["api/computed.ts", null, [`export interface Creds {`, `  [${q}${name}${q}]?: Map<string, string>;`, `}`]],
    ]),
    { hits: [], humanOnly: false },
  );
});

/**
 * TypeScript declarations the text ends inside, over the change's lines from line 20. hits: [path, texts, hit lines];
 * references: [path, texts].
 */
function unfinishedCases(name, lit) {
  const q = "'";
  const ts = "api/client.ts";
  const py = "tools/ci/seed.py";
  return {
    hits: [
      // A generic method's list over lines, on a word, a quoted and computed names, after a modifier.
      [ts, [`class C { ${name}<`, `  T`, `>(): string { return ${lit}; } }`], [20]],
      [ts, [`class C { ${q}${name}${q}<`, `  T`, `>(): string { return ${lit}; } }`], [20]],
      [ts, [`class C { [${q}${name}${q}]<`, `  T`, `>(): string { return ${lit}; } }`], [20]],
      [ts, [`class C { [\x60${name}\x60]<`, `  T`, `>(): string { return ${lit}; } }`], [20]],
      [ts, [`class C {`, `  ${name}<`, `    T,`, `  >(): string {`, `    return ${lit};`, `  }`, `}`], [21]],
      [ts, [`class C {`, `  async ${name}<`, `    T`, `  >(): Promise<string> { return ${lit}; }`, `}`], [21]],
      [ts, [`class C {`, `  static ${q}${name}${q}<T`, `  >() { return ${lit}; }`, `}`], [21]],
      [ts, [`class C { ${name}?<`, `  T`, `>(): string { return ${lit}; } }`], [20]],
      [ts, [`function ${name}<`, `  T`, `>(x: T): string { return ${lit}; }`], [20]],
      // A method's parameters, or its body, on the next lines.
      [ts, [`class C {`, `  ${name}(`, `    a = ${lit},`, `  ) { return a; }`, `}`], [21]],
      [ts, [`class C {`, `  ${name}()`, `  {`, `    return ${lit};`, `  }`, `}`], [21]],
      [ts, [`class C {`, `  get ${name}():`, `    string {`, `    return ${lit};`, `  }`, `}`], [21]],
      [ts, [`class C {`, `  get ${name}()`, `  {`, `    return ${lit};`, `  }`, `}`], [21]],
      [ts, [`function ${name}(`, `  x: string = ${lit},`, `): string {`, `  return x;`, `}`], [20]],
      // A field's or an arrow's type over lines.
      [ts, [`  ${name}?: Map<string,`, `    string> = ${lit};`], [20]],
      [ts, [`  @Input() ${name}: Map<string,`, `    string> = ${lit};`], [20]],
      [ts, [`const ${name} = <T,`, `>(x: T): string => ${lit};`], [20]],
      [ts, [`const ${name} = (): Map<string,`, `  string> => ${lit};`], [20]],
      [py, [`def ${name}[`, `    T`, `](self) -> str:`, `    return ${lit}`], [20]],
      // Where the change ends with the declaration still open, it counts.
      [ts, [`class C { ${name}<`], [20]],
      [ts, [`class C { ${name}?<`], [20]],
      [ts, [`function ${name}<T`], [20]],
      [ts, [`  async ${name}(`], [20]],
      [ts, [`  get ${name}():`], [20]],
      [ts, [`  @Input() ${name}: Map<string,`], [20]],
    ],
    references: [
      [ts, [`class C { ${name}<`, `  T`, `>(): string { return this.p; } }`]],
      [ts, [`class C { ${q}${name}${q}<`, `  T`, `>(): string { return this.p; } }`]],
      [ts, [`class C { [${q}${name}${q}]<`, `  T`, `>(): string { return this.p; } }`]],
      [ts, [`function ${name}<`, `  T`, `>(x: T): T { return x; }`]],
      [ts, [`class C {`, `  get ${name}():`, `    string {`, `    return this.p;`, `  }`, `}`]],
      [ts, [`class C {`, `  get ${name}():`, `    Map<string, string> {`, `    return this.m;`, `  }`, `}`]],
      [ts, [`class C {`, `  ${name}(`, `    a = cfg.x,`, `  ) { return a; }`, `}`]],
      [ts, [`class C {`, `  ${name}()`, `  {`, `    return this.p;`, `  }`, `}`]],
      [ts, [`class C {`, `  get ${name}()`, `  : string {`, `    return this.p;`, `  }`, `}`]],
      [ts, [`function ${name}(`, `  x: string,`, `): string {`, `  return x;`, `}`]],
      [ts, [`function ${name}<T extends`, `  string>(x: T): T { return x; }`]],
      [ts, [`class C {`, `  ${name}<T>(`, `    x: T,`, `  ): T {`, `    return x;`, `  }`, `}`]],
      [ts, [`interface I {`, `  ${name}?:`, `    string;`, `}`]],
      [ts, [`interface I {`, `  ${name}:`, `    Map<string, string>;`, `}`]],
      [ts, [`  ${name}?: Map<string,`, `    string> = new Map();`]],
      [ts, [`const ${name} = <T,`, `>(x: T): string => cfg.p;`]],
      [ts, [`const ${name} = (): Map<string,`, `  string> => cfg.p;`]],
      [ts, [`const ${name} = (`, `  x: string,`, `): string => x;`]],
      [ts, [`const ${name} = async <T,>(`, `  x: T,`, `): Promise<T> => x;`]],
      [py, [`def ${name}[`, `    T`, `](self) -> str:`, `    return self._pw`]],
      [ts, [`if (${name} <`, `    max) {`, `  run();`, `}`]],
      // Where the change ends, a name that may be a call's or a comparison's, or a signature, is read on only.
      [ts, [`  ${name}(`]],
      [ts, [`if (${name} <`]],
      [ts, [`  ${name}(): string`]],
    ],
  };
}

test("secrets, through markerHits and stationReport: a TypeScript declaration the text ends inside is read on through the change's next lines before it is decided, on a word, a quoted and a computed name, and counts where the change ends with it open; the same shapes with references do not", () => {
  // Built at run time, so this file holds no literal for the marker to find. A generic list, a type, a method's
  // parameters or its body still to come are read before the declaration is decided, not on its first line: a
  // multi-line generic method, a method's parameters or body on the next lines and a field's type over lines count,
  // while a generic function and getters whose reference-only return type starts on the next line, a generic
  // constraint, an interface's and a field's type over lines, generic arrows that return a reference and Python's
  // generic def over lines do not.
  const name = ["pass", "word"].join("");
  const lit = `'v${randomBytes(6).toString("hex")}'`;
  const { hits, references } = unfinishedCases(name, lit);
  for (const [path, texts, lines] of hits) assert.deepEqual(linesHit(path, texts), { hits: lines, humanOnly: true }, texts.join(" / "));
  for (const [path, texts] of references) assert.deepEqual(linesHit(path, texts), { hits: [], humanOnly: false }, texts.join(" / "));
});

/**
 * `text` split in two at `k`: false where TypeScript allows no line break there (inside a token or a string, before
 * "=>", "++" or "--", after return, throw, yield, break or continue, before a type's arguments, a "[]" or a non-null
 * "!" after a name, inside a decorator), or where the reader is known to read the split otherwise: a call's or a
 * subscript's bracket on the next line after a name counts (readValue reads it as no reference, fail closed).
 */
function splitsAt(text, k) {
  const a = text.slice(0, k);
  const b = text.slice(k);
  const at = a.at(-1);
  const nx = b[0];
  if (!a.trim() || !b.trim()) return false;
  if (!(at === " " || nx === " " || /\w/.test(at) !== /\w/.test(nx))) return false;
  for (const quote of ["'", '"', "\x60"]) if ((a.split(quote).length - 1) % 2 === 1) return false;
  if (/^\s*(=>|\+\+|--)/.test(b) || /(\breturn|\bthrow|\byield|\bbreak|\bcontinue|\+\+|--)\s*$/.test(a)) return false;
  if (/[=!<>?.&|:+\-*]$/.test(a) && /^[=>&|?.:]/.test(b)) return false;
  if (/[\w)]\s*$/.test(a) && /^\s*[<[!(]/.test(b)) return false;
  return !/@\s*\w*\s*$/.test(a);
}

test("secrets: each of the TypeScript declarations above, split over two and over three lines wherever TypeScript allows a line break, reads as the line does", () => {
  // Built at run time, so this file holds no literal for the marker to find. A declaration is decided once its lines
  // are read, not on its first line, in each of the 1,047 two-line and 6,639 three-line splits. Not split: where
  // TypeScript reads a line break otherwise (splitsAt), and before a declaration's name when only its keyword stands
  // on the line above (the name must stand on a text's first line: let, const, function or a decorator alone on a
  // line).
  const name = ["pass", "word"].join("");
  const lit = `'v${randomBytes(6).toString("hex")}'`;
  const hit = (texts) => markerHits("api/client.ts", texts.map((text, i) => ({ line: 20 + i, text }))).some((h) => h.row === "secrets");
  const all = typeScriptHits(name, lit);
  const read = [...all.map((text) => [text, true]), ...typeScriptReferences(name).map((text) => [text, false])];
  const misread = [];
  let splits = 0;
  for (const [text, want] of read) {
    const first = (k) => !/^\s*(let|const|function|@\w*(\(\))?)\s*$/.test(text.slice(0, k));
    for (let x = 1; x < text.length; x++) {
      if (!splitsAt(text, x) || !first(x)) continue;
      splits++;
      if (hit([text.slice(0, x).trimEnd(), `  ${text.slice(x).trimStart()}`]) !== want) misread.push([text.slice(0, x), text.slice(x)]);
      for (let y = x + 1; y < text.length; y++) {
        if (!splitsAt(text, y) || !splitsAt(text.slice(x), y - x)) continue;
        splits++;
        const lines = [text.slice(0, x).trimEnd(), `  ${text.slice(x, y).trim()}`, `  ${text.slice(y).trimStart()}`];
        if (hit(lines) !== want) misread.push(lines);
      }
    }
  }
  assert.equal(splits, 1047 + 6639);
  assert.deepEqual(misread, []);
});

test("secrets, through markerHits and stationReport: a bare literal at the top of the lines a caller hands shows no statement starting there, so an added literal continuation counts in JavaScript, PHP and Python, while a fresh statement, a key, a block's end and HTML there stay no hit", () => {
  // Built at run time, so this file holds no literal for the marker to find. A line that starts with a string does
  // not show its statement starting there: a password's declaration whose "=" ends its line, 250 blank lines and an
  // added literal line count with every line above handed, with 200 and with none (startsAbove).
  const name = ["pass", "word"].join("");
  const q = "'";
  const lit = `${q}v${randomBytes(6).toString("hex")}${q}`;
  const blank = Array(250).fill("");
  const js = "javascript/db.js";
  const php = "includes/newFeature.php";
  const cases = [
    // A declaration whose "=" ends its line, 250 blank lines, and an added literal: every line above, 200 or none.
    [js, [`const ${name} =`, ...blank, `  ${lit};`], true],
    [js, [`const ${name} =`, ...blank, `  ${lit}`], true],
    [js, [`const ${name} =`, ...blank, `  "v${randomBytes(4).toString("hex")}";`], true],
    [js, [`const ${name} =`, ...blank, `  \x60v${randomBytes(4).toString("hex")}\x60;`], true],
    [js, [`const ${name} =`, ...blank, `  ${lit} + suffix;`], true],
    [js, [`let ${name} =`, ...blank, `  123456789;`], true],
    [php, [`<?php`, `$${name} =`, ...blank, `    ${lit};`], true],
    [php, [`<?php`, `$${name} =`, ...blank, `    "v${randomBytes(4).toString("hex")}";`], true],
    [php, [`<?php`, `$${name} =`, ...blank, `    ${lit} . $suffix;`], true],
    [php, [`<?php`, `$cfg[${q}db_${name}${q}] =`, ...blank, `    ${lit};`], true],
    ["tools/ci/seed.py", [`${name} = (`, ...blank, `    ${lit})`], true],
    // A literal in brackets, a regular expression, a ternary's branch, and a name joined to a literal: no statement
    // worth writing either (bareValue).
    [js, [`const ${name} =`, ...blank, `  (${lit});`], true],
    [js, [`const ${name} =`, ...blank, `  [${lit}];`], true],
    [js, [`const ${name} =`, ...blank, `  /v[a-z]+0/;`], true],
    [js, [`const ${name} = ok ?`, ...blank, `  ${lit} : other;`], true],
    [js, [`const ${name} =`, ...blank, `  fallback ?? ${lit};`], true],
    [js, [`const ${name} =`, ...blank, `  cfg.prefix + ${lit};`], true],
    [php, [`<?php`, `$${name} =`, ...blank, `    $prefix . ${lit};`], true],
    [php, [`<?php`, `$${name} =`, ...blank, `    $cfg->prefix . ${lit};`], true],
    ["tools/ci/seed.py", [`${name} = (`, ...blank, `    prefix + ${lit})`], true],
    ["tools/ci/seed.py", [`${name} = (`, ...blank, `    x if c else ${lit})`], true],
    // A statement that starts in the lines handed, a key given a reference or a literal (an item may start there), a
    // computed key, a return, a block's end, HTML.
    [js, [`const ${name} = process.env.X;`, ...blank, `const label = ${lit};`], false],
    [php, [`<?php`, `$x = 1;`, ...blank, `$y = ${lit};`], false],
    [php, [`<?php`, `$cfg = [`, ...blank, `    ${q}${name}${q} => $pw,`], false],
    // PHP's own tokens read this as PHP's code, where document.getElementById(...) is a constant joined to a
    // call no reader knows: its argument counts.
    [php, [`<?php`, `$cfg = [`, ...blank, `    ${q}${name}${q}: document.getElementById(${q}pw${q}).value`], true],
    [js, [`const cfg = {`, ...blank, `  ${q}${name}${q}: process.env.X,`], false],
    [js, [`const cfg = {`, ...blank, `  ${q}k${q}: ${lit},`], false],
    [php, [`<?php`, `$cfg = [`, ...blank, `    ${q}k${q} => ${lit},`], false],
    ["api/client.ts", [`class C {`, ...blank, `  [${q}${name}${q}]?: Map<string, string>;`], false],
    [php, [`<?php`, `if ($a) {`, ...blank, `    return ${lit};`], false],
    [php, [`<?php`, `?>`, ...blank, `Adgangskode <input type="${name}" name="pw">`], false],
    [php, [`<?php`, `if ($a) {`, ...blank, `} elseif ($b) $msg = "Kode udløbet";`], false],
    [php, [`<?php`, `?>`, ...blank, `<input type="${name}" placeholder="Kode">`], false],
  ];
  for (const [path, file, hit] of cases) {
    for (const before of [undefined, 200, 0]) {
      assert.deepEqual(farHit(path, file, [file.length], before), { hits: hit ? [file.length] : [], humanOnly: hit }, `${file.at(-1)}: ${before ?? "every"} line(s) handed`);
    }
  }
  // A bare literal at the top of what was handed, after a statement that ended above it, counts where that end is
  // not handed: the reader cannot see it.
  const ended = [`const ${name} = process.env.X;`, ...blank, `  ${lit};`];
  assert.deepEqual(farHit(js, ended, [ended.length]), { hits: [], humanOnly: false }, "every line handed");
  for (const before of [200, 0]) assert.deepEqual(farHit(js, ended, [ended.length], before), { hits: [ended.length], humanOnly: true }, `${before} lines handed`);
  // What is left: a call at the top of what was handed may start a statement, so a literal handed to it counts only
  // where the declaration above is handed too.
  const call = [`const ${name} =`, ...blank, `  run(${lit});`];
  assert.deepEqual(farHit(js, call, [call.length]), { hits: [call.length], humanOnly: true }, "every line handed");
  for (const before of [200, 0]) assert.deepEqual(farHit(js, call, [call.length], before), { hits: [], humanOnly: false }, `a call, ${before} lines handed`);
});

/**
 * TypeScript declarations that give a password a value after a type, [label, (type, value) => text]. `methods` are a
 * key's method returns, read only with the types the reader reads.
 */
function typedDeclarations(name) {
  const q = "'";
  const forms = [
    ["a class field", (t, v) => `class C { ${name}: ${t} = ${v}; }`],
    ["a single-quoted field", (t, v) => `class C { ${q}${name}${q}: ${t} = ${v}; }`],
    ["a double-quoted field", (t, v) => `class C { "${name}": ${t} = ${v}; }`],
    ["a computed field", (t, v) => `class C { [${q}${name}${q}]: ${t} = ${v}; }`],
    ["a #private field", (t, v) => `class C { #${name}: ${t} = ${v}; }`],
    ["a decorated field", (t, v) => `class C { @Input() ${name}: ${t} = ${v}; }`],
    ["a decorated quoted field", (t, v) => `class C { @Input() ${q}${name}${q}: ${t} = ${v}; }`],
    ["an accessor field", (t, v) => `class C { accessor ${name}: ${t} = ${v}; }`],
    ["an override field", (t, v) => `class C { override ${name}: ${t} = ${v}; }`],
    ["a private field", (t, v) => `class C { private ${name}: ${t} = ${v}; }`],
    ["a private quoted field", (t, v) => `class C { private ${q}${name}${q}: ${t} = ${v}; }`],
    ["an optional field", (t, v) => `class C { ${name}?: ${t} = ${v}; }`],
    ["a field over lines", (t, v) => `class C {\n  ${name}: ${t}\n    = ${v};\n}`],
    ["a parameter's default", (t, v) => `function f(${name}: ${t} = ${v}) {}`],
    ["a second parameter's default", (t, v) => `function f(a: string, ${name}: ${t} = ${v}) {}`],
    ["an arrow parameter's default", (t, v) => `const f = (${name}: ${t} = ${v}) => 0;`],
    ["a method parameter's default", (t, v) => `class C { m(${name}: ${t} = ${v}) {} }`],
    ["a parameter property's default", (t, v) => `class C { constructor(private ${name}: ${t} = ${v}) {} }`],
    ["a decorated parameter's default", (t, v) => `class C { constructor(@Inject() ${name}: ${t} = ${v}) {} }`],
    ["a let", (t, v) => `let ${name}: ${t} = ${v};`],
    ["a function's return", (t, v) => `function ${name}(): ${t} { return ${v}; }`],
    ["a getter's return", (t, v) => `class C { get ${name}(): ${t} { return ${v}; } }`],
    ["an arrow's return", (t, v) => `const ${name} = (): ${t} => ${v};`],
    ["a generic arrow's return", (t, v) => `const ${name} = <T,>(x: T): ${t} => ${v};`],
    ["an async arrow's return", (t, v) => `const ${name} = async (): ${t} => ${v};`],
  ];
  const methods = [
    ["a method's return", (t, v) => `class C { ${name}(): ${t} { return ${v}; } }`],
    ["a quoted method's return", (t, v) => `class C { ${q}${name}${q}(): ${t} { return ${v}; } }`],
  ];
  return { forms, methods };
}

test("secrets, through markerHits and stationReport: a conditional's constrained infer (infer U extends C) is read as TypeScript reads it in every declaration form, and a declaration whose type the reader cannot read counts wherever an initializer, a default or an arrow's => follows it; the same shapes with references, and keys whose value is no type, do not", () => {
  // Built at run time, so this file holds no literal for the marker to find. The extends clause of a conditional reads
  // no conditional of its own: if it did, infer U extends string ? U : never would take the clause's "?" and leave the
  // type unread, and a 'v...' after it in a class field (class C { password: string extends infer U extends string ? U
  // : never = '...' }), a quoted, computed, #private, decorated, accessor or override field, a parameter's default or
  // an arrow's return type would be no hit. The readable
  // types are TypeScript (TypeScript 5.9 parses each without a diagnostic); the unread ones are not ("'?' expected",
  // "Expected '='"), and a type the reader cannot read counts with a reference too. Its initializer cannot hide inside
  // a group, so a declaration with an unread type and no "=" in its item gives no value. A key's method whose return
  // type the reader reads whole and something other than its body follows (password(): (A) B {...}, never TypeScript)
  // is read as a call, as a ternary's branch over lines is, and is left out.
  const name = ["pass", "word"].join("");
  const q = "'";
  const lit = `${q}v${randomBytes(6).toString("hex")}${q}`;
  const ts = "api/client.ts";
  const { forms, methods } = typedDeclarations(name);
  const readable = [
    "string extends infer U extends string ? U : never",
    "T extends infer U extends string | number ? U : never",
    "T extends infer U extends string ? 1 : 0 extends X ? 2 : 3",
    // Outside an extends clause a "?" after the constraint makes the "extends" a conditional's, as TypeScript reads it.
    "infer U extends string ? U : never",
    // A group is passed over whole (a control).
    "T extends [infer U extends string] ? U : never",
  ];
  const unread = ["T extends () => infer R extends string ? R : never", "string extends string extends string ? 1 : 0", "(A) B", "A ?? B"];
  const at = (text) => linesHit(ts, text.split("\n"));
  const hitAt = (text) => ({ hits: [20 + text.split("\n").findIndex((line) => line.includes(name))], humanOnly: true });
  for (const type of readable) {
    for (const [label, form] of [...forms, ...methods]) {
      assert.deepEqual(at(form(type, lit)), hitAt(form(type, lit)), `${label}, ${type}: a literal`);
      assert.deepEqual(at(form(type, "process.env.X")), { hits: [], humanOnly: false }, `${label}, ${type}: a reference`);
    }
  }
  for (const type of unread) {
    for (const [label, form] of forms) {
      assert.deepEqual(at(form(type, lit)), hitAt(form(type, lit)), `${label}, ${type}: a literal`);
      assert.deepEqual(at(form(type, "process.env.X")), hitAt(form(type, "process.env.X")), `${label}, ${type}: a reference, fail closed`);
    }
  }
  // A key whose value is no type (an object's), a ternary's branch over lines, a declaration with no initializer and a
  // type parameter's default: no hit. Keys over lines with no comma (labels: password: a.b(c), whose "type" the reader
  // cannot read) read on to the change's end with no assignment in their item, and give no value; a line break never
  // ends such an item (the reader cannot tell where the type it cannot read ends), so where more than WINDOW_LINES of
  // them follow, the first ones are open past the window and count (fail closed).
  const Name = name.replace("p", "P");
  const labels = (count) => Array.from({ length: count }, () => `  ${name}: a.b(c)`).join("\n");
  assert.deepEqual(at(labels(100)), { hits: Array.from({ length: 59 }, (_, i) => 20 + i), humanOnly: true }, "100 labels: the first 59 run past WINDOW_LINES");
  for (const text of [
    `const cfg = { ${name}: getPw() };`,
    `const cfg = { ${name}: cfg.get(${q}k${q}), mode: m };`,
    `const cfg = { ${name}: ok ? a : b };`,
    `const cfg = { ${name}: !x, y: z };`,
    `const cfg = { ${name}: x => x, other: 1 };`,
    `const cfg = { ${name}: a < b, c: d };`,
    `const cfg = {\n  ${name}: getPw()\n};`,
    `const cfg = {\n  ${name}: getPw()\n    .trim(),\n  user: u,\n};`,
    `const pw = flag ?\n  hash${Name}(x) :\n  other(y);`,
    `const ${name} = ok ? (a) : cfg.x;`,
    `const ${name} = ok ? (a) : x => x;`,
    `class C { @Input() ${name}: (A) B; }`,
    `interface I { ${name}: T extends () => infer R extends string ? R : never; }`,
    `class C { ${name}: <T = string>(x: T) => T; }`,
    labels(41),
  ]) {
    assert.deepEqual(at(text), { hits: [], humanOnly: false }, text.slice(0, 80));
  }
});

test("production wiring, check.mjs --station: a constrained infer counts in a .ts file in a field, a quoted, computed and decorated field and a parameter's default, and so does a type the reader cannot read; the same declarations with references and a readable type do not", () => {
  // Built at run time, so this file holds no literal for the marker to find.
  const name = ["pass", "word"].join("");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  const infer = "string extends infer U extends string ? U : never";
  const unread = "T extends () => infer R extends string ? R : never";
  const change = (v, type) => [
    ["api/field.ts", null, [`export class Creds {`, `  ${name}: ${type} = ${v};`, `}`]],
    ["api/quoted.ts", null, [`export class Creds {`, `  ${q}${name}${q}: ${type} = ${v};`, `}`]],
    ["api/computed.ts", null, [`export class Creds {`, `  [${q}${name}${q}]: ${type} = ${v};`, `}`]],
    ["api/decorated.ts", null, [`export class Creds {`, `  @Input() ${name}: ${type} = ${v};`, `}`]],
    ["api/param.ts", null, [`export function login(`, `  ${name}: ${type} = ${v},`, `): void {}`]],
  ];
  const all = ["api/computed.ts:2", "api/decorated.ts:2", "api/field.ts:2", "api/param.ts:2", "api/quoted.ts:2"];
  assert.deepEqual(stationOnChange(change(`${q}${value}${q}`, infer)), { hits: all, humanOnly: true });
  assert.deepEqual(stationOnChange(change("process.env.X", infer)), { hits: [], humanOnly: false });
  assert.deepEqual(stationOnChange(change("process.env.X", unread)), { hits: all, humanOnly: true });
});

test("markers stay consistent: a reading spends READ_BACK_CHARS once per entries and language, so markerHits, stationReport and readsFrom asked again, or line by line from the last, read the same entries the same", () => {
  // Built at run time, so this file holds no literal for the marker to find. A call that spent the read-back budget
  // again on the same entries would read them otherwise each time: three statements, each with 150 reference fallbacks
  // 4 lines apart below it, would give markerHits no hit and then stationReport humanOnly true, and 260 reference
  // fallbacks and a literal fallback 1,044 lines below its statement the literal, then 98 hits, then all 261.
  const name = ["pass", "word"].join("");
  const q = "'";
  const lit = `${q}v${randomBytes(6).toString("hex")}${q}`;
  const path = "javascript/db.js";
  const entriesOf = (file, added) => addedWithContext(new Map(added.map((n) => [n, file[n - 1]])), `${file.join("\n")}\n`);
  const secretLines = (hits) => hits.filter((hit) => hit.row === "secrets").map((hit) => hit.line);
  const byLine = (entries) => {
    const lines = [];
    for (let i = entries.length - 1; i >= 0; i--) if (!entries[i].context && passwordSpellings(path).some((m) => m.readsFrom(entries, i))) lines.unshift(entries[i].line);
    return lines;
  };
  const consistent = (file, added) => {
    const entries = entriesOf(file, added);
    const first = secretLines(markerHits(path, entries));
    for (let call = 0; call < 4; call++) {
      const report = stationReport({ input: { mode: "diff" }, paths: [path], lines: new Map([[path, entries]]) });
      assert.deepEqual([secretLines(report.markersHit), report.humanOnly], [first, first.length > 0], `stationReport, call ${call + 1}`);
      assert.deepEqual(secretLines(markerHits(path, entries)), first, `markerHits, call ${call + 2}`);
    }
    assert.deepEqual(byLine(entries), first, "readsFrom, line by line from the last, after markerHits");
    // Fresh entries asked line by line from the last before markerHits: the same.
    const fresh = entriesOf(file, added);
    assert.deepEqual(byLine(fresh), first, "readsFrom, line by line from the last, first");
    assert.deepEqual(secretLines(markerHits(path, fresh)), first, "markerHits after readsFrom");
    return first;
  };
  const fallbacks = (prefix, n, env) => {
    const lines = [`const ${prefix}${name} = process.env.X`];
    for (let i = 0; i < n; i++) lines.push("", "", "", `  ?? process.env.${env}${i}`);
    return lines;
  };
  const addedOf = (file) => file.flatMap((text, i) => (text.includes("??") ? [i + 1] : []));
  // Three statements, 150 reference fallbacks below each: no hit, however often the entries are read.
  const three = [...fallbacks("main_", 150, "MAIN_"), ";", ...fallbacks("audit_", 150, "AUDIT_"), ";", ...fallbacks("shop_", 150, "SHOP_"), ";"];
  assert.deepEqual(consistent(three, addedOf(three)), []);
  // 260 reference fallbacks and a literal fallback below them, within the one reading's budget: the literal only.
  const far = [...fallbacks("", 260, "Y"), "", "", "", `  ?? ${lit};`];
  assert.deepEqual(consistent(far, addedOf(far)), [far.length]);
  // 1,000: past READ_BACK_CHARS within the one reading, the runs past the limit count, the same on every call.
  const long = fallbacks("", 1000, "Y");
  const tail = consistent(long, addedOf(long));
  assert.ok(tail.length > 0 && tail.length < 800, `${tail.length} runs counted`);
  assert.deepEqual(tail, addedOf(long).slice(-tail.length), "the runs past the limit, each");
});

/**
 * Decorators as TypeScript reads them, each read back to its "@" whole, so the member after any of them, not only
 * after @name, @a.b and @name(...), is a key whose declaration is read.
 */
const DECORATORS = [
  "@(Dec)",
  "@ (Dec)",
  "@(a.b)",
  "@(a.b)()",
  "@(Dec)<T>()",
  "@Dec()()",
  "@Dec()()()",
  "@Dec(x)(y)",
  "@Dec /* c */ ()()",
  "@Dec<T>()",
  "@Dec<Map<string, string>>()",
  "@a.b<T>()()",
  "@a?.b()",
  "@a?.()",
  "@a!.b()",
  "@a.b!()",
  "@tag\x60x\x60",
  "@(a, b)",
  "@(() => d)()",
  "@Dec({ a: 1 })()",
  "@new Foo()",
  "@'x'",
  "@[a]",
  "@A() @(B)",
  "@(A) @B()()",
];
/** The plain decorators (@name, @a.b, @name(...)): controls. */
const READ_DECORATORS = ["@Dec", "@Dec()", "@a.b", "@a.b.c()", "@Input()"];

/** The members a decorator may stand before, [label, (decorator, value) => text]: words, quoted and computed names. */
function decoratedMembers(name) {
  const q = "'";
  return [
    ["a typed field", (d, v) => `class C { ${d} ${name}: string = ${v}; }`],
    ["a single-quoted field", (d, v) => `class C { ${d} ${q}${name}${q}: string = ${v}; }`],
    ["a double-quoted field", (d, v) => `class C { ${d} "${name}": string = ${v}; }`],
    ["a computed field", (d, v) => `class C { ${d} [${q}${name}${q}]: string = ${v}; }`],
    ["a template-computed field", (d, v) => `class C { ${d} [\x60${name}\x60]: string = ${v}; }`],
    ["a joined computed field", (d, v) => `class C { ${d} [${q}db_${q} + ${q}${name}${q}]: string = ${v}; }`],
    ["a #private field", (d, v) => `class C { ${d} #${name}: string = ${v}; }`],
    ["a field of a whole type", (d, v) => `class C { ${d} ${name}: Map<string, string> = ${v}; }`],
    ["an untyped field", (d, v) => `class C { ${d} ${name} = ${v}; }`],
    ["an untyped quoted field", (d, v) => `class C { ${d} ${q}${name}${q} = ${v}; }`],
    ["an untyped computed field", (d, v) => `class C { ${d} [${q}${name}${q}] = ${v}; }`],
    ["an optional quoted field", (d, v) => `class C { ${d} ${q}${name}${q}?: string = ${v}; }`],
    ["a modified field", (d, v) => `class C { ${d} static readonly ${name}: string = ${v}; }`],
    ["an accessor field", (d, v) => `class C { ${d} accessor ${name}: string = ${v}; }`],
    ["a method", (d, v) => `class C { ${d} ${name}(): string { return ${v}; } }`],
    ["a quoted method", (d, v) => `class C { ${d} ${q}${name}${q}() { return ${v}; } }`],
    ["a computed method", (d, v) => `class C { ${d} [${q}${name}${q}]() { return ${v}; } }`],
    ["a getter", (d, v) => `class C { ${d} get ${name}() { return ${v}; } }`],
    ["a decorated parameter", (d, v) => `class C { constructor(${d} ${name}: string = ${v}) {} }`],
    ["a decorated second parameter", (d, v) => `class C { m(a: string, ${d} ${name}: string = ${v}) {} }`],
  ];
}

test("secrets, through markerHits and stationReport: a decorator is read back to its \"@\" whole, a group, chained calls, type arguments, member names, \"!\", a tagged template or a literal, so a word, quoted or computed field, method or parameter after it is read as a declaration and counts; the same members with references, and a call's result in a ternary, do not", () => {
  // Built at run time, so this file holds no literal for the marker to find. A decorator is more than a name, a path
  // of names and one call: after @(Dec), @Dec()() or @Dec<T>() a name is a key, so class C { @(Dec) password: string
  // = '...'; } is a hit, and so are quoted and computed fields, methods and decorated parameters. The members after
  // the five plain decorators (READ_DECORATORS) are controls.
  const name = ["pass", "word"].join("");
  const q = "'";
  const lit = `${q}v${randomBytes(6).toString("hex")}${q}`;
  const ts = "api/client.ts";
  for (const decorator of [...DECORATORS, ...READ_DECORATORS]) {
    for (const [label, member] of decoratedMembers(name)) {
      assert.deepEqual(linesHit(ts, [member(decorator, lit)]), { hits: [20], humanOnly: true }, `${decorator}, ${label}: a literal`);
      assert.deepEqual(linesHit(ts, [member(decorator, "process.env.X")]), { hits: [], humanOnly: false }, `${decorator}, ${label}: a reference`);
    }
  }
  // Over lines: a decorator whose arguments or type arguments open above the line that names the member counts as one
  // (the line is read alone, and its "(" is not in it), and so does a decorated type the change ends inside.
  for (const [texts, hits] of [
    [[`class C {`, `  @Dec({`, `    a: 1,`, `  }) ${name}: string = ${lit};`, `}`], [23]],
    [[`class C {`, `  @Dec(`, `    cfg,`, `  )() ${q}${name}${q}: string = ${lit};`, `}`], [23]],
    [[`class C {`, `  @Dec<`, `    T`, `  >() [${q}${name}${q}]: string = ${lit};`, `}`], [23]],
    [[`class C {`, `  @(Dec) ${name}: Map<string,`, `    string> = ${lit};`, `}`], [21]],
    [[`class C {`, `  @(Dec) ${name}: Map<string,`], [21]],
    [[`class C {`, `  @Dec()() ${q}${name}${q}: Map<string,`], [21]],
    // A control: a decorator alone on its line leaves the member at a line's start.
    [[`class C {`, `  @(Dec)`, `  ${name}: string = ${lit};`, `}`], [22]],
  ]) {
    assert.deepEqual(linesHit(ts, texts), { hits, humanOnly: true }, texts.join(" / "));
  }
  for (const texts of [
    [`class C {`, `  @Dec({`, `    a: 1,`, `  }) ${name}: string = process.env.X;`, `}`],
    [`class C {`, `  @Dec(`, `    cfg,`, `  )() ${q}${name}${q}: string = process.env.X;`, `}`],
    [`class C {`, `  @Dec<`, `    T`, `  >() [${q}${name}${q}]: string = process.env.X;`, `}`],
    [`class C {`, `  @(Dec) ${name}: Map<string,`, `    string> = new Map();`, `}`],
    [`class C {`, `  @Dec()() ${q}${name}${q}: string;`, `}`],
  ]) {
    assert.deepEqual(linesHit(ts, texts), { hits: [], humanOnly: false }, texts.join(" / "));
  }
  // No decorator: a call's result, a subscript of it or a comparison before a name in a ternary, a member read, a call
  // after an if's condition. Each reads back to something other than "@".
  for (const text of [
    `const v = c ? f()[${q}${name}${q}] : ${lit};`,
    `const v = c ? (a)(b)[${q}${name}${q}] : ${lit};`,
    `const v = c ? x<T>(y)[${q}${name}${q}] : ${lit};`,
    `const v = c ? f()!.${name} : ${lit};`,
    `const v = c ? typeof ${name} : ${lit};`,
    `const v = c ? a > ${name} : ${lit};`,
    `if (a < b && c > (d)) ${name}(x);`,
    `if (ok) ${name}(x);`,
    `const v = { a: f()[${q}${name}${q}] };`,
  ]) {
    assert.deepEqual(linesHit(ts, [text]), { hits: [], humanOnly: false }, text);
  }
});

/**
 * Types nested deeper than READ_DEPTH over lines, each a line break where the type
 * goes on ("?", ":", extends or "=>" at a line's end, or the type on the line after the name's ":"), and the same types
 * one level shallower, which the reader reads whole.
 */
function deepTypes(levels) {
  return [
    ["a conditional, \"?\" at each line's end", `${"A extends B ?\n".repeat(levels)}A${" : B".repeat(levels)}`],
    ["conditionals in the false branch, \":\" at each line's end", `${"A extends B ? A :\n".repeat(levels)}B`],
    ["\"extends\" at each line's end", `${"A extends\nB ? ".repeat(levels)}A${" : B".repeat(levels)}`],
    ["the type on the line after the name", `\n  ${"A extends B ?\n".repeat(levels)}A${" : B".repeat(levels)}`],
    ["function types, \"=>\" at each line's end", `${"() =>\n".repeat(levels)}string`],
  ];
}

test("secrets, through markerHits and stationReport: a type deeper than READ_DEPTH over lines is read whole, so a declaration's literal initializer counts in fields, quoted, computed and decorated fields, parameter defaults, a let and a const, and so does an arrow's or a method's literal return that deep; its reference or none does not", () => {
  // Built at run time, so this file holds no literal for the marker to find. A type deeper than READ_DEPTH is read
  // whole (tsType keeps what is still to come on a stack, so no depth stops it), and the line break after its first
  // line's "?" does not end the item: the initializer on a later line is found, so a class field of nine conditionals
  // over lines and then a literal initializer counts, and so do the quoted, computed, decorated and parameter forms and
  // an arrow's return. A literal counts, a reference and none do not; an arrow's or a method's return type that deep is
  // read through to its "=>" or body, so a reference there is no hit either.
  const name = ["pass", "word"].join("");
  const q = "'";
  const lit = `${q}v${randomBytes(6).toString("hex")}${q}`;
  const ts = "api/client.ts";
  const { forms, methods } = typedDeclarations(name);
  const decorated = [
    ["a (Dec)-decorated field", (t, v) => `class C { @(Dec) ${name}: ${t} = ${v}; }`],
    ["a Dec()()-decorated quoted field", (t, v) => `class C { @Dec()() ${q}${name}${q}: ${t} = ${v}; }`],
    ["a Dec<T>()-decorated computed field", (t, v) => `class C { @Dec<T>() [${q}${name}${q}]: ${t} = ${v}; }`],
    ["a const", (t, v) => `const ${name}: ${t} = ${v};`],
  ];
  const returns = [...forms.filter(([label]) => label.endsWith("return")), ...methods];
  const initialized = [...forms.filter(([label]) => !label.endsWith("return")), ...decorated];
  const at = (text) => linesHit(ts, text.split("\n"));
  const hitAt = (text) => ({ hits: [20 + text.split("\n").findIndex((line) => line.includes(name))], humanOnly: true });
  const none = { hits: [], humanOnly: false };
  for (const [kind, type] of deepTypes(9)) {
    for (const [label, form] of initialized) {
      assert.deepEqual(at(form(type, lit)), hitAt(form(type, lit)), `${label}, ${kind}: a literal`);
      assert.deepEqual(at(form(type, "process.env.X")), none, `${label}, ${kind}: a reference`);
    }
    for (const [label, form] of returns) {
      assert.deepEqual(at(form(type, lit)), hitAt(form(type, lit)), `${label}, ${kind}: a literal`);
      assert.deepEqual(at(form(type, "this.p")), none, `${label}, ${kind}: a reference`);
    }
    // No initializer: a field, an interface's member, an object type's key, a let, and a parameter.
    for (const text of [
      `class C { ${name}: ${type}; }`,
      `interface I { ${q}${name}${q}: ${type}; }`,
      `type T = { ${name}: ${type} };`,
      `let ${name}: ${type};`,
      `function f(${name}: ${type}) {}`,
    ]) {
      assert.deepEqual(at(text), none, `${kind}: ${text.slice(0, 40)}`);
    }
    // A type read whole ends where TypeScript's does, at a line break the next line does not go on from: a statement
    // there, or a field, is not the declaration's initializer.
    for (const text of [`class C {\n  ${name}: ${type}\n  other = ${lit}\n}`, `let ${name}: ${type}\nother = ${lit};`]) {
      assert.deepEqual(at(text), none, `${kind}: the next statement`);
    }
  }
  // One level shallower the reader reads the type whole too (controls).
  for (const [kind, type] of deepTypes(8)) {
    for (const [label, form] of [...initialized, ...returns]) {
      assert.deepEqual(at(form(type, lit)), hitAt(form(type, lit)), `${label}, ${kind} (8): a literal`);
      assert.deepEqual(at(form(type, "process.env.X")), none, `${label}, ${kind} (8): a reference`);
    }
  }
  // An initializer behind a "<" that does not close: the reader cannot tell it from a list's, so it counts in a field,
  // a parameter and a let, deep or not. A comparison in an object's value, a ternary's
  // arrow after one, and a type parameter's default inside a whole list do not.
  const [, conditional] = deepTypes(9)[0];
  for (const text of [
    `class C { ${name}: (A) B < C = ${lit}; }`,
    `function f(${name}: (A) B < C = ${lit}) {}`,
    `class C { ${name}: ${conditional} < X = process.env.X; }`,
    `let ${name}: (A) B < C = ${lit};`,
  ]) {
    assert.deepEqual(at(text), hitAt(text), text.slice(0, 60));
  }
  for (const text of [`const cfg = { ${name}: a < b, c: d };`, `const ${name} = ok ? (a) : n < m ? x => x : y;`, `class C { ${name}: <T = string>(x: T) => T; }`]) {
    assert.deepEqual(at(text), none, text);
  }
});

test("production wiring, check.mjs --station: in a .ts file, decorated fields after @(Dec) and @Dec()() and a declaration's literal after a type deeper than READ_DEPTH over lines count; the same declarations with references do not", () => {
  // Built at run time, so this file holds no literal for the marker to find.
  const name = ["pass", "word"].join("");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  const deep = `${"A extends B ?\n    ".repeat(9)}A${" : B".repeat(9)}`.split("\n");
  const change = (v) => [
    ["api/paren.ts", null, [`export class Creds {`, `  @(Dec) ${name}: string = ${v};`, `}`]],
    ["api/chained.ts", null, [`export class Creds {`, `  @Dec()() ${q}${name}${q}: string = ${v};`, `}`]],
    ["api/computed.ts", null, [`export class Creds {`, `  @Dec<T>() [${q}${name}${q}]: string = ${v};`, `}`]],
    ["api/overLines.ts", null, [`export class Creds {`, `  @Dec({`, `    a: 1,`, `  }) ${name}: string = ${v};`, `}`]],
    ["api/deepField.ts", null, [`export class Creds {`, `  ${name}: ${deep[0]}`, ...deep.slice(1).map((line) => `    ${line}`), `    = ${v};`, `}`]],
    ["api/deepParam.ts", null, [`export function login(`, `  ${name}: ${deep[0]}`, ...deep.slice(1).map((line) => `    ${line}`), `    = ${v},`, `): void {}`]],
  ];
  const all = ["api/chained.ts:2", "api/computed.ts:2", "api/deepField.ts:2", "api/deepParam.ts:2", "api/overLines.ts:4", "api/paren.ts:2"];
  assert.deepEqual(stationOnChange(change(`${q}${value}${q}`)), { hits: all, humanOnly: true });
  assert.deepEqual(stationOnChange(change("process.env.X")), { hits: [], humanOnly: false });
});

test("markers stay linear: decorators read back over long call chains and type arguments, comparisons before many names, and many decorated or deeply typed names on one line grow no faster than linearly (linear-time.mjs), and count as the reader decides", () => {
  // Built at run time, so this file holds no literal for the marker to find. Each item's reading is kept per token, so
  // a decorated name with a type the reader cannot read does not scan its item to the line's end again (20,000 of them
  // on one line). A decorator's type arguments are read back at most DECORATOR_TOKENS tokens; past
  // that the name counts as a decorated member's, so a literal after one counts.
  const name = ["pass", "word"].join("");
  const q = "'";
  const lit = `${q}v${randomBytes(6).toString("hex")}${q}`;
  const deep = `${"A extends B ? ".repeat(9)}A${" : B".repeat(9)}`;
  const cases = [
    ["many decorated unread types", (n) => `class C { ` + (`@a ${name}: (A) B `.repeat(n)) + ` }`, 20000, 0],
    ["many decorated deep types", (n) => `class C { ` + (`@a ${name}: ${deep} `.repeat(n)) + ` }`, 2000, 0],
    ["many deep types over lines", (n) => `class C {\n` + (`  ${name}: ${"A extends B ?\n".repeat(9)}A${" : B".repeat(9)} = x;\n`.repeat(n)) + `}`, 2000, 0],
    ["comparisons before names", (n) => "" + (`a > (b) ${name}: c, `.repeat(n)), 20000, 0],
    ["long call chains", (n) => `class C { ` + (`@Dec${"()".repeat(50)} ${name}: string = x; `.repeat(n)) + ` }`, 4000, 0],
    ["long type arguments", (n) => `class C { ` + (`@Dec<${"Map<".repeat(40)}T${">".repeat(40)}>() ${name}: string = x; `.repeat(n)) + ` }`, 4000, 0],
    ["type arguments past DECORATOR_TOKENS", (n) => `class C { @Dec<${"A | ".repeat(n)}B>() ${name}: string = ${lit}; }`, 2000, 1],
    ["a literal after many decorated names", (n) => `class C { ` + (`@(a) ${name}: string = x; `.repeat(n)) + `@(a) ${name}: string = ${lit}; }`, 20000, 1],
  ];
  for (const [label, text, n, hits] of cases) {
    const read = (k) => markerHits("api/client.ts", text(k).split("\n").map((line, i) => ({ line: i + 1, text: line }))).filter((hit) => hit.row === "secrets").length;
    assert.equal(linear(label, read, n), hits, label);
  }
});

/**
 * TypeScript types that go on over a line break after a complete operand, around and inside conditionals deeper than
 * READ_DEPTH: a constrained infer whose extends stands on the next line (infer U\nextends C), an abstract constructor
 * whose new does (abstract\nnew () => R), and a constraint on the line after its extends. TypeScript 6.0.3 parses each
 * declaration they are put in here (the 27 of typedDeclarations, one level and nine deep) with no diagnostic.
 */
function continuedTypes(levels) {
  const deep = `${"A extends B ?\n".repeat(levels)}A${" : B".repeat(levels)}`;
  return [
    ["a constrained infer over lines around a deep conditional", `A extends infer U\nextends C ? ${deep} : B`],
    ["an abstract constructor over lines returning a deep conditional", `abstract\nnew () => ${deep}`],
    ["a constrained infer over lines at each level", `${"A extends infer U\nextends C ?\n".repeat(levels)}A${" : B".repeat(levels)}`],
    ["an abstract constructor over lines at each level", `${"abstract\nnew () =>\n".repeat(levels)}A`],
    ["an abstract constructor over lines in each branch", `${"A extends B ?\nabstract\nnew () =>\n".repeat(levels)}A${" : B".repeat(levels)}`],
    ["a constraint on the line after its extends at each level", `${"A extends infer U extends\nC ?\n".repeat(levels)}A${" : B".repeat(levels)}`],
  ];
}

test("secrets, through markerHits and stationReport: a type that goes on over a line break after a complete operand (infer U\\nextends C, abstract\\nnew () => R) around or inside conditionals deeper than READ_DEPTH is read whole, so a literal initializer, default or return counts in 27 declaration forms and a reference does not, as one level deep; an arrow whose line ends right after its whole return type is read with the next line", () => {
  // Built at run time, so this file holds no literal for the marker to find. Every type is read whole, with no depth
  // limit, and the item read for its initializer does not end at a line break after a complete operand where the type
  // goes on without an operator (infer U, then extends on the next line, or abstract, then new). So a class field,
  // parameter default and arrow return holding a nine-deep conditional inside A extends infer U\nextends C ? ... : B,
  // or after abstract\nnew () =>, then a literal, count as they do one level deep: a literal counts and a reference
  // does not, and a generic arrow whose line ends after its return type is read on.
  const name = ["pass", "word"].join("");
  const q = "'";
  const lit = `${q}v${randomBytes(6).toString("hex")}${q}`;
  const ts = "api/client.ts";
  const { forms, methods } = typedDeclarations(name);
  const at = (text) => linesHit(ts, text.split("\n"));
  const hitAt = (text) => ({ hits: [20 + text.split("\n").findIndex((line) => line.includes(name))], humanOnly: true });
  const none = { hits: [], humanOnly: false };
  // Nine deep first, the class field first: the literals, then the references.
  for (const levels of [9, 1]) {
    for (const [kind, type] of continuedTypes(levels)) {
      for (const [label, form] of [...forms, ...methods]) {
        assert.deepEqual(at(form(type, lit)), hitAt(form(type, lit)), `${label}, ${kind} (${levels}): a literal`);
      }
    }
    for (const [kind, type] of continuedTypes(levels)) {
      for (const [label, form] of [...forms, ...methods]) {
        assert.deepEqual(at(form(type, "process.env.X")), none, `${label}, ${kind} (${levels}): a reference`);
      }
    }
  }
  // No initializer, and a field or a statement on the next line: the type ends where TypeScript's does.
  for (const [kind, type] of continuedTypes(9)) {
    for (const text of [
      `class C { ${name}: ${type}; }`,
      `interface I { ${q}${name}${q}: ${type}; }`,
      `function f(${name}: ${type}) {}`,
      `let ${name}: ${type};`,
      `class C {\n  ${name}: ${type}\n  other = ${lit}\n}`,
      `let ${name}: ${type}\nother = ${lit};`,
    ]) {
      assert.deepEqual(at(text), none, `${kind}: ${text.slice(0, 40)}`);
    }
  }
  // An arrow whose line ends right after its whole return type: the type may go on on the next line (| B, new () => R
  // after abstract, .B), and its "=>" after it, so the line is read with the change's next one, not counted whatever
  // follows. "(a) : x" is also a ternary's branch: one that holds a literal, or one the change ends after, is read as
  // a ternary, and its literal counts.
  for (const [texts, hits] of [
    [[`const ${name} = <T,>(x: T): A`, `  | B => process.env.X;`], []],
    [[`const ${name} = <T,>(x: T): abstract`, `  new () => A => process.env.X;`], []],
    [[`const ${name} = <T,>(x: T): A`, `  .B => process.env.X;`], []],
    [[`const ${name} = (): A`, `  .B => process.env.X;`], []],
    [[`const ${name} = <T,>(x: T): A`, `  | B => ${lit};`], [20]],
    [[`const ${name} = <T,>(x: T): abstract`, `  new () => A => ${lit};`], [20]],
    [[`const ${name} = (): abstract`, `  new () => A => ${lit};`], [20]],
    [[`const ${name} = c ? (a) : ${lit}`], [20]],
    [[`const ${name} = c ? (a) : ${lit}`, `run();`], [20]],
    [[`const ${name} = c ? <T>(x) : ${lit}`], [20]],
    [[`const ${name} = c ? (a) : x`, `  + ${lit};`], [20]],
  ]) {
    assert.deepEqual(linesHit(ts, texts), { hits, humanOnly: hits.length > 0 }, texts.join(" / "));
  }
});

/**
 * Decorators split over lines (TypeScript reads a decorator's "@", its names, members, calls and type arguments over
 * line breaks) and decorators whose names are written with escapes. TypeScript 6.0.3 parses each member below with
 * each of them with no diagnostic.
 */
const SPLIT_DECORATORS = ["@\nDec()", "@\n  Dec()", "@Dec\n()", "@Dec(\n)", "@a\n.b()", "@a.\nb()", "@(\nDec\n)", "@Dec()\n()", "@\n(Dec)", "@Dec<\nT\n>()", "@\n\\u0044ec()", "@a\n.\\u0044ec()"];
const ESCAPED_DECORATORS = ["@\\u0044ec()", "@a.\\u0044ec()", "@\\u{44}ec()", "@\\u0044ec", "@a.b\\u0063()", "@\\u0044ec.a()", "@\\u0044ec()()", "@(\\u0044ec)"];

/** The members a decorator over lines may stand before, [label, (decorator, value) => text]: words, quoted and computed names, methods and a parameter. */
function membersOverLines(name) {
  const q = "'";
  return [
    ["a field", (d, v) => `class C {\n  ${d} ${name}: string = ${v};\n}`],
    ["a single-quoted field", (d, v) => `class C {\n  ${d} ${q}${name}${q}: string = ${v};\n}`],
    ["a double-quoted field", (d, v) => `class C {\n  ${d} "${name}": string = ${v};\n}`],
    ["a computed field", (d, v) => `class C {\n  ${d} [${q}${name}${q}]: string = ${v};\n}`],
    ["an untyped field", (d, v) => `class C {\n  ${d} ${name} = ${v};\n}`],
    ["an untyped quoted field", (d, v) => `class C {\n  ${d} ${q}${name}${q} = ${v};\n}`],
    ["a method", (d, v) => `class C {\n  ${d} ${name}(): string { return ${v}; }\n}`],
    ["a quoted method", (d, v) => `class C {\n  ${d} ${q}${name}${q}() { return ${v}; }\n}`],
    ["a computed method", (d, v) => `class C {\n  ${d} [${q}${name}${q}]() { return ${v}; }\n}`],
    ["a parameter", (d, v) => `class C {\n  constructor(${d} ${name}: string = ${v}) {}\n}`],
  ];
}

test("secrets, through markerHits and stationReport: a decorator split over lines (@\\nDec(), @Dec\\n(), @a\\n.b(), ...) or whose names are escapes (@\\u0044ec(), @a.\\u0044ec(), @\\u{44}ec()) is read back to its \"@\", in the line's text or in the lines above it that the entries hold, so a word, quoted or computed field, a method and a parameter after it count, and with references do not; where the entries do not hold the line above, the name counts as a decorated member's", () => {
  // Built at run time, so this file holds no literal for the marker to find. A decorator is read back past the line
  // read, so with its "@" or its callee on the line above (the line that names the member read alone) the name is
  // still a key, and an escape in a decorator's name (Dec) is a word. A decorator whose text reads whole on the
  // member's line, and the untyped field or method read there without one, are controls.
  const name = ["pass", "word"].join("");
  const q = "'";
  const lit = `${q}v${randomBytes(6).toString("hex")}${q}`;
  const ts = "api/client.ts";
  const at = (text) => linesHit(ts, text.split("\n"));
  const hitAt = (text) => ({ hits: [20 + text.split("\n").findIndex((line) => line.includes(name))], humanOnly: true });
  const none = { hits: [], humanOnly: false };
  for (const decorator of [...SPLIT_DECORATORS, ...ESCAPED_DECORATORS]) {
    for (const [label, member] of membersOverLines(name)) {
      assert.deepEqual(at(member(decorator, lit)), hitAt(member(decorator, lit)), `${JSON.stringify(decorator)}, ${label}: a literal`);
      assert.deepEqual(at(member(decorator, "process.env.X")), none, `${JSON.stringify(decorator)}, ${label}: a reference`);
    }
  }
  // Only the member's line is the change's: the "@" or the callee stands on an unchanged line above it, which check.mjs
  // hands with it (addedWithContext: every unchanged line above a run). [file, the member's line]
  const fill = (file, v) => file.map((text) => text.replace("%", v));
  for (const [file, line] of [
    [[`class C {`, `  @`, `  Dec() ${name}: string = %;`, `}`], 3],
    [[`class C {`, `  @Dec`, `  () ${q}${name}${q}: string = %;`, `}`], 3],
    [[`class C {`, `  @a`, `  .b`, `  () [${q}${name}${q}]: string = %;`, `}`], 4],
    [[`class C {`, `  @\\u0044ec`, `  () ${name}() { return %; }`, `}`], 3],
  ]) {
    assert.deepEqual(contextHit(ts, fill(file, lit), [line]), { hits: [line], humanOnly: true }, file.join(" / "));
    assert.deepEqual(contextHit(ts, fill(file, "process.env.X"), [line]), none, file.join(" / "));
    // With one unchanged line above the run, the "@" or the callee is in it too.
    assert.deepEqual(farHit(ts, fill(file, lit), [line], 1), { hits: [line], humanOnly: true }, `${file.join(" / ")}, one line above`);
  }
  // A caller that hands no unchanged line above a run: the "@" may stand above the entries, which start below the
  // file's first line, so the member counts as a decorated member's with a literal (fail closed), and not with a
  // reference. At the file's first line nothing stands above it, so no decorator does.
  const split = [`class C {`, `  @`, `  Dec() ${name}: string = %;`, `}`];
  assert.deepEqual(farHit(ts, fill(split, lit), [3], 0), { hits: [3], humanOnly: true }, "no line above the run");
  assert.deepEqual(farHit(ts, fill(split, "process.env.X"), [3], 0), none, "no line above the run, a reference");
  assert.deepEqual(contextHit(ts, [`Dec() ${name}: string = ${lit};`], [1]), none, "the file's first line");
});

test("production wiring, check.mjs --station: in a .ts file, members after a decorator split over lines or written with escapes, also where only the member's line changes, and a field, a parameter's default and an arrow's return after a constrained infer or an abstract constructor over lines around a deep conditional count; the same with references do not", () => {
  // Built at run time, so this file holds no literal for the marker to find.
  const name = ["pass", "word"].join("");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  const deep = `${"A extends B ?\n".repeat(9)}A${" : B".repeat(9)}`.split("\n").map((line) => `    ${line}`);
  const deepLast = deep.length - 1;
  const edited = (v) => [`export class Creds {`, `  @`, `  Dec() ${name}: string = ${v};`, `}`];
  const change = (v) => [
    ["api/splitAt.ts", null, [`export class Creds {`, `  @`, `  Dec() ${name}: string = ${v};`, `}`]],
    ["api/splitCall.ts", null, [`export class Creds {`, `  @Dec`, `  () ${q}${name}${q}: string = ${v};`, `}`]],
    ["api/escaped.ts", null, [`export class Creds {`, `  @\\u0044ec() [${q}${name}${q}]: string = ${v};`, `}`]],
    ["api/escapedMember.ts", null, [`export class Creds {`, `  @a.\\u0044ec() ${name}(): string { return ${v}; }`, `}`]],
    ["api/edited.ts", edited("process.env.X"), edited(v)],
    ["api/deepInfer.ts", null, [`export class Creds {`, `  ${name}: A extends infer U`, `    extends C ? ${deep[0].trim()}`, ...deep.slice(1, deepLast), `${deep[deepLast]} : B = ${v};`, `}`]],
    ["api/abstractParam.ts", null, [`export function login(`, `  ${name}: abstract`, `    new () => ${deep[0].trim()}`, ...deep.slice(1, deepLast), `${deep[deepLast]} = ${v},`, `): void {}`]],
    ["api/abstractArrow.ts", null, [`export const ${name} = (): abstract`, `  new () => ${deep[0].trim()}`, ...deep.slice(1, deepLast), `${deep[deepLast]} => ${v};`]],
  ];
  const all = ["api/abstractArrow.ts:1", "api/abstractParam.ts:2", "api/deepInfer.ts:2", "api/edited.ts:3", "api/escaped.ts:2", "api/escapedMember.ts:2", "api/splitAt.ts:3", "api/splitCall.ts:3"];
  assert.deepEqual(stationOnChange(change(`${q}${value}${q}`)), { hits: all, humanOnly: true });
  assert.deepEqual(stationOnChange(change("process.env.X")), { hits: [], humanOnly: false });
});

test("markers stay linear: decorators whose \"@\" stands on the line above, long chains over lines and many escaped decorators grow no faster than linearly (linear-time.mjs), and count as the reader decides", () => {
  // Built at run time, so this file holds no literal for the marker to find. A member's line that does not hold its
  // decorator's "@" reads the decorator back in the block's text, which is read once per entries and language
  // (layoutOf), from the member's own token, so each such line costs its decorator's tokens.
  const name = ["pass", "word"].join("");
  const q = "'";
  const lit = `${q}v${randomBytes(6).toString("hex")}${q}`;
  const cases = [
    ["many members after a split decorator", (n) => `class C {\n` + (`  @\n  Dec() ${name}: string = x;\n`.repeat(n)) + `}`, 10000, 0],
    ["a literal after many split decorators", (n) => `class C {\n` + (`  @\n  Dec() ${name}: string = x;\n`.repeat(n)) + `  @\n  Dec() ${name}: string = ${lit};\n}`, 10000, 1],
    ["a chain over many lines", (n) => `class C {\n  @a\n${"  .b\n".repeat(n)}  () ${name}: string = ${lit};\n}`, 20000, 1],
    ["many escaped decorators", (n) => `class C { ` + (`@\\u0044ec() ${name}: string = x; `.repeat(n)) + `@a.\\u0044ec() ${name}: string = ${lit}; }`, 20000, 1],
  ];
  for (const [label, text, n, hits] of cases) {
    const read = (k) => markerHits("api/client.ts", text(k).split("\n").map((line, i) => ({ line: i + 1, text: line }))).filter((hit) => hit.row === "secrets").length;
    assert.equal(linear(label, read, n), hits, label);
  }
});

/**
 * Decorators that hold a string or a comment over lines, so the member's line starts inside it. TypeScript 6.0.3 parses
 * each member below with each of them with no diagnostic. The member's line is lexed from where the lines above leave
 * it: lexed as if it started in code, the string's closing "`" or quote would open a string there, with the member's
 * name inside it.
 */
const STRING_DECORATORS = [
  "@Dec(`x\ny`)",
  "@Dec(`x\n\ny\n`)",
  "@tag`x\ny`",
  "@Dec('x\\\ny')",
  '@Dec("x\\\ny")',
  "@Dec(`x\ny`)()",
  "@(Dec(`x\ny`))",
  "@Dec<T>(`x\ny`)",
  "@a.b(`x\ny`)",
  "@Dec(`${a(\nb)}`)",
  "@Dec(`${`a\nb`}`)",
  '@Dec(`${"a\\\nb"}`)',
  "@Dec(`${a / b}\n`)",
  "@Dec(`${s.replace(/[a-z]/g, '')}\n`)",
  "@Dec(/* x\n` */)",
  "@Dec(/* x\n' */)",
  "@Dec(`x\ny`, `z\nw`)",
  "@Dec(`x\n${'y'}`)",
];

/** The member's line: the line of `text` that names `name`, in any case, and the hit a literal there gives, its first line numbered `first`. */
const memberHit = (text, name, first = 1) => ({ hits: [first + text.split("\n").findIndex((line) => line.toLowerCase().includes(name))], humanOnly: true });

/**
 * The decorators above whose template holds a template or a regular expression in an interpolation: outside doors.mjs's
 * plain subset, so the member's line counts with a reference too.
 */
const UNPLAIN_DECORATORS = new Set(["@Dec(`${`a\nb`}`)", "@Dec(`${s.replace(/[a-z]/g, '')}\n`)"]);

/**
 * Assert that `hit` and `ref` (a literal's and a reference's form of one text, its first line 20) both count outside the
 * plain subset: human-only, the line that names `name` among the hits (the lines from the unusual one on that hold
 * anything are the rest: this block starts below the file's first line).
 */
function assertCountsOverLines(path, hit, ref, name, label) {
  for (const [text, kind] of [
    [hit, "a literal"],
    [ref, "a reference"],
  ]) {
    const routed = linesHit(path, text.split("\n"));
    assert.equal(routed.humanOnly, true, `${label}: ${kind}`);
    assert.ok(routed.hits.includes(memberHit(text, name, 20).hits[0]), `${label}: ${kind}: ${routed.hits}`);
  }
}

test("secrets, through markerHits and stationReport: a member whose line starts inside a decorator's string or comment over lines (a template, a tagged template, a continued quote, an interpolation's group, a nested template, a block comment holding a quote) is lexed from where the lines above leave it, so a word, quoted or computed field, a method and a parameter count, also where only the member's line changes, and with references do not", () => {
  // Built at run time, so this file holds no literal for the marker to find. class C { @Dec(`x\ny`) password: string =
  // '...'; } is a hit. Lexed as code, the member's line would be read as the text of a string its closing "`" or quote
  // opens (a quoted or an untyped field's "name: string = ..." or "name = ..."), and a reference would count too.
  const name = ["pass", "word"].join("");
  const lit = `'v${randomBytes(6).toString("hex")}'`;
  const ts = "api/client.ts";
  const none = { hits: [], humanOnly: false };
  for (const decorator of STRING_DECORATORS) {
    const plain = !UNPLAIN_DECORATORS.has(decorator);
    for (const [label, member] of membersOverLines(name)) {
      const [hit, ref] = [member(decorator, lit), member(decorator, "process.env.X")];
      const line = memberHit(hit, name).hits[0];
      const what = `${JSON.stringify(decorator)}, ${label}`;
      if (plain) {
        assert.deepEqual(linesHit(ts, hit.split("\n")), memberHit(hit, name, 20), `${what}: a literal`);
        assert.deepEqual(linesHit(ts, ref.split("\n")), none, `${what}: a reference`);
      } else assertCountsOverLines(ts, hit, ref, name, what);
      // Only the member's line is the change's: the string's start is on an unchanged line above it.
      assert.deepEqual(contextHit(ts, hit.split("\n"), [line]), { hits: [line], humanOnly: true }, `${what}: a literal, only the member's line changes`);
      assert.deepEqual(contextHit(ts, ref.split("\n"), [line]), plain ? none : { hits: [line], humanOnly: true }, `${what}: a reference, only the member's line changes`);
    }
  }
});

test("secrets, through markerHits and stationReport: any line that starts inside a string or a block comment a line above opens is lexed from there, in JavaScript, TypeScript, Python and PHP, while what such a string holds on the line is still read as code, so a string that holds a script still counts and a reference stays no hit", () => {
  // Built at run time, so this file holds no literal for the marker to find. [path, file with V for the value, the
  // reference]: a declaration, a key, a set call, a keyword argument or a PHP variable after a string's or a comment's
  // close counts, and its reference does not; the heredoc's statement after its close is a control. In the second
  // group, a password a string over lines gives in its own text counts, and a template's "name: process.env.X" is no
  // hit (its closing "`" opens nothing).
  const name = ["pass", "word"].join("");
  const NAME = name.toUpperCase();
  const Name = `P${name.slice(1)}`;
  const value = `v${randomBytes(6).toString("hex")}`;
  const q = "'";
  const none = { hits: [], humanOnly: false };
  for (const [path, file, ref] of [
    ["api/client.ts", `const s = \`x\ny\`; let ${name}: string = V;`, "process.env.X"],
    ["api/client.ts", `const s = \`x\ny\`, ${name}: string = V;`, "process.env.X"],
    ["api/client.js", `f(\`x\ny\`, { ${name}: V });`, "process.env.X"],
    ["api/client.js", `set(\n  'x\\\ny', '${name}', V);`, "process.env.X"],
    ["tools/ci/seed.py", `x = """a\nb"""; ${name}: str = V`, `os.environ["X"]`],
    ["tools/ci/seed.py", `connect('a\\\nb', ${name}=V)`, `os.environ["X"]`],
    ["includes/newFeature.php", `<?php\nf(/* a\n ' */ $${name} = V);`, "getenv('X')"],
    ["includes/newFeature.php", `<?php\n$x = <<<EOT\na\nEOT; $${name} = V;`, "getenv('X')"],
  ]) {
    const hit = file.replace("V", `${q}${value}${q}`);
    const line = memberHit(hit, name).hits[0];
    assert.deepEqual(linesHit(path, hit.split("\n")), memberHit(hit, name, 20), `${path}: ${JSON.stringify(file)}`);
    assert.deepEqual(contextHit(path, hit.split("\n"), [line]), { hits: [line], humanOnly: true }, `${path}: ${JSON.stringify(file)}, only its line`);
    assert.deepEqual(linesHit(path, file.replace("V", ref).split("\n")), none, `${path}: ${JSON.stringify(file)}, a reference`);
  }
  // A string over lines that holds code: what it gives a password on a line below its start counts.
  for (const [path, shell] of [
    ["api/client.js", (t) => `const t = \`\n  ${t}\n\`;`],
    ["includes/newFeature.php", (t) => `<?php\n$h = <<<HTML\n  ${t}\nHTML;`],
    ["tools/ci/seed.py", (t) => `T = """\n  ${t}\n"""`],
  ]) {
    for (const inner of [`${name}: '${value}'`, `{ ${name}: '${value}' }`, `set${Name}('${value}');`, `define('${NAME}', '${value}');`, `['${name}' => '${value}']`, `cfg['${name}'] = '${value}';`]) {
      const text = shell(inner);
      assert.deepEqual(linesHit(path, text.split("\n")), memberHit(text, name, 20), `${path}: ${JSON.stringify(text)}`);
    }
    for (const inner of [`<input type="${name}" value="\${pw}">`, `${name}: process.env.X`, `set${Name}(pw);`]) {
      assert.deepEqual(linesHit(path, shell(inner).split("\n")), none, `${path}: ${JSON.stringify(shell(inner))}`);
    }
  }
});

test("secrets: a regular expression or a comment in a template's interpolation leaves the plain subset, so a later line that names a password counts with a reference too, while a division and groups over lines decide as the line does; where JavaScript reads no such template (a quote a line break ends, a close that opens nothing) or more than LEAD_FRAMES stand open, the lexical state of every later line is unknown, and one that names a password counts, with a reference too", () => {
  // Built at run time, so this file holds no literal for the marker to find. A regular expression or a comment in an
  // interpolation (one that holds a quote, a backtick or a brace, or a comment over lines) is outside the plain subset,
  // so the first six files below count with a reference too. A division and groups over lines are plain, and decide
  // as the line does. The rest are no JavaScript template, or hold more than
  // LEAD_FRAMES open, and still count with a reference.
  const name = ["pass", "word"].join("");
  const lit = `'v${randomBytes(6).toString("hex")}'`;
  const ts = "api/client.ts";
  for (const file of [
    `class C {\n  @Dec(\`\${s.replace(/'/g, '')}\n\`) ${name}: string = V;\n}`,
    `class C {\n  @Dec(\`\${s.replace(/\`/g, '')}\`)\n  ${name}: string = V;\n}`,
    `class C {\n  @Dec(\`\${a // don't\n}\`) ${name}: string = V;\n}`,
    `class C {\n  @Dec(\`\${a /* x\n */}\`) ${name}: string = V;\n}`,
    `const t = \`\${s.replace(/"/g, '')}\`;\nconst u = 1;\nclass C { ${name}: string = V; }`,
    `class C {\n  @Dec(\`\${s.replace(/[a-z]/g, '')}\n\`) ${name}: string = V;\n}`,
  ]) {
    assertCountsOverLines(ts, file.replace("V", lit), file.replace("V", "process.env.X"), name, JSON.stringify(file));
  }
  for (const file of [
    `class C {\n  @Dec(\`\${a / b}\n\`) ${name}: string = V;\n}`,
    `const t = \`\${f(` + "(\n".repeat(60) + ")".repeat(60) + `)}\`;\nclass C { ${name}: string = V; }`,
  ]) {
    const text = file.replace("V", lit);
    assert.deepEqual(linesHit(ts, text.split("\n")), memberHit(text, name, 20), `${JSON.stringify(file)}: a literal`);
    assert.deepEqual(linesHit(ts, file.replace("V", "process.env.X").split("\n")), { hits: [], humanOnly: false }, `${JSON.stringify(file)}: a reference`);
  }
  for (const file of [
    `const t = \`\${f(` + "(\n".repeat(70) + ")".repeat(70) + `)}\`;\nclass C { ${name}: string = V; }`,
    `const t = \`\${'a\n}\`;\nclass C { ${name}: string = V; }`,
    `const t = \`\${a)}\`;\nconst u = 1;\nclass C { ${name}: string = V; }`,
  ]) {
    assertCountsOverLines(ts, file.replace("V", lit), file.replace("V", "process.env.X"), name, JSON.stringify(file));
  }
});

test("production wiring, check.mjs --station: in a .ts file, members after a decorator's template, tagged template, continued quote or comment over lines count, also where only the member's line changes; the same with references do not", () => {
  // Built at run time, so this file holds no literal for the marker to find.
  const name = ["pass", "word"].join("");
  const q = "'";
  const value = `v${randomBytes(6).toString("hex")}`;
  const edited = (v) => [`export class Creds {`, "  @Dec(`x", `  y\`) ${name}: string = ${v};`, `}`];
  const change = (v) => [
    ["api/template.ts", null, [`export class Creds {`, "  @Dec(`x", `  y\`) ${name}: string = ${v};`, `}`]],
    ["api/tagged.ts", null, [`export class Creds {`, "  @tag`x", `  y\` ${q}${name}${q}: string = ${v};`, `}`]],
    ["api/continued.ts", null, [`export class Creds {`, `  @Dec(${q}x\\`, `  y${q}) [${q}${name}${q}]: string = ${v};`, `}`]],
    ["api/comment.ts", null, [`export class Creds {`, `  @Dec(/* x`, `  ${q} */) ${name}(): string { return ${v}; }`, `}`]],
    ["api/param.ts", null, [`export class Creds {`, "  constructor(@Dec(`x", `  y\`) ${name}: string = ${v}) {}`, `}`]],
    ["api/edited.ts", edited("process.env.X"), edited(v)],
  ];
  const all = ["api/comment.ts:3", "api/continued.ts:3", "api/edited.ts:3", "api/param.ts:3", "api/tagged.ts:3", "api/template.ts:3"];
  assert.deepEqual(stationOnChange(change(`${q}${value}${q}`)), { hits: all, humanOnly: true });
  assert.deepEqual(stationOnChange(change("process.env.X")), { hits: [], humanOnly: false });
});

test("markers stay linear: a template over 20,000 lines, 20,000 lines of markup or of an interpolation's code in one, 10,000 members after a decorator's template over lines, an interpolation that holds 20,000 brackets open and a block comment over 20,000 lines grow no faster than linearly (linear-time.mjs), and count as the reader decides", () => {
  // Built at run time, so this file holds no literal for the marker to find. A block's templates are read once for
  // the lexer's state at each line's start (leadOf), so each line costs what it holds and what is open there, at most
  // LEAD_FRAMES; past that the state is unknown, and a later line that names a password counts.
  const name = ["pass", "word"].join("");
  const lit = `'v${randomBytes(6).toString("hex")}'`;
  const cases = [
    ["a template argument over 20,000 lines before a member", (n) => `class C {\n  @Dec(\`${"x\n".repeat(n)}\`) ${name}: string = ${lit};\n}`, 20000, 1],
    ["20,000 lines of markup in a template", (n) => `const t = \`\n` + (`  <input type="${name}" value="\${pw}">\n`.repeat(n)) + `\`;`, 20000, 0],
    ["20,000 lines of an interpolation's code in a template", (n) => `const t = \`\${[\n` + (`  f(${name}),\n`.repeat(n)) + `].join('')}\`;`, 20000, 0],
    ["10,000 members after a decorator's template over two lines", (n) => `class C {\n` + (`  @Dec(\`x\n  y\`) ${name}: string = x;\n`.repeat(n)) + `  @Dec(\`x\n  y\`) ${name}: string = ${lit};\n}`, 10000, 1],
    ["an interpolation that holds 20,000 brackets open over lines", (n) => `const t = \`\${f(${"(\n".repeat(n)}${")".repeat(n)})}\`;\nclass C { ${name}: string = x; }`, 20000, 1],
    ["a block comment over 20,000 lines", (n) => `/*\n` + (`  the ${name} is read from the environment\n`.repeat(n)) + `*/\nconst ${name} = ${lit};`, 20000, 1],
  ];
  for (const [label, text, n, hits] of cases) {
    const read = (k) => markerHits("api/client.ts", text(k).split("\n").map((line, i) => ({ line: i + 1, text: line }))).filter((hit) => hit.row === "secrets").length;
    assert.equal(linear(label, read, n), hits, label);
  }
});

test("the guardrails' own files hold no password literal, so a change to them is not human-only by its comments", () => {
  // A comment that describes a reading with a lower-case $-value in a JS file would make every change to
  // these files human-only.
  const dir = fileURLToPath(new URL("../", import.meta.url));
  const files = ["check.mjs", ...["lib", "checks", "tests"].flatMap((sub) => readdirSync(join(dir, sub)).filter((f) => f.endsWith(".mjs")).map((f) => `${sub}/${f}`))];
  assert.ok(files.includes("lib/doors.mjs") && files.includes("tests/station.test.mjs"), files.join(" "));
  for (const file of files) {
    const lines = readFileSync(join(dir, file), "utf8").split("\n").map((text, i) => ({ line: i + 1, text }));
    assert.deepEqual(markerHits(`tools/guardrails/${file}`, lines).filter((hit) => hit.row === "secrets"), [], file);
  }
});

test("markers stay linear: many quoted elements, a long printf flag run or long spaces grow no faster than linearly (linear-time.mjs), and the --station call routes each as the reader decides", () => {
  // The --station call in a scratch repository whose working tree adds the long lines.
  // A regular expression that reads one quote two ways backtracks exponentially on the quoted lists; one
  // that can split a run of zeros two ways, or looks behind over spaces at every position, is quadratic.
  const name = ["pass", "word"].join("");
  // It starts with a letter, so "$v..." is a variable where quotes interpolate ("$1abc" would be a literal).
  const value = `v${randomBytes(6).toString("hex")}`;
  const cases = [
    ["includes/quotedList.php", (k) => `$codes = [${Array(k(3000)).fill("'1'").join(", ")}]; $${name} = $pw;`, []],
    ["includes/quotedListDouble.php", (k) => `$codes = [${Array(k(3000)).fill('"1"').join(", ")}]; $${name} = "$pw";`, []],
    ["javascript/quotedList.js", (k) => `const codes = [${Array(k(3000)).fill("'1'").join(", ")}]; const ${name} = pw;`, []],
    // "%000...0z" is no printf conversion, so it is a literal.
    ["includes/zeroPad.php", (k) => `$${name} = '%${"0".repeat(k(200000))}z';`, ["secrets"]],
    // Once a pattern has run on one long line, V8 matched the old look-behinds in time quadratic in the
    // spaces; the first line warms the patterns that way.
    ["includes/spaces.php", (k) => `$filler = '${"x".repeat(k(20000))}';\n$sql =${" ".repeat(k(200000))}'select 1';`, []],
    ["javascript/quotedLiteral.mjs", (k) => `const codes = [${Array(k(3000)).fill("'1'").join(", ")}]; const ${name} = "$${value}";`, ["secrets"]],
    // A single-quoted password inside a connection string, in each literal-quote language, after many strings.
    ["javascript/dsn.mjs", (k) => `const codes = [${Array(k(3000)).fill('"1"').join(", ")}]; const dsn = "host=localhost ${name}='$${value}'";`, ["secrets"]],
    ["api/client.ts", (k) => `const dsn: string = "host=localhost ${name}='$${value}' port=5432";`, ["secrets"]],
    ["api/seed.py", (k) => `dsn = "host=localhost ${name}='$${value}'"`, ["secrets"]],
    // A double-quoted password inside single quotes or a template, after many strings, and after the runs
    // the shell-command scan reads: a 200000-dash run before an option, assignments before a command.
    ["javascript/nestedDsn.mjs", (k) => `const codes = [${Array(k(3000)).fill("'1'").join(", ")}]; const dsn = 'host=localhost ${name}="$${value}"';`, ["secrets"]],
    ["javascript/templateDsn.mjs", (k) => `const codes = [${Array(k(3000)).fill("`1`").join(", ")}]; const dsn = \`${name}="$${value}"\`;`, ["secrets"]],
    ["tools/dashes.py", (k) => `x = '${"-".repeat(k(200000))}'; dsn = '${name}="$${value}"'`, ["secrets"]],
    ["tools/options.py", (k) => `x = '${"--a".repeat(k(70000))}'; dsn = '${name}="$${value}"'`, ["secrets"]],
    ["tools/envChain.py", (k) => `cmd = '${"A=b ".repeat(k(50000))}PG${name.toUpperCase()}="$PW" psql'`, []],
    ["javascript/execSpaces.js", (k) => `exec${" ".repeat(k(200000))}x; const dsn = '${name}="$${value}"';`, ["secrets"]],
    // The checks on the string a shell may be handed: many calls, a long argument list before shell=...,
    // long spaces inside a shell call, and a long option name.
    ["javascript/manyCalls.js", (k) => `${"f('1'); ".repeat(k(30000))}const dsn = '${name}="$${value}"';`, ["secrets"]],
    ["tools/manyShellFalse.py", (k) => `x = [${Array(k(3000)).fill("g('1', shell=False)").join(", ")}]; dsn = '${name}="$${value}"'`, ["secrets"]],
    ["javascript/callArgs.js", (k) => `spawn('psql', [${Array(k(3000)).fill("'1'").join(", ")}, 'host=x ${name}="$${value}"']);`, ["secrets"]],
    ["javascript/execArgSpaces.js", (k) => `exec(${" ".repeat(k(200000))}'echo ${name}="$PW"');`, []],
    // Shell-option shapes: many strings, block comments, nested objects, regular expressions,
    // triple-quoted strings, open calls and deep templates around the string, then the value.
    ["javascript/optionTokens.js", (k) => `spawn('echo ${name}="$${value}"', { a: [${Array(k(3000)).fill('"shell: true", /* shell: true */').join(" ")} 1], shell: false });`, ["secrets"]],
    ["tools/optionTokens.py", (k) => `subprocess.run('echo ${name}="$${value}"', env={${Array(k(3000)).fill('"a": "shell=True",').join(" ")}}, shell=False)`, ["secrets"]],
    ["tools/optionRun.py", (k) => `cmd = '--${"a-".repeat(k(100000))}${name}="$PW"'`, []],
    ["javascript/optionDepth.js", (k) => `spawn('echo ${name}="$${value}"', { a: [${Array(k(3000)).fill("/shell: true/, { shell: true, b: { shell: true } },").join(" ")} 1], shell: false });`, ["secrets"]],
    ["tools/optionTriple.py", (k) => `subprocess.run('echo ${name}="$${value}"', env={` + (Array(k(3000)).fill(`"a": ${'"'.repeat(k(3))}x" shell=True "y${'"'.repeat(k(3))},`).join(" ")) + `}, shell=False)`, ["secrets"]],
    ["javascript/optionCalls.js", (k) => `${"spawn('x', ".repeat(k(20000))}spawn('echo ${name}="$${value}"', { shell: false })`, ["secrets"]],
    // Templates nested in interpolations: outside the plain subset, so the line that names the password counts.
    ["javascript/optionTemplates.js", (k) => `spawn('echo ${name}="$PW"', { a: \`` + "${`".repeat(k(2000)) + "`}".repeat(k(2000)) + `\`, shell: false });`, ["secrets"]],
    // The value readings: many names in one string, each with a reference; many triple-quoted strings
    // and f-prefixed strings before the name; long runs of ${, $a[ and {$ in a value; a long type
    // between the name and "="; many fallbacks and names in one expression; many names with no "=".
    ["javascript/manyNames.js", (k) => `const s = '` + (`${name}="$PW" `.repeat(k(20000))) + `';`, []],
    ["tools/manyNamesUnquoted.py", (k) => `s = "` + (`${name}=$PW `.repeat(k(20000))) + `"`, []],
    ["tools/manyTriples.py", (k) => (`${'"'.repeat(k(3))}a${'"'.repeat(k(3))} `.repeat(k(30000))) + `${name} = "$PW"`, []],
    ["tools/manyPrefixes.py", (k) => `${"f'x' rb'y' ".repeat(k(20000))}${name} = f'{pw}'`, []],
    ["includes/braceRun.php", (k) => `$${name} = "${"${".repeat(k(100000))}";`, ["secrets"]],
    ["includes/subscriptRun.php", (k) => `$${name} = "$a${"[".repeat(k(100000))}";`, ["secrets"]],
    ["includes/complexRun.php", (k) => `$${name} = "${"{$".repeat(k(100000))}";`, ["secrets"]],
    ["api/longType.ts", (k) => `let ${name}: ${"a | ".repeat(k(50000))}b = pw;`, []],
    ["javascript/manyFallbacks.js", (k) => `const ${name} = ${"a || ".repeat(k(50000))}"";`, []],
    ["javascript/manyAssignments.js", (k) => (`${name} = a ?? `.repeat(k(20000))) + `b;`, []],
    ["javascript/manyNamesNoValue.js", (k) => "" + (`${name}: x `.repeat(k(30000))), []],
    ["javascript/caseSpaces.js", (k) => `case${" ".repeat(k(200000))}${name} = pw;`, []],
    ["javascript/spacesThenNames.js", (k) => `${" ".repeat(k(100000))}` + (`${name} `.repeat(k(20000))) + `= pw;`, []],
    ["tools/deploy.sh", (k) => "" + (`PG${name.toUpperCase()}="\${PG${name.toUpperCase()}:-$PW}" `.repeat(k(20000))), []],
    // More value readings: long operator chains, deep or unclosed groups, calls and brackets, many keys,
    // casts, ternaries and subscript or define keys, long indents before a key, refs in a YAML scalar, and
    // operands parted by long spaces.
    ["includes/concat.php", (k) => `$${name} = ${"$a . ".repeat(k(50000))}$b;`, []],
    // A group deeper than the reader goes, a value left open and a tagged template's literal count.
    ["javascript/deepGroups.js", (k) => `const ${name} = ${"(".repeat(k(50000))}a${")".repeat(k(50000))};`, ["secrets"]],
    ["javascript/openGroups.js", (k) => `const ${name} = ${"(".repeat(k(100000))}`, ["secrets"]],
    ["javascript/openBrackets.js", (k) => `const ${name} = ${"[".repeat(k(50000))}`, ["secrets"]],
    ["tools/keywords.py", (k) => `${name} = ${"a or ".repeat(k(40000))}b`, []],
    ["tools/openCalls.py", (k) => `${name} = f(${"g(".repeat(k(30000))}`, ["secrets"]],
    ["tools/openDefault.py", (k) => `${name} = os.getenv("X", ${"(".repeat(k(50000))}`, ["secrets"]],
    ["javascript/manyKeys.js", (k) => `x = {` + (`${name}: a, `.repeat(k(20000))) + `}`, []],
    ["includes/casts.php", (k) => `$${name} = ${"(string) ".repeat(k(30000))}$x;`, []],
    ["javascript/ternaries.js", (k) => `const ${name} = ${"a ? b : ".repeat(k(30000))}c;`, []],
    ["javascript/accesses.js", (k) => `const ${name} = ${"a.b(c)[d] + ".repeat(k(30000))}e;`, []],
    ["javascript/tags.js", (k) => `const ${name} = x${"`a`".repeat(k(30000))};`, ["secrets"]],
    ["includes/subscripts.php", (k) => "" + (`$cfg['${name}'] = $a; `.repeat(k(20000))), []],
    ["includes/defines.php", (k) => "" + (`define('${name}', $a); `.repeat(k(20000))), []],
    ["includes/heredocs.php", (k) => `$${name} = ${"<<<".repeat(k(50000))}`, ["secrets"]],
    ["javascript/operandSpaces.js", (k) => `const ${name} = a${" ".repeat(k(200000))}b;`, []],
    ["javascript/keyIndent.js", (k) => `${" ".repeat(k(200000))}${name}: a,`, []],
    ["config/keyIndent.yml", (k) => `${" ".repeat(k(200000))}${name}: \${A}`, []],
    ["config/scalarRefs.yml", (k) => `${name}: ${"${{ a }}".repeat(k(20000))}`, []],
    ["config/scalarSpaces.yml", (k) => `${name}: ${"${{ a }} ".repeat(k(20000))}`, ["secrets"]],
    ["tools/envWords.sh", (k) => `PG${name.toUpperCase()}=${"$A".repeat(k(50000))} cmd`, []],
    ["tools/jsonKeys.py", (k) => `s = '` + (`{"${name}": "$A"}, `.repeat(k(20000))) + `'`, []],
    // Literal-only and mixed argument lists, deep and open calls, keys holding
    // calls, item lists in a PHP array, many quoted or escaped segments in a string or a shell word, an
    // environment word in a comment, nested defaults, divisions, and a context look-behind on each value.
    ["javascript/literalArgs.js", (k) => `const ${name} = f(${"'a', ".repeat(k(30000))}x);`, ["secrets"]],
    ["javascript/mixedArgs.js", (k) => `const ${name} = f(${"x, ".repeat(k(30000))}'${value}');`, ["secrets"]],
    ["javascript/referenceArgs.js", (k) => `const ${name} = f(${"x, ".repeat(k(30000))}y);`, []],
    ["javascript/nestedCalls.js", (k) => `const ${name} = ${"f(".repeat(k(40000))}x${")".repeat(k(40000))};`, ["secrets"]],
    ["javascript/openArgs.js", (k) => `const ${name} = ${"f(x, ".repeat(k(30000))}`, ["secrets"]],
    ["javascript/keyCalls.js", (k) => `x = f(` + (`${name}: g(a), `.repeat(k(20000))) + `)`, []],
    ["javascript/manyValues.js", (k) => "" + (`const ${name} = f(x, y); `.repeat(k(15000))), []],
    ["javascript/manyMixedValues.js", (k) => (`const ${name} = f(x, y); `.repeat(k(15000))) + `const ${name} = f('x', y);`, ["secrets"]],
    ["includes/arrayItems.php", (k) => `$${name} = array(${"'k' => $v, ".repeat(k(30000))});`, []],
    ["javascript/quotedSegments.js", (k) => `const s = "${name}='$X'${"'$X'".repeat(k(40000))}";`, []],
    ["javascript/escapedSegments.js", (k) => `const s = '${name}="${'\\"$X\\"'.repeat(k(40000))}';`, ["secrets"]],
    ["tools/shellSegments.sh", (k) => `PG${name.toUpperCase()}=${'"$X"'.repeat(k(40000))} psql`, []],
    ["javascript/commentEnv.js", (k) => `// PG${name.toUpperCase()}=${"$X".repeat(k(60000))} psql`, []],
    ["tools/nestedDefaults.py", (k) => `${name} = ${"os.getenv('X', ".repeat(k(20000))}''${")".repeat(k(20000))}`, ["secrets"]],
    ["javascript/divisions.js", (k) => `const ${name} = x${" / x".repeat(k(60000))};`, []],
    ["includes/columns.php", (k) => `$cols = [` + (`'${name}' => 'INTEGER', `.repeat(k(10000))) + `];`, []],
    ["includes/notColumns.php", (k) => `$c = [` + (`'${name}' => 'INTEGER', `.repeat(k(10000))) + `];`, ["secrets"]],
  ];
  // In this process, each case at a quarter of its size and at its size: the reader's growth (linear-time.mjs).
  // A PHP case is PHP's code from its first line, as PHP's own tokens read a file's first line as HTML before "<?php".
  const code = (path, text) => (path.endsWith(".php") ? `<?php ${text}` : text);
  for (const [path, text, rows] of cases) {
    const read = (n) => {
      const lines = code(path, text((count) => Math.max(1, Math.round((count * n) / 4)))).split("\n");
      return markerHits(path, lines.map((line, i) => ({ line: i + 1, text: line }))).map((hit) => hit.row);
    };
    assert.deepEqual(linear(path, read, 4), rows, path);
  }
  // And through the --station call, every case in one change: each file's rows, as check.mjs routes them.
  const dir = mkdtempSync(join(tmpdir(), "guardrails-linear-"));
  const git = (...args) => spawnSync("git", ["-c", "user.name=guardrails-test", "-c", "user.email=guardrails-test@example.invalid", "-c", "commit.gpgsign=false", ...args], { cwd: dir, encoding: "utf8" });
  try {
    assert.equal(git("init", "-q").status, 0);
    assert.equal(git("commit", "-q", "--allow-empty", "--no-verify", "-m", "base").status, 0);
    for (const [path, text] of cases) {
      const file = join(dir, path);
      mkdirSync(dirname(file), { recursive: true });
      writeFileSync(file, `${code(path, text((count) => count))}\n`);
    }
    // The timeout only ends a call that hangs; the bound is the growth measured above.
    const result = spawnSync(process.execPath, [CHECK, "--station", "--json", "--base", "HEAD"], { cwd: dir, encoding: "utf8", timeout: 600000, maxBuffer: 1 << 26 });
    assert.equal(result.signal, null, "the --station call hung");
    assert.equal(result.status, 0, result.stderr);
    const report = JSON.parse(result.stdout);
    assert.deepEqual([...report.changedPaths].sort(), cases.map(([path]) => path).sort());
    for (const [path, , rows] of cases) assert.deepEqual(report.markersHit.filter((hit) => hit.path === path).map((hit) => hit.row), rows, path);
    assert.equal(report.humanOnly, true);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});

/** Every line of commit `sha` that `git grep -i -E pattern` finds, by path, as markerHits takes them. */
function linesAt(sha, pattern) {
  const out = spawnSync("git", ["grep", "-n", "-I", "-i", "-E", pattern, sha, "--"], { cwd: ROOT, encoding: "utf8", maxBuffer: 1 << 26 });
  assert.equal(out.status, 0, out.stderr);
  const byPath = new Map();
  for (const hit of out.stdout.split("\n").filter(Boolean)) {
    const [, path, line, text] = /^[^:]+:(.+?):(\d+):(.*)$/s.exec(hit);
    if (!byPath.has(path)) byPath.set(path, []);
    byPath.get(path).push({ line: Number(line), text });
  }
  return byPath;
}

/**
 * `sha`'s lines that `pattern` finds where `row`'s markers hit, each file's lines read as check.mjs hands a change that
 * adds them: after every unchanged line above each run (changeset.mjs addedWithContext), so a comment line's lexer state
 * is established: "path:line".
 */
function rowHitsInFilesAt(sha, pattern, row) {
  const vendored = (path) => VENDORED.test(path);
  const hits = [];
  for (const [path, lines] of linesAt(sha, pattern)) {
    const file = spawnSync("git", ["show", `${sha}:${path}`], { cwd: ROOT, encoding: "utf8", maxBuffer: 1 << 26 }).stdout;
    const entries = addedWithContext(new Map(lines.map((entry) => [entry.line, entry.text])), file);
    for (const hit of markerHits(path, entries, vendored)) if (hit.row === row) hits.push(`${path}:${hit.line}`);
  }
  return hits.sort();
}

/** The lines of `sha` that `pattern` finds whose statement a password spelling's readsFrom() reads as a literal, as "path:line" (doors.mjs alone). */
function spellingHitsAt(sha, pattern) {
  const hits = [];
  for (const [path, lines] of linesAt(sha, pattern)) {
    if (VENDORED.test(path)) continue;
    const spellings = passwordSpellings(path);
    lines.forEach((entry, i) => {
      if (spellings.some((m) => m.readsFrom(lines, i))) hits.push(`${path}:${entry.line}`);
    });
  }
  return hits.sort();
}

/** The hits of one row's markers on every line of `sha` that `pattern` finds, as "path:line". */
function rowHitsAt(sha, pattern, row) {
  const vendored = (path) => VENDORED.test(path);
  const hits = [];
  for (const [path, lines] of linesAt(sha, pattern)) {
    for (const hit of markerHits(path, lines, vendored)) if (hit.row === row) hits.push(`${path}:${hit.line}`);
  }
  return hits.sort();
}

/**
 * The base 5419eee2's statements whose end is not on their line, a pure reference each: read alone
 * (pattern.test) they count, because the next line might go on with them; markerHits reads on
 * (readsFrom), and they end where the change does (the next one, unchanged, is not the change's).
 */
/**
 * The base 5419eee2's lines whose statement is still open where linesAt's lines stop (git grep's lines, the next one
 * further on: a gap, doors.mjs cutAt), so the file goes on below them unseen and they count read so. Four are
 * UNENDED_AT_BASE's (a reference with no ";", a PHP array's last item before its ")" on the next line; the other two
 * are their file's last lines handed, and linesAt marks none cut, so they read as the file's end);
 * sager/kontrol_sager.php:1528 is a line comment's text inside a block comment that no line handed opens. Read as
 * check.mjs hands a change, with every line below them, each ends, and none is a hit (spellingHitsWithContextAt).
 */
const CUT_AT_BASE = [
  "rental/daysoff.js:8",
  "rental/items.js:22",
  "rental/remote.js:8",
  "sager/kontrol_sager.php:1528",
  "systemdata/diverseIncludes/create_vibrant_login.php:112",
];

const UNENDED_AT_BASE = [
  "rental/daysoff.js:8",
  "rental/items.js:22",
  "rental/items.js:62",
  "rental/remote.js:8",
  "rental/remote.js:95",
  "systemdata/diverseIncludes/create_vibrant_login.php:112",
];

/**
 * The base 5419eee2's page-script lines in PHP files that count read alone, because PHP's own tokens read a PHP file's
 * line handed without the lines above it as PHP's code (doors.mjs phpSource), where document.getElementById(...) is a
 * constant joined by "." to a call no reader knows, handed a literal. With every line above them, as check.mjs hands a
 * change, admin_panel.php:2001 stands in an explicit <script> element (OUTSIDE_PLAIN_AT_BASE) and sys_div_func.php's
 * two in PHP strings (PHP_STRING_TEXT_AT_BASE).
 */
const SCRIPT_READ_AS_PHP_AT_BASE = ["admin/admin_panel.php:2001", "systemdata/sys_div_func.php:1460", "systemdata/sys_div_func.php:1789"];

/**
 * The base 5419eee2's lines inside a PHP string over lines that give a password a value in the string's text:
 * sys_div_func.php prints the Vibrant and Flatpay login scripts with print "...", and the lines 1460 (var passwd =
 * document.getElementById(...).value;) and 1789 ('password': document.getElementById(...).value) are that string's text.
 * A string's text that gives a password a value counts (stringReading), as the same string on one line does; the
 * string's later lines are its text, not code. No literal: each counts as text.
 */
const PHP_STRING_TEXT_AT_BASE = ["systemdata/sys_div_func.php:1460", "systemdata/sys_div_func.php:1789"];

/**
 * The base 5419eee2's YAML keys given nothing on their line: their value is on the lines after them, which
 * linesAt does not hand on (only the lines its pattern matches), so they count read alone and read on. The
 * OpenAPI property `password:` holds a nested schema there (type, format, description, example). Read as check.mjs
 * hands a change that adds them, with every unchanged line below them too, the nested schema is read, and they are no
 * hit.
 */
const OPEN_AT_BASE = ["restapi/swagger.yaml:3488"];

/**
 * The base 5419eee2's lines that start with a literal, each an item or an argument whose group opens on a line above
 * (sprintf's connection-string template, a psql command or its argument list): linesAt hands them without that line,
 * so the reader cannot see where their statement starts, and a bare literal at the top of what it was handed may be
 * a password's value (startsAbove, bareValue). Read with every line above them, as
 * check.mjs hands a change, none of them is a hit.
 */
const LEADING_LITERALS_AT_BASE = [
  "e2e/seed-user.mjs:33",
  "tests/assist/run_records_integration.py:68",
  "tests/characterization/debitor/ZReportPosOrdersOnlyTest.test.php:52",
  "tests/characterization/finans/support/MomsPeriodeLukEnv.php:73",
  "tests/characterization/includes/OrderLineDeliveredBackfillTest.test.php:56",
  "tests/characterization/includes/support/run_leveret_backfill.php:31",
  "tests/characterization/support/CharacterizationEnv.php:100",
  "tests/restapi/support/RestApiEnv.php:177",
];

/**
 * The base 5419eee2's lines that are hits only read with every line above them: tests/TestCredentials.php:37 is a
 * line of TestCredentials::password()'s body, and a change that adds it alone leaves that body open where the change
 * ends, so it counts.
 */
const IN_OPEN_BODY_AT_BASE = ["tests/TestCredentials.php:37"];

/**
 * The base 5419eee2's lines that are hits only read with every line above them because they stand outside the plain
 * subset: admin/admin_panel.php:2001, the user form's password: key given document.getElementById(...).value, in the
 * page's <script> element below line 1965, whose template's interpolation holds a regular expression
 * (.replace(/'/g, ...)), where JavaScript's plain subset ends, as in a .js file. No literal; outside the plain subset a
 * line that names a password counts. The script is read as JavaScript, not as a PHP backtick string, so line 1939 (a
 * template's <input type="password">, above 1965 in a template's text) gives no password a value here; it is listed in
 * PIECES_UNKNOWN_AT_BASE.
 */
const OUTSIDE_PLAIN_AT_BASE = ["admin/admin_panel.php:2001"];

/**
 * The base 5419eee2's lines that name a password before "=" or ":" below a piece of PHP anywhere but
 * in HTML's data state (doors.mjs htmlCodes: what it prints may end the tag, comment or element it stands in and open
 * any other, so no later place is certain): read with every line above them, as check.mjs hands a change, each is
 * outside the plain subset and counts, references too. None is a literal: inputs of type
 * password, a form's password field read in a script, PHP's own references, prints and system() calls. The first
 * pieces: index.php's in a script's start tag (a nonce, line 170); ordrefunc.php's, brugerNew.php's and brugere.php's in
 * a script's code; brugerNewest.php's and brugerNewest1.php's in a style's text; the others' in a tag's attributes.
 */
const PIECES_UNKNOWN_AT_BASE = [
  "admin/admin_panel.php:1939",
  "admin/slet_regnskab.php:103",
  "admin/slet_regnskab.php:104",
  "admin/slet_regnskab.php:105",
  "includes/ordrefunc.php:6024",
  "index/index.php:175",
  "index/index.php:177",
  "index/index.php:258",
  "index/index.php:259",
  "index/index.php:295",
  "index/index.php:298",
  "index/index.php:299",
  "index/index.php:302",
  "index/login.php:1001",
  "index/login.php:1085",
  "index/login.php:1087",
  "index/login.php:1145",
  "index/login.php:636",
  "index/login.php:984",
  "index/tofaktor.php:43",
  "rotary/pbs_tilmelding2.php:370",
  "systemdata/bruger2.php:870",
  "systemdata/bruger2.php:878",
  "systemdata/brugerNew.php:1035",
  "systemdata/brugerNew.php:1040",
  "systemdata/brugerNew.php:867",
  "systemdata/brugerNew.php:872",
  "systemdata/brugerNewest.php:723",
  "systemdata/brugerNewest.php:728",
  "systemdata/brugerNewest.php:862",
  "systemdata/brugerNewest.php:867",
  "systemdata/brugerNewest1.php:714",
  "systemdata/brugerNewest1.php:719",
  "systemdata/brugerNewest1.php:853",
  "systemdata/brugerNewest1.php:858",
  "systemdata/brugere.php:350",
  "systemdata/brugere.php:351",
  "systemdata/brugere.php:421",
  "systemdata/brugere.php:422",
];

/** The lines of `sha` that `pattern` finds, each file's added after every line of `sha` above them, whose statement a password spelling's readsFrom() reads as a literal. */
function spellingHitsWithContextAt(sha, pattern) {
  const hits = [];
  for (const [path, lines] of linesAt(sha, pattern)) {
    if (VENDORED.test(path)) continue;
    const head = spawnSync("git", ["show", `${sha}:${path}`], { cwd: ROOT, encoding: "utf8", maxBuffer: 1 << 26 });
    assert.equal(head.status, 0, head.stderr);
    const entries = addedWithContext(new Map(lines.map((entry) => [entry.line, entry.text])), head.stdout);
    const spellings = passwordSpellings(path);
    entries.forEach((entry, i) => {
      if (!entry.context && spellings.some((m) => m.readsFrom(entries, i))) hits.push(`${path}:${entry.line}`);
    });
  }
  return hits.sort();
}

test("the base 5419eee2: the password marker finds its literal lines and none of the interpolated SQL, shell or CI strings", () => {
  // Every line that names a password before an "=" (a type between them included, "=>" too) or a ":".
  // e2e/support/app.mjs:16 is the e2e login's literal fallback after process.env (?? "..."). Keys are read:
  // the seven tests/ lines are fixtures given a literal by "=>" ('passwd' => '...'), and
  // .github/workflows/tests.yml:36 is the CI login's literal (SALDI_E2E_PASSWORD: ...), so a change to any of
  // them goes to a person. The schema columns (a password column of varchar(255)), the docblock's "password":
  // "password", the API example (your-api-user-password), the JSON keys given a name and the shell lines in
  // "$(...)" are not literals. A value is a literal unless all of it is a reference: docker-compose.yml:29 is
  // the local database's login (POSTGRES_PASSWORD: password), and restapi/endpoints/v1/auth/login.php:9 and
  // restapi/IMPLEMENTATION_STATUS.md:91 document the login body with a literal value. Minified jQuery
  // (1.8 and the placeholder plugin) gives a password key or field a call handed only strings; it is
  // vendored (changeset.mjs VENDORED), so no marker reads it. The column maps
  // ($expectedColumns = [... 'INTEGER' ...]) name their context and stay no hit. A call's arguments are read by
  // their roles: debitor/debitorkort.php:743, a commented-out SQL fragment, gives the password
  // saldikrypt('$id','$password'), two literal strings (single quotes do not interpolate in PHP). Read alone, the
  // statements of UNENDED_AT_BASE count too, but markerHits reads on and they end. YAML's empty key takes the
  // lines after it (OPEN_AT_BASE), which linesAt does not hand on. A literal that starts a line read without the
  // lines above it counts (LEADING_LITERALS_AT_BASE); with them, as check.mjs reads a change, it does not. A PHP
  // file is read by PHP's own tokens: three page-script lines count read alone as PHP's code
  // (SCRIPT_READ_AS_PHP_AT_BASE), and two of them with context as a PHP string's text (PHP_STRING_TEXT_AT_BASE).
  // A piece of PHP outside HTML's data state leaves the plain subset (PIECES_UNKNOWN_AT_BASE).
  const name = ["pass", "(word|wd)"].join("");
  const pattern = `${name}['"]?[[:space:]]*:|${name}[^=]*=`;
  const lines = linesAt("5419eee2", pattern);
  assert.ok(lines.has("admin/vis_regnskaber.php") && lines.has("mysale/editProfile.php") && lines.has(".github/workflows/tests.yml"), "the interpolated lines are read");
  assert.ok(lines.has("tests/assist/run_records_integration.py") && lines.has("tools/ci/bootstrap-stack.sh"), "the shell command lines are read");
  assert.ok(lines.has("includes/opdat_4.0.php") && lines.has("restapi/endpoints/v1/auth/login.php") && lines.has("restapi/swagger.yaml"), "the schema, docblock and API example lines are read");
  const literals = [
    ".github/workflows/tests.yml:36",
    "api/signup.php:41",
    "debitor/debitorkort.php:743",
    "debitor/formprintfunc.php:534",
    "debitor/mail_faktura.php:124",
    "debitor/rykkertjek.php:115",
    "docker-compose.yml:29",
    "e2e/support/app.mjs:16",
    "includes/login.php:64",
    "restapi/IMPLEMENTATION_STATUS.md:91",
    "restapi/endpoints/v1/auth/login.php:9",
    "tests/includes/auditLog.test.php:52",
    "tests/restapi/AdminOpretAuthorizationTest.php:40",
    "tests/restapi/AdminOpretAuthorizationTest.php:87",
    "tests/restapi/EndpointAuthorizationTest.php:135",
    "tests/restapi/SignupApiAuthorizationTest.php:110",
    "tests/test_cash_count_transaction.php:19",
    "tests/test_zreport_proforma.php:24",
  ];
  assert.deepEqual(spellingHitsAt("5419eee2", pattern), [...literals, ...OPEN_AT_BASE, ...LEADING_LITERALS_AT_BASE, ...SCRIPT_READ_AS_PHP_AT_BASE, ...CUT_AT_BASE].sort());
  // Read as check.mjs hands a change that adds them, with every line above them, the leading literals are not hits.
  assert.deepEqual(spellingHitsWithContextAt("5419eee2", pattern), [...literals, ...IN_OPEN_BODY_AT_BASE, ...OUTSIDE_PLAIN_AT_BASE, ...PHP_STRING_TEXT_AT_BASE, ...PIECES_UNKNOWN_AT_BASE].sort());
  // The unended references are hits only read alone (markerHits reads on: the production wiring test).
  for (const where of UNENDED_AT_BASE) {
    const [path, line] = where.split(":");
    const text = lines.get(path).find((entry) => entry.line === Number(line)).text;
    assert.ok(passwordSpellings(path).some((m) => m.pattern.test(text)), `${where} read alone`);
  }
});

test("the base 5419eee2: TRUNCATE finds resetAccount.php's statements, not apexcharts' compared \"truncate\" or truncate_number()", () => {
  const lines = linesAt("5419eee2", "truncate");
  assert.ok(lines.has("javascript/apexcharts.js") && lines.has("debitor/pos_ordre_includes/helperFunc.php"), "the non-SQL lines are read");
  // Line 91 is the refusal message that names TRUNCATE; the upper-case keyword counts anywhere. A comment line that names
  // it is passed over where the lexer establishes the comment: with the lines above it. Read alone, below the
  // file's first line, its state is not established, and the comment lines count.
  assert.deepEqual(rowHitsInFilesAt("5419eee2", "truncate", "destructive"), ["systemdata/resetAccount.php:51", "systemdata/resetAccount.php:54", "systemdata/resetAccount.php:91"]);
  assert.ok(rowHitsAt("5419eee2", "truncate", "destructive").includes("systemdata/resetAccount.php:87"), "a comment line handed alone keeps its text");
});

test("secrets: a key-like literal counts in any file, comment or test; a key prefix alone does not", () => {
  const key = ["sk", "live", "0123456789abcdefghij"].join("_");
  const block = ["-----BEGIN", "RSA PRIVATE KEY-----"].join(" ");
  assert.deepEqual(markerHits("tests/fixture.json", [{ line: 3, text: `"key": "${key}"` }]), [{ row: "secrets", marker: "sk_live_ key", line: 3 }]);
  assert.deepEqual(markerHits("doc/setup.md", [{ line: 1, text: block }]), [{ row: "secrets", marker: "PRIVATE KEY block", line: 1 }]);
  assert.deepEqual(markerHits("includes/stripeIncludes/x.php", [{ line: 1, text: "$live = strpos($secret, 'sk_live_') === 0;" }]), []);
  const report = stationReport({ input: { mode: "diff" }, paths: ["tests/fixture.json"], lines: new Map([["tests/fixture.json", [{ line: 3, text: key }]]]) });
  assert.equal(report.party, "1d");
  assert.equal(report.humanOnly, true);
});

test("routing example 1, a payment page with a text row (#980 and a keyed text): rule 1, party 1b", () => {
  const report = paths("debitor/betalinger.php", "importfiler/tekster_keyed.tsv");
  assert.deepEqual([report.rule, report.station, report.party, report.doorFloor], [1, "envejsdorene", "1b", "one-way"]);
  assert.deepEqual(report.humanAtPass, { lead: "Nicolai", second: "Saul", label: "forslag" });
  assert.deepEqual(report.tasterTier, { level: "deep", effort: "xhigh", longContext: "always", securityPass: true, recheck: "xhigh" });
  assert.deepEqual(sources(report), ["payments", "envejsdorene 1b", "texts"]);
  assert.deepEqual(report.minimumProof.map((proof) => proof.e2e), ["flow", "flow", "none"]);
  assert.deepEqual(report.careRows, [
    { row: "texts", station: "skaerm-og-tekst", hits: [{ kind: "path", path: "importfiler/tekster_keyed.tsv", pattern: "importfiler/*_keyed.tsv" }] },
  ]);
});

test("routing example 2, a stylesheet (#886): rule 4, quick tier, Saul leads", () => {
  const report = paths("css/docpool.css");
  assert.deepEqual([report.rule, report.station, report.party, report.doorFloor], [4, "skaerm-og-tekst", null, "none-detected"]);
  assert.deepEqual(report.humanAtPass, { lead: "Saul", second: "Nicolai", label: "forslag" });
  assert.equal(report.tasterTier.level, "quick");
  assert.deepEqual(sources(report), ["skaerm-og-tekst"]);
});

test("routing example 3, a kassekladde file plus guardrails (#912 at triage): rule 2 with Gartneren's proof", () => {
  const report = paths("finans/kassekladde_includes/lineFill.php", "AGENTS.md", "tools/guardrails/checks/tekster.mjs");
  assert.deepEqual([report.rule, report.station, report.doorFloor], [2, "faellesgryden", "two-way-with-care"]);
  assert.deepEqual(report.changedPaths, ["AGENTS.md", "finans/kassekladde_includes/lineFill.php", "tools/guardrails/checks/tekster.mjs"]);
  assert.deepEqual(report.tasterTier, { level: "standard", effort: "high", longContext: "if-large-change", securityPass: false, recheck: "low" });
  assert.deepEqual(sources(report), ["faellesgryden", "gartneren"]);
  assert.deepEqual(report.gatePaths, ["AGENTS.md", "tools/guardrails/checks/tekster.mjs"]);
  assert.equal(report.input.mode, "paths");
  assert.deepEqual(report.markersHit, []);
});

test("#980 527579cc: the payment list is party 1b, and a one-way match still exits 0", () => {
  const { status, report } = commit("527579cc");
  assert.equal(status, 0);
  assert.deepEqual([report.rule, report.party, report.humanOnly], [1, "1b", false]);
  assert.deepEqual(report.doorRows, [
    { row: "payments", party: "1b", hits: [{ kind: "path", path: "debitor/betalinger.php", pattern: "{debitor,kreditor}/{betalinger,betalingsliste}.php" }] },
  ]);
  assert.match(report.input.baseSha, /^[0-9a-f]{40}$/);
  assert.match(report.doorMap.sha256, /^[0-9a-f]{64}$/);
});

test("#886 bad3e197: CSS only goes to Skærm og tekst at the quick tier", () => {
  const { report } = commit("bad3e197");
  assert.deepEqual([report.rule, report.station, report.tasterTier.level], [4, "skaerm-og-tekst", "quick"]);
});

test("#913 40fff95c: the Dockerfile goes to Gartneren with the image proof", () => {
  const { report } = commit("40fff95c");
  assert.deepEqual([report.rule, report.station, report.doorFloor], [3, "gartneren", "two-way-with-care"]);
  assert.deepEqual(report.careRows.map((match) => match.row), ["ci"]);
  assert.match(report.minimumProof[0].text, /the image builds, and Guardrails and Tests pass inside it/);
});

test("#912 eaaca4b7: by path the guardrails PR touches a kassekladde file, so Fællesgryden owns it", () => {
  const { report } = commit("eaaca4b7");
  assert.deepEqual([report.rule, report.station], [2, "faellesgryden"]);
  assert.deepEqual(report.careRows.map((match) => match.row), ["kassekladde", "ci"]);
  assert.ok(report.gatePaths.includes("AGENTS.md") && report.gatePaths.includes("tools/guardrails/check.mjs"));
  assert.deepEqual(report.markersHit, [], "markers quoted in tools/guardrails do not count");
});

test("#755 2fdbb9a7, a schema data step alone (UPDATE rentalsettings in betweenUpdates.php), takes the deep tier", () => {
  const { report } = commit("2fdbb9a7");
  // Its rental/*.js imports name checkSettingsPassword in a statement with no ";" (rental/daysoff.js:8,
  // rental/items.js:62, rental/remote.js:8). Read alone such a line may go on, so it would count; check.mjs hands
  // markerHits the change's lines and markerHits reads on, so each ends where its line does and is no hit.
  assert.deepEqual([report.rule, report.party, report.doorRows.map((match) => match.row)], [1, "1c", ["schema"]]);
  assert.deepEqual(report.markersHit.map((hit) => [hit.row, hit.path, hit.marker]), [["schema", "includes/betweenUpdates.php", "update <table> set"]]);
  assert.equal(report.humanOnly, false);
  assert.deepEqual(report.tasterTier, TIERS.dataStep);
  assert.deepEqual(report.tasterTier, { level: "deep", effort: "xhigh", longContext: "always", securityPass: false, recheck: "xhigh" });
});

test("#611 4602fb01, schema structure alone (new columns in betweenUpdates.php), takes the structure tier", () => {
  const { report } = commit("4602fb01");
  assert.deepEqual([report.rule, report.party, report.doorRows.map((match) => match.row)], [1, "1c", ["schema"]]);
  assert.deepEqual(report.markersHit, []);
  assert.deepEqual(report.tasterTier, { level: "standard", effort: "high", longContext: "never", securityPass: false, recheck: "xhigh" });
});

test("#832 8e09393b: posting and schema give party 1a; the backfill's UPDATE is a data step", () => {
  const { report } = commit("8e09393b");
  assert.deepEqual([report.rule, report.party], [1, "1a"]);
  assert.deepEqual(report.doorRows.map((match) => match.row), ["posting", "schema"]);
  assert.deepEqual(sources(report), ["posting", "schema", "envejsdorene 1a", "envejsdorene 1c"]);
  assert.ok(report.markersHit.some((hit) => hit.row === "schema" && hit.path === "includes/betweenUpdates.php"));
  assert.equal(report.tasterTier.level, "deep");
  assert.ok(!report.markersHit.some((hit) => hit.path.startsWith("tests/")), "the characterization test's SQL is not a hit");
});

test("#917 ff80f1b5: the order page alone is Fællesgryden; the door question, not the path, moves it to 1a", () => {
  const { report } = commit("ff80f1b5");
  assert.deepEqual([report.rule, report.station, report.humanAtPass.lead], [2, "faellesgryden", "Nicolai"]);
  assert.deepEqual(report.careRows.map((match) => match.row), ["order-pages"]);
});

test("#935 d8a2bc70: a workflow that reads a repo secret is the deploy row and human-only; its comment line is not a hit", () => {
  const { report } = commit("d8a2bc70");
  assert.deepEqual([report.rule, report.party, report.humanOnly], [1, "1d", true]);
  assert.deepEqual(report.markersHit.map((hit) => [hit.row, hit.marker, hit.path]), [
    ["deploy", "secrets.", ".github/workflows/review-notify.yml"],
    ["deploy", "secrets.", ".github/workflows/review-notify.yml"],
  ]);
  const file = spawnSync("git", ["show", "d8a2bc70:.github/workflows/review-notify.yml"], { cwd: ROOT, encoding: "utf8" }).stdout.split("\n");
  const uses = file.flatMap((line, i) => (/\$\{\{\s*secrets\./.test(line) ? [i + 1] : []));
  assert.ok(file.some((line) => /^#.*secrets\./.test(line)), "the file has a comment that names secrets");
  assert.deepEqual(report.markersHit.map((hit) => hit.line), uses);
});

test("#910 e1a3e534: the back-port hits six one-way rows, its test fixture's literal password among them; party 1b leads (PARTY_ORDER)", () => {
  // A key given a value by "=>" counts: tests/restapi/SignupApiAuthorizationTest.php:110 gives 'passwd' a
  // literal, so the back-port is human-only as well.
  const { report } = commit("e1a3e534");
  assert.deepEqual(report.doorRows.map((match) => match.row), ["outbound", "posting", "schema", "auth", "destructive", "secrets"]);
  assert.deepEqual(report.markersHit.filter((hit) => hit.row === "secrets").map((hit) => `${hit.path}:${hit.line}`), ["tests/restapi/SignupApiAuthorizationTest.php:110"]);
  assert.equal(report.humanOnly, true);
  assert.equal(report.party, "1b");
  assert.deepEqual(report.tasterTier, { level: "deep", effort: "xhigh", longContext: "always", securityPass: true, recheck: "xhigh" });
});

test("the same input gives the same bytes", () => {
  const first = commit("e1a3e534");
  assert.equal(first.status, 0);
  assert.ok(first.report.markersHit.length > 0);
  assert.equal(first.stdout, commit("e1a3e534").stdout);
});

test("the rules come from the copy that runs: a copy of tools/guardrails with its own map routes by that map", () => {
  const dir = mkdtempSync(join(tmpdir(), "guardrails-rules-"));
  try {
    cpSync(join(ROOT, "tools/guardrails"), join(dir, "tools/guardrails"), { recursive: true });
    // cpSync keeps the source's modes, and a checkout may be read-only (a sandboxed run's sealed
    // tree), so the copy is made writable before it is edited and removed.
    const writable = (path) => {
      if (statSync(path).isDirectory()) {
        chmodSync(path, 0o755);
        for (const name of readdirSync(path)) writable(join(path, name));
      } else {
        chmodSync(path, 0o644);
      }
    };
    writable(join(dir, "tools/guardrails"));
    // The copy claims css/ as a payments path, which the working tree's map does not.
    const copy = join(dir, "tools/guardrails/lib/doors.mjs");
    const original = readFileSync(copy, "utf8");
    const changed = original.replace('      "api/stripe/",\n', '      "api/stripe/",\n      "css/",\n');
    assert.notEqual(changed, original);
    writeFileSync(copy, changed);
    const input = "css/docpool.css\n";
    const own = station(["--base", "HEAD", "--paths", "-"], { input });
    const copied = station(["--base", "HEAD", "--paths", "-"], { input, script: join(dir, "tools/guardrails/check.mjs") });
    assert.equal(copied.status, 0);
    assert.deepEqual([own.report.rule, own.report.station], [4, "skaerm-og-tekst"]);
    assert.deepEqual([copied.report.rule, copied.report.party, copied.report.doorRows.map((match) => match.row)], [1, "1b", ["payments"]]);
    assert.deepEqual(copied.report.doorRows[0].hits, [{ kind: "path", path: "css/docpool.css", pattern: "css/" }]);
    assert.equal(copied.report.doorMap.sha256, createHash("sha256").update(changed).digest("hex"));
    assert.notEqual(copied.report.doorMap.sha256, own.report.doorMap.sha256);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});

test("a candidate path is matched as the repository path it names", () => {
  assert.equal(canonicalPath("./finans/bogfor.php"), "finans/bogfor.php");
  assert.equal(canonicalPath("finans//bogfor.php"), "finans/bogfor.php");
  assert.equal(canonicalPath("debitor/../finans/./bogfor.php"), "finans/bogfor.php");
  const aliases = paths("./finans/bogfor.php", "finans/./bogfor.php", "kreditor/../finans/bogfor.php");
  assert.deepEqual(aliases.changedPaths, ["finans/bogfor.php"]);
  assert.deepEqual([aliases.rule, aliases.party, aliases.doorRows.map((match) => match.row)], [1, "1a", ["posting"]]);
  const deploy = paths("./.github/workflows/deploy-example.yml");
  assert.deepEqual([deploy.changedPaths, deploy.doorRows.map((match) => match.row), deploy.humanOnly], [[".github/workflows/deploy-example.yml"], ["deploy"], true]);
  for (const outside of ["../finans/bogfor.php", "finans/../../finans/bogfor.php", "..", ".", "./", "/srv/finans/bogfor.php", "finans\\bogfor.php"]) {
    assert.equal(canonicalPath(outside), null, outside);
    const { status, report } = station(["--base", "HEAD", "--paths", "-"], { input: `finans/kassekladde.php\n${outside}\n` });
    assert.equal(status, 2, outside);
    assert.equal(report.error.code, "bad-argument", outside);
  }
});

test("refusals are one saldi.station.v1 error and exit 2", () => {
  const cases = [
    [["--base", "origin/no-such-branch"], undefined, "unknown-ref"],
    [["--base", "HEAD", "--head", "no-such-commit"], undefined, "unknown-ref"],
    [["--frobnicate"], undefined, "bad-argument"],
    [["--paths", "-", "--head", "HEAD"], "", "bad-argument"],
    [["--base", "HEAD", "--paths", "-"], "/tmp/finans/bogfor.php\n", "bad-argument"],
  ];
  for (const [args, input, code] of cases) {
    const { status, report } = station(args, { input });
    assert.equal(status, 2, args.join(" "));
    assert.deepEqual(Object.keys(report), ["schema", "error"]);
    assert.equal(report.error.code, code, args.join(" "));
  }
  const outside = spawnSync(process.execPath, [CHECK, "--station", "--json"], { cwd: tmpdir(), encoding: "utf8" });
  assert.equal(outside.status, 2);
  assert.equal(JSON.parse(outside.stdout).error.code, "not-a-git-repo");
});

test("without --json a developer gets the same answer as text", () => {
  const result = spawnSync(process.execPath, [CHECK, "--station", "--base", "527579cc^", "--head", "527579cc"], { cwd: ROOT, encoding: "utf8" });
  assert.equal(result.status, 0);
  assert.match(result.stdout, /^station: +Envejsdørene, party 1b Betaling og afsendelse \(rule 1\)$/m);
  assert.match(result.stdout, /^human at the pass: Nicolai, second Saul \(forslag\)$/m);
  assert.match(result.stdout, /^ {2}- payments: Test mode only/m);
});
