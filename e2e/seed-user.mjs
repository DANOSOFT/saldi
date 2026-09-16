// e2e/seed-user.mjs - idempotently seed the local e2e login (SD-603).
//
// Requires SALDI_E2E_TENANT_DB, SALDI_E2E_ACCOUNT, SALDI_E2E_PASSWORD
// and SALDI_CHAR_PGPASS. The account must resolve to the configured tenant.
//
// Usage: node e2e/seed-user.mjs
//
// History:
// 20260724 CL/LH SD-603: created.

import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";

import { e2eConfig } from "./config.mjs";
const { db, account, user, password, master, pgUser } = e2eConfig();
if (!process.env.SALDI_CHAR_PGPASS) throw new Error("SALDI_CHAR_PGPASS is required");

const md5 = createHash("md5").update(password).digest("hex");
const sql =
  `DELETE FROM brugere WHERE brugernavn='${user}'; ` +
  `INSERT INTO brugere (brugernavn, kode, email, rettigheder, status, regnskabsaar) ` +
  `VALUES ('${user}', '${md5}', 'e2e@example.invalid', repeat('9',50), true, 1);`;

function psql(database, statement) {
  return execFileSync("docker", ["compose", "exec", "-T", "-e", "PGPASSWORD", "postgres",
    "psql", "-X", "-v", "ON_ERROR_STOP=1", "-t", "-A", "-U", pgUser, "-d", database],
    { input: statement, encoding: "utf8", env: { ...process.env, PGPASSWORD: process.env.SALDI_CHAR_PGPASS } });
}
const escapedAccount = account.replaceAll("'", "''");
const resolved = psql(master, `SELECT db FROM regnskab WHERE regnskab='${escapedAccount}';`).trim();
if (resolved !== db) throw new Error("SALDI_E2E_ACCOUNT does not resolve uniquely to SALDI_E2E_TENANT_DB");
psql(db, sql);
psql(master, `DELETE FROM online WHERE brugernavn='${user}' AND db='${db}';`);
console.log(`seeded ${user} in ${db}`);
