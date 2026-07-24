// e2e/seed-user.mjs - idempotently seed the local e2e login (SD-603).
//
// Creates/replaces the "e2etest" user in the tenant database of the local
// docker-compose stack. Local-only credentials; nothing here touches any
// production system. Overridable via env:
//   SALDI_E2E_TENANT_DB (default saldi_2)
//   SALDI_E2E_USER      (default e2etest)
//   SALDI_E2E_PASSWORD  (default e2etest-local-2026)
//
// Usage: node e2e/seed-user.mjs
//
// History:
// 20260724 CL/LH SD-603: created.

import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";

const db = process.env.SALDI_E2E_TENANT_DB ?? "saldi_2";
const user = process.env.SALDI_E2E_USER ?? "e2etest";
const password = process.env.SALDI_E2E_PASSWORD ?? "e2etest-local-2026";

if (!/^[a-z0-9_]+$/.test(db) || !/^[a-z0-9_]+$/i.test(user)) {
  console.error("unsafe db/user name");
  process.exit(2);
}

const md5 = createHash("md5").update(password).digest("hex");
const sql =
  `DELETE FROM brugere WHERE brugernavn='${user}'; ` +
  `INSERT INTO brugere (brugernavn, kode, email, rettigheder, status, regnskabsaar) ` +
  `VALUES ('${user}', '${md5}', 'e2e@example.invalid', repeat('9',50), true, 1);`;

function psql(database, statement) {
  execFileSync(
    "docker",
    ["compose", "exec", "-T", "-e", "PGPASSWORD=password", "postgres", "psql", "-U", "user", "-d", database, "-c", statement],
    { stdio: "inherit" }
  );
}

psql(db, sql);
// Clear any stale online-session row so login doesn't hit the
// "user already logged in" force-logout interstitial.
psql("saldi", `DELETE FROM online WHERE brugernavn='${user}';`);
console.log(`seeded ${user} in ${db}`);
