// tools/guardrails/tests/include-paths.test.mjs - checks/include-paths.mjs on the shapes it must and must not flag.
//
// History:
// 20261105 CL/LH Created; pins ba02be399's bare includes in run_varesync.php and c290172a7's in signupBootstrap.php.
import test from "node:test";
import assert from "node:assert/strict";
import includePaths from "../checks/include-paths.mjs";

/** A change that leaves `after` in `path`, adding the lines in `additions` and removing `removed`. */
function context(path, after, additions, { status = "M", removed = [] } = {}) {
  const lines = after.split("\n");
  return {
    files: [{ path, status, added: new Map(additions.map((line) => [line, lines[line - 1]])),
      hunks: [{ removed, added: additions.map((line) => ({ line, text: lines[line - 1] })) }] }],
    isVendored: () => false,
    readHead: () => after,
    readBase: () => null,
  };
}
const lineCount = (source) => source.split("\n").length;
const all = (source) => Array.from({ length: lineCount(source) }, (_, index) => index + 1);
const flagged = (findings) => findings.map((finding) => finding.line);

test("bare relative includes in a new runner are flagged (ba02be399, run_varesync.php)", () => {
  const runner = [
    "<?php",
    'chdir("$chartestRoot/systemdata");',
    "include '../includes/connect.php'; // connects to master",
    "include '../includes/std_func.php';",
    "$db = $chartestTenantDb;",
    "include '../api/varesync.php';",
  ].join("\n");
  const findings = includePaths.run(context("tests/characterization/support/run_varesync.php", runner, all(runner), { status: "A" }));
  assert.deepEqual(flagged(findings), [3, 4, 6]);
  assert.match(findings[0].message, /__DIR__ \. '\/\.\.\.' with the path from tests\/characterization\/support\//);
});

test("bare includes beside __DIR__ ones in a new bootstrap are flagged (c290172a7, signupBootstrap.php)", () => {
  const bootstrap = [
    "<?php",
    'include("../includes/connect.php");',
    'include("../includes/std_func.php");',
    'include(__DIR__ . "/../opretfunc.php");',
    'include(__DIR__ . "/../cvrLookup.php");',
  ].join("\n");
  const findings = includePaths.run(context("includes/signupIncludes/signupBootstrap.php", bootstrap, all(bootstrap), { status: "A" }));
  assert.deepEqual(flagged(findings), [2, 3]);
});

test("every include and require form with a bare literal path is flagged, also when it starts a concatenation or an interpolation", () => {
  const source = [
    "<?php",
    "require 'vendor/autoload.php';",
    'require_once("./local.php");',
    "include_once ( '../includes/' . $file );",
    'include "../temp/$db/settings.php";',
    'INCLUDE("std_func.php");',
  ].join("\n");
  assert.deepEqual(flagged(includePaths.run(context("lager/new_page.php", source, all(source), { status: "A" }))), [2, 3, 4, 5, 6]);
});

test("anchored paths are not flagged: __DIR__, dirname(), a variable or constant, an absolute path, a stream wrapper", () => {
  const source = [
    "<?php",
    "include __DIR__ . '/../includes/connect.php';",
    "require_once(dirname(__FILE__) . '/x.php');",
    "include($path);",
    'include "$root/includes/std_func.php";',
    'include "{$root}/includes/std_func.php";',
    "include SALDI_ROOT . '/includes/x.php';",
    "include '/etc/saldi/connect.php';",
    "include 'phar://archive.phar/x.php';",
  ].join("\n");
  assert.deepEqual(includePaths.run(context("lager/new_page.php", source, all(source), { status: "A" })), []);
});

test("an include in a comment, a string or an inline script is not a statement", () => {
  const source = [
    "<?php",
    "// include '../includes/old.php';",
    "# require('../includes/old.php');",
    "/* include_once '../x.php'; */",
    "$hint = \"include '../includes/connect.php'\";",
    "?>",
    "<script>const lib = require('./lib.js');</script>",
  ].join("\n");
  assert.deepEqual(includePaths.run(context("lager/new_page.php", source, all(source), { status: "A" })), []);
});

test("a legacy include the change does not touch is not judged", () => {
  const source = "<?php\ninclude('../includes/connect.php');\n$x = 1;\n$y = 2;";
  assert.deepEqual(includePaths.run(context("lager/varer.php", source, [4])), []);
});

test("an include the change only moves or re-indents is not new; a rewritten one is", () => {
  const source = "<?php\nif ($a) {\n\tinclude('../includes/connect.php');\n\tinclude_once('../includes/std_func.php');\n}";
  const findings = includePaths.run(context("lager/varer.php", source, [3, 4], {
    removed: ["include('../includes/connect.php');", "include('../includes/std_func.php');"],
  }));
  assert.deepEqual(flagged(findings), [4]);
});

test("a moved file keeps its includes: the same lines stood in the file the change deletes", () => {
  const moved = "<?php\ninclude('../includes/connect.php');";
  const ctx = context("lager/nyt_navn.php", moved, [1, 2], { status: "A" });
  ctx.files.push({ path: "lager/gammelt_navn.php", status: "D", added: new Map(), hunks: [] });
  ctx.readBase = (path) => (path === "lager/gammelt_navn.php" ? moved : null);
  assert.deepEqual(includePaths.run(ctx), []);
});
