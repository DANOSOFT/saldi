// 20261124 CDX/NTR Accept structure builders while keeping DDL out of runtime database helpers and pages.
// 20261125 CDX/NTR Check grouped schema helper calls and permit only its shared implementation.

import test from "node:test";
import assert from "node:assert/strict";
import migrations from "../checks/migrations.mjs";

function findings(path, source = 'db_modify("CREATE TABLE example (id integer)");') {
  return migrations.run({
    files: [{ path, status: "A", added: new Map([[2, source]]) }],
    isVendored: () => false,
  });
}

test("structure builders and update helpers can define schema", () => {
  assert.deepEqual(findings("includes/structure/operatorPanelSchema.php"), []);
  assert.deepEqual(findings("includes/opdat_func/example.php"), []);
});

test("runtime database helpers and pages cannot define schema", () => {
  assert.equal(findings("includes/database/compatibility.php").length, 1);
  assert.equal(findings("admin/example.php").length, 1);
});

test("the shared ensureTable implementation may define schema", () => {
  assert.deepEqual(findings("includes/database/ensureTable.php"), []);
  assert.equal(findings("includes/database/anotherHelper.php").length, 1);
});

test("both schema helper entry points are restricted to update/install paths", () => {
  for (const source of ["db_ensure_table('lines', $types);", "DB_ENSURE::ensure('lines', $types);"]) {
    assert.equal(findings("debitor/ordre.php", source).length, 1);
    assert.equal(findings("includes/database/compatibility.php", source).length, 1);
    assert.deepEqual(findings("includes/structure/lines.php", source), []);
    assert.deepEqual(findings("includes/betweenUpdates.php", source), []);
  }
});
