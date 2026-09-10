// e2e/workflow.spec.mjs - one real business workflow, end to end (SD-603).
//
// Drives the docker-compose stack (http://localhost:5000) through a real
// browser: UI login -> create a kassekladde voucher -> post it (bogfor) ->
// verify the app reports the voucher as posted. This is the flow that was
// missing: e2e/smoke.spec.mjs only proves the stack answers HTTP.
//
// PREREQUISITES (same stack as tests/characterization, see that README):
//   docker compose up -d          # installed app + tenant (default saldi_2)
//   npm install && npx playwright install chromium
//   node e2e/seed-user.mjs        # seeds the e2e login (idempotent)
//
// Run: npm test   (alias for `playwright test`, see package.json)
//
// The login/account/password are local-only seeds, overridable via
// SALDI_E2E_ACCOUNT / SALDI_E2E_USER / SALDI_E2E_PASSWORD.
//
// History:
// 20260724 CL/LH SD-603: created.

import { test, expect } from "@playwright/test";

const ACCOUNT = process.env.SALDI_E2E_ACCOUNT ?? "saldi_chatbot_test";
const USER = process.env.SALDI_E2E_USER ?? "e2etest";
const PASSWORD = process.env.SALDI_E2E_PASSWORD ?? "e2etest-local-2026";

// Two plain (VAT-free) accounts from the standard chart of accounts.
const DEBIT_ACCOUNT = "1050";
const CREDIT_ACCOUNT = "1100";

test("login, create kassekladde voucher, post it, verify it is posted", async ({ page }) => {
  // The legacy UI throws confirm()/alert() dialogs - accept them all and
  // remember the messages (step 4 asserts on one of them).
  const dialogMessages = [];
  page.on("dialog", (dialog) => {
    dialogMessages.push(dialog.message());
    dialog.accept().catch(() => {});
  });

  // --- 1. UI login (validates against the tenant's brugere table) ---
  await page.goto("/saldi/index/index.php");
  await page.locator('input[name="regnskab"]').fill(ACCOUNT);
  await page.locator('input[name="brugernavn"]').fill(USER);
  await page.locator('input[name="password"]').fill(PASSWORD);
  await page.getByRole("button", { name: "Login" }).click();

  // If the user is still registered in the online table (e.g. an aborted
  // earlier run), the app shows a force-logout interstitial - confirm it.
  // (e2e/seed-user.mjs clears stale rows, so this is belt-and-braces.)
  for (let i = 0; i < 3; i++) {
    const forceLogout = page.locator('input[name="force_logout"]');
    if (await forceLogout.isVisible({ timeout: 3_000 }).catch(() => false)) {
      await forceLogout.click();
    } else {
      break;
    }
  }

  // Successful login lands in the app shell with a logout control.
  await expect(page.locator("body")).toContainText(/Log ud|Oversigt/, { timeout: 15_000 });

  // --- 2. Create a new kassekladde with one balanced line ---
  // A first-visit tutorial overlay intercepts all clicks for new users and
  // reappears on every kassekladde page load. It is not part of the workflow
  // under test - auto-dismiss it whenever it blocks an action.
  await page.addLocatorHandler(page.locator("#tutorial-overlay"), async () => {
    // The evaluate can race a page navigation - never let it fail the test.
    await page
      .evaluate(() => document.getElementById("tutorial-overlay")?.remove())
      .catch(() => {});
  });

  await page.goto("/saldi/finans/kassekladde.php?returside=kladdeliste.php&tjek=-1");
  const description = `E2E workflow ${Date.now()}`;
  await page.locator('input[name="besk1"]').fill(description);
  await page.locator('input[name="debe1"]').fill(DEBIT_ACCOUNT);
  await page.locator('input[name="kred1"]').fill(CREDIT_ACCOUNT);
  await page.locator('input[name="belo1"]').fill("123,45");
  await page.locator('[name="save"]').click(); // "Gem"

  // The saved line is rendered back with a real kladde_id.
  await expect(page.locator('input[name="besk1"]')).toHaveValue(description, { timeout: 15_000 });
  const kladdeId = await page.locator('input[name="kladde_id"]').first().inputValue();
  expect(Number(kladdeId), "saving assigns a kladde id").toBeGreaterThan(0);

  // --- 3. Post it: "Bogfør" opens the posting preview, confirm there ---
  await page.locator('[name="doPost"]').click();
  await expect(page.locator("body")).toContainText("Finansbevægelser", { timeout: 15_000 });
  // The preview lists both accounts and the amount before the final confirm.
  await expect(page.locator("body")).toContainText(DEBIT_ACCOUNT);
  await expect(page.locator("body")).toContainText(CREDIT_ACCOUNT);
  // Final "Bogfør" confirm. Posting + genberegn + the meta-refresh chain can
  // take a while - don't tie the navigation wait to the click itself.
  await page.locator('[name="bogfor"]').click({ noWaitAfter: true });

  // After posting the page redirects to the kladde list.
  await page.waitForURL(/kladdeliste\.php/, { timeout: 45_000 });

  // --- 4. Verify via the UI that the voucher is posted ---
  // Re-opening the posting page for the same kladde must refuse: the page
  // raises an "already posted" alert and bails out (finans/bogfor.php:107).
  await page.goto(`/saldi/finans/bogfor.php?funktion=bogfor&kladde_id=${kladdeId}`);
  await expect
    .poll(() => dialogMessages.some((m) => /allerede bogf/i.test(m)), {
      timeout: 15_000,
      message: "re-opening a posted kladde must announce it is already posted",
    })
    .toBe(true);

  // Leave a clean session behind for the next run.
  await page.goto("/saldi/index/logud.php").catch(() => {});
});
