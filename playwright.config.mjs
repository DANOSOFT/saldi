// Playwright config for the Saldi live-sandbox E2E smoke suite
// (SALDI-INTEGRATION-AUDIT gap G6, STAGING slice).
//
// Seeded so scripts/gates/run-gates.mjs findPlaywrightConfig() in the Darth
// Devy factory repo can find this file at the repo root once this clone is
// wired into the factory's client-mode gates.
//
// NOTE: this config and e2e/smoke.spec.mjs are SEEDED, not yet exercised.
// They require the docker-compose stack in this repo (docker-compose.yml,
// service "web", published at http://localhost:5000) to be running:
//
//   docker compose up -d
//
// Until that stack is up (or until this repo has @playwright/test installed
// via composer/npm tooling), this suite cannot execute. That is exactly why
// .factory/capability-manifest.json marks "e2e" inapplicable today - see
// that file's "reasons" field and FACTORY-ONBOARDING-NOTES.md for the exact
// operator step to flip it back on once the stack is up.
//
// This file intentionally has NO fallback base URL trick (unlike the
// factory's own integrations/saldi/e2e suite, which points at a packaged
// sandbox on :5100) - this config targets THIS repo's own docker-compose
// stack on :5000 exclusively, per gap G6.

import { defineConfig, devices } from "@playwright/test";

const BASE_URL = process.env.SALDI_BASE_URL ?? "http://localhost:5000";

export default defineConfig({
  testDir: "e2e",
  testMatch: /.*\.spec\.mjs$/,
  retries: 0,
  timeout: 60_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  reporter: [["list"]],
  use: {
    baseURL: BASE_URL,
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
    trace: "off",
    screenshot: "only-on-failure",
    ignoreHTTPSErrors: true,
  },
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
  ],
});
