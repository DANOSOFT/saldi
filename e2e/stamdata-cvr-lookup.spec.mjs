// e2e/stamdata-cvr-lookup.spec.mjs - Stamdata fills the company from a CVR-nr. again.
//
// Indstillinger » Stamdata (systemdata/settingsSection.php?s=company.data) took over from systemdata/stamkort.php, which
// loaded javascript/cvrapiopslag.js: a CVR-nr. typed between *, + or / was looked up at cvrapi.dk and filled the company's
// name, address, postal code, town and phone. The new page loaded no such script and names its fields f[company.data.*],
// so *20756438* did nothing: no request, no field changed (testlab round 2, company-masterdata).
//
// cvrapi.dk is answered by page.route with a JSONP reply, as e2e/cvr-auto-lookup.spec.mjs does, and the save is caught by
// page.route too: the spec reads what Gem ændringer posts, and the tenant's Stamdata is left as it was.
//
// PREREQUISITES: see e2e/workflow.spec.mjs.
//
// History:
// 20261105 CL/LH Created (testlab round 2).

import { test, expect } from "@playwright/test";
import { login, logout } from "./support/app.mjs";

const SECTION = "/saldi/systemdata/settingsSection.php?s=company.data";

// cvrapi.dk's answer for the lab's number. Fields it sends as null are left alone.
const company = { vat: 20756438, name: "Testlab CVR ApS", address: "Cvrvej 7", zipcode: "6100", city: "Haderslev", phone: "70202020", email: null, fax: null };

// The Stamdata field each part of the answer lands in, with the value it must get.
const filled = { cvr: "20756438", name: "Testlab CVR ApS", address1: "Cvrvej 7", address2: "", zip: "6100", city: "Haderslev", phone: "70202020" };

test("a CVR-nr. typed between stars on Stamdata fills the company, and Gem ændringer posts it", async ({ page }) => {
  const errors = [];
  page.on("pageerror", (error) => errors.push(error.message));
  const lookups = [];
  await page.route((url) => url.hostname === "cvrapi.dk", (route) => {
    const url = new URL(route.request().url());
    lookups.push(`${url.searchParams.get("vat")}/${url.searchParams.get("country")}`);
    const callback = url.searchParams.get("callback");
    return route.fulfill({ contentType: "application/javascript; charset=utf-8", body: `${callback}(${JSON.stringify(company)});` });
  });

  await login(page);
  try {
    await page.goto(SECTION);
    const field = (name) => page.locator(`#st-form input[name="f[company.data.${name}]"]`);
    const card = (name) => page.locator(`#st-form .st-field[data-key="company.data.${name}"]`);

    // A company that is already filled in, as in the lab: the lookup asks before it overwrites it.
    await field("name").fill("Gammelt firmanavn ApS");
    await field("cvr").fill("");
    await field("cvr").pressSequentially("*20756438*");
    await expect.poll(() => lookups, { message: "the number is looked up once, without the stars" }).toEqual(["20756438/dk"]);
    await page.locator("#cvr-btn-yes").click();

    for (const [name, value] of Object.entries(filled)) {
      await expect(field(name), `${name} is filled from the lookup`).toHaveValue(value);
    }
    // The save bar counts what the lookup wrote, as if it had been typed.
    await expect(page.locator("#st-savebar")).toBeVisible();
    for (const name of ["name", "address1", "city", "phone"]) {
      await expect(card(name), `${name} is marked as changed`).toHaveClass(/\bst-changed\b/);
    }

    // Gem ændringer posts the looked-up company; the post is answered here, so nothing is stored.
    let posted = null;
    await page.route(/systemdata\/settingsSection\.php\?s=company\.data/, (route) => {
      if (route.request().method() !== "POST") {
        return route.continue();
      }
      posted = new URLSearchParams(route.request().postData() ?? "");
      return route.fulfill({ contentType: "text/html; charset=utf-8", body: "<!doctype html><title>caught</title>" });
    });
    await page.locator("#st-save").click();
    await expect.poll(() => posted !== null, { message: "Gem ændringer posts the form" }).toBe(true);
    for (const [name, value] of Object.entries(filled)) {
      expect(posted.get(`f[company.data.${name}]`), `Gem ændringer posts the looked-up ${name}`).toBe(value);
    }
    expect(errors, "no script error on the page").toEqual([]);
  } finally {
    await page.unrouteAll({ behavior: "ignoreErrors" });
    await logout(page);
  }
});
