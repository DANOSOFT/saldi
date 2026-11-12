// 20260916 CDX/LH Share explicit tenant and credential configuration between seed and browser.
// 20261022 CL/NTR Postgres user/password/master db fall back to tools/ci/bootstrap-stack.sh's SALDI_PGUSER/SALDI_PGPASS/SALDI_MASTER_DB.
export function missingE2eConfiguration(env = process.env, seed = false) {
  const names = ["SALDI_E2E_TENANT_DB", "SALDI_E2E_ACCOUNT", "SALDI_E2E_PASSWORD"];
  const missing = names.filter(name => !env[name]);
  if (seed && !env.SALDI_CHAR_PGPASS && !env.SALDI_PGPASS) missing.push("SALDI_CHAR_PGPASS (or SALDI_PGPASS)");
  return missing;
}
export function e2eConfig(env = process.env) {
  const missing = missingE2eConfiguration(env);
  if (missing.length) throw new Error(`${missing.join(", ")} is required`);
  const config = { db: env.SALDI_E2E_TENANT_DB, account: env.SALDI_E2E_ACCOUNT,
    user: env.SALDI_E2E_USER || "e2etest", password: env.SALDI_E2E_PASSWORD,
    master: env.SALDI_CHAR_MASTER_DB || env.SALDI_MASTER_DB || "saldi",
    pgUser: env.SALDI_CHAR_PGUSER || env.SALDI_PGUSER || "user",
    pgPass: env.SALDI_CHAR_PGPASS || env.SALDI_PGPASS };
  if (![config.db, config.master, config.user].every(value => /^[a-z0-9_]+$/i.test(value))) {
    throw new Error("Unsafe database or user name");
  }
  if (config.db === config.master) throw new Error("E2E tenant must differ from master");
  return config;
}
