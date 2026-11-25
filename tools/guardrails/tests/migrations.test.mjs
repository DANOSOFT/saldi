// 20261124 CDX/NTR Accept structure builders while keeping DDL out of runtime database helpers and pages.

import test from "node:test";
import assert from "node:assert/strict";
import migrations from "../checks/migrations.mjs";

function findings(path) {
  return migrations.run({
    files: [{ path, status: "A", added: new Map([[2, 'db_modify("CREATE TABLE example (id integer)");']]) }],
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
