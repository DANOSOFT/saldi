// 20261029 CDX/MJ Pin the repeated PR #882 header-version and misplaced-history findings.
// 20261030 CL/MJ Pin that history after a plain /* note inside the header (includes/grid.php) is not flagged.
// 20261101 CL/MJ Pin that a /*****\ licence banner (includes/barcode.php) is not read as PHPDoc.
import test from "node:test";
import assert from "node:assert/strict";
import history from "../checks/file-history.mjs";
import version from "../checks/file-version.mjs";

function context(before, after, additions = []) {
  const lines = after.split("\n");
  return {
    today: "2026-10-29",
    files: [{ path: "example.php", status: "M", added: new Map(additions.map(i => [i, lines[i-1]])),
      hunks: [{ removed: ["old"], added: additions.map(i => ({line:i, text:lines[i-1]})) }] }],
    isVendored: () => false,
    readBase: () => before,
    readHead: path => path === "includes/version.php" ? '<?php $version = "5.1.0";' : after,
    addedSinceFork: () => new Set(),
  };
}
const head = '<?php\n// example.php ver 5.1.0 2026-10-29\n// Copyright (c) 2026 Danosoft ApS\n// 20261028 CDX/MJ Existing behavior.\n';

test("history belongs before PHPDoc even when another new header entry is present", () => {
  const after = head + '// 20261029 CDX/MJ First new entry.\n/** Function documentation. */\n// 20261029 CDX/MJ Misplaced entry.\nfunction example() {}';
  const findings = history.run(context(head,after,[5,7]));
  assert(findings.some(f => f.line === 7 && f.message.includes("outside the header")));
});
test("contiguous history retains the function PHPDoc", () => {
  const after = head + '// 20261029 CDX/MJ New entry.\n/** Function documentation. */\nfunction example() {}';
  assert.deepEqual(history.run(context(head,after,[5])), []);
});
test("runtime history comments are outside the leading-header rule", () => {
  const after = head + '// 20261029 CDX/MJ New entry.\nfunction example() {\n// 20261029 CDX/MJ Runtime explanation.\n}';
  assert.deepEqual(history.run(context(head,after,[5,7])), []);
});
test("history after a plain /* note inside the header is not misplaced (includes/grid.php)", () => {
  const before = '<?php\n#..includes/grid.php\n####### Notice #######\n// What this file is for.\n/*\npages that use the grid\n*/\n// 20261028 CDX/MJ Existing behavior.\n####### EndNotice #######\n/**\n * Function documentation.\n */\nfunction example() {}';
  const after = before.replace('// 20261028 CDX/MJ Existing behavior.\n', '// 20261028 CDX/MJ Existing behavior.\n// 20261029 CDX/MJ New entry.\n');
  const findings = history.run(context(before, after, [9]));
  assert(!findings.some(f => f.message.includes("outside the header")), JSON.stringify(findings));
});
test("history after a /*****\\ licence banner is not misplaced (includes/barcode.php)", () => {
  const before = '<?php\n\n/*************************\\\n\nbarcode.php - MIT license.\n\n\\*************************/\n//\n// 20261007 MJ SST-830 Existing behavior.\n\nif (true) {}';
  const after = before.replace('// 20261007 MJ SST-830 Existing behavior.\n', '// 20261007 MJ SST-830 Existing behavior.\n// 20261029 CL/MJ New entry.\n');
  const findings = history.run(context(before, after, [10]));
  assert(!findings.some(f => f.message.includes("outside the header")), JSON.stringify(findings));
});
test("a one-line /** docblock still ends the header", () => {
  const after = head + '/** Short doc */\n// 20261029 CL/MJ Misplaced entry.\nfunction example() {}';
  const findings = history.run(context(head, after, [6]));
  assert(findings.some(f => f.line === 6 && f.message.includes("outside the header")), JSON.stringify(findings));
});
const old = '<?php\n// example.php lap 2.0.9 2026-10-29\nfunction example() { return "a // b"; }';
test("behavior changes require the application version", () => {
  assert.equal(version.run(context(old,old.replace('return "a // b"','return "changed"'))).length,1);
});
test("a comment-only edit does not require a version bump", () => {
  assert.deepEqual(version.run(context(old,old.replace('function example()', '// A comment.\nfunction example()'))),[]);
});
test("formatting does not require a version bump", () => {
  assert.deepEqual(version.run(context(old,old.replace('return "a // b";', 'return    "a // b";'))),[]);
});
test("whitespace inside returned strings is behavior", () => {
  assert.equal(version.run(context(old,old.replace('a // b','a  // b'))).length,1);
});
test("a current version satisfies a behavior change", () => {
  assert.deepEqual(version.run(context(old,old.replace('lap 2.0.9','ver 5.1.0').replace('a // b','changed'))),[]);
});
