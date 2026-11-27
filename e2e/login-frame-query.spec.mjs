// e2e/login-frame-query.spec.mjs - the login page keeps its query when it leaves an iframe.
//
// A restore started from Indstillinger runs in main.php's iframe and ends on
// index/index.php?regnskab=...&restored=..., which says the ledger was restored
// and from when. The login page's frame check reloaded the top window as plain
// index/index.php, so the notice never reached the user (testlab round 1,
// backup-restore-roundtrip). The restore itself is pinned in
// tests/characterization/admin/RestoreDoneRedirectTest.
//
// Needs no login: the frame is put on the login page itself, as main.php's
// iframe would hold it.
//
// History:
// 20261105 CL/LH Created.

import { test, expect } from "@playwright/test";

test("the login page in an iframe takes its query to the top window and shows the restore notice", async ({ page }) => {
  await page.goto("/saldi/index/index.php");
  await page.evaluate(() => {
    const frame = document.createElement("iframe");
    frame.src = "index.php?regnskab=e2e_frame_query&restored=20261105-2258";
    document.body.appendChild(frame);
  });

  await page.waitForURL(/[?&]restored=20261105-2258/);
  const url = new URL(page.url());
  expect(url.pathname).toBe("/saldi/index/index.php");
  expect(url.searchParams.get("regnskab")).toBe("e2e_frame_query");
  await expect(page.locator(".restore-done")).toContainText("05-11-2026");
  await expect(page.locator(".restore-done")).toContainText("22:58");
  await expect(page.locator("#regnskab")).toHaveValue("e2e_frame_query");
});
