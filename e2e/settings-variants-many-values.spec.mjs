// e2e/settings-variants-many-values.spec.mjs - Varianter keeps what is saved when a variant has more than 1000 values.
//
// Indstillinger » Varianter (systemdata/settingsSection.php?s=items.variants) draws every value of the selected
// variant as a cell of the one save form. javascript/settingsRows.js let every cell go along on Gem ændringer,
// so a variant with more values than PHP's max_input_vars (1000) sent a post PHP cut short without a word: a
// value added with Tilføj værdi (drawn after the stored ones) and an edit to one of the last values were lost,
// and the page still came back saved (testlab round 1, variants-assemblies-production: test_39's variant "."
// has 2690 values, and the filter selects it first).
//
// The spec makes a variant with 1100 values through the page's own save, in parts that stay under the limit,
// then adds a value and edits the last stored one through the form, as a user does, and reads both back.
//
// PREREQUISITES: see e2e/workflow.spec.mjs.
//
// History:
// 20261105 CL/LH Created (testlab round 1).

import { test, expect } from "@playwright/test";
import { login, logout, captureDialogs } from "./support/app.mjs";

const SECTION = "/saldi/systemdata/settingsSection.php?s=items.variants";
const VALUES = 1100;
const PER_POST = 550;

/** The hidden fields every post of the page's save form carries (online.php refuses a post without thisDb). */
async function formBase(page) {
  const form = page.locator("#st-form");
  const base = { action: "save" };
  for (const name of ["csrf_token", "thisDb", "version"]) {
    base[name] = await form.locator(`input[name="${name}"]`).inputValue();
  }
  return base;
}

test("Varianter saves a new value and an edit when the variant has more than 1000 values", async ({ page }) => {
  captureDialogs(page);
  await login(page);
  const name = `E2E variant ${Date.now()}`;
  let typeId = "";

  try {
    // The variant, through the form.
    await page.goto(SECTION);
    const types = page.locator('.st-rows[data-table="types"]');
    await types.locator(".st-rfoot [data-add]").click();
    await types.locator("tr.st-new .st-rin").fill(name);
    await page.locator("#st-save").click();
    const option = page.locator("#st-f-values option", { hasText: name });
    await expect(option).toHaveCount(1, { timeout: 30_000 });
    typeId = ((await option.getAttribute("value")) ?? "").match(/[?&]type=(\d+)/)?.[1] ?? "";
    expect(typeId, "the new variant is offered in the Variant filter").not.toBe("");
    const url = `${SECTION}&type=${typeId}`;

    // Its values, posted as the save form posts new rows, PER_POST at a time so no post reaches the limit.
    for (let from = 1; from <= VALUES; from += PER_POST) {
      await page.goto(url);
      const form = await formBase(page);
      for (let i = from; i < from + PER_POST && i <= VALUES; i++) {
        form[`r[values][n${i}][beskrivelse]`] = `E2E-${String(i).padStart(4, "0")}`;
      }
      const res = await page.request.post(url, { form });
      expect(res.url(), `values ${from}.. were saved`).toContain("saved=");
    }

    // A user's save: edit the last stored value, add one with Tilføj værdi, Gem ændringer.
    await page.goto(url);
    const values = page.locator('.st-rows[data-table="values"]');
    await expect(values.locator("tbody tr[data-row]")).toHaveCount(VALUES, { timeout: 30_000 });
    const lastRow = values.locator('tr[data-code="E2E-1100"]');
    const lastId = (await lastRow.getAttribute("data-row")) ?? "";
    await lastRow.locator(".st-rin").fill("E2E-1100 ret");
    await values.locator(".st-rfoot [data-add]").click();
    await values.locator("tr.st-new .st-rin").fill("DVTEST");
    const request = page.waitForRequest((r) => r.method() === "POST" && r.url().includes("s=items.variants"));
    await page.locator("#st-save").click();
    const posted = new URLSearchParams((await request).postData() ?? "");

    await expect(values.locator('tr[data-code="DVTEST"]'), "the value added with Tilføj værdi is stored").toHaveCount(1, { timeout: 30_000 });
    await expect(values.locator('tr[data-code="E2E-1100 ret"]'), "the edit of the last value is stored").toHaveCount(1);
    await expect(values.locator("tbody tr[data-row]")).toHaveCount(VALUES + 1);
    // The cause: only the changed cell and the new row go along, not one field per stored value.
    expect([...posted.keys()].filter((key) => key.startsWith("r[")).sort()).toEqual([`r[values][${lastId}][beskrivelse]`, "r[values][n1][beskrivelse]"]);
  } finally {
    if (typeId !== "") {
      // Deleting the variant deletes its values too (on_delete variant_type_values).
      await page.goto(`${SECTION}&type=${typeId}`);
      const { csrf_token, thisDb } = await formBase(page);
      await page.request.post(`${SECTION}&type=${typeId}`, { form: { csrf_token, thisDb, action: "row_delete", table: "types", id: typeId } });
    }
    await logout(page);
  }
});
