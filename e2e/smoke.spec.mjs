// Live-sandbox smoke spec (SALDI-INTEGRATION-AUDIT gap G6, STAGING slice).
//
// Loads http://localhost:5000 (the "web" service published port in this
// repo's docker-compose.yml) and asserts the stack answers with a real HTTP
// response. This is intentionally the SMALLEST possible smoke check - it
// does not log in or exercise any workflow (see the factory's
// integrations/saldi/e2e/*.spec.mjs for that fuller suite against the
// packaged :5100 sandbox) - it only proves the docker-compose stack in THIS
// repo is reachable end-to-end through a real browser.
//
// PREREQUISITE: the docker-compose stack in this repo must be up:
//   docker compose up -d
// and @playwright/test must be installed (e.g. `npm install -D
// @playwright/test && npx playwright install chromium`).
//
// Until both of those are true, running this spec will fail to connect
// (or fail to import @playwright/test at all) - that is expected and is
// exactly why .factory/capability-manifest.json marks "e2e" inapplicable
// today rather than letting client-mode gates fail closed on a sandbox the
// operator hasn't started yet.

import { test, expect } from "@playwright/test";

test("Saldi docker-compose stack answers at http://localhost:5000", async ({ page }) => {
  const response = await page.goto("/");

  expect(response, "expected a real HTTP response from the stack").not.toBeNull();
  // Any answer under 500 counts as "the stack is up and serving" for this
  // smoke check - the Apache root serves 403 by design (the app lives under
  // /saldi/, checked below), and a 403/3xx/2xx are all valid "it's alive"
  // signals. A 5xx (or no response at all, asserted above) means the stack
  // is not healthy.
  expect(response.status(), `unexpected status ${response.status()}`).toBeLessThan(500);

  // Basic content sanity: the response body is non-empty HTML, not a blank
  // Apache/PHP error page.
  const body = await response.text();
  expect(body.length, "expected a non-empty response body").toBeGreaterThan(0);
});

test("Saldi app itself serves at /saldi/", async ({ page }) => {
  // The real application path (sandbox layout: DocumentRoot forbids /, the
  // app is mounted at /saldi/ - verified live 2026-07-16). A 200 with real
  // HTML here is the meaningful Gate 5 execution proof.
  const response = await page.goto("/saldi/");

  expect(response, "expected a real HTTP response from /saldi/").not.toBeNull();
  expect(response.status(), `unexpected status ${response.status()}`).toBe(200);
  // The legacy app chain-navigates immediately after load (JS/meta
  // redirects), so grabbing response.text()/page.content() races the
  // navigation (both observed failing live 2026-07-16). A locator assertion
  // auto-waits across navigations: a document with an attached <body> proves
  // a real page rendered, whichever redirect the app settled on.
  await expect(page.locator("body")).toBeAttached();
});
