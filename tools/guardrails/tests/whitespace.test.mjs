// 20261109 CDX/NTR Cover blank separators from a6bb95b17 and retain trailing-whitespace, line-ending and mode checks.
// 20261109 CDX/NTR Catch standalone blank-line churn while allowing separators alongside content edits.
import test from "node:test";
import assert from "node:assert/strict";
import whitespace from "../checks/whitespace.mjs";
import { parseDiff } from "../lib/changeset.mjs";

function context(removed = [], added = [], overrides = {}) {
  const entries = added.map((text, index) => ({ line: index + 1, text }));
  return {
    files: [{
      path: "example.php", status: "M", oldMode: "100644", newMode: "100644", binary: false,
      hunks: [{ removed, added: entries }], added: new Map(entries.map(entry => [entry.line, entry.text])),
      ...overrides,
    }],
    isVendored: () => false,
  };
}

test("blank separators may be empty or contain spaces and tabs", () => {
  for (const blank of ["", " ", "\t", "\t\t", " \t ", "\r", "\t\r", "  \r"]) {
    assert.deepEqual(whitespace.run(context([], ["$before = 1;", blank, "$after = 2;"])), [], JSON.stringify(blank));
  }
});

test("standalone indentation changes to existing blank separators are rejected", () => {
  for (const [before, after] of [["", "\t"], ["\t", ""], ["\t", "  "], [" \t", "\t\t"]]) {
    const findings = whitespace.run(context([before], [after]));
    assert.equal(findings.length, 1);
    assert.match(findings[0].message, /blank line/);
  }
});

test("trim-on-save cannot silently rewrite indentation on many existing blank lines", () => {
  const before = Array.from({ length: 38 }, (_, index) => "\t".repeat(index % 3 + 1));
  const findings = whitespace.run(context(before, before.map(() => "")));
  assert.equal(findings.length, 38);
  assert(findings.every(finding => /blank line/.test(finding.message)));
});

test("blank separators can change alongside content in the same hunk", () => {
  assert.deepEqual(whitespace.run(context(["$value = 1;", "\t"], ["$value = 2;", ""])), []);
  assert.deepEqual(whitespace.run(context(["\t"], [])), []);
});

test("removing old code may leave the indented separators reported on rapportfunc.php lines 646 and 662", () => {
  const patch = [
    "diff --git a/includes/rapportfunc.php b/includes/rapportfunc.php",
    "--- a/includes/rapportfunc.php",
    "+++ b/includes/rapportfunc.php",
    "@@ -645,3 +645,2 @@ function bogfor_nu($id)",
    "-\t/*", "-\told settings code", "-\t*/", "+", "+\t",
    "@@ -674 +662 @@ function bogfor_nu($id)",
    "-\t\t$refnr;", "+\t\t",
  ].join("\n");
  assert.deepEqual(whitespace.run({ files: [...parseDiff(patch).values()], isVendored: () => false }), []);
});

test("spaces and tabs after code or comments are still rejected", () => {
  for (const content of ["$value = 1;", "// Explanation.", "# Explanation.", "/* Explanation. */"]) {
    for (const suffix of [" ", "\t", " \t", " \r"]) {
      const findings = whitespace.run(context([], [content + suffix]));
      assert.equal(findings.length, 1);
      assert.match(findings[0].message, /trailing whitespace/);
    }
  }
});

test("trailing whitespace cannot hide behind an inserted blank separator", () => {
  const findings = whitespace.run(context(["$value = 1;"], ["\t", "$value = 2; "]));
  assert.equal(findings.length, 1);
  assert.equal(findings[0].line, 2);
  assert.match(findings[0].message, /trailing whitespace/);
});

test("incidental trailing-whitespace-only edits to content are still rejected", () => {
  for (const [before, after] of [["$value = 1; ", "$value = 1;"], ["$value = 1;", "$value = 1;\t"]]) {
    const findings = whitespace.run(context([before], [after]));
    assert.equal(findings.length, 1);
    assert.match(findings[0].message, /only the line ending or trailing whitespace changed/);
  }
});

test("line-ending-only edits to content are still rejected in both directions", () => {
  for (const [before, after] of [["$value = 1;", "$value = 1;\r"], ["$value = 1;\r", "$value = 1;"]]) {
    const findings = whitespace.run(context([before], [after]));
    assert.equal(findings.length, 1);
    assert.match(findings[0].message, /only the line ending or trailing whitespace changed/);
  }
});

test("file mode changes are still rejected when only blank separators are added", () => {
  const findings = whitespace.run(context([], ["\t"], { newMode: "100755" }));
  assert.equal(findings.length, 1);
  assert.match(findings[0].message, /file mode changed/);
});
